<?php

namespace App\Services;

use App\Models\BugAttempt;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\Submission;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menentukan status progres tiap kelompok (dan penanda "macet").
 *
 * Semua rumus ada di sini — komponen Livewire hanya memanggil service ini.
 * Untuk satu kelas, gunakan resolveKelas() agar tidak query per kelompok.
 */
class TeamProgressResolver
{
    /**
     * Status untuk sekumpulan kelompok (bulk, tanpa query per kelompok).
     *
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, TeamProgress> kunci = team_id
     */
    public function resolveKelas(Collection $teams, int $totalLevel): Collection
    {
        $teamIds = $teams->pluck('team_id')->map(fn ($id) => (int) $id)->all();

        if ($teamIds === []) {
            return collect();
        }

        $levelPerMisi = $this->levelPerMisi();

        $rencana = MissionPlan::query()
            ->whereIn('team_id', $teamIds)
            ->get(['plan_id', 'team_id', 'mission_id', 'planned_at'])
            ->groupBy('team_id');

        $submission = Submission::query()
            ->whereIn('team_id', $teamIds)
            ->orderByDesc('submitted_at')
            ->get(['submission_id', 'team_id', 'mission_id', 'submitted_at', 'all_passed', 'status'])
            ->groupBy('team_id');

        $bugTerakhir = BugAttempt::query()
            ->whereIn('team_id', $teamIds)
            ->selectRaw('team_id, MAX(attempted_at) as terakhir')
            ->groupBy('team_id')
            ->pluck('terakhir', 'team_id');

        $hasil = collect();

        foreach ($teams as $team) {
            $id = (int) $team->team_id;

            $hasil->put($id, $this->susun(
                $id,
                $rencana->get($id, collect()),
                $submission->get($id, collect()),
                $levelPerMisi,
                $totalLevel,
                $bugTerakhir->get($id),
            ));
        }

        return $hasil;
    }

    /**
     * Status untuk satu kelompok (memakai jalur bulk agar konsisten).
     */
    public function resolve(Team $team, int $totalLevel): TeamProgress
    {
        $hasil = $this->resolveKelas(collect([$team]), $totalLevel);

        return $hasil->get((int) $team->team_id)
            ?? new TeamProgress((int) $team->team_id, TeamProgress::STATUS_BELUM_MULAI, null, 0, $totalLevel, 0, false, null, null);
    }

    /**
     * @param  Collection<int, MissionPlan>  $rencanaTim
     * @param  Collection<int, Submission>  $submissionTim
     * @param  array<int, int>  $levelPerMisi
     */
    private function susun(
        int $teamId,
        Collection $rencanaTim,
        Collection $submissionTim,
        array $levelPerMisi,
        int $totalLevel,
        mixed $bugTerakhir,
    ): TeamProgress {
        $punyaRencana = $rencanaTim->isNotEmpty();
        $punyaSubmission = $submissionTim->isNotEmpty();

        $levelDimulai = collect()
            ->merge($rencanaTim->pluck('mission_id'))
            ->merge($submissionTim->pluck('mission_id'))
            ->map(fn ($missionId) => $levelPerMisi[(int) $missionId] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $levelAktif = $levelDimulai->isEmpty() ? null : (int) $levelDimulai->max();

        $levelLulus = $submissionTim
            ->filter(fn (Submission $s) => $s->sudahDinilai() && (bool) $s->all_passed)
            ->map(fn (Submission $s) => $levelPerMisi[(int) $s->mission_id] ?? null)
            ->filter()
            ->unique()
            ->max();

        $levelLulus = $levelLulus === null ? 0 : (int) $levelLulus;
        $semuaLulus = $totalLevel > 0 && $levelLulus >= $totalLevel;

        $status = match (true) {
            ! $punyaRencana && ! $punyaSubmission => TeamProgress::STATUS_BELUM_MULAI,
            ! $punyaSubmission => TeamProgress::STATUS_MERENCANAKAN,
            $semuaLulus => TeamProgress::STATUS_SELESAI,
            $levelLulus > 0 => TeamProgress::STATUS_LULUS,
            default => TeamProgress::STATUS_MENGERJAKAN,
        };

        $aktivitasTerakhir = $this->aktivitasTerakhir($rencanaTim, $submissionTim, $bugTerakhir);

        [$macet, $alasan] = $this->periksaMacet($submissionTim, $aktivitasTerakhir, $semuaLulus, $status);

        return new TeamProgress(
            $teamId,
            $status,
            $levelAktif,
            $levelLulus,
            $totalLevel,
            $submissionTim->count(),
            $macet,
            $alasan,
            $aktivitasTerakhir,
        );
    }

    /**
     * @param  Collection<int, MissionPlan>  $rencanaTim
     * @param  Collection<int, Submission>  $submissionTim
     */
    private function aktivitasTerakhir(Collection $rencanaTim, Collection $submissionTim, mixed $bugTerakhir): ?Carbon
    {
        $kandidat = collect()
            ->merge($rencanaTim->pluck('planned_at'))
            ->merge($submissionTim->pluck('submitted_at'))
            ->filter()
            ->map(fn ($waktu) => $waktu instanceof Carbon ? $waktu : Carbon::parse((string) $waktu));

        if ($bugTerakhir !== null) {
            $kandidat->push(Carbon::parse((string) $bugTerakhir));
        }

        return $kandidat->isEmpty() ? null : $kandidat->max();
    }

    /**
     * Ambang macet diambil dari config/teater.php.
     *
     * @param  Collection<int, Submission>  $submissionTim
     * @return array{0: bool, 1: string|null}
     */
    private function periksaMacet(
        Collection $submissionTim,
        ?Carbon $aktivitasTerakhir,
        bool $semuaLulus,
        string $status,
    ): array {
        if ($semuaLulus || $status === TeamProgress::STATUS_BELUM_MULAI) {
            return [false, null];
        }

        $menit = (int) config('teater.stuck.no_submission_minutes', 10);
        $batasGagal = (int) config('teater.stuck.consecutive_failures', 5);

        // (a) Gagal beruntun diperiksa LEBIH DULU karena paling informatif:
        // guru langsung tahu kelompok tersangkut di misi tertentu.
        // Submission 'menunggu'/'galat'/'waktu_habis' TIDAK dihitung gagal.
        if ($submissionTim->isNotEmpty()) {
            $dinilai = $submissionTim
                ->filter(fn (Submission $s) => $s->sudahDinilai())
                ->values();

            if ($dinilai->isNotEmpty()) {
                $misiTerakhir = (int) $dinilai->first()->mission_id;

                $gagalBeruntun = $dinilai
                    ->takeWhile(fn (Submission $s) => (int) $s->mission_id === $misiTerakhir && ! (bool) $s->all_passed)
                    ->count();

                if ($gagalBeruntun >= $batasGagal) {
                    return [true, $gagalBeruntun.' kali gagal beruntun'];
                }
            }
        }

        // (b) Tidak ada aktivitas selama N menit.
        if ($aktivitasTerakhir !== null && $aktivitasTerakhir->diffInMinutes(Carbon::now()) >= $menit) {
            return [true, $menit.' menit tanpa aktivitas'];
        }

        return [false, null];
    }

    /**
     * Peta mission_id => level_number.
     *
     * @return array<int, int>
     */
    private function levelPerMisi(): array
    {
        return Mission::query()
            ->join('levels', 'levels.level_id', '=', 'missions.level_id')
            ->pluck('levels.level_number', 'missions.mission_id')
            ->map(fn ($nomor) => (int) $nomor)
            ->all();
    }
}
