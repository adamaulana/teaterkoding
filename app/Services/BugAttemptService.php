<?php

namespace App\Services;

use App\Jobs\GradeBugAttemptJob;
use App\Models\BugAttempt;
use App\Models\BugTestCase;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Pipeline pengerjaan Kartu Bug (petunjuk + submit perbaikan).
 *
 * Aturan jeda & ukuran kode SAMA dengan submission misi
 * (submit_interval_seconds, max_code_bytes, tolak byte NUL).
 */
final class BugAttemptService
{
    public const ERR_UKURAN = 'ukuran';

    public const ERR_NUL = 'nul';

    public const ERR_JEDA = 'jeda';

    public const ERR_TANPA_KASUS = 'tanpa_kasus';

    public const ERR_SUDAH_LULUS = 'sudah_lulus';

    /**
     * Buka petunjuk (maksimal 1 kali). Mengembalikan teks petunjuk.
     *
     * @return array{status: string, hint?: string|null}
     */
    public function mintaPetunjuk(BugAttempt $attempt, Student $siswa): array
    {
        if ($attempt->is_solved) {
            return ['status' => self::ERR_SUDAH_LULUS];
        }

        if ((int) $attempt->hints_used >= 1) {
            return ['status' => 'sudah', 'hint' => $attempt->bugCard?->hint_text];
        }

        $attempt->update(['hints_used' => 1]);

        app(ScoringService::class)->hintPenalty($attempt);

        return ['status' => 'ok', 'hint' => $attempt->bugCard?->hint_text];
    }

    /**
     * Submit perbaikan kode.
     *
     * @return array{status: string, attempt?: BugAttempt, tunggu?: int}
     */
    public function submit(BugAttempt $attempt, Student $siswa, string $kode): array
    {
        if ($attempt->is_solved) {
            return ['status' => self::ERR_SUDAH_LULUS];
        }

        $maksByte = (int) config('teater.max_code_bytes', 20000);

        if (strlen($kode) > $maksByte) {
            return ['status' => self::ERR_UKURAN];
        }

        if (str_contains($kode, "\0")) {
            return ['status' => self::ERR_NUL];
        }

        $tunggu = $this->sisaJeda($attempt);

        if ($tunggu > 0) {
            return ['status' => self::ERR_JEDA, 'tunggu' => $tunggu];
        }

        if (BugTestCase::query()->where('bug_id', $attempt->bug_id)->doesntExist()) {
            return ['status' => self::ERR_TANPA_KASUS];
        }

        $attempt->update([
            'fixed_code' => $kode,
            'grading_status' => BugAttempt::STATUS_MENUNGGU,
            'error_message' => null,
            'attempted_at' => now(),
        ]);

        // Penilaian di browser: siapkan nonce sekali pakai dan JANGAN dispatch
        // job. Server tidak pernah menjalankan kode siswa.
        if ($this->memakaiPyodide()) {
            $attempt->update([
                'grading_nonce' => bin2hex(random_bytes(32)),
                'nonce_expires_at' => now()->addMinutes(
                    (int) config('teater.pyodide.nonce_ttl_minutes', 15),
                ),
            ]);
        } else {
            GradeBugAttemptJob::dispatch($attempt->attempt_id);
        }

        return ['status' => 'ok', 'attempt' => $attempt->fresh()];
    }

    /**
     * Apakah penilaian dilakukan di browser (Pyodide)?
     */
    public function memakaiPyodide(): bool
    {
        return config('teater.runner') === 'pyodide';
    }

    /**
     * Sisa jeda (detik) sebelum kelompok boleh submit lagi pada kartu ini.
     */
    public function sisaJeda(BugAttempt $attempt): int
    {
        if ($attempt->attempted_at === null) {
            return 0;
        }

        $interval = (int) config('teater.submit_interval_seconds', 15);
        $lewat = Carbon::parse((string) $attempt->attempted_at)->diffInSeconds(now());

        return max(0, $interval - $lewat);
    }
}
