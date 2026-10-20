<?php

namespace App\Actions;

/**
 * Pembanding nilai laporan browser vs expected_value (MURNI — tanpa DB).
 *
 * Kenapa ada di sini, bukan di runner:
 * Kode siswa dijalankan di BROWSER siswa, jadi browser hanya boleh melaporkan
 * NILAI MENTAH (tipe + nilai). Keputusan lulus/gagal SELALU dihitung server
 * dengan fungsi ini. expected_value & tolerance tidak pernah dikirim ke
 * browser, sehingga laporan palsu tidak bisa "menebak" jawaban.
 *
 * Laporan dari penilai.py berbentuk:
 *   ['tipe' => 'int'|'float'|'str'|'bool'|'none'|'lain'|'belum_dibuat',
 *    'nilai' => skalar]
 *
 * Aturan pencocokan (sengaja ketat):
 *   - Angka  : dibandingkan numerik, selisih <= tolerance (0 = harus sama).
 *              int 4780850 dan float 4780850.0 dianggap sama.
 *   - String : harus sama persis (peka huruf besar/kecil).
 *   - Bool   : harus sama persis.
 *   - None   : hanya cocok dengan None.
 *   - 'lain' / 'belum_dibuat' : SELALU gagal (list, objek, galat, variabel
 *              yang tidak dibuat siswa).
 */
final class BandingkanNilai
{
    /**
     * Apakah laporan cocok dengan expected_value?
     *
     * @param  array{tipe?: string, nilai?: mixed}  $laporan
     */
    public function cocok(array $laporan, string $expected, mixed $tolerance = 0): bool
    {
        $tipe = (string) ($laporan['tipe'] ?? 'lain');

        // Tipe yang tidak bisa dibandingkan tidak pernah lulus.
        if ($tipe === 'lain' || $tipe === 'belum_dibuat') {
            return false;
        }

        $diharapkan = $this->uraiExpected($expected);

        if ($diharapkan === null) {
            // expected_value tidak dikenali (seharusnya dicegah ValidasiKasusUji).
            return false;
        }

        [$jenisHarap, $nilaiHarap] = $diharapkan;

        if ($jenisHarap === 'angka') {
            $angka = $this->angkaLaporan($laporan);

            if ($angka === null) {
                return false;
            }

            return abs($angka - (float) $nilaiHarap) <= (float) ($tolerance ?? 0) + 1e-9;
        }

        if ($jenisHarap === 'teks') {
            return $tipe === 'str' && (string) ($laporan['nilai'] ?? '') === $nilaiHarap;
        }

        if ($jenisHarap === 'bool') {
            return $tipe === 'bool' && (bool) ($laporan['nilai'] ?? false) === $nilaiHarap;
        }

        // jenisHarap === 'none'
        return $tipe === 'none';
    }

    /**
     * Teks yang disimpan di kolom actual_value (maks 100 karakter).
     *
     * Untuk 'belum_dibuat' dipakai penanda yang mudah dibaca guru, bukan null,
     * agar guru bisa membedakan "tidak dibuat" dari "tidak dilaporkan".
     *
     * @param  array{tipe?: string, nilai?: mixed}  $laporan
     */
    public function teksAktual(array $laporan): string
    {
        $tipe = (string) ($laporan['tipe'] ?? 'lain');
        $nilai = $laporan['nilai'] ?? null;

        $teks = match ($tipe) {
            'belum_dibuat' => '(variabel belum dibuat)',
            'none' => 'None',
            'bool' => $nilai ? 'True' : 'False',
            'str' => (string) $nilai,
            // JSON menghapus ".0", jadi tipe float dipakai untuk mengembalikannya.
            'float' => $this->formatAngka($nilai, paksaFloat: true),
            'int' => $this->formatAngka($nilai),
            default => (string) ($nilai ?? ''),
        };

        return mb_substr($teks, 0, 100);
    }

    /**
     * Ubah expected_value (literal Python skalar) menjadi [jenis, nilai].
     *
     * @return array{0: 'angka'|'teks'|'bool'|'none', 1: mixed}|null
     */
    public function uraiExpected(string $expected): ?array
    {
        $teks = trim($expected);

        if ($teks === '') {
            return null;
        }

        if ($teks === 'True') {
            return ['bool', true];
        }

        if ($teks === 'False') {
            return ['bool', false];
        }

        if ($teks === 'None') {
            return ['none', null];
        }

        // String Python: kutip tunggal atau ganda, boleh berisi escape.
        if (preg_match('/^(["\'])(?:[^\\\\]|\\\\.)*\1$/s', $teks) === 1) {
            return ['teks', $this->uraiTeks($teks)];
        }

        // Angka: 10_000_000, -5 (underscore dibuang).
        if (preg_match('/^-?\d+(?:_\d+)*$/', $teks) === 1) {
            return ['angka', (int) str_replace('_', '', $teks)];
        }

        // Desimal: 3.14, -0.5
        if (preg_match('/^-?\d+\.\d+$/', $teks) === 1) {
            return ['angka', (float) $teks];
        }

        return null;
    }

    /**
     * Isi string Python tanpa tanda kutip pembungkus.
     */
    private function uraiTeks(string $teks): string
    {
        $isi = mb_substr($teks, 1, mb_strlen($teks) - 2);

        return str_replace(
            ['\\\\', '\\"', "\\'", '\\n', '\\t'],
            ['\\', '"', "'", "\n", "\t"],
            $isi,
        );
    }

    /**
     * Nilai numerik dari laporan (null bila bukan angka).
     *
     * @param  array{tipe?: string, nilai?: mixed}  $laporan
     */
    private function angkaLaporan(array $laporan): ?float
    {
        $tipe = (string) ($laporan['tipe'] ?? '');
        $nilai = $laporan['nilai'] ?? null;

        // bool adalah subclass int di Python, tapi di sini TIDAK dianggap angka
        // agar `True` tidak lolos untuk expected 1.
        if ($tipe !== 'int' && $tipe !== 'float') {
            return null;
        }

        if (! is_int($nilai) && ! is_float($nilai)) {
            return null;
        }

        if (is_float($nilai) && (is_nan($nilai) || is_infinite($nilai))) {
            return null;
        }

        return (float) $nilai;
    }

    /**
     * Tampilan angka: 20000.0 tetap "20000.0", 4780850 menjadi "4780850".
     *
     * JSON menghapus penanda float (500.0 menjadi 500), jadi $paksaFloat dipakai
     * saat tipe asli Python adalah float.
     */
    private function formatAngka(mixed $nilai, bool $paksaFloat = false): string
    {
        if (is_int($nilai)) {
            return (string) $nilai;
        }

        if (! is_float($nilai) && ! is_numeric($nilai)) {
            return (string) $nilai;
        }

        $angka = (float) $nilai;

        // round() mencegah 0.1+0.2 tampil sebagai 0.30000000000000004.
        $bulat = round($angka, 6);

        if ($paksaFloat) {
            $teks = (string) $bulat;

            return str_contains($teks, '.') ? $teks : $teks.'.0';
        }

        return $bulat == (int) $bulat
            ? (string) (int) $bulat
            : (string) $bulat;
    }
}
