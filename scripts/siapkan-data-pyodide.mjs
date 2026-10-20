/**
 * Bangun tests/Pyodide/data/kasus.json dari SEEDER ASLI.
 *
 * Kenapa lewat PHP, bukan membaca database langsung:
 * Uji Node harus memakai kasus uji NYATA yang dihasilkan MateriKoperasiSeeder
 * (L1 5, L2 16, L3 5, L4 13; bug 8 kartu / 13 kasus). Seeder memakai
 * updateOrCreate dan tidak destruktif, jadi aman dijalankan di database
 * pengembangan; hasilnya diekspor ke JSON yang ikut di-commit sehingga
 * `npm run test:pyodide` bisa berjalan tanpa database.
 *
 * Jalankan: php artisan teater:ekspor-kasus-uji
 * (atau: npm run test:pyodide:data)
 */
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const tujuan = join(akar, 'tests', 'Pyodide', 'data', 'kasus.json');

if (existsSync(tujuan) && ! process.argv.includes('--paksa')) {
    console.log(`[data:pyodide] ${tujuan} sudah ada. Pakai --paksa untuk membangun ulang.`);
    process.exit(0);
}

console.log('[data:pyodide] menyiapkan materi dari seeder...');

/**
 * Cari executable PHP tanpa `shell: true` (yang memicu DEP0190 di Node 24).
 *
 * Windows: `where php`; Unix: `which php`. Bila gagal, jatuh kembali ke 'php'.
 */
const cariPhp = () => {
    const perintah = process.platform === 'win32' ? 'where' : 'which';

    try {
        const hasil = execFileSync(perintah, ['php'], { encoding: 'utf8' });
        const baris = hasil.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);

        return baris[0] ?? 'php';
    } catch {
        return 'php';
    }
};

const php = cariPhp();

const jalankanPhp = (argumen) => execFileSync(php, argumen, {
    cwd: akar,
    stdio: 'inherit',
});

jalankanPhp(['artisan', 'db:seed', '--class=MateriKoperasiSeeder', '--force']);

jalankanPhp(['artisan', 'teater:ekspor-kasus-uji', `--path=${tujuan}`]);

console.log('[data:pyodide] selesai.');
