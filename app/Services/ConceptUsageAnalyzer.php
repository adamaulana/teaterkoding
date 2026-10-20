<?php

namespace App\Services;

use App\Models\ConceptCard;
use App\Models\MissionPlan;
use Illuminate\Support\Collection;

/**
 * Analisis penggunaan konsep pada kode siswa — HEURISTIK TEKS, bukan parser
 * Python (aplikasi ini PHP). Karena itu hasilnya diberi keterangan
 * "perkiraan otomatis" di UI dan TIDAK PERNAH dipakai sebagai komponen nilai.
 *
 * Langkah: buang komentar (# …) dan isi string literal lebih dulu, supaya
 * kata di dalam komentar/string tidak ikut terhitung.
 */
class ConceptUsageAnalyzer
{
    public const KONSEP_VARIABEL = 'Variabel';

    public const KONSEP_OPERATOR = 'Operator';

    public const KONSEP_IF = 'If/Else';

    public const KONSEP_LOOP = 'Loop';

    public const KONSEP_FUNCTION = 'Function';

    public const HASIL_SESUAI = 'sesuai';

    public const HASIL_DIPILIH_TAK_DIPAKAI = 'dipilih_tak_dipakai';

    public const HASIL_DIPAKAI_TAK_DIRENCANAKAN = 'dipakai_tak_direncanakan';

    public const HASIL_BELUM_MERENCANAKAN = 'belum_merencanakan';

    public const HASIL_BELUM_ADA_KODE = 'belum_ada_kode';

    /**
     * Konsep yang terdeteksi pada kode.
     *
     * @return array<int, string>
     */
    public function deteksi(string $kode): array
    {
        $bersih = $this->bersihkan($kode);

        $terdeteksi = [];

        if ($this->adaAssignment($bersih)) {
            $terdeteksi[] = self::KONSEP_VARIABEL;
        }

        if ($this->adaOperator($bersih)) {
            $terdeteksi[] = self::KONSEP_OPERATOR;
        }

        if ($this->adaKata($bersih, ['if', 'elif', 'else'])) {
            $terdeteksi[] = self::KONSEP_IF;
        }

        if ($this->adaKata($bersih, ['for', 'while'])) {
            $terdeteksi[] = self::KONSEP_LOOP;
        }

        if ($this->adaKata($bersih, ['def'])) {
            $terdeteksi[] = self::KONSEP_FUNCTION;
        }

        return $terdeteksi;
    }

    /**
     * Bandingkan kartu konsep yang DIPILIH tim dengan yang TERDETEKSI di kode.
     *
     * @param  Collection<int, ConceptCard>  $dipilih
     * @return array{status: string, dipilih_tak_dipakai: array<int, string>, dipakai_tak_direncanakan: array<int, string>, terdeteksi: array<int, string>}
     */
    public function bandingkan(Collection $dipilih, ?string $kode): array
    {
        $namaDipilih = $dipilih->pluck('concept_name')
            ->map(fn ($n) => (string) $n)
            ->unique()
            ->values()
            ->all();

        if (trim((string) $kode) === '') {
            return [
                'status' => self::HASIL_BELUM_ADA_KODE,
                'dipilih_tak_dipakai' => [],
                'dipakai_tak_direncanakan' => [],
                'terdeteksi' => [],
            ];
        }

        if ($namaDipilih === []) {
            return [
                'status' => self::HASIL_BELUM_MERENCANAKAN,
                'dipilih_tak_dipakai' => [],
                'dipakai_tak_direncanakan' => [],
                'terdeteksi' => $this->deteksi($kode),
            ];
        }

        $terdeteksi = $this->deteksi($kode);

        $dipilihTakDipakai = array_values(array_diff($namaDipilih, $terdeteksi));
        $dipakaiTakDirencanakan = array_values(array_diff($terdeteksi, $namaDipilih));

        $status = match (true) {
            $dipilihTakDipakai !== [] => self::HASIL_DIPILIH_TAK_DIPAKAI,
            $dipakaiTakDirencanakan !== [] => self::HASIL_DIPAKAI_TAK_DIRENCANAKAN,
            default => self::HASIL_SESUAI,
        };

        return [
            'status' => $status,
            'dipilih_tak_dipakai' => $dipilihTakDipakai,
            'dipakai_tak_direncanakan' => $dipakaiTakDirencanakan,
            'terdeteksi' => $terdeteksi,
        ];
    }

