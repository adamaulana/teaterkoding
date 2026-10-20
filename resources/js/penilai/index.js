/**
 * Penilai di browser (Fase 6) — orkestrasi di MAIN THREAD.
 *
 * === PEMBAGIAN TUGAS ===
 * Worker (worker.js)  : menjalankan Python, tidak menyimpan state.
 * Main thread (file ini):
 *   - mengatur urutan kelompok setup_code,
 *   - menegakkan batas waktu PER KELOMPOK dan TOTAL,
 *   - mematikan (terminate) worker yang macet lalu membuat ulang,
 *   - mengirim laporan nilai MENTAH ke server (tanpa klaim lulus/gagal),
 *   - menampilkan progres + indikator di topbar.
 *
 * === MENGAPA TIDAK ADA SharedArrayBuffer ===
 * SharedArrayBuffer butuh header COOP/COEP yang akan mematikan pemakaian
 * aset lintas-origin dan menambah kompleksitas deployment. Karena kode siswa
 * berjalan sinkron, satu-satunya cara menghentikannya adalah terminate().
 * Worker selalu dibuat ulang setelah dihentikan, sehingga state tidak bocor.
 */

import { jadwalkanSantai, JEDA_PRAMAAT } from './jadwal.js';

const PENANDA = 'penilai-pyodide';

/** Batas waktu bawaan (dipakai bila server tidak mengirim nilai). */
const BAWAAN = {
    batas_kelompok_ms: 3000,
    batas_total_ms: 15000,
};

/**
 * State modul (satu per halaman).
 *
 * @type {{
 *   worker: Worker|null,
 *   siap: boolean,
 *   menunggu: Map<string, {resolve: Function, reject: Function, timer: number}>,
 *   antrean: Promise<void>,
 *   versi: string|null,
 *   status: 'idle'|'memuat'|'siap'|'menilai'|'galat',
 * }}
 */
const state = {
    worker: null,
    siap: false,
    menunggu: new Map(),
    janjiMuat: null,
    versi: null,
    status: 'idle',
};

let urutanPesan = 0;

/**
 * URL worker dibuat oleh Vite (bundle ESM terpisah).
 *
 * `{ type: 'module' }` WAJIB: pyodide.mjs adalah ES module dan hanya bisa
 * dimuat di worker bertipe module.
 */
function buatWorker() {
    const worker = new Worker(new URL('./worker.js', import.meta.url), {
        type: 'module',
        name: PENANDA,
    });

    worker.addEventListener('message', (event) => {
        const pesan = event.data ?? {};

        if (pesan.type === 'progres') {
            perbaruiIndikator('memuat', pesan.persen ?? 0, pesan.tahap ?? '');
            return;
        }

        if (pesan.type === 'siap') {
            state.siap = true;
            state.status = 'siap';
            state.versi = pesan.versi ?? null;
            perbaruiIndikator('siap', 100, '');
            selesaikanMenunggu('__muat__', { ok: true, versi: state.versi });
            return;
        }

        if (pesan.type === 'hasil' || pesan.type === 'galat') {
            const id = String(pesan.id ?? '');

            if (pesan.type === 'galat') {
                selesaikanMenunggu(id, { ok: false, pesan: pesan.pesan });
            } else {
                selesaikanMenunggu(id, {
                    ok: true,
                    hasil: pesan.hasil ?? [],
                    keluaran: pesan.keluaran ?? '',
                    galat: pesan.galat ?? null,
                });
            }
        }
    });

    worker.addEventListener('error', (event) => {
        // Galat tak tertangkap di worker: gagalkan semua permintaan menunggu.
        const pesan = String(event?.message ?? 'Worker penilai gagal.').slice(0, 300);

        state.status = 'galat';
        perbaruiIndikator('galat', 0, pesan);

        for (const id of [...state.menunggu.keys()]) {
            selesaikanMenunggu(id, { ok: false, pesan });
        }
    });

    return worker;
}

