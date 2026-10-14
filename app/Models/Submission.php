<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $submission_id
 * @property int $team_id
 * @property int $mission_id
 * @property int $submitted_by
 * @property string $code_text
 * @property Carbon $submitted_at
 * @property bool $all_passed
 * @property string $status menunggu|dinilai|galat|waktu_habis
 * @property int $attempt_no
 * @property Carbon|null $graded_at
 * @property string|null $error_message
 * @property int|null $runtime_ms
 */
#[Fillable([
    'team_id',
    'mission_id',
    'submitted_by',
    'code_text',
    'submitted_at',
    'all_passed',
    'status',
    'attempt_no',
    'graded_at',
    'error_message',
    'runtime_ms',
])]
class Submission extends Model
{
    /** Status penilaian submission. */
    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DINILAI = 'dinilai';

    public const STATUS_GALAT = 'galat';

    public const STATUS_WAKTU_HABIS = 'waktu_habis';

    protected $table = 'submissions';

    protected $primaryKey = 'submission_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'all_passed' => 'boolean',
            'attempt_no' => 'integer',
            'graded_at' => 'datetime',
            'runtime_ms' => 'integer',
        ];
    }

    /**
     * Label status untuk badge di UI (siswa & guru).
     */
    public function labelStatus(): string
    {
        return match ($this->status) {
            self::STATUS_MENUNGGU => 'Menunggu penilaian',
            self::STATUS_DINILAI => 'Sudah dinilai',
            self::STATUS_GALAT => 'Galat',
            self::STATUS_WAKTU_HABIS => 'Waktu habis',
            default => 'Tidak diketahui',
        };
    }

    /**
     * Varian badge untuk status penilaian.
     */
    public function variantStatus(): string
    {
        return match ($this->status) {
            self::STATUS_MENUNGGU => 'info',
            self::STATUS_DINILAI => 'sukses',
            self::STATUS_GALAT => 'bahaya',
            self::STATUS_WAKTU_HABIS => 'peringatan',
            default => 'netral',
        };
    }

    /**
     * Apakah submission sudah selesai dinilai (dan karenanya boleh dihitung).
     *
     * Submission 'menunggu' TIDAK dihitung sebagai lulus maupun gagal.
     */
    public function sudahDinilai(): bool
    {
        return $this->status === self::STATUS_DINILAI;
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    /**
     * Siswa pengirim kode (kolom submitted_by).
     *
     * @return BelongsTo<Student, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'submitted_by');
    }

    /**
     * @return HasMany<SubmissionResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(SubmissionResult::class, 'submission_id');
    }

    /**
     * Potongan kode per siswa yang menyusun submission ini (misi sumatif).
     *
     * @return HasMany<SubmissionPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(SubmissionPart::class, 'submission_id');
    }
}
