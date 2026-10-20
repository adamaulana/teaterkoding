<?php

namespace App\Services;

use App\Actions\ValidasiKasusUji;
use App\Models\BugCard;
use App\Models\FunctionOwnership;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\TeamMember;
use App\Models\TestCase;
use Illuminate\Support\Collection;

/**
 * Perhitungan nilai sumatif per siswa per misi sumatif.
 *
 * Lima komponen 0-100:
 *   1. Fungsi individu — dari function_ownership siswa pada misi itu.
 *   2. Kelompok        — persen seluruh kasus uji misi yang lulus pada
 *                        submission acuan tim.
 *   3. Kartu bug & rencana — score_entries tim (bug_bonus, plan_bonus,
 *                        hint_penalty) dibagi total poin maksimal yang
 *                        mungkin, dibatasi 0-100.
 *   4. Refleksi/rubrik — manual_scores.reflection_score.
 *   5. Observasi       — manual_scores.observation_score.
 *
 * Nilai akhir = jumlah (komponen x bobot kelas) / 100, dibulatkan 1 desimal.
 * Komponen manual kosong dihitung 0 tetapi baris ditandai "Sementara".
 *
 * SEMUA rumus ada di sini — tidak ada rumus di Blade maupun komponen Livewire.
 */
class SumativeScoreCalculator
{
    /**
     * Hitung nilai sumatif untuk seluruh siswa pada satu misi sumatif.
     *
     * @param  Collection<int, Student>  $siswa
     * @param  array<string, int>  $bobot
     * @return Collection<int, SumativeScore> kunci = student_id
     */
    public function hitungKelas(Collection $siswa, Mission $misi, array $bobot): Collection
    {
        $misi->loadMissing('level');

        $kasusUji = TestCase::query()
            ->where('mission_id', $misi->mission_id)
            ->orderBy('test_case_id')
            ->get();

        $teamPerSiswa = $this->teamPerSiswa($siswa);

        $submissionAcuan = $this->submissionAcuan(
            $teamPerSiswa->flatten()->unique()->values()->all(),
            (int) $misi->mission_id,
        );

        $hasilPerSubmission = $this->hasilPerSubmission($submissionAcuan->pluck('submission_id')->all());

        $kepemilikan = FunctionOwnership::query()
            ->where('mission_id', $misi->mission_id)
            ->whereIn('student_id', $siswa->pluck('student_id')->all())
            ->get()
            ->groupBy('student_id');

        $manual = ManualScore::query()
            ->where('mission_id', $misi->mission_id)
            ->whereIn('student_id', $siswa->pluck('student_id')->all())
            ->get()
            ->keyBy('student_id');

        $poinBugRencana = $this->poinBugRencanaPerTeam($submissionAcuan, $misi);
        $maksimalBugRencana = $this->maksimalBugRencana($misi);

        $hasil = collect();

        foreach ($siswa as $s) {
            $studentId = (int) $s->student_id;
            $teamId = $teamPerSiswa->get($studentId);
            $submission = $teamId !== null ? $submissionAcuan->get($teamId) : null;

            // null = tim belum pernah submit → komponen kelompok belum dinilai.
            $hasilKasus = $submission === null
                ? null
                : $hasilPerSubmission->get((int) $submission->submission_id, collect());

            $hasil->put($studentId, $this->hitungSatu(
                $studentId,
                $kasusUji,
                $hasilKasus,
                $kepemilikan->get($studentId, collect()),
                $manual->get($studentId),
                (int) $poinBugRencana->get($teamId, 0),
                $maksimalBugRencana,
                $bobot,
            ));
        }

        return $hasil;
    }

