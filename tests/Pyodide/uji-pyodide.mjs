/**
 * Uji runtime Pyodide (Bagian E poin 2) â€” memakai paket & skrip ASLI.
 *
 * Cakupan:
 *   (a) jawaban acuan tiap misi lulus SEMUA kasus uji nyata dari seeder
 *   (b) 8 Kartu Bug: buggy_code tidak lulus semua, versi perbaikan lulus semua
 *   (c) menulis ulang `saldo` di Level 2 â†’ pesan khusus
 *   (d) kode terlarang ditolak
 *   (e) print palsu tidak memengaruhi hasil
 *   (f) batas waktu dengan worker_threads + terminate()
 *
 * Jalankan: npm run test:pyodide
 */
import { readFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { jalankanKasus, Penilai } from './harness.mjs';
import { JAWABAN_ACUAN, KODE_PRINT_PALSU, KODE_SALDO, KODE_TERLARANG } from './jawaban.mjs';
import { KODE_PERBAIKAN_BUG } from './perbaikan-bug.mjs';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const data = JSON.parse(await readFile(join(akar, 'tests', 'Pyodide', 'data', 'kasus.json'), 'utf8'));

let gagal = 0;
let lulus = 0;

/** Catat hasil satu pemeriksaan. */
function periksa(nama, kondisi, detail = '') {
    if (kondisi) {
        lulus += 1;
        console.log(`  OK    ${nama}`);

        return;
    }

    gagal += 1;
    console.log(`  GAGAL ${nama}${detail === '' ? '' : ` â€” ${detail}`}`);
}

console.log(`Data kasus uji: ${data.misi.length} misi, ${data.bug.length} kartu bug\n`);

// Batas waktu 5 detik per permintaan, meniru TEATER_PYODIDE_SETUP_TIMEOUT_MS
// yang diperlonggar. Kode yang macet (mis. bug #6) akan dihentikan paksa.
const penilai = new Penilai(5000);
await penilai.siapkan();

console.log(`Pyodide ${penilai.versi} siap (worker_threads)\n`);

// ---------- (a) Jawaban acuan tiap misi ----------
console.log('(a) Jawaban acuan tiap misi lulus SEMUA kasus uji nyata');

const jumlahDiharapkan = { 'KOP-L1-BUDI': 5, 'KOP-L2-STATUS': 16, 'KOP-L3-LOOP': 5, 'KOP-L4-KASIR': 13 };

for (const misi of data.misi) {
    const kode = JAWABAN_ACUAN[misi.kode];

    periksa(`jawaban acuan tersedia untuk ${misi.kode}`, typeof kode === 'string', 'tidak ada di jawaban.mjs');

    if (typeof kode !== 'string') {
        continue;
    }

    periksa(
        `${misi.kode}: jumlah kasus = ${jumlahDiharapkan[misi.kode]}`,
        misi.kasus.length === jumlahDiharapkan[misi.kode],
        `dapat ${misi.kasus.length}`,
    );

    const hasil = await jalankanKasus(penilai, kode, misi.kasus);

    periksa(
        `${misi.kode}: ${hasil.lulus}/${hasil.total} kasus lulus`,
        hasil.lulus === hasil.total && hasil.galat === null,
        hasil.galat ? `galat: ${hasil.galat.jenis}: ${hasil.galat.pesan}` : 'ada kasus gagal',
    );
}

// ---------- (b) 8 Kartu Bug ----------
console.log('\n(b) Kartu Bug: buggy_code gagal, versi perbaikan lulus');

for (const bug of data.bug) {
    const perbaikan = KODE_PERBAIKAN_BUG[bug.judul];

    periksa(
        `bug #${bug.id} "${bug.judul}": kode perbaikan tersedia`,
        typeof perbaikan === 'string',
        'tidak ada di perbaikan-bug.mjs',
    );

    if (typeof perbaikan !== 'string') {
        continue;
    }

    const sebelum = await jalankanKasus(penilai, bug.buggy_code, bug.kasus);
    const sesudah = await jalankanKasus(penilai, perbaikan, bug.kasus);

    periksa(
        `bug #${bug.id} "${bug.judul}": kode bermasalah TIDAK lulus semua`,
        sebelum.lulus < sebelum.total || sebelum.galat !== null,
        sebelum.galat
            ? `${sebelum.lulus}/${sebelum.total} (galat: ${sebelum.galat.jenis})`
            : `${sebelum.lulus}/${sebelum.total} lulus`,
    );

    periksa(
        `bug #${bug.id} "${bug.judul}": versi perbaikan lulus semua`,
        sesudah.lulus === sesudah.total && sesudah.galat === null,
        sesudah.galat ? `galat: ${sesudah.galat.jenis}: ${sesudah.galat.pesan}` : `${sesudah.lulus}/${sesudah.total}`,
    );
}

// ---------- (c) Konflik setup saldo (Level 2) ----------
console.log('\n(c) Menulis ulang `saldo` di Level 2 mendapat pesan khusus');

const l2 = data.misi.find((m) => m.kode === 'KOP-L2-STATUS');
const konflik = await penilai.nilai({
    kode: KODE_SALDO,
    setup_code: 'saldo = 12000000',
    target: l2.kasus.slice(0, 1).map((k) => ({ id: k.id, check_target: k.check_target })),
    modul_diizinkan: [],
    maks_keluaran: 2000,
});

periksa(
    'jenis galat = KonflikSetup',
    konflik.galat?.jenis === 'KonflikSetup',
    JSON.stringify(konflik.galat),
);
periksa(
    'pesan menyebut variabel `saldo`',
    String(konflik.galat?.pesan ?? '').includes('saldo'),
    String(konflik.galat?.pesan ?? ''),
);

// ---------- (d) Kode terlarang ----------
console.log('\n(d) Kode terlarang ditolak');

for (const [nama, kode] of Object.entries(KODE_TERLARANG)) {
    const hasil = await penilai.nilai({
        kode,
        setup_code: '',
        target: [{ id: 1, check_target: 'x' }],
        modul_diizinkan: ['math'],
        maks_keluaran: 2000,
    });

    periksa(
        `${nama} ditolak`,
        hasil.galat?.jenis === 'PerintahTerlarang',
        JSON.stringify(hasil.galat),
    );
}

// ---------- (e) Print palsu tidak memengaruhi hasil ----------
console.log('\n(e) print palsu yang meniru laporan tidak memengaruhi hasil');

const l1 = data.misi.find((m) => m.kode === 'KOP-L1-BUDI');
const palsu = await jalankanKasus(penilai, KODE_PRINT_PALSU, l1.kasus);

periksa(
    'nilai variabel tetap dihitung dari kode, bukan dari print',
    palsu.lulus < palsu.total,
    `${palsu.lulus}/${palsu.total} lulus`,
);
periksa('keluaran (stdout) tetap dilaporkan terpisah', palsu.hasil.length > 0);

// ---------- (f) Batas waktu: worker + terminate ----------
console.log('\n(f) Batas waktu dengan worker_threads + terminate()');

// Kode macet nyata (sama seperti bug #6): penilai harus menghentikannya
// dalam batas waktu, bukan menggantung.
const sebelumTerminate = penilai.jumlahTerminate;
const mulai = Date.now();

const macet = await penilai.nilai({
    kode: 'while True:\n    pass',
    setup_code: '',
    target: [{ id: 1, check_target: 'x' }],
    modul_diizinkan: [],
    maks_keluaran: 2000,
});

const durasi = Date.now() - mulai;

periksa(
    'kode macet dihentikan dengan galat WaktuHabis',
    macet.galat?.jenis === 'WaktuHabis',
    JSON.stringify(macet.galat),
);
periksa(
    'worker dimatikan paksa (terminate) saat batas waktu',
    penilai.jumlahTerminate === sebelumTerminate + 1,
    `terminate=${penilai.jumlahTerminate - sebelumTerminate}, durasi=${durasi}ms`,
);
periksa(
    'penghentian selesai dalam batas waktu yang wajar',
    durasi < 30000,
    `${durasi}ms`,
);

// Setelah terminate, penilai harus pulih: worker baru dibuat otomatis.
const pulih = await penilai.nilai({
    kode: 'x = 7',
    setup_code: '',
    target: [{ id: 1, check_target: 'x' }],
    modul_diizinkan: [],
    maks_keluaran: 2000,
});

periksa(
    'penilai pulih otomatis setelah terminate',
    pulih.hasil?.[0]?.nilai === 7 && pulih.galat === null,
    JSON.stringify(pulih.hasil),
);

await penilai.matikan();

// ---------- Ringkasan ----------
console.log(`\n${lulus} lulus, ${gagal} gagal`);

if (gagal > 0) {
    console.log('HASIL: GAGAL');
    process.exitCode = 1;
} else {
    console.log('HASIL: LULUS');
}

