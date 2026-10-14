<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil satu kasus uji pada percobaan Kartu Bug (meniru SubmissionResult).
 *
 * @property int $id
 * @property int $attempt_id
 * @property int $bug_test_case_id
 * @property bool $is_passed
 * @property string|null $actual_value
 */
#[Fillable(['attempt_id', 'bug_test_case_id', 'is_passed', 'actual_value'])]
class BugAttemptResult extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_passed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BugAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(BugAttempt::class, 'attempt_id');
    }

    /**
     * @return BelongsTo<BugTestCase, $this>
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(BugTestCase::class, 'bug_test_case_id');
    }
}
