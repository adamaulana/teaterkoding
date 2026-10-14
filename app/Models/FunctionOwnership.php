<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Skema memiliki unique (mission_id, team_id, function_name) — satu fungsi
 * hanya boleh punya satu pemilik per kelompok per misi.
 *
 * @property int $ownership_id
 * @property int $mission_id
 * @property string $function_name
 * @property int $team_id
 * @property int $student_id
 */
#[Fillable(['mission_id', 'function_name', 'team_id', 'student_id'])]
class FunctionOwnership extends Model
{
    protected $table = 'function_ownership';

    protected $primaryKey = 'ownership_id';

    public $timestamps = false;

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
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
}
