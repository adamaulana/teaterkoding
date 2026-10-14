<?php

namespace App\Actions;

/**
 * Pembagian peran awal (Level 1) secara acak namun mematuhi aturan (murni).
 *
 * Aturan: Coder, Tester, dan Analyst wajib terisi tepat satu orang yang
 * berbeda; Reviewer dan Bug Hunter opsional (maksimal satu orang per peran)
 * dan hanya diberikan bila anggota masih tersisa.
 */
class BagiPeranAwal
{
    /**
     * Peran wajib: nama peran di tabel roles.
     *
     * @var array<int, string>
     */
    public const WAJIB = ['Coder', 'Tester', 'Analyst'];

    /**
     * Peran opsional.
     *
     * @var array<int, string>
     */
    public const OPSIONAL = ['Reviewer', 'Bug Hunter'];

    /**
     * Bagi peran Level 1 untuk satu kelompok.
     *
     * @param  array<int, int|string>  $studentIds
     * @param  array<string, int>  $roleIds  Peta nama peran => role_id.
     * @return array<int, int> Peta student_id => role_id.
     */
    public function bagi(array $studentIds, array $roleIds, bool $acak = true): array
    {
        $anggota = array_values(array_unique(array_map('intval', $studentIds)));

        if (count($anggota) < count(self::WAJIB)) {
            return [];
        }

        if ($acak) {
            shuffle($anggota);
        }

        $hasil = [];
        $kursor = 0;

        foreach (self::WAJIB as $nama) {
            $studentId = $anggota[$kursor];
            $hasil[$studentId] = $roleIds[$nama] ?? 0;
            $kursor++;
        }

        foreach (self::OPSIONAL as $nama) {
            if (! isset($anggota[$kursor])) {
                break;
            }

            $studentId = $anggota[$kursor];
            $hasil[$studentId] = $roleIds[$nama] ?? 0;
            $kursor++;
        }

        return $hasil;
    }
}
