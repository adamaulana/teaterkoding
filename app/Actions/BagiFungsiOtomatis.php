<?php

namespace App\Actions;

use App\Models\FunctionOwnership;
use App\Models\Team;

/**
 * Bagi kepemilikan fungsi secara merata ke anggota kelompok (murni).
 *
 * Aturan: satu fungsi tepat satu pemilik; pemilik harus anggota kelompok.
 * Bila anggota lebih sedikit daripada fungsi, anggota terakhir memegang
 * fungsi sisanya (sesuai spesifikasi Fase 3).
 */
class BagiFungsiOtomatis
{
    /**
     * @param  array<int, string>  $fungsi
     * @param  array<int, int>  $anggotaIds
     * @return array<int, int> Peta student_id => jumlah fungsi (untuk pratinjau)
     */
    public function bagi(array $fungsi, array $anggotaIds, bool $acak = true): array
    {
        return array_map(
            fn (array $item): int => count($item),
            $this->petaPemilik($fungsi, $anggotaIds, $acak),
        );
    }

    /**
     * Peta lengkap: student_id => daftar fungsi yang dipegang.
     *
     * Distribusi MERATA (bukan bergiliran):
     *   - Bila fungsi <= anggota: tiap anggota pertama memegang tepat satu.
     *   - Bila fungsi >  anggota: dibagi rata, sisanya ditambahkan ke anggota
     *     terakhir (sesuai spesifikasi: "anggota terakhir memegang dua fungsi").
     *
     * @param  array<int, string>  $fungsi
     * @param  array<int, int>  $anggotaIds
     * @return array<int, array<int, string>>
     */
    public function petaPemilik(array $fungsi, array $anggotaIds, bool $acak = true): array
    {
        $anggota = array_values(array_unique(array_map('intval', $anggotaIds)));
        $daftarFungsi = array_values(array_unique(array_map('strval', $fungsi)));

        if ($anggota === [] || $daftarFungsi === []) {
            return [];
        }

        if ($acak) {
            shuffle($daftarFungsi);
        }

        $peta = [];
        foreach ($anggota as $id) {
            $peta[$id] = [];
        }

        $jumlahAnggota = count($anggota);
        $jumlahFungsi = count($daftarFungsi);

        if ($jumlahFungsi <= $jumlahAnggota) {
            // Satu fungsi per anggota; anggota terakhir boleh tidak kebagian.
            foreach ($daftarFungsi as $indeks => $namaFungsi) {
                $peta[$anggota[$indeks]][] = $namaFungsi;
            }

            return $peta;
        }

        // Fungsi lebih banyak daripada anggota: bagi rata, sisa ke anggota terakhir.
        $dasar = intdiv($jumlahFungsi, $jumlahAnggota);
        $sisa = $jumlahFungsi % $jumlahAnggota;

        $offset = 0;

        foreach ($anggota as $indeks => $id) {
            $ukuran = $dasar + ($indeks === $jumlahAnggota - 1 ? $sisa : 0);
            $peta[$id] = array_slice($daftarFungsi, $offset, $ukuran);
            $offset += $ukuran;
        }

        return $peta;
    }

    /**
     * Status kelengkapan kepemilikan fungsi untuk satu kelompok.
     *
     * @param  array<int, string>  $fungsi
     */
    public function statusKelompok(Team $team, array $fungsi, int $missionId): string
    {
        if ($fungsi === []) {
            return 'kosong';
        }

        $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        if ($anggota === []) {
            return 'belum_lengkap';
        }

        $pemilik = FunctionOwnership::query()
            ->where('mission_id', $missionId)
            ->where('team_id', $team->team_id)
            ->pluck('student_id', 'function_name')
            ->all();

        foreach ($fungsi as $namaFungsi) {
            if (! isset($pemilik[$namaFungsi])) {
                return 'belum_lengkap';
            }

            if (! in_array((int) $pemilik[$namaFungsi], $anggota, true)) {
                return 'belum_lengkap';
            }
        }

        return 'lengkap';
    }
}
