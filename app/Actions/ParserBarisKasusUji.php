<?php

namespace App\Actions;

/**
 * Pengurai baris kasus uji untuk fitur "Tambah Banyak Sekaligus" (murni).
 *
 * Format tiap baris (dipisah tanda pipa):
 *   label | setup_code | check_target | expected_value | toleransi
 *
 * Kolom setup_code dan toleransi boleh kosong. Baris yang diawali '#' atau
 * kosong akan dilewati sebagai komentar.
 */
class ParserBarisKasusUji
{
    public function __construct(private readonly ValidasiKasusUji $validator = new ValidasiKasusUji) {}

    /**
     * Uraikan teks menjadi baris valid & baris bermasalah.
     *
     * @return array{
     *     valid: array<int, array{line: int, label: string, setup_code: string|null, check_target: string, expected_value: string, tolerance: float|null}>,
     *     invalid: array<int, array{line: int, raw: string, reason: string}>,
     *     total: int,
     * }
     */
    public function parse(string $teks): array
    {
        $baris = preg_split('/\r\n|\r|\n/', $teks) ?: [];

        $valid = [];
        $invalid = [];
        $total = 0;
        $sudahAda = [];

        foreach ($baris as $indeks => $mentah) {
            $nomorBaris = $indeks + 1;
            $bersih = trim($mentah);

            if ($bersih === '' || str_starts_with($bersih, '#')) {
                continue;
            }

            $total++;

            $kolom = array_map('trim', explode('|', $bersih));
            $label = $kolom[0];
            $setup = $kolom[1] ?? '';
            $target = $kolom[2] ?? '';
            $expected = $kolom[3] ?? '';
            $toleransi = $kolom[4] ?? '';

            if (count($kolom) < 4) {
                $invalid[] = [
                    'line' => $nomorBaris,
                    'raw' => $bersih,
                    'reason' => 'Format harus: label | setup_code | check_target | expected_value | toleransi (minimal 4 kolom).',
                ];

                continue;
            }

            $data = [
                'label' => $label,
                'setup_code' => $setup,
                'check_target' => $target,
                'expected_value' => $expected,
                'tolerance' => $toleransi,
            ];

            if (($pesan = $this->validator->periksa($data)) !== null) {
                $invalid[] = ['line' => $nomorBaris, 'raw' => $bersih, 'reason' => $pesan];

                continue;
            }

            $kunci = mb_strtolower($label.'|'.$setup.'|'.$target.'|'.$expected);

            if (isset($sudahAda[$kunci])) {
                $invalid[] = ['line' => $nomorBaris, 'raw' => $bersih, 'reason' => 'Kasus uji ini duplikat dengan baris sebelumnya.'];

                continue;
            }

            $sudahAda[$kunci] = true;

            $valid[] = [
                'line' => $nomorBaris,
                'label' => $label,
                'setup_code' => $setup === '' ? null : $setup,
                'check_target' => $target,
                'expected_value' => $expected,
                'tolerance' => $toleransi === '' ? null : (float) $toleransi,
            ];
        }

        return ['valid' => $valid, 'invalid' => $invalid, 'total' => $total];
    }
}