    /**
     * Analisis untuk satu rencana misi + kode submission acuan.
     *
     * Rencana HANYA dianggap ada bila sudah DIKUNCI (locked_at terisi). Sebelum
     * dikunci, tim masih bisa mengubah pilihan kartu, sehingga analisis
     * diperlakukan sebagai "belum merencanakan" (bukan "tidak pakai konsep").
     *
     * @return array{status: string, dipilih_tak_dipakai: array<int, string>, dipakai_tak_direncanakan: array<int, string>, terdeteksi: array<int, string>}
     */
    public function analisisRencana(?MissionPlan $rencana, ?string $kode): array
    {
        // Memakai properti relasi (bukan ->concepts()->get()) supaya hasil eager
        // loading di halaman Pantau Live dipakai apa adanya, tanpa query ulang.
        $dipilih = $rencana !== null && $rencana->terkunci()
            ? $rencana->concepts
            : collect();

        return $this->bandingkan($dipilih, $kode);
    }

    /**
     * Label ringkas untuk badge/tooltip.
     *
     * @param  array{status: string}  $hasil
     */
    public function label(array $hasil): string
    {
        return match ($hasil['status']) {
            self::HASIL_SESUAI => 'Sesuai',
            self::HASIL_DIPILIH_TAK_DIPAKAI => 'Dipilih tapi tidak dipakai',
            self::HASIL_DIPAKAI_TAK_DIRENCANAKAN => 'Dipakai tapi tidak direncanakan',
            self::HASIL_BELUM_MERENCANAKAN => 'Belum merencanakan',
            self::HASIL_BELUM_ADA_KODE => 'Belum ada kode',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Varian badge untuk tiap status.
     *
     * @param  array{status: string}  $hasil
     */
    public function variant(array $hasil): string
    {
        return match ($hasil['status']) {
            self::HASIL_SESUAI => 'sukses',
            self::HASIL_DIPILIH_TAK_DIPAKAI => 'peringatan',
            self::HASIL_DIPAKAI_TAK_DIRENCANAKAN => 'info',
            default => 'netral',
        };
    }

    /**
     * Buang komentar dan isi string literal.
     */
    private function bersihkan(string $kode): string
    {
        $hasil = '';
        $panjang = strlen($kode);
        $kutip = null;

        for ($i = 0; $i < $panjang; $i++) {
            $huruf = $kode[$i];

            // Di dalam string literal: buang isinya sampai kutip penutup.
            if ($kutip !== null) {
                if ($huruf === '\\') {
                    $i++;

                    continue;
                }

                if ($huruf === $kutip) {
                    $kutip = null;
                }

                continue;
            }

            // Komentar: buang sampai akhir baris (baris baru dipertahankan).
            if ($huruf === '#') {
                while ($i < $panjang && $kode[$i] !== "\n") {
                    $i++;
                }

                $hasil .= "\n";

                continue;
            }

            if ($huruf === '"' || $huruf === "'") {
                $kutip = $huruf;
                $hasil .= '""';

                continue;
            }

            $hasil .= $huruf;
        }

        return $hasil;
    }

    /**
     * Variabel: ada assignment `=` yang BUKAN `==`, `<=`, `>=`, `!=`.
     */
    private function adaAssignment(string $kode): bool
    {
        return preg_match('/(?<![=!<>])=(?!=)/', $kode) === 1;
    }

    /**
     * Operator aritmetika atau perbandingan.
     */
    private function adaOperator(string $kode): bool
    {
        return preg_match('/[+\-*\/%]/', $kode) === 1
            || preg_match('/(==|!=|<=|>=|<|>)/', $kode) === 1;
    }

    /**
     * Apakah salah satu kata muncul sebagai kata utuh.
     *
     * @param  array<int, string>  $kata
     */
    private function adaKata(string $kode, array $kata): bool
    {
        foreach ($kata as $satu) {
            if (preg_match('/\b'.preg_quote($satu, '/').'\b/', $kode) === 1) {
                return true;
            }
        }

        return false;
    }
}
