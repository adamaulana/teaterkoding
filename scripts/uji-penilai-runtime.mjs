/**
 * Uji jalur runtime NYATA: loadPyodide({ indexURL }) + penilai.py + stdout.
 *
 * Skrip ini meniru worker.js tanpa browser, sehingga kita tahu bahwa
 * (a) aset self-hosted cukup untuk menjalankan Python,
 * (b) penilai.py bisa dieksekusi lewat runPython, dan
 * (c) nilai_paket() mengembalikan JSON yang bisa di-parse.
 *
 * Jalankan: node scripts/uji-penilai-runtime.mjs
 */
import { readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const aset = join(akar, 'public', 'vendor', 'pyodide', '314.0.7') + '/';

const { loadPyodide, version } = await import(pathToFileURL(join(aset, 'pyodide.mjs')).href);

console.log('versi pyodide:', version);

let keluaran = '';

const py = await loadPyodide({
    indexURL: aset,
    stdout: (teks) => {
        keluaran += teks + '\n';
    },
    stderr: (teks) => {
        keluaran += teks + '\n';
    },
});

console.log('loadPyodide: OK');

const skrip = await readFile(join(akar, 'public', 'vendor', 'teater', 'penilai.py'), 'utf8');
py.runPython(skrip);

console.log('penilai.py: OK');

const payload = {
    kode: 'saldo = 10_000\nbunga = saldo * 0.05\nprint(bunga)',
    setup_code: '',
    target: [
        { id: 1, check_target: 'bunga' },
        { id: 2, check_target: 'saldo' },
        { id: 3, check_target: 'tidak_ada' },
    ],
    modul_diizinkan: ['math'],
    maks_keluaran: 2000,
};

const hasil = JSON.parse(py.runPython(`nilai_paket(${JSON.stringify(JSON.stringify(payload))})`));

console.log('hasil:', JSON.stringify(hasil));

// Kasus konflik setup (Level 2) dan loop tak berujung tidak diuji di sini
// karena yang terakhir butuh worker.terminate() dari browser.
const konflik = JSON.parse(py.runPython(`nilai_paket(${JSON.stringify(
    JSON.stringify({
        kode: 'saldo = 5\nstatus = "Gold"',
        setup_code: 'saldo = 12000000',
        target: [{ id: 1, check_target: 'status' }],
        modul_diizinkan: [],
        maks_keluaran: 2000,
    }),
)})`));

console.log('konflik:', JSON.stringify(konflik));

const lulus = hasil.hasil[0].tipe === 'float'
    && hasil.hasil[0].nilai === 500
    && hasil.hasil[1].tipe === 'int'
    && hasil.hasil[2].tipe === 'belum_dibuat'
    && hasil.keluaran.trim() === '500.0'
    && konflik.galat?.jenis === 'KonflikSetup';

console.log(lulus ? '\nRUNTIME OK' : '\nRUNTIME GAGAL');

process.exitCode = lulus ? 0 : 1;
