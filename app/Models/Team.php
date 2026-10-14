<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $team_id
 * @property int $class_id
 * @property string $team_name
 */
#[Fillable(['class_id', 'team_name'])]
class Team extends Model
{
    protected $table = 'teams';

    protected $primaryKey = 'team_id';

    public $timestamps = false;

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'team_members', 'team_id', 'student_id');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'team_id');
    }

    /**
     * @return HasMany<MissionPlan, $this>
     */
    public function missionPlans(): HasMany
    {
        return $this->hasMany(MissionPlan::class, 'team_id');
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'team_id');
    }

    /**
     * @return HasMany<FunctionOwnership, $this>
     */
    public function functionOwnerships(): HasMany
    {
        return $this->hasMany(FunctionOwnership::class, 'team_id');
    }

    /**
     * @return HasMany<BugAttempt, $this>
     */
    public function bugAttempts(): HasMany
    {
        return $this->hasMany(BugAttempt::class, 'team_id');
    }

    /**
     * @return HasMany<ScoreEntry, $this>
     */
    public function scoreEntries(): HasMany
    {
        return $this->hasMany(ScoreEntry::class, 'team_id');
    }

    /**
     * Draf kode tim ini (per misi, per fungsi).
     *
     * @return HasMany<CodeDraft, $this>
     */
    public function codeDrafts(): HasMany
    {
        return $this->hasMany(CodeDraft::class, 'team_id');
    }

    /**
     * Catatan & refleksi anggota tim ini.
     *
     * @return HasMany<Reflection, $this>
     */
    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class, 'team_id');
    }

    /**
     * Scope visibilitas: admin semua, guru hanya kelompok di kelasnya.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'schoolClass',
            fn (Builder $classQuery) => $classQuery->where('user_id', $user->id),
        );
    }
}
