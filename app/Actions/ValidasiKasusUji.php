<?php

namespace App\Actions;

/**
 * Validasi format kasus uji (murni — tanpa DB, mudah diuji).
 *
 * Kasus uji adalah BANK JAWABAN RAHASIA: formatnya divalidasi ketat di sini
 * agar aman dieksekusi di sandbox pada tahap berikutnya, dan agar tidak
 * pernah bocor ke halaman siswa.
 */
class ValidasiKasusUji
{
    public const JENIS_VARIABEL = 'Variabel';

    public const JENIS_FUNGSI = 'Fungsi';

    /**
     * Panjang maksimum mengikuti panjang kolom skema.
     *
     * @return array<string, int>
     */
    public static function batas(): array
    {
        return config('materi.batas', [
            'label' => 100,
            'setup_code' => 200,
            'check_target' => 100,
            'expected_value' => 100,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public static function tokenTerlarang(): array
    {
        return config('materi.check_target_terlarang', [
            'import', '__', 'exec', 'eval', 'open', 'os', 'sys', 'subprocess', 'input',
        ]);
    }

    /**
     * Validasi satu kasus uji. Mengembalikan pesan error (null bila lolos).
     *
     * @param  array{label?: string, setup_code?: string|null, check_target?: string, expected_value?: string, tolerance?: mixed}  $data
     */
    public function periksa(array $data): ?string
    {
        $batas = self::batas();

        $label = trim((string) ($data['label'] ?? ''));
        $setup = trim((string) ($data['setup_code'] ?? ''));
        $target = trim((string) ($data['check_target'] ?? ''));
        $expected = trim((string) ($data['expected_value'] ?? ''));
        $tolerance = $data['tolerance'] ?? 0;

        if ($label === '') {
            return 'Label kasus uji wajib diisi.';
        }

        if (mb_strlen($label) > $batas['label']) {
            return "Label maksimal {$batas['label']} karakter.";
        }

        if ($target === '') {
            return 'Target pengecekan wajib diisi.';
        }

        if (mb_strlen($target) > $batas['check_target']) {
            return "Target pengecekan maksimal {$batas['check_target']} karakter.";
        }

        if ($expected === '') {
            return 'Nilai diharapkan wajib diisi.';
        }

        if (mb_strlen($expected) > $batas['expected_value']) {
            return "Nilai diharapkan maksimal {$batas['expected_value']} karakter.";
        }

        if ($setup !== '' && mb_strlen($setup) > $batas['setup_code']) {
            return "Kode input awal maksimal {$batas['setup_code']} karakter.";
        }

        if (($pesan = $this->periksaCheckTarget($target)) !== null) {
            return $pesan;
        }

        if (($pesan = $this->periksaExpectedValue($expected)) !== null) {
            return $pesan;
        }

        if (($pesan = $this->periksaTolerance($tolerance, $expected)) !== null) {
            return $pesan;
        }

        if ($setup !== '' && ($pesan = $this->periksaSetupCode($setup)) !== null) {
            return $pesan;
        }

        return null;
    }

    /**
     * check_target harus berupa nama variabel Python yang valid ATAU tepat
     * satu pemanggilan fungsi (nama diikuti tanda kurung seimbang).
     */
    public function periksaCheckTarget(string $target): ?string
    {
        if (preg_match('/[;\r\n]/', $target) === 1) {
            return 'Target pengecekan tidak boleh memuat titik koma atau baris baru.';
        }

        $lower = mb_strtolower($target);

        foreach (self::tokenTerlarang() as $token) {
            if (str_contains($lower, mb_strtolower($token))) {
                return "Target pengecekan memuat kata terlarang: {$token}.";
            }
        }

        // Varian 1: nama variabel Python valid.
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $target) === 1) {
            return null;
        }

        // Varian 2: satu pemanggilan fungsi: nama(...)
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*\(.*\)$/', $target)) {
            return 'Target pengecekan harus berupa nama variabel (mis. status) atau satu pemanggilan fungsi (mis. hitung_total(100)).';
        }

        if (! $this->tandaKurungSeimbang($target)) {
            return 'Tanda kurung pada target pengecekan tidak seimbang.';
        }

        if (! $this->kutipSeimbang($target)) {
            return 'Tanda kutip pada target pengecekan tidak seimbang.';
        }

        return null;
    }

