<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $level_id
 * @property int $level_number
 * @property string $level_name
 * @property bool $is_summative
 */
#[Fillable(['level_number', 'level_name', 'is_summative'])]
class Level extends Model
{
    protected $table = 'levels';

    protected $primaryKey = 'level_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_summative' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Mission, $this>
     */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'level_id');
    }

    /**
     * @return HasMany<RoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'level_id');
    }

    /**
     * @return HasMany<BugCard, $this>
     */
    public function bugCards(): HasMany
    {
        return $this->hasMany(BugCard::class, 'level_id');
    }

    /**
     * Pengaturan buka/tutup level ini per kelas.
     *
     * @return HasMany<ClassLevelAccess, $this>
     */
    public function classAccess(): HasMany
    {
        return $this->hasMany(ClassLevelAccess::class, 'level_id');
    }
}
