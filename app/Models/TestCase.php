<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $test_case_id
 * @property int $mission_id
 * @property string $label
 * @property string|null $setup_code
 * @property string $check_target
 * @property string $expected_value
 * @property string|float|null $tolerance
 */
#[Fillable(['mission_id', 'label', 'setup_code', 'check_target', 'expected_value', 'tolerance'])]
class TestCase extends Model
{
    protected $table = 'test_cases';

    protected $primaryKey = 'test_case_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tolerance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    /**
     * @return HasMany<SubmissionResult, $this>
     */
    public function submissionResults(): HasMany
    {
        return $this->hasMany(SubmissionResult::class, 'test_case_id');
    }
}
