<?php

namespace App\Services;

use App\Jobs\GradeSubmissionJob;
use App\Models\Mission;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pipeline submit (komponen terpisah dari UI, bisa diuji).
 *
 * Validasi: canWork, hak peran, ukuran <= max_code_bytes (byte, tolak NUL),
 * jeda minimal submit_interval_seconds per kelompok per misi, dan misi punya
 * kasus uji. Dalam transaksi: buat submissions + submission_parts, lalu
 * dispatch GradeSubmissionJob SETELAH commit.
 */
final class SubmissionService
{
    public const ERR_BUKAN_LEVEL = 'bukan_level';

    public const ERR_HAK_PERAN = 'hak_peran';

    public const ERR_UKURAN = 'ukuran';

    public const ERR_NUL = 'nul';

    public const ERR_JEDA = 'jeda';

    public const ERR_TANPA_KASUS = 'tanpa_kasus';

    /**
     * Jalankan pipeline submit.
     *
     * @param  array<int, array{function_name: string, student_id: int|null, code_text: string}>  $parts
     * @return array{status: string, submission?: Submission, tunggu?: int, pesan?: string}
     */
    public function submit(
        Team $team,
        Mission $misi,
        Student $siswa,
        string $kode,
        array $parts = [],
    ): array {
        if (! app(LevelAccessService::class)->canWork($team, $misi)) {
            return ['status' => self::ERR_BUKAN_LEVEL];
        }

        // Hak peran: hanya Coder boleh submit di L1-L3 (bila penegakan aktif).
        if (! app(RoleService::class)->boleh($team, $siswa, (int) $misi->level_id, [RoleService::PERAN_CODER])) {
            return ['status' => self::ERR_HAK_PERAN];
        }

        $maksByte = (int) config('teater.max_code_bytes', 20000);

        if (strlen($kode) > $maksByte) {
            return ['status' => self::ERR_UKURAN];
        }

        if (str_contains($kode, "\0")) {
            return ['status' => self::ERR_NUL];
        }

        $tunggu = $this->sisaJeda($team, $misi);

        if ($tunggu > 0) {
            return ['status' => self::ERR_JEDA, 'tunggu' => $tunggu];
        }

        if (TestCase::query()->where('mission_id', $misi->mission_id)->doesntExist()) {
            return ['status' => self::ERR_TANPA_KASUS];
        }

        try {
            $submission = DB::transaction(function () use ($team, $misi, $siswa, $kode, $parts) {
                $attemptNo = (int) Submission::query()
                    ->where('team_id', $team->team_id)
                    ->where('mission_id', $misi->mission_id)
                    ->count() + 1;

                $data = [
                    'team_id' => $team->team_id,
                    'mission_id' => $misi->mission_id,
                    'submitted_by' => $siswa->student_id,
                    'code_text' => $kode,
                    'submitted_at' => now(),
                    'all_passed' => false,
                    'status' => Submission::STATUS_MENUNGGU,
                    'attempt_no' => $attemptNo,
                ];

                // Runner pyodide: siapkan nonce sekali pakai untuk laporan dari
                // browser siswa. Nonce TIDAK pernah dikirim ke worker Pyodide.
                if ($this->memakaiPyodide()) {
                    $data['grading_nonce'] = $this->buatNonce();
                    $data['nonce_expires_at'] = now()->addMinutes(
                        (int) config('teater.pyodide.nonce_ttl_minutes', 15),
                    );
                }

                $submission = Submission::create($data);

                foreach ($parts as $part) {
                    $submission->parts()->create([
                        'function_name' => $part['function_name'],
                        'student_id' => $part['student_id'],
                        'code_text' => $part['code_text'],
                    ]);
                }

                return $submission;
            });
        } catch (Throwable $e) {
            return ['status' => 'galat', 'pesan' => $e->getMessage()];
        }

        // Penilaian di browser: JANGAN dispatch job (server tidak menilai).
        if (! $this->memakaiPyodide()) {
            GradeSubmissionJob::dispatch($submission->submission_id);
        }

        return ['status' => 'ok', 'submission' => $submission];
    }

    /**
     * Apakah penilaian dilakukan di browser (Pyodide)?
     */
    public function memakaiPyodide(): bool
    {
        return config('teater.runner') === 'pyodide';
    }

    /**
     * Nonce acak 64 karakter hex (sekali pakai).
     */
    public function buatNonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Sisa jeda (detik) sebelum kelompok boleh submit lagi pada misi ini.
     */
    public function sisaJeda(Team $team, Mission $misi): int
    {
        $terakhir = Submission::query()
            ->where('team_id', $team->team_id)
            ->where('mission_id', $misi->mission_id)
            ->max('submitted_at');

        if ($terakhir === null) {
            return 0;
        }

        $interval = (int) config('teater.submit_interval_seconds', 15);
        $lewat = Carbon::parse((string) $terakhir)->diffInSeconds(now());

        return max(0, $interval - $lewat);
    }
}
