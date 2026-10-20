/**
 * Salin runtime Pyodide (CORE SAJA) ke public/vendor/pyodide/{versi}/, lalu
 * salin skrip penilai Python ke public/vendor/teater/penilai.py.
 *
 * Kenapa disalin, bukan diimpor lewat Vite:
 *   - Pyodide memuat berkas .wasm & python_stdlib.zip secara RUNTIME lewat
 *     indexURL. Berkas itu tidak bisa "di-bundle" oleh Vite.
 *   - Jalur berversi (…/{versi}/…) membuat cache browser aman: versi baru
 *     otomatis jadi URL baru, jadi aman diberi header cache panjang.
 *   - TANPA CDN: seluruh aset berasal dari node_modules (paket npm resmi).
 *
 * penilai.py ikut disalin karena Web Worker memuatnya lewat fetch() sebagai
 * teks — bukan sebagai modul JS — supaya bisa dieksekusi di namespace Pyodide.
 *
 * Dipanggil oleh: npm run vendor:pyodide, postinstall, dan sebelum build.
 */
import { createRequire } from 'node:module';
import { cp, mkdir, readFile, stat } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..');

/** Berkas minimal untuk menjalankan Pyodide (setara pyodide-core). */
const BERKAS = [
    'pyodide.mjs',
    'pyodide.asm.mjs',
    'pyodide.asm.wasm',
    'python_stdlib.zip',
    'pyodide-lock.json',
    'pyodide.js',
];

/** Skrip penilai Python yang dijalankan di dalam Pyodide. */
const SKRIP_PENILAI = {
    dari: join(akar, 'resources', 'py', 'penilai.py'),
    ke: join(akar, 'public', 'vendor', 'teater', 'penilai.py'),
};

function pesan(teks) {
    process.stdout.write(`[vendor:pyodide] ${teks}\n`);
}

async function main() {
    const pkgPath = require.resolve('pyodide/package.json');
    const pkg = JSON.parse(await readFile(pkgPath, 'utf8'));
    const versi = pkg.version;
    const sumber = dirname(pkgPath);
    const tujuan = join(akar, 'public', 'vendor', 'pyodide', versi);

    await mkdir(tujuan, { recursive: true });

    let disalin = 0;

    for (const nama of BERKAS) {
        const dari = join(sumber, nama);

        try {
            await stat(dari);
        } catch {
            pesan(`LEWATI ${nama} (tidak ada di paket pyodide ${versi})`);
            continue;
        }

        await cp(dari, join(tujuan, nama));
        disalin += 1;
    }

    // Penanda versi untuk dokumentasi & debugging di browser.
    await cp(join(sumber, 'pyodide-lock.json'), join(tujuan, 'pyodide-lock.json'));

    pesan(`pyodide ${versi} → public/vendor/pyodide/${versi}/ (${disalin} berkas)`);

    if (disalin < 5) {
        pesan('PERINGATAN: berkas inti kurang lengkap; periksa paket pyodide.');
        process.exitCode = 1;
    }

    // Skrip penilai Python (dibaca worker lewat fetch sebagai teks).
    try {
        await stat(SKRIP_PENILAI.dari);
        await mkdir(dirname(SKRIP_PENILAI.ke), { recursive: true });
        await cp(SKRIP_PENILAI.dari, SKRIP_PENILAI.ke);
        pesan('penilai.py → public/vendor/teater/penilai.py');
    } catch (error) {
        pesan(`GAGAL menyalin penilai.py: ${error.message}`);
        process.exitCode = 1;
    }
}

main().catch((error) => {
    pesan(`GAGAL: ${error.message}`);
    process.exitCode = 1;
});