/** Selesaikan (resolve) satu permintaan yang menunggu. */
function selesaikanMenunggu(id, nilai) {
    const entri = state.menunggu.get(String(id));

    if (! entri) {
        return;
    }

    clearTimeout(entri.timer);
    state.menunggu.delete(String(id));

    entri.resolve(nilai);
}

/** Batalkan seluruh permintaan yang menunggu (dipakai saat terminate). */
function batalkanSemua(pesan) {
    for (const id of [...state.menunggu.keys()]) {
        selesaikanMenunggu(id, { ok: false, pesan, dibatalkan: true });
    }
}

/**
 * Matikan worker paksa lalu buat ulang.
 *
 * Ini satu-satunya cara menghentikan kode Python yang berjalan sinkron
 * (mis. `while True:` tanpa perubahan kondisi).
 */
function matikanWorker() {
    if (state.worker !== null) {
        state.worker.terminate();
        state.worker = null;
    }

    state.siap = false;
    state.janjiMuat = null;
    state.status = 'idle';
    batalkanSemua('Penilai dihentikan karena melewati batas waktu.');
}

/**
 * Pastikan worker + Pyodide siap. Aman dipanggil berkali-kali.
 *
 * @param {string} aset URL direktori aset Pyodide (…/vendor/pyodide/314.0.7/)
 * @param {string} versi
 */
function siapkan(aset, versi) {
    if (state.siap && state.worker !== null) {
        return Promise.resolve({ ok: true, versi: state.versi });
    }

    // Pemanggilan bersamaan memakai satu proses pemuatan yang sama, supaya
    // worker tidak dibuat dua kali dan antrean pesan tidak tercampur.
    if (state.janjiMuat !== null) {
        return state.janjiMuat;
    }

    state.worker = buatWorker();
    state.status = 'memuat';
    perbaruiIndikator('memuat', 0, 'memuat-runtime');

    // Pemuatan runtime bisa jauh lebih lama dari batas penilaian (unduh wasm
    // ±10 MB pada koneksi lambat), jadi batasnya dilonggarkan.
    state.janjiMuat = tungguPesan(
        '__muat__',
        BAWAAN.batas_total_ms * 8,
        'Batas waktu tercapai saat menyiapkan penilai di perangkat.',
    ).then((jawaban) => {
        state.janjiMuat = null;

        if (! jawaban.ok) {
            // Runtime gagal dimuat / macet: buang worker agar percobaan
            // berikutnya mulai dari keadaan bersih.
            matikanWorker();
            state.status = 'galat';
            perbaruiIndikator('galat', 0, jawaban.pesan ?? '');
        }

        return jawaban;
    });

    state.worker.postMessage({ type: 'muat', aset, versi });

    return state.janjiMuat;
}

/**
 * Daftarkan permintaan yang menunggu jawaban worker, dengan batas waktu.
 *
 * @param {string} id
 * @param {number} batasMs
 * @param {string} pesanHabis pesan saat batas waktu tercapai
 */
function tungguPesan(id, batasMs, pesanHabis = 'Batas waktu penilaian tercapai.') {
    return new Promise((resolve) => {
        const timer = window.setTimeout(() => {
            selesaikanMenunggu(id, {
                ok: false,
                pesan: pesanHabis,
                waktuHabis: true,
            });
        }, batasMs);

        state.menunggu.set(String(id), { resolve, timer });
    });
}

/**
 * Nilai SATU kelompok setup_code di worker.
 *
 * @param {object} kelompok { setup_code, target }
 * @param {object} konteks  { kode, modul_diizinkan, maks_keluaran, batas_kelompok_ms, hangat }
 */
