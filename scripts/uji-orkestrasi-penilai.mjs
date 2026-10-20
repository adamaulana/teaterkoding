/**
 * Uji orkestrasi main-thread penilai TANPA browser.
 *
 * Yang diuji:
 *   1. Timeout per kelompok memicu terminate() lalu worker dibuat ulang.
 *   2. Total timeout menghentikan sisa kelompok.
 *   3. Kelompok setup_code berbeda dijalankan berurutan dan hasilnya digabung.
 *
 * Worker palsu di bawah meniru protokol worker.js, termasuk perilaku "macet"
 * (tidak pernah membalas) untuk menguji terminate.
 *
 * Jalankan: node scripts/uji-orkestrasi-penilai.mjs
 */
import { readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sumber = await readFile(join(akar, 'resources', 'js', 'penilai', 'index.js'), 'utf8');

// --- Lingkungan tiruan -------------------------------------------------------
const dibuat = [];
const dihentikan = [];

class WorkerPalsu {
    constructor(url, opsi) {
        this.url = url;
        this.opsi = opsi;
        this.pendengar = {};
        this.hidup = true;
        dibuat.push(this);
    }

    addEventListener(nama, fn) {
        this.pendengar[nama] = fn;
    }

    postMessage(pesan) {
        if (pesan.type === 'muat') {
            setTimeout(() => this.kirim({ type: 'siap', versi: '314.0.7' }), 1);
            return;
        }

        // Simulasi kode macet: tidak pernah membalas.
        if (pesan.setup_code === 'MENCEKIK') {
            return;
        }

        setTimeout(() => {
            this.kirim({
                type: 'hasil',
                id: pesan.id,
                hasil: [{ id: pesan.target[0].id, tipe: 'int', nilai: 1 }],
                keluaran: `grup:${pesan.setup_code}`,
                galat: null,
            });
        }, 1);
    }

    kirim(data) {
        this.pendengar.message?.({ data });
    }

    terminate() {
        this.hidup = false;
        dihentikan.push(this);
    }
}

globalThis.window = globalThis;
globalThis.document = { querySelector: () => null, querySelectorAll: () => [], readyState: 'complete' };
globalThis.performance = globalThis.performance ?? { now: () => Date.now() };
globalThis.Worker = WorkerPalsu;

// Ubah ESM menjadi fungsi yang bisa dievaluasi dengan Worker palsu.
const modul = sumber
    .replace(/^export\s*\{[\s\S]*?\};$/m, '')
    .replace(/new Worker\(new URL\('\.\/worker\.js', import\.meta\.url\),/g, 'new Worker("worker.js",');

const jalankanModul = new Function(`${modul}\nreturn { jalankanPaket, statusRingkas };`);
const { jalankanPaket, statusRingkas } = jalankanModul();

// --- Kasus 1: tiga kelompok berurutan ---------------------------------------
const paketNormal = {
    aset: '/vendor/pyodide/314.0.7/',
    versi_pyodide: '314.0.7',
    kode: 'x = 1',
    batas_kelompok_ms: 300,
    batas_total_ms: 5000,
    maks_keluaran: 2000,
    kelompok: [
        { setup_code: 'a', target: [{ id: 1, check_target: 'x' }] },
        { setup_code: 'b', target: [{ id: 2, check_target: 'x' }] },
        { setup_code: '', target: [{ id: 3, check_target: 'x' }] },
    ],
};

const hasilNormal = await jalankanPaket(paketNormal);

console.log('kasus 1 (3 kelompok):', JSON.stringify(hasilNormal));
console.log('kasus 1 hasil.length =', hasilNormal.hasil.length, '(harus 3)');
console.log('kasus 1 keluaran =', JSON.stringify(hasilNormal.keluaran));

// --- Kasus 2: kelompok kedua macet → terminate + lanjut ---------------------
const paketMacet = {
    ...paketNormal,
    batas_kelompok_ms: 120,
    kelompok: [
        { setup_code: 'a', target: [{ id: 1, check_target: 'x' }] },
        { setup_code: 'MENCEKIK', target: [{ id: 2, check_target: 'x' }] },
    ],
};

const jumlahWorkerSebelum = dibuat.length;
const hasilMacet = await jalankanPaket(paketMacet);

console.log('\nkasus 2 (macet):', JSON.stringify(hasilMacet));
console.log('kasus 2 worker dihentikan =', dihentikan.length - 0, '(harus >= 1)');
console.log('kasus 2 jenis galat =', hasilMacet.galat?.jenis, '(harus WaktuHabis)');

// --- Kasus 3: worker baru dibuat setelah terminate -------------------------
const paketPulih = {
    ...paketNormal,
    batas_kelompok_ms: 300,
    kelompok: [{ setup_code: 'c', target: [{ id: 9, check_target: 'x' }] }],
};

const hasilPulih = await jalankanPaket(paketPulih);

console.log('\nkasus 3 (pulih):', JSON.stringify(hasilPulih.hasil));
console.log('kasus 3 worker total dibuat =', dibuat.length, 'sebelumnya', jumlahWorkerSebelum);
console.log('kasus 3 status =', JSON.stringify(statusRingkas()));

const lulus = hasilNormal.ok
    && hasilNormal.hasil.length === 3
    && hasilNormal.keluaran.includes('grup:a')
    && hasilMacet.galat?.jenis === 'WaktuHabis'
    && dihentikan.length >= 1
    && hasilPulih.ok
    && hasilPulih.hasil.length === 1
    && dibuat.length > jumlahWorkerSebelum;

console.log(lulus ? '\nORKESTRASI OK' : '\nORKESTRASI GAGAL');

process.exitCode = lulus ? 0 : 1;
