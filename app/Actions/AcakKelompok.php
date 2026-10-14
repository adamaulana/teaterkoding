<?php

namespace App\Actions;

/**
 * Pembagian kelompok otomatis (murni — tanpa DB, mudah diuji).
 *
 * Rumus: jumlah kelompok g = ceil(n / target), lalu sisa anggota dibagi
 * ke kelompok-kelompok pertama sehingga tidak ada kelompok berisi 1–2 orang
 * pada kelas berukuran wajar.
 *
 * Contoh: 22 siswa target 5 → 5,5,4,4,4 · 13 siswa target 4 → 4,3,3,3.
 */
class AcakKelompok
{
    public const TARGET_VALID = [4, 5];

    /**
     * Bagi daftar siswa menjadi kelompok seimbang.
     *
     * @param  array<int, int|string>  $studentIds
     * @param  bool  $acak  Acak urutan siswa sebelum dibagi (matikan pada pengujian).
     * @return array<int, array{name: string, student_ids: array<int, int>}>
     */
    public function susun(array $studentIds, int $target, int $nomorAwal = 1, bool $acak = true): array
    {
        $ids = array_values(array_unique(array_map('intval', $studentIds)));

        if ($ids === []) {
            return [];
        }

        if (! in_array($target, self::TARGET_VALID, true)) {
            $target = self::TARGET_VALID[0];
        }

        if ($acak) {
            shuffle($ids);
        }

        $jumlah = count($ids);
        $grup = max(1, (int) ceil($jumlah / $target));
        $dasar = intdiv($jumlah, $grup);
        $sisa = $jumlah % $grup;

        $hasil = [];
        $offset = 0;

        for ($i = 0; $i < $grup; $i++) {
            $ukuran = $dasar + ($i < $sisa ? 1 : 0);
            $anggota = array_slice($ids, $offset, $ukuran);
            $offset += $ukuran;

            $hasil[] = [
                'name' => 'Kelompok '.($nomorAwal + $i),
                'student_ids' => $anggota,
            ];
        }

        return $hasil;
    }

    /**
     * Nomor awal lanjutan dari nama kelompok yang sudah ada ("Kelompok 3" → 4).
     *
     * @param  iterable<int, string>  $existingNames
     */
    public function deteksiNomorAwal(iterable $existingNames): int
    {
        $maks = 0;

        foreach ($existingNames as $name) {
            if (preg_match('/Kelompok\s+(\d+)/i', (string) $name, $cocok) === 1) {
                $maks = max($maks, (int) $cocok[1]);
            }
        }

        return $maks + 1;
    }
}
