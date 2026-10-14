<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $mission_id
 * @property int $level_id
 * @property string $unlock_code
 * @property string $client_brief
 * @property string|null $starter_code
 */
#[Fillable(['level_id', 'unlock_code', 'client_brief', 'starter_code'])]
class Mission extends Model
{
    protected $table = 'missions';

    protected $primaryKey = 'mission_id';

    public $timestamps = false;

    /**
     * @return BelongsTo<Level, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    /**
     * @return HasMany<MissionPlan, $this>
     */
    public function missionPlans(): HasMany
    {
        return $this->hasMany(MissionPlan::class, 'mission_id');
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'mission_id');
    }

    /**
     * @return HasMany<TestCase, $this>
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class, 'mission_id');
    }

    /**
     * @return HasMany<FunctionOwnership, $this>
     */
    public function functionOwnerships(): HasMany
    {
        return $this->hasMany(FunctionOwnership::class, 'mission_id');
    }

    /**
     * @return HasMany<ScoreEntry, $this>
     */
    public function scoreEntries(): HasMany
    {
        return $this->hasMany(ScoreEntry::class, 'mission_id');
    }

    /**
     * Nilai manual guru untuk misi ini.
     *
     * @return HasMany<ManualScore, $this>
     */
    public function manualScores(): HasMany
    {
        return $this->hasMany(ManualScore::class, 'mission_id');
    }

    /**
     * Draf kode tim untuk misi ini.
     *
     * @return HasMany<CodeDraft, $this>
     */
    public function codeDrafts(): HasMany
    {
        return $this->hasMany(CodeDraft::class, 'mission_id');
    }

    /**
     * Catatan & refleksi siswa pada misi ini.
     *
     * @return HasMany<Reflection, $this>
     */
    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class, 'mission_id');
    }
}
