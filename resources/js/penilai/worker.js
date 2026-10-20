/**
 * Web Worker penilai Python (Pyodide) — Fase 6.
 *
 * === KENAPA WORKER, BUKAN MAIN THREAD ===
 * `exec()` kode siswa bersifat SINKRON: sekali berjalan, main thread membeku
 * dan tombol "Batal" tidak akan pernah sempat diklik. Worker bisa DIMATIKAN
 * paksa (worker.terminate()) dari main thread saat melewati batas waktu.
 *
 * === KENAPA type: "module" ===
 * pyodide.mjs adalah ES module. Worker klasik tidak bisa memuatnya, jadi
 * worker ini WAJIB dibuat dengan `new Worker(url, { type: 'module' })`
 * (lihat index.js). Ini persyaratan Pyodide, bukan pilihan gaya.
 *
 * === PROTOKOL PESAN ===
 * Masuk : { type: 'muat', aset, versi }
 *         { type: 'nilai', id, kode, setup_code, target, modul_diizinkan,
 *           maks_keluaran }
 * Keluar: { type: 'siap', versi }            → Pyodide + penilai.py siap
 *         { type: 'progres', tahap, persen } → untuk indikator di topbar
 *         { type: 'hasil', id, hasil, keluaran, galat }
 *         { type: 'galat', id, pesan }
 *
 * SATU PESAN = SATU KELOMPOK setup_code. Main thread yang mengatur urutan,
 * batas waktu per kelompok, dan batas waktu total; worker tidak menyimpan
 * state antar pesan sehingga aman dimatikan lalu dibuat ulang.
 *
 * === YANG TIDAK PERNAH MASUK WORKER ===
 * expected_value, tolerance, nonce, dan cookie/CSRF. Worker hanya menerima
 * check_target + kode siswa + setup_code.
 */

let pyodide = null;
let siap = false;
let asetUrl = null;

/** Kirim pesan ke main thread. */
function kirim(pesan) {
    self.postMessage(pesan);
}

/**
 * Muat Pyodide dari aset SELF-HOSTED (tanpa CDN) lalu jalankan penilai.py.
 *
 * penilai.py diambil sebagai TEKS lewat fetch karena harus dieksekusi di
 * namespace Python, bukan diimpor sebagai modul JS.
 */
async function muat(pesan) {
    if (siap) {
        kirim({ type: 'siap', versi: pesan.versi });
        return;
    }

    asetUrl = pesan.aset;

    kirim({ type: 'progres', tahap: 'memuat-runtime', persen: 20 });

    // Dynamic import supaya URL aset tetap runtime (bukan di-bundle Vite).
    const modul = await import(/* @vite-ignore */ `${asetUrl}pyodide.mjs`);

    kirim({ type: 'progres', tahap: 'menyiapkan-python', persen: 60 });

    pyodide = await modul.loadPyodide({
        indexURL: asetUrl,
        // Tanpa ini, Pyodide menulis ke console & stdout tidak bisa ditangkap.
        stdout: () => {},
        stderr: () => {},
    });

    kirim({ type: 'progres', tahap: 'memuat-penilai', persen: 85 });

    const respons = await fetch('/vendor/teater/penilai.py', { cache: 'force-cache' });

    if (!respons.ok) {
        throw new Error(`Gagal memuat penilai.py (HTTP ${respons.status}).`);
    }

    const skrip = await respons.text();

    // Jalankan skrip penilai SEKALI di namespace global Python.
    pyodide.runPython(skrip);

    siap = true;

    kirim({ type: 'siap', versi: pesan.versi ?? modul.version ?? null });
}

/**
 * Nilai satu kelompok kasus uji.
 *
 * Semua keluaran dikembalikan sebagai string JSON dari penilai.py; worker
 * hanya meneruskan (tidak menafsirkan) agar logika tetap satu tempat.
 */
function nilai(pesan) {
    if (!siap || pyodide === null) {
        kirim({ type: 'galat', id: pesan.id, pesan: 'Penilai Python belum siap.' });
        return;
    }

    const muatan = {
        kode: pesan.kode ?? '',
        setup_code: pesan.setup_code ?? '',
        target: pesan.target ?? [],
        modul_diizinkan: pesan.modul_diizinkan ?? [],
        maks_keluaran: pesan.maks_keluaran ?? 2000,
    };

    try {
        // Penilai mengembalikan string JSON; parse di sini agar main thread
        // menerima objek biasa.
        const keluaran = pyodide.runPython(
            `nilai_paket(${JSON.stringify(JSON.stringify(muatan))})`,
        );

        kirim({ type: 'hasil', id: pesan.id, ...JSON.parse(keluaran) });
    } catch (error) {
        kirim({
            type: 'galat',
            id: pesan.id,
            pesan: String(error?.message ?? error).slice(0, 300),
        });
    }
}

self.addEventListener('message', async (event) => {
    const pesan = event.data ?? {};

    try {
        if (pesan.type === 'muat') {
            await muat(pesan);
            return;
        }

        if (pesan.type === 'nilai') {
            nilai(pesan);
            return;
        }
    } catch (error) {
        kirim({
            type: 'galat',
            id: pesan.id ?? null,
            pesan: String(error?.message ?? error).slice(0, 300),
        });
    }
});
