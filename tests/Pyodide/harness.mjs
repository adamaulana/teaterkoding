/**
 * Helper uji runtime Pyodide (dipakai uji-pyodide.mjs).
 *
 * Memuat Pyodide dari aset self-hosted yang SAMA dengan produksi, di dalam
 * worker_threads Node, memakai skrip penilai asli (public/vendor/teater/penilai.py).
 *
 * === KENAPA ADA KELAS Penilai ===
 * Sebagian kode materi MEMANG macet (mis. Kartu Bug "Bulan yang Tak Pernah
 * Berganti" berisi while tanpa increment). Di browser, main thread memanggil
 * worker.terminate(). Kelas ini meniru perilaku itu: setiap permintaan punya
 * batas waktu, dan bila terlampaui worker dimatikan lalu dibuat ulang. Tanpa
 * ini, uji Node akan menggantung selamanya.
 */
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { Worker } from 'node:worker_threads';
import { cocok } from './bandingkan.mjs';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');

export const ASET = join(akar, 'public', 'vendor', 'pyodide', '314.0.7') + '/';
export const PENILAI = join(akar, 'public', 'vendor', 'teater', 'penilai.py');

/** Modul yang diizinkan (sama dengan config/teater.php). */
export const MODUL = ['math', 'statistics', 'random', 'datetime', 'decimal', 'fractions'];

/** Buat worker Node yang memuat Pyodide + penilai.py asli. */
export function buatWorker() {
    return new Worker(new URL('../../scripts/worker-penilai-node.mjs', import.meta.url), {
        workerData: {
            pyodideUrl: pathToFileURL(join(ASET, 'pyodide.mjs')).href,
            aset: ASET,
            penilai: PENILAI,
        },
    });
}

/** Tunggu pesan pertama { siap: true }. */
export function tungguSiap(worker, batasMs = 180000) {
    return new Promise((selesai, gagal) => {
        const batas = setTimeout(() => gagal(new Error('timeout memuat Pyodide')), batasMs);

        const padaPesan = (pesan) => {
            if (pesan.siap) {
                clearTimeout(batas);
                worker.off('message', padaPesan);
                selesai(pesan);
            }
        };

        worker.on('message', padaPesan);
        worker.on('error', gagal);
    });
}

/** Kelompokkan kasus uji per setup_code (sama seperti PaketPenilaian). */
export function kelompokkan(kasus) {
    const peta = new Map();

    for (const k of kasus) {
        const kunci = k.setup_code ?? '';

        if (! peta.has(kunci)) {
            peta.set(kunci, { setup_code: kunci, target: [] });
        }

        peta.get(kunci).target.push({ id: k.id, check_target: k.check_target });
    }

    return [...peta.values()];
}

/**
 * Penilai dengan batas waktu per permintaan + pemulihan worker.
 *
 * Meniru resources/js/penilai/index.js: bila batas waktu terlampaui, worker
 * dimatikan (terminate) dan dibuat ulang untuk permintaan berikutnya.
 */
export class Penilai {
    constructor(batasMs = 5000) {
        this.batasMs = batasMs;
        this.worker = null;
        this.versi = null;
        this.jumlahTerminate = 0;
    }

    /** Pastikan worker siap (membuat/memuat ulang bila perlu). */
    async siapkan() {
        if (this.worker !== null) {
            return;
        }

        this.worker = buatWorker();
        const info = await tungguSiap(this.worker);
        this.versi = info.versi;
    }

    /** Matikan worker paksa (dipakai saat batas waktu atau di akhir uji). */
    async matikan() {
        if (this.worker === null) {
            return null;
        }

        const worker = this.worker;
        this.worker = null;

        const kode = await worker.terminate();
        this.jumlahTerminate += 1;

        return kode;
    }

    /**
     * Kirim satu paket dan tunggu hasilnya, dengan batas waktu.
     *
     * @returns {Promise<object>} hasil penilai, atau { galat: { jenis: 'WaktuHabis' } }
     */
    async nilai(paket) {
        await this.siapkan();

        const worker = this.worker;

        return new Promise((selesai) => {
            let sudah = false;

            const akhiri = (hasil) => {
                if (sudah) {
                    return;
                }

                sudah = true;
                clearTimeout(batas);
                worker.off('message', padaPesan);
                selesai(hasil);
            };

            const padaPesan = (pesan) => {
                if (pesan.ok === undefined) {
                    return;
                }

                akhiri(pesan.ok
                    ? pesan.hasil
                    : { galat: { jenis: 'NodeError', pesan: pesan.pesan }, hasil: [] });
            };

            const batas = setTimeout(() => {
                // Kode macet: hentikan worker, lalu permintaan berikutnya
                // membuat worker baru (sama seperti UI di browser).
                // matikan() di-await agar penghitung terminate sudah final
                // sebelum hasil dikembalikan ke pemanggil.
                this.matikan().finally(() => {
                    akhiri({
                        hasil: [],
                        keluaran: '',
                        galat: { jenis: 'WaktuHabis', pesan: 'Batas waktu penilaian tercapai.', baris: null },
                    });
                });
            }, this.batasMs);

            worker.on('message', padaPesan);
            worker.postMessage(paket);
        });
    }
}

/**
 * Jalankan seluruh kasus satu misi/bug (per kelompok setup) lalu bandingkan
 * dengan expected_value memakai aturan yang sama dengan server.
 *
 * @returns {Promise<{lulus: number, total: number, galat: object|null, hasil: Array}>}
 */
export async function jalankanKasus(penilai, kode, kasus) {
    const semua = [];
    let galat = null;

    for (const grup of kelompokkan(kasus)) {
        const keluaran = await penilai.nilai({
            kode,
            setup_code: grup.setup_code,
            target: grup.target,
            modul_diizinkan: MODUL,
            maks_keluaran: 2000,
        });

        semua.push(...(keluaran.hasil ?? []));

        if (keluaran.galat && galat === null) {
            galat = keluaran.galat;
        }

        // Kode macet: kelompok lain tidak akan lebih baik, hentikan.
        if (keluaran.galat?.jenis === 'WaktuHabis') {
            break;
        }
    }

    const perId = new Map(semua.map((s) => [s.id, s]));

    let jumlahLulus = 0;

    for (const k of kasus) {
        const laporan = perId.get(k.id) ?? { tipe: 'belum_dibuat', nilai: null };

        if (cocok(laporan, k.expected_value, k.tolerance)) {
            jumlahLulus += 1;
        }
    }

    return { lulus: jumlahLulus, total: kasus.length, galat, hasil: semua };
}

