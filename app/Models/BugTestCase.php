<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kasus uji sebuah Kartu Bug.
 *
 * Struktur & aturan validasinya SAMA dengan TestCase (misi), sehingga
 * App\Actions\ValidasiKasusUji dipakai ulang tanpa duplikasi.
 *
 * @property int $id
 * @property int $bug_id
 * @property string $label
 * @property string|null $setup_code
 * @property string $check_target
 * @property string $expected_value
 * @property string|float|null $tolerance
 */
#[Fillable(['bug_id', 'label', 'setup_code', 'check_target', 'expected_value', 'tolerance'])]
class BugTestCase extends Model
{
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
     * @return BelongsTo<BugCard, $this>
     */
    public function bugCard(): BelongsTo
    {
        return $this->belongsTo(BugCard::class, 'bug_id');
    }

    /**
     * @return HasMany<BugAttemptResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(BugAttemptResult::class, 'bug_test_case_id');
    }
}