function nilaiKelompok(kelompok, konteks) {
    if (state.worker === null) {
        return Promise.resolve({
            ok: false,
            pesan: 'Penilai belum siap. Tekan "Nilai Ulang".',
        });
    }

    const id = `k${++urutanPesan}`;

    // Kelompok PERTAMA memikul biaya "pemanasan" Python (kompilasi kode,
    // impor malas, inisialisasi namespace), jadi batasnya dilonggarkan.
    const dasar = Math.max(500, Number(konteks.batas_kelompok_ms) || BAWAAN.batas_kelompok_ms);
    const batas = konteks.hangat ? dasar + 2000 : dasar;

    const janji = tungguPesan(
        id,
        batas,
        'Kode melewati batas waktu. Periksa apakah ada perulangan tanpa akhir.',
    );

    state.worker.postMessage({
        type: 'nilai',
        id,
        kode: konteks.kode,
        setup_code: kelompok.setup_code ?? '',
        target: kelompok.target ?? [],
        modul_diizinkan: konteks.modul_diizinkan ?? [],
        maks_keluaran: konteks.maks_keluaran ?? 2000,
    });

    return janji.then((jawaban) => {
        if (! jawaban.ok && jawaban.waktuHabis) {
            // Kode siswa macet (mis. loop tak berujung): worker dibuang.
            matikanWorker();
        }

        return jawaban;
    });
}

/**
 * Jalankan seluruh paket penilaian secara berurutan (satu kelompok per waktu).
 *
 * @param {object} paket paket dari server
 * @param {(tahap: string, info: object) => void} [onProgres]
 * @returns {Promise<{ok: boolean, hasil: Array, keluaran: string, galat: object|null, durasi_ms: number, pesan?: string}>}
 */
async function jalankanPaket(paket, onProgres) {
    const mulai = performance.now();

    const siapHasil = await siapkan(paket.aset, paket.versi_pyodide);

    if (! siapHasil.ok) {
        return {
            ok: false,
            hasil: [],
            keluaran: '',
            galat: null,
            durasi_ms: Math.round(performance.now() - mulai),
            pesan: siapHasil.pesan ?? 'Penilai gagal disiapkan.',
        };
    }

    state.status = 'menilai';

    const kelompok = Array.isArray(paket.kelompok) ? paket.kelompok : [];
    const batasTotal = Math.max(1000, Number(paket.batas_total_ms) || BAWAAN.batas_total_ms);

    const hasil = [];
    let keluaran = '';
    let galat = null;

    for (let i = 0; i < kelompok.length; i += 1) {
        // Batas total berlaku untuk seluruh paket, bukan per kelompok.
        if (performance.now() - mulai > batasTotal) {
            galat = {
                jenis: 'WaktuHabis',
                pesan: 'Penilaian melewati batas waktu total. Periksa apakah ada perulangan tanpa akhir.',
                baris: null,
            };

            break;
        }

        onProgres?.('kelompok', { ke: i + 1, dari: kelompok.length });

        const jawaban = await nilaiKelompok(kelompok[i], {
            kode: paket.kode ?? '',
            modul_diizinkan: paket.modul_diizinkan ?? [],
            maks_keluaran: paket.maks_keluaran ?? 2000,
            batas_kelompok_ms: paket.batas_kelompok_ms,
            hangat: i === 0,
        });

        if (! jawaban.ok) {
            galat = {
                jenis: jawaban.waktuHabis ? 'WaktuHabis' : 'PenilaiError',
                pesan: jawaban.pesan ?? 'Penilai gagal menjalankan kode.',
                baris: null,
            };

            // Worker sudah dimatikan; kelompok berikutnya butuh runtime baru.
            if (jawaban.waktuHabis) {
                break;
            }

            continue;
        }

        hasil.push(...(jawaban.hasil ?? []));

        if (jawaban.keluaran) {
            keluaran += (keluaran === '' ? '' : '\n') + jawaban.keluaran;
        }

        // Galat Python pada kelompok pertama sudah cukup untuk ditampilkan.
        if (jawaban.galat && galat === null) {
            galat = jawaban.galat;
        }
    }

    state.status = 'siap';
    perbaruiIndikator('siap', 100, '');

    return {
        ok: true,
        hasil,
        keluaran: keluaran.slice(0, Number(paket.maks_keluaran) || 2000),
        galat,
        durasi_ms: Math.round(performance.now() - mulai),
    };
}

/**
 * Perbarui indikator penilai di topbar (elemen [data-penilai-indikator]).
 *
 * Indikator ini SENGAJA hanya informatif: ia tidak pernah menampilkan
 * expected_value atau status lulus/gagal.
 *
 * @param {'idle'|'memuat'|'siap'|'menilai'|'galat'} status
 * @param {number} persen
 * @param {string} keterangan
 */
