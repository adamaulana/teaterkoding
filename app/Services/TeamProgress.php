<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Hasil analisis progres satu kelompok (nilai, bukan model).
 */
final readonly class TeamProgress
{
    public const STATUS_BELUM_MULAI = 'belum_mulai';

    public const STATUS_MERENCANAKAN = 'merencanakan';

    public const STATUS_MENGERJAKAN = 'mengerjakan';

    public const STATUS_LULUS = 'lulus';

    public const STATUS_SELESAI = 'selesai';

    public function __construct(
        public int $teamId,
        public string $status,
        public ?int $levelAktif,
        public int $levelLulus,
        public int $totalLevel,
        public int $jumlahSubmission,
        public bool $macet,
        public ?string $alasanMacet,
        public ?Carbon $aktivitasTerakhir,
    ) {}

    /**
     * Label status untuk badge di UI.
     */
    public function label(): string
    {
        return match ($this->status) {
            self::STATUS_BELUM_MULAI => 'Belum mulai',
            self::STATUS_MERENCANAKAN => 'Merencanakan',
            self::STATUS_MENGERJAKAN => 'Mengerjakan',
            self::STATUS_LULUS => 'Lulus Level '.$this->levelLulus,
            self::STATUS_SELESAI => 'Selesai',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Varian badge (mengikuti varian x-ui.badge).
     */
    public function variant(): string
    {
        if ($this->macet) {
            return 'bahaya';
        }

        return match ($this->status) {
            self::STATUS_SELESAI => 'sukses',
            self::STATUS_LULUS => 'info',
            self::STATUS_MENGERJAKAN => 'peringatan',
            self::STATUS_MERENCANAKAN => 'netral',
            default => 'netral',
        };
    }

    /**
     * Kemajuan 0..totalLevel untuk progress-bar.
     */
    public function kemajuan(): int
    {
        return max(0, min($this->totalLevel, $this->levelLulus));
    }

    public function sudahMulai(): bool
    {
        return $this->status !== self::STATUS_BELUM_MULAI;
    }
}
