<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $student_id
 * @property int $class_id
 * @property string $full_name
 * @property string|null $student_no
 * @property string|null $access_code
 * @property Carbon|null $access_code_generated_at
 * @property Carbon|null $last_login_at
 */
#[Fillable(['class_id', 'full_name', 'student_no', 'access_code', 'access_code_generated_at', 'last_login_at'])]
class Student extends Model implements Authenticatable
{
    // Siswa masuk lewat guard `siswa` memakai KODE AKSES, bukan email/password.
    // Trait ini menyediakan getAuthIdentifier() dsb. tanpa kolom password.
    use AuthenticatableTrait;

    protected $table = 'students';

    protected $primaryKey = 'student_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_code_generated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'student_id', 'team_id');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'student_id');
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'student_id');
    }

    /**
     * Siswa yang mengirim kode (kolom submissions.submitted_by).
     *
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'submitted_by');
    }

    /**
     * @return HasMany<FunctionOwnership, $this>
     */
    public function functionOwnerships(): HasMany
    {
        return $this->hasMany(FunctionOwnership::class, 'student_id');
    }

    /**
     * @return HasMany<ScoreEntry, $this>
     */
    public function scoreEntries(): HasMany
    {
        return $this->hasMany(ScoreEntry::class, 'student_id');
    }

    /**
     * Nilai manual guru untuk siswa ini (refleksi/observasi).
     *
     * @return HasMany<ManualScore, $this>
     */
    public function manualScores(): HasMany
    {
        return $this->hasMany(ManualScore::class, 'student_id');
    }

    /**
     * Catatan & refleksi siswa ini.
     *
     * @return HasMany<Reflection, $this>
     */
    public function reflections(): HasMany
    {
        return $this->hasMany(Reflection::class, 'student_id');
    }

    /**
     * Draf kode yang dimiliki siswa ini (misi sumatif).
     *
     * @return HasMany<CodeDraft, $this>
     */
    public function ownedDrafts(): HasMany
    {
        return $this->hasMany(CodeDraft::class, 'owner_student_id');
    }

    /**
     * Kelompok siswa ini (satu siswa hanya di satu kelompok per kelas).
     */
    public function team(): ?Team
    {
        return $this->teams()->first();
    }

    /**
     * Scope visibilitas: admin semua, guru hanya siswa di kelasnya.
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
