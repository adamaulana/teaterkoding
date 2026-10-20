<?php

namespace App\Contracts;

use App\Dto\GradingResult;
use App\Models\BugAttempt;
use App\Models\Submission;

/**
 * Kontrak penilai otomatis (Fase 5).
 *
 * TIDAK ada eksekusi Python di fase ini — implementasi nyata (sandbox Piston)
 * dipasang pada tahap berikutnya lewat kontrak yang sama. Untuk saat ini:
 *   - NullGradingRunner  : produksi, membiarkan status "menunggu".
 *   - FakeGradingRunner  : local/testing, menilai alur UI secara deterministik.
 */
interface GradingRunner
{
    /**
     * Nilai satu submission misi dan kembalikan hasil per kasus uji.
     */
    public function gradeSubmission(Submission $submission): GradingResult;

    /**
     * Nilai satu percobaan kartu bug dan kembalikan hasil per kasus uji bug.
     */
    public function gradeBugAttempt(BugAttempt $attempt): GradingResult;
}