    /**
     * expected_value harus literal Python SKALAR (bukan list/ekspresi).
     */
    public function periksaExpectedValue(string $expected): ?string
    {
        // Bilangan bulat, boleh pakai underscore: 10_000_000, -5, 0
        if (preg_match('/^-?\d+(?:_\d+)*$/', $expected) === 1) {
            return null;
        }

        // Desimal: 3.14, -0.5
        if (preg_match('/^-?\d+\.\d+$/', $expected) === 1) {
            return null;
        }

        // String dalam kutip tunggal/ganda.
        if (preg_match('/^(["\'])(?:[^\\\\]|\\\\.)*\1$/s', $expected) === 1) {
            return null;
        }

        // Boolean / None
        if (in_array($expected, ['True', 'False', 'None'], true)) {
            return null;
        }

        return 'Nilai diharapkan harus literal skalar: bilangan (mis. 1500 atau 3.5), string dalam kutip (mis. "lunas"), True, False, atau None.';
    }

    /**
     * tolerance hanya boleh > 0 bila expected_value numerik, dan tidak negatif.
     */
    public function periksaTolerance(mixed $tolerance, string $expected): ?string
    {
        if ($tolerance === null || $tolerance === '' || $tolerance === 0 || $tolerance === '0') {
            return null;
        }

        if (! is_numeric($tolerance)) {
            return 'Toleransi harus berupa angka.';
        }

        if ((float) $tolerance < 0) {
            return 'Toleransi tidak boleh negatif.';
        }

        if (! is_numeric($expected)) {
            return 'Toleransi hanya boleh diisi bila nilai diharapkan berupa angka.';
        }

        return null;
    }

    /**
     * setup_code hanya boleh berisi satu atau beberapa assignment sederhana
     * `nama = literal`, dipisah baris baru.
     */
    public function periksaSetupCode(string $setup): ?string
    {
        $baris = preg_split('/\r\n|\r|\n/', $setup) ?: [];

        foreach ($baris as $satu) {
            $satu = trim($satu);

            if ($satu === '') {
                continue;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*\s*=\s*.+$/', $satu) !== 1) {
                return "Kode input awal hanya boleh berisi assignment sederhana (mis. saldo = 10_000_000). Baris bermasalah: {$satu}";
            }

            $posisi = mb_strpos($satu, '=');
            $nilai = trim($posisi === false ? '' : mb_substr($satu, $posisi + 1));

            if ($this->periksaExpectedValue($nilai) !== null) {
                return "Nilai pada kode input awal harus literal skalar. Baris bermasalah: {$satu}";
            }
        }

        return null;
    }

    /**
     * Jenis kasus uji: Variabel atau Fungsi (otomatis dari check_target).
     */
    public function jenis(string $checkTarget): string
    {
        return str_contains($checkTarget, '(')
            ? self::JENIS_FUNGSI
            : self::JENIS_VARIABEL;
    }

    /**
     * Nama fungsi dari check_target jenis fungsi (teks sebelum tanda kurung).
     */
    public function namaFungsi(string $checkTarget): ?string
    {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $checkTarget, $cocok) === 1) {
            return $cocok[1];
        }

        return null;
    }

    /**
     * Daftar nama fungsi unik dari sekumpulan check_target.
     *
     * @param  iterable<int, string>  $checkTargets
     * @return array<int, string>
     */
    public function daftarFungsi(iterable $checkTargets): array
    {
        $hasil = [];

        foreach ($checkTargets as $target) {
            $nama = $this->namaFungsi((string) $target);

            if ($nama !== null && ! in_array($nama, $hasil, true)) {
                $hasil[] = $nama;
            }
        }

        return $hasil;
    }

    private function tandaKurungSeimbang(string $teks): bool
    {
        $dalam = 0;
        $panjang = mb_strlen($teks);

        for ($i = 0; $i < $panjang; $i++) {
            $huruf = mb_substr($teks, $i, 1);

            if ($huruf === '(') {
                $dalam++;
            } elseif ($huruf === ')') {
                $dalam--;

                if ($dalam < 0) {
                    return false;
                }
            }
        }

        return $dalam === 0;
    }

    private function kutipSeimbang(string $teks): bool
    {
        foreach (['"', "'"] as $kutip) {
            if (substr_count($teks, $kutip) % 2 !== 0) {
                return false;
            }
        }

        return true;
    }
}
