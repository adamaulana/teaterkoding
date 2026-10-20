<?php

namespace App\Grading;

use App\Contracts\GradingRunner;
use App\Dto\GradingCaseResult;
use App\Dto\GradingResult;
use App\Models\BugAttempt;
use App\Models\BugTestCase;
use App\Models\Submission;
use App\Models\TestCase;

/**
 * Runner PALSU — HANYA untuk local/testing agar alur UI bisa diuji tanpa
 * menjalankan Python. Aturannya deterministik:
 *
 *   - Kode memuat baris "# demo:lulus"  → semua kasus uji LULUS.
 *   - Kode memuat baris "# demo:galat"  → status galat (error).
 *   - Selain itu                         → separuh kasus uji lulus.
 *
 * actual_value yang dikembalikan hanya teks tiruan (bukan hasil Python).
 */
final class FakeGradingRunner implements GradingRunner
{
    public function gradeSubmission(Submission $submission): GradingResult
    {
        $kasus = TestCase::query()
            ->where('mission_id', $submission->mission_id)
            ->orderBy('test_case_id')
            ->get();

        return $this->nilai($kasus, $submission->code_text);
    }

    public function gradeBugAttempt(BugAttempt $attempt): GradingResult
    {
        $kasus = BugTestCase::query()
            ->where('bug_id', $attempt->bug_id)
            ->orderBy('id')
            ->get();

        return $this->nilai($kasus, (string) $attempt->fixed_code);
    }

    /**
     * @param  iterable<int, TestCase|BugTestCase>  $kasus
     */
    private function nilai(iterable $kasus, string $kode): GradingResult
    {
        $teks = strtolower($kode);

        if (str_contains($teks, '# demo:galat')) {
            return GradingResult::galat('Pesan galat tiruan dari FakeGradingRunner.');
        }

        $semuaLulus = str_contains($teks, '# demo:lulus');

        $daftar = [];

        foreach ($kasus as $index => $satu) {
            $lulus = $semuaLulus || ($index % 2 === 0);

            $daftar[] = new GradingCaseResult(
                caseId: (int) ($satu->test_case_id ?? $satu->id),
                isPassed: $lulus,
                actualValue: $lulus ? (string) ($satu->expected_value ?? 'ok') : 'nilai-tiruan',
            );
        }

        return GradingResult::dinilai($daftar, 42);
    }
}
