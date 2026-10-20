<?php

namespace App\Grading;

use App\Contracts\GradingRunner;
use App\Dto\GradingResult;
use App\Models\BugAttempt;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;

/**
 * Runner cadangan: TIDAK menilai.
 *
 * Dipakai saat config('teater.runner') bukan 'fake' dan bukan 'pyodide'
 * (termasuk 'null' yang merupakan default config).
 *
 * PENTING: mengembalikan status 'menunggu' TANPA batas membuat submission
 * menggantung selamanya tanpa pesan apa pun — inilah yang dulu membuat siswa
 * melihat "Sedang dinilai..." terus-menerus. Karena itu:
 *   - masalahnya dicatat sebagai WARNING (bukan info) agar terlihat di log,
 *   - galatnya dikembalikan sebagai status 'galat' dengan tanda khusus, supaya
 *     UI menampilkan penyebab, bukan spinner abadi.
 */
final class NullGradingRunner implements GradingRunner
{
    public const PESAN_BELUM_DIKONFIGURASI = 'penilai_belum_dikonfigurasi';

    public function gradeSubmission(Submission $submission): GradingResult
    {
        Log::warning('NullGradingRunner: submission tidak bisa dinilai karena runner tidak dikonfigurasi.', [
            'submission_id' => $submission->submission_id,
            'runner' => config('teater.runner'),
            'saran' => 'Set TEATER_RUNNER=pyodide di .env (penilaian di browser).',
        ]);

        return GradingResult::galat(self::PESAN_BELUM_DIKONFIGURASI);
    }

    public function gradeBugAttempt(BugAttempt $attempt): GradingResult
    {
        Log::warning('NullGradingRunner: percobaan bug tidak bisa dinilai karena runner tidak dikonfigurasi.', [
            'attempt_id' => $attempt->attempt_id,
            'runner' => config('teater.runner'),
            'saran' => 'Set TEATER_RUNNER=pyodide di .env (penilaian di browser).',
        ]);

        return GradingResult::galat(self::PESAN_BELUM_DIKONFIGURASI);
    }
}
