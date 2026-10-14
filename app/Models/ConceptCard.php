<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $concept_id
 * @property string $concept_name
 * @property string|null $function_desc
 * @property string|null $syntax_example
 * @property string|null $when_to_use
 * @property string|null $common_pitfalls
 * @property string|null $trigger_question
 */
#[Fillable([
    'concept_name',
    'function_desc',
    'syntax_example',
    'when_to_use',
    'common_pitfalls',
    'trigger_question',
])]
class ConceptCard extends Model
{
    protected $table = 'concept_cards';

    protected $primaryKey = 'concept_id';

    public $timestamps = false;

    /**
     * @return BelongsToMany<MissionPlan, $this>
     */
    public function missionPlans(): BelongsToMany
    {
        return $this->belongsToMany(MissionPlan::class, 'mission_plan_concepts', 'concept_id', 'plan_id');
    }
}
