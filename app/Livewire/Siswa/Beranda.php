<?php

namespace App\Livewire\Siswa;

use App\Actions\GenerateKodeUnlock;
use App\Models\BugAttempt;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\RoleAssignment;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\LevelAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa')]
#[Title('Beranda')]
class Beranda extends Component
{
    public string $kodeMisi = '';

    #[Computed]
    public function siswa(): Student
    {
        return Auth::guard('siswa')->user();
    }

    #[Computed]
    public function kelasNama(): string
    {
        return (string) ($this->siswa->schoolClass?->class_name ?? '');
    }

    #[Computed]
    public function team(): ?Team
    {
        return $this->siswa->team();
    }

    #[Computed]
    public function punyaKelompok(): bool
    {
        return $this->team !== null;
    }

    /**
     * Anggota kelompok + peran pada level aktif (bila ada level aktif).
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function anggota(): Collection
    {
        if ($this->team === null) {
            return collect();
        }

        $team = $this->team;
        $petaPeran = $this->peranAnggota($team);

        return Student::query()
            ->whereIn('student_id', TeamMember::query()->where('team_id', $team->team_id)->pluck('student_id'))
            ->get()
            ->map(function (Student $s) use ($petaPeran) {
                $s->peran_aktif = $petaPeran->get((int) $s->student_id);

                return $s;
            })
            ->sortBy('full_name')
            ->values();
    }

    /**
     * Peran siswa yang sedang login (untuk kartu sorotan di beranda).
     */
    #[Computed]
    public function peranSaya(): ?string
    {
        return $this->anggota
            ->firstWhere('student_id', $this->siswa->student_id)
            ?->peran_aktif;
    }

    /**
     * Empat kartu level dengan statusnya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function kartuLevel(): Collection
    {
        if ($this->team === null) {
            return collect();
        }

        $team = $this->team;

        $levels = Level::query()->orderBy('level_number')->get();
        $misiPerLevel = Mission::query()->get()->groupBy('level_id');
        $aksesPerLevel = ClassLevelAccess::query()
            ->where('class_id', $team->class_id)
            ->get()
            ->keyBy('level_id');

        $lulusLevelIds = $this->levelLulusIds($team);
        $mulaiLevelIds = $this->levelMulaiIds($team);

        return $levels->map(function (Level $level) use ($team, $misiPerLevel, $aksesPerLevel, $lulusLevelIds, $mulaiLevelIds) {
            $misi = $misiPerLevel->get($level->level_id)?->first();
            $akses = $aksesPerLevel->get($level->level_id);

            $terbuka = $akses !== null && $akses->bolehDibuka();
            $lulus = $lulusLevelIds->contains($level->level_id);
            $mulai = $mulaiLevelIds->contains($level->level_id);

            $status = 'terkunci';
            $keterangan = 'Guru belum membuka level ini.';

            if ($lulus) {
                $status = 'lulus';
                $keterangan = $this->ringkasLulus($team, $misi);
            } elseif ($terbuka && $mulai) {
                $status = 'berjalan';
                $keterangan = 'Tahap: '.$this->tahapUntuk($team, $misi);
            } elseif ($terbuka) {
                $status = 'siap';
                $keterangan = $misi !== null ? 'Masukkan kode misi untuk mulai.' : 'Belum ada misi di level ini.';
            }

            return [
                'level' => $level,
                'misi' => $misi,
                'status' => $status,
                'keterangan' => $keterangan,
            ];
        })->values();
    }

    /**
     * Kartu Bug aktif (sudah dirilis guru & belum terpecahkan).
     *
     * @return Collection<int, BugAttempt>
     */
    #[Computed]
    public function kartuBug(): Collection
    {
        if ($this->team === null) {
            return collect();
        }

        return BugAttempt::query()
            ->where('team_id', $this->team->team_id)
            ->whereNotNull('released_at')
            ->where('is_solved', false)
            ->with('bugCard')
            ->orderByDesc('released_at')
            ->get();
    }

