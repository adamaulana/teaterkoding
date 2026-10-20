<?php

namespace App\Services;

use App\Actions\ValidasiKasusUji;
use App\Models\BugAttempt;
use App\Models\MissionPlan;
use App\Models\ScoreEntry;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\TestCase;

/**
 * Pemberian poin (idempoten lewat award_key unik).
 *
 * HANYA submission berstatus 'dinilai' (dan percobaan bug yang lulus) yang
 * menghasilkan poin. Setiap aturan memakai firstOrCreate dengan award_key
 * sehingga job yang diulang tidak menghasilkan poin ganda.
 *
 * Aturan: mission_result (tim), entri individual sumatif, first_try_bonus,
 * plan_bonus, bug_bonus, hint_penalty.
 */
final class ScoringService
{
    /**
     * Poin hasil misi tim (persen kasus uji lulus, skema selisih).
     * Hanya menambah bila persen baru lebih tinggi dari skor terbaik sebelumnya.
     * award_key: mr:{team}:{mission}:{submission}.
     */
    public function missionResult(Submission $submission): void
    {
        if (! $submission->sudahDinilai()) {
            return;
        }

        $persen = $this->persenKasus($submission);

        if ($persen === null) {
            return;
        }

        $terbaik = $this->skorMisiTim((int) $submission->team_id, (int) $submission->mission_id, (int) $submission->submission_id);

        $delta = $persen - $terbaik;

        if ($delta <= 0) {
            return;
        }

        $this->buat(
            'mr:'.(int) $submission->team_id.':'.(int) $submission->mission_id.':'.(int) $submission->submission_id,
            (int) $submission->team_id,
            null,
            (int) $submission->mission_id,
            'mission_result',
            $delta,
            (string) $submission->submitted_at,
        );
    }

    /**
     * Entri individual sumatif (persen kasus uji fungsi milik tiap siswa,
     * skema selisih). award_key: mri:{student}:{mission}:{submission}.
     */
    public function missionResultIndividu(Submission $submission): void
    {
        if (! $submission->sudahDinilai()) {
            return;
        }

        $persenPerSiswa = $this->persenPerSiswa($submission);

        foreach ($persenPerSiswa as $studentId => $persen) {
            $terbaik = $this->skorMisiSiswa($studentId, (int) $submission->mission_id, (int) $submission->submission_id);

            $delta = $persen - $terbaik;

            if ($delta <= 0) {
                continue;
            }

            $this->buat(
                'mri:'.$studentId.':'.(int) $submission->mission_id.':'.(int) $submission->submission_id,
                (int) $submission->team_id,
                $studentId,
                (int) $submission->mission_id,
                'mission_result',
                $delta,
                (string) $submission->submitted_at,
            );
        }
    }

    /**
     * Bonus percobaan pertama: attempt_no = 1 dan semua kasus lulus.
     * award_key: first:{team}:{mission}.
     */
    public function firstTryBonus(Submission $submission): void
    {
        if ((int) $submission->attempt_no !== 1 || ! (bool) $submission->all_passed) {
            return;
        }

        $this->buat(
            'first:'.(int) $submission->team_id.':'.(int) $submission->mission_id,
            (int) $submission->team_id,
            null,
            (int) $submission->mission_id,
            'first_try_bonus',
            (int) config('teater.first_try_bonus', 10),
            (string) $submission->submitted_at,
        );
    }

    /**
     * Bonus rencana: semua lulus, rencana terkunci SEBELUM submission pertama,
     * dan ConceptUsageAnalyzer menyatakan "Sesuai".
     * award_key: plan:{team}:{mission}.
     */
    public function planBonus(Submission $submission): void
    {
        if (! (bool) $submission->all_passed) {
            return;
        }

        $rencana = MissionPlan::query()
            ->where('team_id', $submission->team_id)
            ->where('mission_id', $submission->mission_id)
            ->first();

        if ($rencana === null || ! $rencana->terkunci()) {
            return;
        }

        $submissionPertama = Submission::query()
            ->where('team_id', $submission->team_id)
            ->where('mission_id', $submission->mission_id)
            ->orderBy('submitted_at')
            ->orderBy('submission_id')
            ->first();

        if ($submissionPertama === null || $rencana->locked_at->gt($submissionPertama->submitted_at)) {
            return;
        }

        $hasil = app(ConceptUsageAnalyzer::class)->analisisRencana($rencana, $submission->code_text);

        if ($hasil['status'] !== ConceptUsageAnalyzer::HASIL_SESUAI) {
            return;
        }

        $this->buat(
            'plan:'.(int) $submission->team_id.':'.(int) $submission->mission_id,
            (int) $submission->team_id,
            null,
            (int) $submission->mission_id,
            'plan_bonus',
            (int) config('teater.plan_bonus', 10),
            (string) $submission->submitted_at,
        );
    }

