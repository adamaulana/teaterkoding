<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_id
 * @property int $bug_id
 * @property int $team_id
 * @property int|null $released_by
 * @property Carbon|null $released_at
 * @property string|null $fixed_code
 * @property bool $is_solved
 * @property Carbon|null $solved_at
 * @property string $grading_status belum|menunggu|dinilai|galat|waktu_habis|menunggu_verifikasi
 * @property string|null $grading_source klien|terverifikasi|server
 * @property string|null $grading_nonce
 * @property Carbon|null $nonce_expires_at
 * @property array<string, mixed>|null $client_report
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property bool $has_mismatch
 * @property array<string, mixed>|null $mismatch_detail
 * @property string|null $error_message
 * @property int $hints_used
 * @property Carbon $attempted_at
 */
#[Fillable([
    'bug_id',
    'team_id',
    'released_by',
    'released_at',
    'fixed_code',
    'is_solved',
    'solved_at',
    'grading_status',
    'grading_source',
    'grading_nonce',
    'nonce_expires_at',
    'client_report',
    'verified_at',
    'verified_by',
    'has_mismatch',
    'mismatch_detail',
    'error_message',
    'hints_used',
    'attempted_at',
])]
class BugAttempt extends Model
{
    public const STATUS_BELUM = 'belum';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DINILAI = 'dinilai';

    public const STATUS_GALAT = 'galat';

    public const STATUS_WAKTU_HABIS = 'waktu_habis';

    /** Hasil perangkat siswa sudah masuk, menunggu verifikasi guru (sumatif). */
    public const STATUS_MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';

    public const SUMBER_KLIEN = 'klien';

    public const SUMBER_TERVERIFIKASI = 'terverifikasi';

    public const SUMBER_SERVER = 'server';

    protected $table = 'bug_attempts';

    protected $primaryKey = 'attempt_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_solved' => 'boolean',
            'attempted_at' => 'datetime',
            'released_at' => 'datetime',
            'solved_at' => 'datetime',
            'hints_used' => 'integer',
            'nonce_expires_at' => 'datetime',
            'client_report' => 'array',
            'verified_at' => 'datetime',
            'has_mismatch' => 'boolean',
            'mismatch_detail' => 'array',
        ];
    }

    /**
     * Apakah kartu ini sudah dirilis guru ke kelompok.
     */
    public function sudahDirilis(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * Label status penilaian untuk badge.
     */
    public function labelStatus(): string
    {
        return match ($this->grading_status) {
            self::STATUS_MENUNGGU => 'Menunggu penilaian',
            self::STATUS_DINILAI => 'Sudah dinilai',
            self::STATUS_GALAT => 'Galat',
            self::STATUS_WAKTU_HABIS => 'Waktu habis',
            self::STATUS_MENUNGGU_VERIFIKASI => 'Menunggu verifikasi',
            default => 'Belum dikerjakan',
        };
    }

    /**
     * Varian badge untuk status penilaian.
     */
    public function variantStatus(): string
    {
        return match ($this->grading_status) {
            self::STATUS_MENUNGGU => 'info',
            self::STATUS_DINILAI => 'sukses',
            self::STATUS_GALAT => 'bahaya',
            self::STATUS_WAKTU_HABIS => 'peringatan',
            // Netral: bukan gagal, bukan lulus.
            self::STATUS_MENUNGGU_VERIFIKASI => 'netral',
            default => 'netral',
        };
    }

    /**
     * @return BelongsTo<BugCard, $this>
     */
    public function bugCard(): BelongsTo
    {
        return $this->belongsTo(BugCard::class, 'bug_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Guru yang merilis kartu ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * Guru/admin yang memverifikasi hasil percobaan ini (level sumatif).
     *
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Hasil kasus uji bug ini.
     *
     * @return HasMany<BugAttemptResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(BugAttemptResult::class, 'attempt_id');
    }
}
