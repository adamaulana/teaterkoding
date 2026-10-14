<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $team_member_id
 * @property int $team_id
 * @property int $student_id
 */
#[Fillable(['team_id', 'student_id'])]
class TeamMember extends Model
{
    protected $table = 'team_members';

    protected $primaryKey = 'team_member_id';

    public $timestamps = false;

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
