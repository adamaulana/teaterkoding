/**
 * Pembanding paritas: jalankan port JS atas matriks kasus yang dikirim PHP.
 *
 * Dipakai tests/Feature/PyodideParitasTest.php. Keluaran HARUS tepat
 * "PARITAS OK" bila semua hasil sama dengan PHP; selain itu cetak perbedaan
 * agar mudah ditelusuri.
 */
import { readFileSync } from 'node:fs';
import { cocok } from './bandingkan.mjs';

const berkas = process.argv[2];

if (!berkas) {
    console.error('Pemakaian: node paritas-pembanding.mjs <berkas.json>');
    process.exit(2);
}

const { kasus, harapan } = JSON.parse(readFileSync(berkas, 'utf8'));

let beda = 0;

kasus.forEach((k, i) => {
    const hasil = cocok(k.laporan, k.expected, k.tolerance);

    if (hasil !== harapan[i]) {
        beda += 1;
        console.log(`BEDA #${i}: js=${hasil} php=${harapan[i]} laporan=${JSON.stringify(k.laporan)} expected=${k.expected}`);
    }
});

if (beda > 0) {
    console.log(`${beda} perbedaan`);
    process.exit(1);
}

console.log('PARITAS OK');
