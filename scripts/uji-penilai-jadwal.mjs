/**
 * Uji penjadwal "santai" (resources/js/penilai/jadwal.js) TANPA browser.
 *
 * Latar belakang: kode lama memakai
 *   (window.requestIdleCallback ?? window.setTimeout)(mulai, 2000)
 * yang memicu di browser:
 *   TypeError: Failed to execute 'requestIdleCallback' on 'Window':
 *              The provided value is not of type 'IdleRequestOptions'
 * karena requestIdleCallback mengharapkan OBJEK opsi, sedangkan setTimeout
 * mengharapkan ANGKA.
 *
 * Jalankan: npm run uji:jadwal
 */
import { jadwalkanSantai, JEDA_PRAMAAT } from '../resources/js/penilai/jadwal.js';

let lulus = 0;
let gagal = 0;

/** Catat hasil satu pemeriksaan. */
function periksa(nama, kondisi, detail = '') {
    if (kondisi) {
        lulus += 1;
        console.log(`  OK    ${nama}`);

        return;
    }

    gagal += 1;
    console.log(`  GAGAL ${nama}${detail === '' ? '' : ` — ${detail}`}`);
}

/** Lingkungan tiruan: mencatat bagaimana callback dijadwalkan. */
function buatLingkungan({ idle, lempar = false, adaTimeout = true } = {}) {
    const catatan = { idle: [], timeout: [] };

    const target = {
        requestIdleCallback(fn, opsi) {
            if (lempar) {
                // Meniru penolakan browser atas opsi yang salah jenis.
                throw new TypeError(
                    "Failed to execute 'requestIdleCallback' on 'Window': The provided value is not of type 'IdleRequestOptions'.",
                );
            }

            catatan.idle.push({ fn, opsi });
        },
    };

    if (adaTimeout) {
        target.setTimeout = (fn, ms) => {
            catatan.timeout.push({ fn, ms });
        };
    }

    if (idle === false) {
        delete target.requestIdleCallback;
    }

    const jalankanSemua = () => {
        for (const satu of catatan.idle) {
            satu.fn();
        }
        for (const satu of catatan.timeout) {
            satu.fn();
        }
    };

    return { target, catatan, jalankanSemua };
}

// ---------- (a) requestIdleCallback tersedia ----------
console.log('(a) requestIdleCallback tersedia → opsi berupa OBJEK');

{
    const { target, catatan } = buatLingkungan();

    const jalur = jadwalkanSantai(() => {}, 2000, target);

    periksa('jalur = idle', jalur === 'idle', String(jalur));
    periksa('requestIdleCallback dipanggil sekali', catatan.idle.length === 1, `jumlah=${catatan.idle.length}`);
    periksa('setTimeout TIDAK dipakai', catatan.timeout.length === 0, `jumlah=${catatan.timeout.length}`);

    const opsi = catatan.idle[0]?.opsi;

    periksa(
        'parameter kedua berupa objek (bukan angka)',
        typeof opsi === 'object' && opsi !== null,
        `typeof=${typeof opsi}`,
    );
    periksa('opsi.timeout = 2000', opsi?.timeout === 2000, JSON.stringify(opsi));
    periksa('angka 2000 TIDAK dikirim sebagai opsi', opsi !== 2000, String(opsi));
}

// ---------- (b) requestIdleCallback tidak ada ----------
console.log('\n(b) requestIdleCallback tidak ada → setTimeout dengan ANGKA');

{
    const { target, catatan } = buatLingkungan({ idle: false });

    const jalur = jadwalkanSantai(() => {}, 2000, target);

    periksa('jalur = timeout', jalur === 'timeout', String(jalur));
    periksa('setTimeout dipanggil sekali', catatan.timeout.length === 1, `jumlah=${catatan.timeout.length}`);
    periksa('delay berupa angka 2000', catatan.timeout[0]?.ms === 2000, String(catatan.timeout[0]?.ms));
    periksa('requestIdleCallback tidak dipanggil', catatan.idle.length === 0, `jumlah=${catatan.idle.length}`);
}

// ---------- (c) requestIdleCallback melempar ----------
console.log('\n(c) requestIdleCallback melempar → jatuh ke setTimeout tanpa galat lolos');

{
    const { target, catatan } = buatLingkungan({ lempar: true });
    let melempar = null;
    let jalur = null;

    try {
        jalur = jadwalkanSantai(() => {}, 2000, target);
    } catch (galat) {
        melempar = galat;
    }

    periksa('tidak ada galat yang lolos ke pemanggil', melempar === null, melempar?.message ?? '');
    periksa('jalur = timeout', jalur === 'timeout', String(jalur));
    periksa('setTimeout dipakai sebagai cadangan', catatan.timeout.length === 1, `jumlah=${catatan.timeout.length}`);
}

// ---------- (d) callback terpanggil tepat sekali ----------
console.log('\n(d) Callback terpanggil tepat sekali');

{
    const { target, jalankanSemua } = buatLingkungan();
    let jumlah = 0;

    jadwalkanSantai(() => { jumlah += 1; }, 2000, target);
    jalankanSemua();

    periksa('callback dijalankan sekali (jalur idle)', jumlah === 1, String(jumlah));
}

{
    const { target, jalankanSemua } = buatLingkungan({ idle: false });
    let jumlah = 0;

    jadwalkanSantai(() => { jumlah += 1; }, 2000, target);
    jalankanSemua();

    periksa('callback dijalankan sekali (jalur timeout)', jumlah === 1, String(jumlah));
}

// ---------- (e) tanpa kedua API ----------
console.log('\n(e) Tanpa requestIdleCallback & setTimeout → dijalankan langsung');

{
    const { target } = buatLingkungan({ idle: false, adaTimeout: false });
    let jumlah = 0;

    const jalur = jadwalkanSantai(() => { jumlah += 1; }, 2000, target);

    periksa('callback tetap dijalankan', jumlah === 1, String(jumlah));
    periksa('jalur = timeout', jalur === 'timeout', String(jalur));
}

// ---------- (f) nilai bawaan & masukan tidak sah ----------
console.log('\n(f) Nilai bawaan & masukan tidak sah');

periksa('JEDA_PRAMAAT = 2000 ms', JEDA_PRAMAAT === 2000, String(JEDA_PRAMAAT));

{
    const { target, catatan } = buatLingkungan();

    jadwalkanSantai(() => {}, undefined, target);

    periksa(
        'jeda bawaan dipakai saat ms tidak diberikan',
        catatan.idle[0]?.opsi?.timeout === 2000,
        JSON.stringify(catatan.idle[0]?.opsi),
    );
}

{
    const { target, catatan } = buatLingkungan();
    let melempar = null;

    try {
        jadwalkanSantai(null, 2000, target);
    } catch (galat) {
        melempar = galat;
    }

    periksa('fn bukan fungsi tidak melempar', melempar === null, melempar?.message ?? '');
    periksa('fn bukan fungsi tidak menjadwalkan', catatan.idle.length === 0 && catatan.timeout.length === 0);
}

// ---------- Ringkasan ----------
console.log(`\n${lulus} lulus, ${gagal} gagal`);

if (gagal > 0) {
    console.log('HASIL: GAGAL');
    process.exitCode = 1;
} else {
    console.log('HASIL: LULUS');
}
