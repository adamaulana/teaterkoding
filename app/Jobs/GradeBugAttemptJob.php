<?php

namespace App\Jobs;

use App\Contracts\GradingRunner;
use App\Models\BugAttempt;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Nilai satu percobaan Kartu Bug lewat GradingRunner, simpan hasil per kasus
 * uji, lalu beri poin (bug_bonus saat lulus).
 *
 * IDEMPOTEN: bila percobaan sudah berstatus 'dinilai', job langsung selesai.
 */
class GradeBugAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $attemptId) {}

    public function handle(GradingRunner $runner, ScoringService $scoring): void
    {
        // Runner pyodide: penilaian terjadi di BROWSER. Server tidak pernah
        // menjalankan kode siswa, jadi job ini tidak boleh menilai apa pun.
        if (config('teater.runner') === 'pyodide') {
            return;
        }

        $attempt = BugAttempt::query()->find($this->attemptId);

        if ($attempt === null || $attempt->grading_status === BugAttempt::STATUS_DINILAI) {
            return;
        }

        $hasil = $runner->gradeBugAttempt($attempt);

        DB::transaction(function () use ($attempt, $hasil): void {
            if ($hasil->status === BugAttempt::STATUS_MENUNGGU) {
                return;
            }

            foreach ($hasil->cases as $kasus) {
                $attempt->results()->updateOrCreate(
                    ['attempt_id' => $attempt->attempt_id, 'bug_test_case_id' => $kasus->caseId],
                    ['is_passed' => $kasus->isPassed, 'actual_value' => $kasus->actualValue],
                );
            }

            $lulus = $hasil->status === BugAttempt::STATUS_DINILAI
                && $hasil->cases !== []
                && count(array_filter($hasil->cases, fn ($c) => $c->isPassed)) === count($hasil->cases);

            $attempt->update([
                'grading_status' => $hasil->status,
                'is_solved' => $lulus,
                'solved_at' => $lulus ? now() : null,
                'error_message' => $hasil->errorMessage !== null
                    ? mb_substr($hasil->errorMessage, 0, 1500)
                    : null,
            ]);
        });

        $scoring->bugBonus($attempt->fresh());
    }
}
