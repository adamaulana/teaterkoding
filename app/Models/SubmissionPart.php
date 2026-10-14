<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Potongan kode milik satu siswa yang masuk ke sebuah submission.
 *
 * Jejak audit nilai individu pada misi sumatif: guru bisa melihat bagian kode
 * mana yang dikerjakan siapa. function_name '' = PROGRAM UTAMA.
 *
 * @property int $id
 * @property int $submission_id
 * @property string $function_name
 * @property int|null $student_id
 * @property string $code_text
 */
#[Fillable(['submission_id', 'function_name', 'student_id', 'code_text'])]
class SubmissionPart extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
