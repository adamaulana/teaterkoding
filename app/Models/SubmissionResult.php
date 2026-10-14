<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $result_id
 * @property int $submission_id
 * @property int $test_case_id
 * @property bool $is_passed
 * @property string|null $actual_value
 */
#[Fillable(['submission_id', 'test_case_id', 'is_passed', 'actual_value'])]
class SubmissionResult extends Model
{
    protected $table = 'submission_results';

    protected $primaryKey = 'result_id';

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
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<TestCase, $this>
     */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class, 'test_case_id');
    }
}
