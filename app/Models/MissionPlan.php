<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $plan_id
 * @property int $team_id
 * @property int $mission_id
 * @property Carbon $planned_at
 * @property Carbon|null $locked_at
 * @property string|null $steps_note
 * @property int|null $unlocked_by
 */
#[Fillable(['team_id', 'mission_id', 'planned_at', 'locked_at', 'steps_note', 'unlocked_by'])]
class MissionPlan extends Model
{
    protected $table = 'mission_plans';

    protected $primaryKey = 'plan_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Apakah rencana sudah dikunci Analyst.
     *
     * ConceptUsageAnalyzer hanya menganggap tim "sudah merencanakan" bila
     * rencananya terkunci.
     */
    public function terkunci(): bool
    {
        return $this->locked_at !== null;
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
     * @return BelongsToMany<ConceptCard, $this>
     */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(ConceptCard::class, 'mission_plan_concepts', 'plan_id', 'concept_id');
    }

    /**
     * @return HasMany<MissionPlanConcept, $this>
     */
    public function planConcepts(): HasMany
    {
        return $this->hasMany(MissionPlanConcept::class, 'plan_id');
    }
}