    #[Computed]
    public function skorKelompok(): int
    {
        if ($this->team === null) {
            return 0;
        }

        return (int) ScoreEntry::query()
            ->where('team_id', $this->team->team_id)
            ->whereNull('student_id')
            ->sum('points');
    }

    /**
     * Peringkat kelompok di kelasnya (hanya bila show_leaderboard).
     *
     * @return array{posisi: int, total_kelompok: int}|null
     */
    #[Computed]
    public function peringkat(): ?array
    {
        if (! config('teater.show_leaderboard', true) || $this->team === null) {
            return null;
        }

        $team = $this->team;

        $skorPerTeam = ScoreEntry::query()
            ->whereNull('student_id')
            ->whereIn('team_id', Team::query()->where('class_id', $team->class_id)->pluck('team_id'))
            ->selectRaw('team_id, SUM(points) as total')
            ->groupBy('team_id')
            ->orderByDesc('total')
            ->get();

        $posisi = 1;

        foreach ($skorPerTeam as $baris) {
            if ((int) $baris->team_id === (int) $team->team_id) {
                return ['posisi' => $posisi, 'total_kelompok' => $skorPerTeam->count()];
            }

            $posisi++;
        }

        return ['posisi' => $skorPerTeam->count() + 1, 'total_kelompok' => $skorPerTeam->count() + 1];
    }

    /**
     * Buka misi dari kode unlock (input Kode Misi).
     */
    public function bukaMisi(): void
    {
        if ($this->team === null) {
            return;
        }

        // Kode MISI memakai aturan guru (GenerateKodeUnlock): huruf besar dan
        // hanya A-Z 0-9 + tanda hubung. JANGAN memakai AccessCodeService —
        // layanan itu untuk kode akses SISWA dan membuang tanda hubung,
        // sehingga "KOP-L1-BUDI" menjadi "KOPL1BUDI" dan tidak pernah cocok.
        $kode = app(GenerateKodeUnlock::class)->normalisasi($this->kodeMisi);

        $kunci = 'siswa-unlock:'.$this->siswa->student_id;

        if (RateLimiter::tooManyAttempts($kunci, (int) config('teater.unlock_throttle', 5))) {
            $this->dispatch('toast', type: 'peringatan', message: 'Terlalu sering mencoba. Coba lagi sebentar lagi.');

            return;
        }

        $misi = Mission::query()->where('unlock_code', $kode)->first();

        if ($misi === null) {
            // Toleransi input: siswa sering mengetik tanpa tanda hubung
            // ("KOPL1BUDI"). Bandingkan bentuk polosnya. REPLACE() tersedia di
            // MySQL/MariaDB dan SQLite, jadi aman di produksi maupun test.
            $polos = str_replace('-', '', $kode);

            if ($polos !== '') {
                $misi = Mission::query()
                    ->whereRaw("replace(unlock_code, '-', '') = ?", [$polos])
                    ->first();
            }
        }

        if ($misi === null) {
            RateLimiter::hit($kunci, 60);
            $this->reset('kodeMisi');
            $this->dispatch('toast', type: 'bahaya', message: 'Kode misi tidak ditemukan.');

            return;
        }

        if (! app(LevelAccessService::class)->canUnlock($this->team, $misi)) {
            RateLimiter::hit($kunci, 60);
            $this->reset('kodeMisi');
            $this->dispatch('toast', type: 'peringatan', message: 'Misi belum bisa dibuka sekarang.');

            return;
        }

        RateLimiter::clear($kunci);

        MissionPlan::firstOrCreate(
            ['team_id' => $this->team->team_id, 'mission_id' => $misi->mission_id],
            ['planned_at' => now(), 'unlocked_by' => $this->siswa->student_id],
        );

        $this->reset('kodeMisi');
        $this->redirect(route('siswa.misi', $misi->mission_id), navigate: true);
    }

