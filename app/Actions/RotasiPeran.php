<?php

namespace App\Actions;

/**
 * Rotasi peran antar-level (murni — tanpa DB, mudah diuji).
 *
 * Peran digeser melingkar mengikuti urutan anggota kelompok: setiap anggota
 * menerima peran milik anggota sebelumnya, dan anggota pertama menerima peran
 * milik anggota terakhir.
 */
class RotasiPeran
{
    /**
     * Hitung peran baru berdasarkan peran level sebelumnya.
     *
     * @param  array<int, int>  $urutanAnggota  ID siswa dalam urutan anggota kelompok.
     * @param  array<int, int|null>  $peranSebelumnya  Peta student_id => role_id level sebelumnya.
     * @return array<int, int|null> Peta student_id => role_id level tujuan.
     */
    public function geser(array $urutanAnggota, array $peranSebelumnya): array
    {
        $anggota = array_values(array_unique(array_map('intval', $urutanAnggota)));
        $jumlah = count($anggota);

        if ($jumlah === 0) {
            return [];
        }

        $hasil = [];

        foreach ($anggota as $i => $studentId) {
            $sebelumnya = $anggota[($i - 1 + $jumlah) % $jumlah];
            $hasil[$studentId] = $peranSebelumnya[$sebelumnya] ?? null;
        }

        return $hasil;
    }
}