    /**
     * Bonus kartu bug: sebesar bug_cards.points saat bug lulus.
     * award_key: bug:{team}:{bug}.
     */
    public function bugBonus(BugAttempt $attempt): void
    {
        if (! (bool) $attempt->is_solved) {
            return;
        }

        $poin = (int) ($attempt->bugCard?->points ?? 0);

        if ($poin <= 0) {
            return;
        }

        $this->buat(
            'bug:'.(int) $attempt->team_id.':'.(int) $attempt->bug_id,
            (int) $attempt->team_id,
            null,
            null,
            'bug_bonus',
            $poin,
            (string) ($attempt->solved_at ?? now()),
        );
    }

    /**
     * Penalti petunjuk: negatif sebesar config hint_penalty saat petunjuk dibuka.
     * award_key: hint:{team}:{bug}.
     */
    public function hintPenalty(BugAttempt $attempt): void
    {
        if ((int) $attempt->hints_used < 1) {
            return;
        }

        $this->buat(
            'hint:'.(int) $attempt->team_id.':'.(int) $attempt->bug_id,
            (int) $attempt->team_id,
            null,
            null,
            'hint_penalty',
            -1 * (int) config('teater.hint_penalty', 5),
            (string) ($attempt->attempted_at ?? now()),
        );
    }

    /**
     * Persen kasus uji lulus pada submission (bulat, 0-100). null bila misi
     * tidak punya kasus uji.
     */
    private function persenKasus(Submission $submission): ?int
    {
        $total = TestCase::query()->where('mission_id', $submission->mission_id)->count();

        if ($total === 0) {
            return null;
        }

        $lulus = SubmissionResult::query()
            ->where('submission_id', $submission->submission_id)
            ->where('is_passed', true)
            ->count();

        return (int) round(($lulus / $total) * 100);
    }

    /**
     * Persen kasus uji lulus per siswa pemilik fungsi (dari submission_parts).
     *
     * @return array<int, int> student_id => persen
     */
    private function persenPerSiswa(Submission $submission): array
    {
        $parts = $submission->parts()->whereNotNull('student_id')->get();

        if ($parts->isEmpty()) {
            return [];
        }

        $validator = new ValidasiKasusUji;

        $kasus = TestCase::query()->where('mission_id', $submission->mission_id)->get();

        $fungsiPerKasus = [];

        foreach ($kasus as $k) {
            $nama = $validator->namaFungsi((string) $k->check_target);

            if ($nama !== null) {
                $fungsiPerKasus[(int) $k->test_case_id] = $nama;
            }
        }

        $lulusPerKasus = SubmissionResult::query()
            ->where('submission_id', $submission->submission_id)
            ->where('is_passed', true)
            ->pluck('test_case_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hasil = [];

        foreach ($parts as $part) {
            $studentId = (int) $part->student_id;
            $nama = (string) $part->function_name;

            $kasusFungsi = array_keys(array_filter(
                $fungsiPerKasus,
                fn (string $n) => $n === $nama,
            ));

            $total = count($kasusFungsi);

            if ($total === 0) {
                continue;
            }

            $lulus = count(array_intersect($kasusFungsi, $lulusPerKasus));

            $hasil[$studentId] = (int) round(($lulus / $total) * 100);
        }

        return $hasil;
    }

    /**
     * Total skor mission_result (tim) yang sudah ada untuk misi ini, tanpa
     * submission saat ini.
     */
    private function skorMisiTim(int $teamId, int $missionId, int $kecualiSubmissionId): int
    {
        return (int) ScoreEntry::query()
            ->where('team_id', $teamId)
            ->where('mission_id', $missionId)
            ->whereNull('student_id')
            ->where('source_type', 'mission_result')
            ->where('award_key', '!=', 'mr:'.$teamId.':'.$missionId.':'.$kecualiSubmissionId)
            ->sum('points');
    }

    /**
     * Total skor individual mission_result untuk siswa pada misi ini.
     */
    private function skorMisiSiswa(int $studentId, int $missionId, int $kecualiSubmissionId): int
    {
        return (int) ScoreEntry::query()
            ->where('student_id', $studentId)
            ->where('mission_id', $missionId)
            ->where('source_type', 'mission_result')
            ->where('award_key', '!=', 'mri:'.$studentId.':'.$missionId.':'.$kecualiSubmissionId)
            ->sum('points');
    }

    /**
     * Buat entry poin secara idempoten (skip bila award_key sudah ada).
     */
    private function buat(
        string $awardKey,
        int $teamId,
        ?int $studentId,
        ?int $missionId,
        string $sourceType,
        int $points,
        string $createdAt,
    ): void {
        ScoreEntry::firstOrCreate(
            ['award_key' => $awardKey],
            [
                'team_id' => $teamId,
                'student_id' => $studentId,
                'mission_id' => $missionId,
                'source_type' => $sourceType,
                'points' => $points,
                'created_at' => $createdAt,
            ],
        );
    }
}