function perbaruiIndikator(status, persen = 0, keterangan = '') {
    const label = {
        idle: 'Penilai siap dipakai',
        memuat: 'Menyiapkan penilai',
        siap: 'Penilai siap',
        menilai: 'Menilai kode',
        galat: 'Penilai bermasalah',
    }[status] ?? 'Penilai';

    document.querySelectorAll('[data-penilai-indikator]').forEach((el) => {
        el.dataset.penilaiStatus = status;
        el.setAttribute('title', keterangan === '' ? label : `${label} — ${keterangan}`);

        const teks = el.querySelector('[data-penilai-label]');
        if (teks) {
            teks.textContent = status === 'memuat' && persen > 0 ? `${label} ${persen}%` : label;
        }

        const titik = el.querySelector('[data-penilai-titik]');
        if (titik) {
            titik.className = warnaTitik(status);
        }
    });
}

/** Warna titik status (kelas Tailwind yang sudah dipakai aplikasi). */
function warnaTitik(status) {
    const dasar = 'size-2 rounded-full ';

    return dasar + {
        idle: 'bg-slate-300',
        memuat: 'bg-amber-400 animate-pulse',
        siap: 'bg-primary-500',
        menilai: 'bg-sky-500 animate-pulse',
        galat: 'bg-rose-500',
    }[status] ?? 'bg-slate-300';
}

/**
 * Kirim laporan nilai MENTAH ke server.
 *
 * Tidak ada klaim lulus/gagal di sini: server yang menghitung. Nonce ikut
 * dikirim karena server memakainya untuk memastikan laporan ini sekali pakai.
 *
 * @param {object} paket
 * @param {object} hasil jalankanPaket()
 */
async function kirimLaporan(paket, hasil) {
    const respons = await fetch('/siswa/penilaian/lapor', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': ambilTokenCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            jenis: paket.jenis,
            id: paket.id,
            nonce: paket.nonce,
            hasil: hasil.hasil ?? [],
            keluaran: hasil.keluaran ?? '',
            galat: hasil.galat ?? null,
            durasi_ms: hasil.durasi_ms ?? 0,
            versi_pyodide: state.versi ?? paket.versi_pyodide ?? '',
        }),
    });

    const data = await respons.json().catch(() => ({}));

    if (! respons.ok) {
        return {
            ok: false,
            status: data.status ?? 'galat',
            pesan: data.pesan ?? 'Laporan tidak diterima server.',
        };
    }

    return { ok: true, ...data };
}

/** Ambil token CSRF dari meta tag layout. */
function ambilTokenCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Alur lengkap: ambil paket → jalankan di worker → laporkan ke server.
 *
 * @param {'misi'|'bug'} jenis
 * @param {number} id submission_id atau attempt_id
 * @param {(tahap: string, info: object) => void} [onProgres]
 * @returns {Promise<{ok: boolean, pesan?: string, status?: string, lulus?: number, total?: number}>}
 */
async function nilaiDanLapor(jenis, id, onProgres) {
    const url = jenis === 'misi'
        ? `/siswa/penilaian/misi/${id}`
        : `/siswa/penilaian/bug/${id}`;

    const respons = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (! respons.ok) {
        const data = await respons.json().catch(() => ({}));

        return {
            ok: false,
            status: data.status ?? 'tidak_tersedia',
            pesan: data.pesan ?? 'Paket penilaian tidak tersedia.',
        };
    }

    const paket = await respons.json();

    onProgres?.('menyiapkan', {});

    const hasil = await jalankanPaket(paket, onProgres);

    if (! hasil.ok) {
        perbaruiIndikator('galat', 0, hasil.pesan ?? '');

        return { ok: false, status: 'galat', pesan: hasil.pesan ?? 'Penilaian gagal.' };
    }

    onProgres?.('melaporkan', {});

    const laporan = await kirimLaporan(paket, hasil);

    perbaruiIndikator(laporan.ok ? 'siap' : 'galat', 100, laporan.ok ? '' : (laporan.pesan ?? ''));

    return laporan;
}

