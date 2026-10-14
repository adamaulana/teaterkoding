<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $class_id
 * @property int|null $user_id
 * @property string $class_name
 * @property string $school_year
 */
#[Fillable(['class_name', 'school_year', 'user_id'])]
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    public $timestamps = false;

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'class_id');
    }

    /**
     * Scope visibilitas: admin melihat semua kelas, guru hanya miliknya.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('user_id', $user->id);
    }

    /**
     * Bobot penilaian sumatif kelas ini (null = pakai default).
     *
     * @return HasOne<GradingSetting, $this>
     */
    public function gradingSetting(): HasOne
    {
        return $this->hasOne(GradingSetting::class, 'class_id');
    }

    /**
     * Bobot efektif: baris tersimpan bila ada, selain itu default config.
     *
     * @return array<string, int>
     */
    public function bobotEfektif(): array
    {
        $setting = $this->gradingSetting;

        return $setting !== null ? $setting->bobot() : GradingSetting::bobotDefault();
    }

    /**
     * Pengaturan buka/tutup level untuk kelas ini.
     *
     * @return HasMany<ClassLevelAccess, $this>
     */
    public function levelAccess(): HasMany
    {
        return $this->hasMany(ClassLevelAccess::class, 'class_id');
    }
}
