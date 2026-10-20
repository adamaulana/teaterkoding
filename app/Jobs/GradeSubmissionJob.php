<?php

namespace App\Jobs;

use App\Contracts\GradingRunner;
use App\Models\Submission;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Nilai satu submission lewat GradingRunner, simpan hasil, lalu beri poin.
 *
 * IDEMPOTEN: bila submission sudah berstatus 'dinilai', job langsung selesai
 * tanpa membuat hasil ganda. Memakai queue database — jalankan dengan
 * `php artisan queue:work`.
 */
class GradeSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $submissionId) {}

    public function handle(GradingRunner $runner, ScoringService $scoring): void
    {
        // Runner pyodide: penilaian terjadi di BROWSER. Server tidak pernah
        // menjalankan kode siswa, jadi job ini tidak boleh menilai apa pun.
        if (config('teater.runner') === 'pyodide') {
            return;
        }

        $submission = Submission::query()->find($this->submissionId);

        if ($submission === null || $submission->sudahDinilai()) {
            return;
        }

        $hasil = $runner->gradeSubmission($submission);

        DB::transaction(function () use ($submission, $hasil): void {
            if ($hasil->status === Submission::STATUS_MENUNGGU) {
                return; // NullGradingRunner: biarkan "menunggu".
            }

            foreach ($hasil->cases as $kasus) {
                $submission->results()->updateOrCreate(
                    ['submission_id' => $submission->submission_id, 'test_case_id' => $kasus->caseId],
                    ['is_passed' => $kasus->isPassed, 'actual_value' => $kasus->actualValue],
                );
            }

            $submission->update([
                'status' => $hasil->status,
                'all_passed' => $hasil->status === Submission::STATUS_DINILAI
                    && $hasil->cases !== []
                    && count(array_filter($hasil->cases, fn ($c) => $c->isPassed)) === count($hasil->cases),
                'graded_at' => now(),
                'runtime_ms' => $hasil->runtimeMs,
                'error_message' => $hasil->errorMessage !== null
                    ? mb_substr($hasil->errorMessage, 0, 1500)
                    : null,
            ]);
        });

        // Beri poin setelah status tersimpan sebagai 'dinilai'.
        $scoring->missionResult($submission->fresh());
        $scoring->missionResultIndividu($submission->fresh());
        $scoring->firstTryBonus($submission->fresh());
        $scoring->planBonus($submission->fresh());
    }
}
