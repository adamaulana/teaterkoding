/**
 * Pembanding nilai — PORT 1:1 dari app/Actions/BandingkanNilai.php.
 *
 * === KENAPA DIPORT, BUKAN DIPANGGIL ===
 * Uji runtime Pyodide berjalan di Node (tanpa PHP), jadi aturan pembanding
 * harus tersedia dalam JS. Supaya tidak ada dua sumber kebenaran yang bisa
 * berbeda, port ini mengikuti aturan PHP PERSIS, dan uji
 * `pembanding-js-sama-dengan-php.test.mjs` membandingkan keduanya pada
 * matriks kasus (dijalankan bila PHP tersedia).
 *
 * Aturan (sama dengan PHP):
 *   - angka  : selisih <= tolerance (+ 1e-9), int 5 == float 5.0
 *   - teks   : sama persis, peka huruf besar/kecil
 *   - bool   : hanya cocok dengan True/False
 *   - None   : hanya cocok dengan None
 *   - lain / belum_dibuat : SELALU gagal
 *   - bool TIDAK dianggap angka (True tidak lolos untuk expected 1)
 */

/**
 * Urai expected_value (literal Python skalar).
 *
 * @param {string} expected
 * @returns {{jenis: 'angka'|'teks'|'bool'|'none', nilai: any}|null}
 */
export function uraiExpected(expected) {
    const teks = String(expected ?? '').trim();

    if (teks === '') {
        return null;
    }

    if (teks === 'True') {
        return { jenis: 'bool', nilai: true };
    }

    if (teks === 'False') {
        return { jenis: 'bool', nilai: false };
    }

    if (teks === 'None') {
        return { jenis: 'none', nilai: null };
    }

    // String Python: kutip tunggal/ganda, boleh berisi escape.
    if (/^(["\'])(?:[^\\]|\\.)*\1$/s.test(teks)) {
        return { jenis: 'teks', nilai: uraiTeks(teks) };
    }

    // Bilangan bulat, boleh pakai underscore.
    if (/^-?\d+(?:_\d+)*$/.test(teks)) {
        return { jenis: 'angka', nilai: Number(teks.replace(/_/g, '')) };
    }

    // Desimal.
    if (/^-?\d+\.\d+$/.test(teks)) {
        return { jenis: 'angka', nilai: Number(teks) };
    }

    return null;
}

/** Isi string Python tanpa tanda kutip pembungkus. */
function uraiTeks(teks) {
    const isi = teks.slice(1, -1);

    return isi
        .replace(/\\\\/g, '\\')
        .replace(/\\"/g, '"')
        .replace(/\\'/g, "'")
        .replace(/\\n/g, '\n')
        .replace(/\\t/g, '\t');
}

/** Nilai numerik dari laporan (null bila bukan angka). */
function angkaLaporan(laporan) {
    const tipe = String(laporan?.tipe ?? '');
    const nilai = laporan?.nilai;

    if (tipe !== 'int' && tipe !== 'float') {
        return null;
    }

    if (typeof nilai !== 'number' || ! Number.isFinite(nilai)) {
        return null;
    }

    return nilai;
}

/**
 * Apakah laporan cocok dengan expected_value?
 *
 * @param {{tipe?: string, nilai?: any}} laporan
 * @param {string} expected
 * @param {number|string} tolerance
 * @returns {boolean}
 */
export function cocok(laporan, expected, tolerance = 0) {
    const tipe = String(laporan?.tipe ?? 'lain');

    if (tipe === 'lain' || tipe === 'belum_dibuat') {
        return false;
    }

    const harap = uraiExpected(expected);

    if (harap === null) {
        return false;
    }

    if (harap.jenis === 'angka') {
        const angka = angkaLaporan(laporan);

        if (angka === null) {
            return false;
        }

        return Math.abs(angka - Number(harap.nilai)) <= Number(tolerance ?? 0) + 1e-9;
    }

    if (harap.jenis === 'teks') {
        return tipe === 'str' && String(laporan?.nilai ?? '') === harap.nilai;
    }

    if (harap.jenis === 'bool') {
        return tipe === 'bool' && Boolean(laporan?.nilai) === harap.nilai;
    }

    return tipe === 'none';
}

/**
 * Teks yang disimpan di kolom actual_value (maks 100 karakter).
 *
 * @param {{tipe?: string, nilai?: any}} laporan
 * @returns {string}
 */
export function teksAktual(laporan) {
    const tipe = String(laporan?.tipe ?? 'lain');
    const nilai = laporan?.nilai ?? null;

    let teks;

    switch (tipe) {
        case 'belum_dibuat':
            teks = '(variabel belum dibuat)';
            break;
        case 'none':
            teks = 'None';
            break;
        case 'bool':
            teks = nilai ? 'True' : 'False';
            break;
        case 'str':
            teks = String(nilai);
            break;
        case 'float':
            teks = formatAngka(nilai, true);
            break;
        case 'int':
            teks = formatAngka(nilai, false);
            break;
        default:
            teks = String(nilai ?? '');
    }

    return teks.slice(0, 100);
}

/**
 * Tampilan angka: float tetap "20000.0", int menjadi "4780850".
 *
 * JSON menghapus penanda float (500.0 menjadi 500), jadi paksaFloat dipakai
 * saat tipe asli Python adalah float.
 */
function formatAngka(nilai, paksaFloat) {
    if (typeof nilai !== 'number' || ! Number.isFinite(nilai)) {
        return String(nilai);
    }

    const bulat = Math.round(nilai * 1e6) / 1e6;

    if (paksaFloat) {
        const teks = String(bulat);

        return teks.includes('.') ? teks : `${teks}.0`;
    }

    return Number.isInteger(bulat) ? String(bulat) : String(bulat);
}