    /**
     * Hitung nilai satu siswa.
     *
     * @param  Collection<int, TestCase>  $kasusUji
     * @param  Collection<int, SubmissionResult>|null  $hasilKasus  null = tim belum submit
     * @param  Collection<int, FunctionOwnership>  $kepemilikan
     * @param  array<string, int>  $bobot
     */
    public function hitungSatu(
        int $studentId,
        Collection $kasusUji,
        ?Collection $hasilKasus,
        Collection $kepemilikan,
        ?ManualScore $manual,
        int $poinBugRencana,
        int $maksimalBugRencana,
        array $bobot,
    ): SumativeScore {
        $adaSubmission = $hasilKasus !== null;
        $hasilKasus = $hasilKasus ?? collect();

        // Tanpa submission acuan, komponen individu & kelompok "belum dinilai".
        if ($adaSubmission) {
            [$komponenIndividu, $punyaFungsi, $rincian] = $this->komponenIndividu($kasusUji, $hasilKasus, $kepemilikan);
        } else {
            $punyaFungsi = $kepemilikan->isNotEmpty();
            $komponenIndividu = null;
            $rincian = $punyaFungsi ? 'Tim belum mengirim submission' : 'Belum punya fungsi';
        }

        $komponenKelompok = $this->komponenKelompok($kasusUji, $hasilKasus, $adaSubmission);

        // Komponen bug & rencana juga "belum ada data" bila tim belum pernah
        // submit — supaya sel tampil netral, bukan angka 0 merah.
        $komponenBugPlan = $adaSubmission && $maksimalBugRencana > 0
            ? max(0, min(100, round(($poinBugRencana / $maksimalBugRencana) * 100, 1)))
            : null;

        $komponenRefleksi = $manual?->reflection_score !== null ? (float) $manual->reflection_score : null;
        $komponenObservasi = $manual?->observation_score !== null ? (float) $manual->observation_score : null;

        $nilaiAkhir = round(
            (($komponenIndividu ?? 0) * ($bobot['w_individual'] ?? 0)
                + ($komponenKelompok ?? 0) * ($bobot['w_team'] ?? 0)
                + ($komponenBugPlan ?? 0) * ($bobot['w_bug_plan'] ?? 0)
                + ($komponenRefleksi ?? 0) * ($bobot['w_reflection'] ?? 0)
                + ($komponenObservasi ?? 0) * ($bobot['w_observation'] ?? 0)) / 100,
            1,
        );

        $sementara = $komponenRefleksi === null || $komponenObservasi === null;

        return new SumativeScore(
            $studentId,
            $komponenIndividu,
            $komponenKelompok,
            $komponenBugPlan,
            $komponenRefleksi,
            $komponenObservasi,
            $nilaiAkhir,
            $sementara,
            $punyaFungsi,
            $rincian,
            $kepemilikan->pluck('function_name')->map(fn ($n) => (string) $n)->values()->all(),
        );
    }

    /**
     * Komponen 1: skor fungsi individu.
     *
     * Kasus uji milik sebuah fungsi = test_cases yang nama fungsi pada
     * check_target-nya sama dengan function_name. Hasil diambil dari
     * submission_results submission acuan tim.
     *
     * @param  Collection<int, TestCase>  $kasusUji
     * @param  Collection<int, SubmissionResult>  $hasilKasus
     * @param  Collection<int, FunctionOwnership>  $kepemilikan
     * @return array{0: float|null, 1: bool, 2: string}
     */
    private function komponenIndividu(Collection $kasusUji, Collection $hasilKasus, Collection $kepemilikan): array
    {
        if ($kepemilikan->isEmpty()) {
            return [null, false, 'Belum punya fungsi'];
        }

        $validator = new ValidasiKasusUji;

        // Peta test_case_id => nama fungsi pemiliknya.
        $fungsiPerKasus = [];

        foreach ($kasusUji as $kasus) {
            $namaFungsi = $validator->namaFungsi((string) $kasus->check_target);

            if ($namaFungsi !== null) {
                $fungsiPerKasus[(int) $kasus->test_case_id] = $namaFungsi;
            }
        }

        $lulusPerKasus = $hasilKasus->keyBy('test_case_id');

        $totalKasus = 0;
        $totalLulus = 0;
        $bagian = [];

        foreach ($kepemilikan as $milik) {
            $namaFungsi = (string) $milik->function_name;

            $kasusFungsi = array_keys(array_filter(
                $fungsiPerKasus,
                fn (string $nama) => $nama === $namaFungsi,
            ));

            $jumlahLulus = 0;

            foreach ($kasusFungsi as $testCaseId) {
                $baris = $lulusPerKasus->get($testCaseId);

                if ($baris !== null && (bool) $baris->is_passed) {
                    $jumlahLulus++;
                }
            }

            $totalKasus += count($kasusFungsi);
            $totalLulus += $jumlahLulus;

            $bagian[] = $jumlahLulus.' dari '.count($kasusFungsi)." kasus uji fungsi {$namaFungsi} lulus";
        }

        $rincian = implode('; ', $bagian);

        if ($totalKasus === 0) {
            return [null, true, $rincian];
        }

        return [round(($totalLulus / $totalKasus) * 100, 1), true, $rincian];
    }

