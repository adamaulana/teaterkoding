<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pivot mission_plan_concepts.
 *
 * Tabel ini memakai primary key komposit (plan_id, concept_id) yang tidak
 * didukung Eloquent, sehingga $primaryKey menunjuk ke plan_id dan
 * auto-increment dimatikan. Untuk pencarian per kombinasi unik gunakan
 * query builder (MissionPlanConcept::where([...])) atau relasi
 * MissionPlan::concepts() / ConceptCard::missionPlans().
 *
 * @property int $plan_id
 * @property int $concept_id
 */
#[Fillable(['plan_id', 'concept_id'])]
class MissionPlanConcept extends Model
{
    protected $table = 'mission_plan_concepts';

    protected $primaryKey = 'plan_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return BelongsTo<MissionPlan, $this>
     */
    public function missionPlan(): BelongsTo
    {
        return $this->belongsTo(MissionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<ConceptCard, $this>
     */
    public function conceptCard(): BelongsTo
    {
        return $this->belongsTo(ConceptCard::class, 'concept_id');
    }
}
