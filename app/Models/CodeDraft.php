<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Draf kode tim untuk satu misi (autosave).
 *
 * function_name kosong ('') = draf PROGRAM UTAMA atau kode tunggal L1-L3.
 *
 * @property int $id
 * @property int $team_id
 * @property int $mission_id
 * @property string $function_name
 * @property int|null $owner_student_id
 * @property string $code_text
 * @property int $version
 * @property int|null $updated_by
 * @property int|null $reviewed_version
 * @property int|null $reviewed_by
 */
#[Fillable([
    'team_id',
    'mission_id',
    'function_name',
    'owner_student_id',
    'code_text',
    'version',
    'updated_by',
    'reviewed_version',
    'reviewed_by',
])]
class CodeDraft extends Model
{
    /** Penanda draf program utama / kode tunggal (bukan NULL, lihat migrasi). */
    public const PROGRAM_UTAMA = '';

    protected $table = 'code_drafts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'reviewed_version' => 'integer',
        ];
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
     * @return BelongsTo<Student, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'owner_student_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'updated_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'reviewed_by');
    }

    /**
     * Apakah draf ini milik program utama (bukan fungsi tertentu).
     */
    public function programUtama(): bool
    {
        return $this->function_name === self::PROGRAM_UTAMA;
    }

    /**
     * Apakah versi terakhir sudah ditinjau Reviewer.
     */
    public function sudahDitinjau(): bool
    {
        return $this->reviewed_version !== null && $this->reviewed_version >= $this->version;
    }
}