    /**
     * Komponen 2: persen kasus uji misi yang lulus pada submission acuan tim.
     *
     * @param  Collection<int, TestCase>  $kasusUji
     * @param  Collection<int, SubmissionResult>  $hasilKasus
     */
    private function komponenKelompok(Collection $kasusUji, Collection $hasilKasus, bool $adaSubmission): ?float
    {
        if (! $adaSubmission || $kasusUji->isEmpty()) {
            return null;
        }

        return round(($hasilKasus->where('is_passed', true)->count() / $kasusUji->count()) * 100, 1);
    }

    /**
     * Peta student_id => team_id untuk sekumpulan siswa.
     *
     * @param  Collection<int, Student>  $siswa
     * @return Collection<int, int>
     */
    private function teamPerSiswa(Collection $siswa): Collection
    {
        $studentIds = $siswa->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        if ($studentIds === []) {
            return collect();
        }

        return TeamMember::query()
            ->whereIn('student_id', $studentIds)
            ->pluck('team_id', 'student_id')
            ->map(fn ($teamId) => (int) $teamId);
    }

    /**
     * Submission acuan per tim sesuai config/teater.php.
     *
     * @param  array<int, int>  $teamIds
     * @return Collection<int, Submission> kunci = team_id
     */
    private function submissionAcuan(array $teamIds, int $missionId): Collection
    {
        if ($teamIds === []) {
            return collect();
        }

        $semua = Submission::query()
            ->whereIn('team_id', $teamIds)
            ->where('mission_id', $missionId)
            // Hanya hasil FINAL yang boleh masuk rekap. Submission
            // 'menunggu_verifikasi' (sumatif, hasil perangkat belum disetujui
            // guru) dan 'menunggu'/'galat' sengaja diabaikan supaya poin tidak
            // pernah dihitung sebelum verifikasi guru.
            ->where('status', Submission::STATUS_DINILAI)
            ->orderBy('submitted_at')
            ->orderBy('submission_id')
            ->get(['submission_id', 'team_id', 'mission_id', 'submitted_at', 'all_passed'])
            ->groupBy('team_id');

        $aturan = (string) config('teater.reference_submission', 'last');

        return $semua->map(fn (Collection $grup) => match ($aturan) {
            'first' => $grup->first(),
            default => $grup->last(),
        });
    }

    /**
     * Hasil kasus uji per submission_id.
     *
     * @param  array<int, int>  $submissionIds
     * @return Collection<int, Collection<int, SubmissionResult>>
     */
    private function hasilPerSubmission(array $submissionIds): Collection
    {
        if ($submissionIds === []) {
            return collect();
        }

        return SubmissionResult::query()
            ->whereIn('submission_id', $submissionIds)
            ->get(['result_id', 'submission_id', 'test_case_id', 'is_passed'])
            ->groupBy('submission_id')
            ->map(fn ($grup) => $grup->values());
    }

    /**
     * Poin bug + rencana per tim (bug_bonus + plan_bonus + hint_penalty).
     *
     * Baris skor kelompok dikenali dari student_id NULL; baris misi dibatasi
     * pada misi yang sedang dihitung, sedangkan bonus bug/rencana bersifat
     * per level sehingga boleh tanpa mission_id.
     *
     * @param  Collection<int, Submission>  $submissionAcuan
     * @return Collection<int, int> kunci = team_id
     */
    private function poinBugRencanaPerTeam(Collection $submissionAcuan, Mission $misi): Collection
    {
        $teamIds = $submissionAcuan->keys()->map(fn ($id) => (int) $id)->all();

        if ($teamIds === []) {
            return collect();
        }

        return ScoreEntry::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('student_id')
            ->whereIn('source_type', ['bug_bonus', 'plan_bonus', 'hint_penalty'])
            ->where(fn ($q) => $q->whereNull('mission_id')->orWhere('mission_id', $misi->mission_id))
            ->selectRaw('team_id, SUM(points) as total')
            ->groupBy('team_id')
            ->pluck('total', 'team_id')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Poin maksimal komponen bug & rencana: jumlah poin semua kartu bug pada
     * level misi + bonus rencana (config/teater.php).
     */
    private function maksimalBugRencana(Mission $misi): int
    {
        $poinBug = (int) BugCard::query()
            ->where('level_id', $misi->level_id)
            ->sum('points');

        return $poinBug + (int) config('teater.plan_bonus_points', 10);
    }
}
