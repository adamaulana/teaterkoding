<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai manual guru (refleksi/rubrik & observasi) per siswa per misi.
 *
 * @property int $id
 * @property int $student_id
 * @property int $mission_id
 * @property int|null $reflection_score
 * @property int|null $observation_score
 * @property string|null $notes
 * @property int|null $graded_by
 */
#[Fillable(['student_id', 'mission_id', 'reflection_score', 'observation_score', 'notes', 'graded_by'])]
class ManualScore extends Model
{
    protected $table = 'manual_scores';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reflection_score' => 'integer',
            'observation_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