    /**
     * Peta student_id => nama peran untuk ditampilkan di beranda.
     *
     * === MENGAPA BUKAN "SATU LEVEL AKTIF" ===
     * Peran disimpan per level, sedangkan level aktif bisa berpindah. Kelompok
     * biasanya hanya ditugaskan pada Level 1 (BagiPeranAwal), lalu naik ke
     * Level 4 setelah Level 1-3 lulus. Bila beranda hanya membaca level aktif,
     * semua anggota tampil "Belum ada peran" meskipun guru sudah menetapkannya
     * — persis bug yang pernah terjadi.
     *
     * Urutan pemilihan:
     *   1. penugasan pada level AKTIF bila ada (peran yang sedang berlaku),
     *   2. bila tidak, level TERENDAH yang punya penugasan (peran awal),
     *      bukan level tertinggi yang umumnya kosong.
     *
     * Satu query untuk seluruh kelompok (bukan query per siswa).
     *
     * @return Collection<int, string>
     */
    private function peranAnggota(Team $team): Collection
    {
        $semua = RoleAssignment::query()
            ->where('team_id', $team->team_id)
            ->with('role')
            ->get();

        if ($semua->isEmpty()) {
            return collect();
        }

        $aktif = $this->levelAktifId();
        $padaAktif = $aktif !== null ? $semua->where('level_id', $aktif) : collect();

        $dipakai = $padaAktif->isNotEmpty()
            ? $padaAktif
            : $semua->where('level_id', $semua->min('level_id'));

        return $dipakai
            ->mapWithKeys(fn ($a) => [(int) $a->student_id => (string) ($a->role?->role_name ?? '')])
            ->filter();
    }

    private function levelAktifId(): ?int
    {
        foreach ($this->kartuLevel as $kartu) {
            if (in_array($kartu['status'], ['berjalan', 'siap'], true)) {
                return (int) $kartu['level']->level_id;
            }
        }

        return null;
    }

    /**
     * Level yang sudah lulus untuk tim ini.
     *
     * @return Collection<int, int>
     */
    private function levelLulusIds(Team $team): Collection
    {
        $missionIds = Submission::query()
            ->where('team_id', $team->team_id)
            ->where('all_passed', true)
            ->where('status', Submission::STATUS_DINILAI)
            ->pluck('mission_id');

        return Mission::query()
            ->whereIn('mission_id', $missionIds)
            ->pluck('level_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Level yang sudah dimulai (punya rencana atau submission).
     *
     * @return Collection<int, int>
     */
    private function levelMulaiIds(Team $team): Collection
    {
        $missionIds = MissionPlan::query()
            ->where('team_id', $team->team_id)
            ->pluck('mission_id')
            ->merge(Submission::query()->where('team_id', $team->team_id)->pluck('mission_id'))
            ->unique();

        return Mission::query()
            ->whereIn('mission_id', $missionIds)
            ->pluck('level_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function ringkasLulus(Team $team, ?Mission $misi): string
    {
        if ($misi === null) {
            return 'Lulus';
        }

        $submission = Submission::query()
            ->where('team_id', $team->team_id)
            ->where('mission_id', $misi->mission_id)
            ->where('all_passed', true)
            ->where('status', Submission::STATUS_DINILAI)
            ->orderByDesc('submitted_at')
            ->first();

        $lulus = $submission?->results()->where('is_passed', true)->count() ?? 0;

        return "Lulus — {$lulus} kasus uji lulus";
    }

    private function tahapUntuk(Team $team, ?Mission $misi): string
    {
        if ($misi === null) {
            return 'Belum ada misi';
        }

        $rencana = MissionPlan::query()
            ->where('team_id', $team->team_id)
            ->where('mission_id', $misi->mission_id)
            ->first();

        $adaSubmission = Submission::query()
            ->where('team_id', $team->team_id)
            ->where('mission_id', $misi->mission_id)
            ->exists();

        if ($adaSubmission) {
            return 'Hasil';
        }

        if ($rencana !== null && $rencana->terkunci()) {
            return 'Koding';
        }

        if ($rencana !== null) {
            return 'Rencana';
        }

        return 'Kartu Misi';
    }

    public function render()
    {
        return view('livewire.siswa.beranda');
    }
}