/**
 * Muat Pyodide di latar belakang setelah halaman siswa terbuka.
 *
 * Dijalankan setelah `load` + idle supaya tidak berebut bandwidth dengan
 * halaman. Kegagalan di sini TIDAK ditampilkan sebagai galat: penilaian
 * berikutnya akan mencoba memuat ulang.
 *
 * @param {{aset?: string, versi?: string}} opsi
 */
function pramuat(opsi = {}) {
    const aset = opsi.aset ?? '/vendor/pyodide/314.0.7/';
    const versi = opsi.versi ?? '314.0.7';

    const mulai = () => {
        siapkan(aset, versi).catch(() => {
            state.status = 'galat';
            perbaruiIndikator('galat', 0, '');
        });
    };

    // JANGAN memakai `(requestIdleCallback ?? setTimeout)(fn, 2000)`: kedua API
    // menerima parameter kedua yang berbeda jenis (objek vs angka), sehingga
    // memicu "not of type 'IdleRequestOptions'". jadwalkanSantai() menangani
    // perbedaan itu sekaligus menyediakan cadangan bila API gagal.
    if (document.readyState === 'complete') {
        jadwalkanSantai(mulai, JEDA_PRAMAAT);
    } else {
        window.addEventListener('load', () => jadwalkanSantai(mulai, JEDA_PRAMAAT), { once: true });
    }
}

/** Apakah penilai sudah siap dipakai tanpa menunggu? */
function sudahSiap() {
    return state.siap;
}

/** Status ringkas untuk debugging (bukan untuk UI). */
function statusRingkas() {
    return { status: state.status, siap: state.siap, versi: state.versi };
}

/**
 * Jalankan paket penilaian yang SUDAH diambil (dipakai mode verifikasi guru).
 *
 * @param {object} paket
 * @param {(tahap: string, info: object) => void} [onProgres]
 * @returns {Promise<{ok: boolean, hasil: Array, keluaran: string, galat: object|null, durasi_ms: number, pesan?: string}>}
 */
async function jalankanPaketLangsung(paket, onProgres) {
    return jalankanPaket(paket, onProgres);
}

/**
 * Ambil paket verifikasi guru, jalankan di perangkat guru, lalu kirim nilai
 * mentah ke endpoint periksa. Server mengembalikan rincian lulus/gagal beserta
 * expected_value (hanya untuk guru).
 *
 * @param {'misi'|'bug'} jenis
 * @param {number} id
 * @param {(tahap: string, info: object) => void} [onProgres]
 * @returns {Promise<{ok: boolean, pesan?: string, hasil?: Array, lulus?: number, total?: number, galat?: mixed}>}
 */
async function periksaUlang(jenis, id, onProgres) {
    const dasar = `/admin/verifikasi-nilai/${jenis}/${id}`;

    const ambil = await fetch(`${dasar}/paket`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (! ambil.ok) {
        const data = await ambil.json().catch(() => ({}));

        return { ok: false, pesan: data.pesan ?? 'Paket pemeriksaan tidak tersedia.' };
    }

    const paket = await ambil.json();

    const hasil = await jalankanPaketLangsung(paket, onProgres);

    if (! hasil.ok) {
        return { ok: false, pesan: hasil.pesan ?? 'Pemeriksaan gagal dijalankan.' };
    }

    const kirim = await fetch(`${dasar}/periksa`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': ambilTokenCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            hasil: hasil.hasil ?? [],
            galat: hasil.galat ?? null,
        }),
    });

    const data = await kirim.json().catch(() => ({}));

    if (! kirim.ok) {
        return { ok: false, pesan: data.message ?? 'Server menolak hasil pemeriksaan.' };
    }

    return { ok: true, ...data };
}

export {
    jalankanPaket,
    jalankanPaketLangsung,
    kirimLaporan,
    nilaiDanLapor,
    periksaUlang,
    pramuat,
    sudahSiap,
    statusRingkas,
};



