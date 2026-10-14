<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $bug_id
 * @property int $level_id
 * @property string $bug_title
 * @property string|null $symptom_text
 * @property string $buggy_code
 * @property string|null $hint_text
 * @property string $fix_explanation
 * @property int $points
 */
#[Fillable(['level_id', 'bug_title', 'symptom_text', 'buggy_code', 'hint_text', 'fix_explanation', 'points'])]
class BugCard extends Model
{
    protected $table = 'bug_cards';

    protected $primaryKey = 'bug_id';

    public $timestamps = false;

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    /**
     * @return HasMany<BugAttempt, $this>
     */
    public function bugAttempts(): HasMany
    {
        return $this->hasMany(BugAttempt::class, 'bug_id');
    }

    /**
     * @return HasMany<ScoreEntry, $this>
     */
    public function scoreEntries(): HasMany
    {
        return $this->hasMany(ScoreEntry::class, 'bug_id');
    }

    /**
     * Kasus uji kartu bug ini.
     *
     * @return HasMany<BugTestCase, $this>
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(BugTestCase::class, 'bug_id');
    }
}
