/**
 * Worker Node untuk uji penilai (Bagian E).
 *
 * Meniru worker.js browser: memuat Pyodide dari aset self-hosted yang SAMA
 * dengan produksi, menjalankan penilai.py asli, lalu menerima pesan
 * { kode, setup_code, target, ... } dan membalas hasil JSON.
 *
 * Dipakai oleh scripts/uji-pyodide.mjs lewat worker_threads, sehingga
 * worker.terminate() bisa dipakai untuk menguji batas waktu.
 */
import { readFile } from 'node:fs/promises';
import { parentPort, workerData } from 'node:worker_threads';

const { loadPyodide } = await import(workerData.pyodideUrl);

const py = await loadPyodide({
    indexURL: workerData.aset,
    stdout: () => {},
    stderr: () => {},
});

py.runPython(await readFile(workerData.penilai, 'utf8'));

parentPort.postMessage({ siap: true, versi: py.version });

parentPort.on('message', (pesan) => {
    try {
        // String JSON dikirim sebagai argumen Python; runPython mengembalikan
        // string JSON yang kita parse kembali.
        const keluaran = py.runPython('nilai_paket(' + JSON.stringify(JSON.stringify(pesan)) + ')');

        parentPort.postMessage({ ok: true, hasil: JSON.parse(keluaran) });
    } catch (error) {
        parentPort.postMessage({ ok: false, pesan: String(error?.message ?? error) });
    }
});
