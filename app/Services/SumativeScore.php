<?php

namespace App\Services;

/**
 * Hasil perhitungan nilai sumatif satu siswa (nilai, bukan model).
 */
final readonly class SumativeScore
{
    /**
     * @param  array<int, string>  $fungsiDimiliki
     */
    public function __construct(
        public int $studentId,
        public ?float $komponenIndividu,
        public ?float $komponenKelompok,
        public ?float $komponenBugPlan,
        public ?float $komponenRefleksi,
        public ?float $komponenObservasi,
        public float $nilaiAkhir,
        public bool $sementara,
        public bool $punyaFungsi,
        public string $rincianIndividu,
        public array $fungsiDimiliki,
    ) {}

    /**
     * Peta komponen untuk tabel & ekspor CSV (urut sesuai spesifikasi).
     *
     * @return array<string, float|null>
     */
    public function komponen(): array
    {
        return [
            'individu' => $this->komponenIndividu,
            'kelompok' => $this->komponenKelompok,
            'bug_plan' => $this->komponenBugPlan,
            'refleksi' => $this->komponenRefleksi,
            'observasi' => $this->komponenObservasi,
        ];
    }

    /**
     * Nilai komponen untuk ditampilkan (null → tanda pisah).
     */
    public function tampil(string $kunci): string
    {
        $nilai = $this->komponen()[$kunci] ?? null;

        return $nilai === null ? '—' : (string) round($nilai, 1);
    }

    /**
     * Varian warna lembut untuk nilai 0-100 (hijau tinggi, amber sedang, rose rendah).
     *
     * null = belum ada data → netral (abu-abu), BUKAN merah. Angka 0 hanya
     * diwarnai merah bila memang nilai nol yang terhitung (ada data).
     */
    public static function warna(float|int|null $nilai): string
    {
        if ($nilai === null) {
            return 'text-slate-400';
        }

        return match (true) {
            $nilai >= 75 => 'text-primary-700',
            $nilai >= 50 => 'text-amber-600',
            default => 'text-rose-600',
        };
    }

    /**
     * Warna latar lembut untuk sel nilai.
     */
    public static function warnaBg(float|int|null $nilai): string
    {
        if ($nilai === null) {
            return 'bg-slate-50';
        }

        return match (true) {
            $nilai >= 75 => 'bg-primary-50',
            $nilai >= 50 => 'bg-amber-50',
            default => 'bg-rose-50',
        };
    }

    /**
     * Apakah ada komponen otomatis yang sudah bisa dinilai (tim sudah submit).
     */
    public function adaDataOtomatis(): bool
    {
        return $this->komponenIndividu !== null || $this->komponenKelompok !== null;
    }

    /**
     * Apakah ADA SATU SAJA komponen yang terisi.
     *
     * Bila false, nilai akhir belum bermakna → tampilkan "—" netral
     * (bukan angka 0 merah), karena 0 berarti "sudah dinilai tapi nol".
     */
    public function adaData(): bool
    {
        foreach ($this->komponen() as $nilai) {
            if ($nilai !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Label status baris rekap nilai.
     *
     * Dipakai badge di tabel DAN kolom Status pada ekspor CSV agar keduanya
     * selalu konsisten:
     *   'Belum ada data' → belum ada komponen terisi sama sekali
     *   'Sementara'      → sudah ada data, tetapi komponen manual masih kosong
     *   'Final'          → semua komponen sudah terisi
     */
    public function statusLabel(): string
    {
        if (! $this->adaData()) {
            return 'Belum ada data';
        }

        return $this->sementara ? 'Sementara' : 'Final';
    }
}
