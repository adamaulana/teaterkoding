<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ledger skor: satu baris per kejadian (boleh negatif untuk hint_penalty).
 *
 * @property int $score_id
 * @property int $team_id
 * @property int|null $student_id
 * @property int|null $mission_id
 * @property int|null $bug_id
 * @property string $source_type mission_result|plan_bonus|bug_bonus|hint_penalty|first_try_bonus
 * @property string|null $award_key Kunci idempotensi pemberian poin
 * @property int $points
 * @property Carbon $created_at
 */
#[Fillable(['team_id', 'student_id', 'mission_id', 'bug_id', 'source_type', 'award_key', 'points', 'created_at'])]
class ScoreEntry extends Model
{
    protected $table = 'score_entries';

    protected $primaryKey = 'score_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
     * @return BelongsTo<BugCard, $this>
     */
    public function bugCard(): BelongsTo
    {
        return $this->belongsTo(BugCard::class, 'bug_id');
    }
}
