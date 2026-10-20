<?php

use App\Models\BugAttempt;
use App\Models\Level;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Services\ConceptUsageAnalyzer;
use App\Services\TeamProgress;
use App\Services\TeamProgressResolver;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantau Live — papan pantau kelompok yang menyegarkan diri sendiri.
 *
 * Polling memakai wire:poll.visible dengan jeda dari config/teater.php dan
 * bisa dibekukan guru lewat tombol Jeda, supaya panel detail tidak berkedip
 * saat sedang dibaca.
 */
new #[Layout('layouts.admin'), Title('Pantau Live')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    /** Kelompok yang dibuka pada panel samping. */
    #[Url(as: 'tim', nullable: true)]
    public ?int $teamId = null;

    public bool $jeda = false;

    // ---- Rilis Kartu Bug ----
    public ?int $rilisTeamId = null;

    public ?int $rilisLevelId = null;

    public ?int $rilisBugId = null;

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->kelasTerpilih();

        if ($this->teamId !== null) {
            $this->pastikanTimTerlihat();
        }
    }

    /**
     * Kelas terpilih wajib terlihat oleh pengguna (guru lain → 403).
     */
    private function kelasTerpilih(): ?SchoolClass
    {
        if ($this->kelasId === null) {
            return null;
        }

        $kelas = SchoolClass::findOrFail($this->kelasId);

        abort_unless(auth()->user()->can('view', $kelas), 403);

        return $kelas;
    }

    /**
     * Kelompok panel samping wajib berada di kelas yang boleh dilihat.
     */
    private function pastikanTimTerlihat(): Team
    {
        $team = Team::query()->with('schoolClass')->findOrFail($this->teamId);

        abort_unless(auth()->user()->can('view', $team->schoolClass), 403);

        return $team;
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();
        $this->teamId = null;

        $this->lupakanPanel();
    }

    public function pilihTim(int $teamId): void
    {
        $this->teamId = $teamId;

        $this->pastikanTimTerlihat();
        $this->dispatch('panel-tim-open');
    }

    public function tutupPanel(): void
    {
        $this->teamId = null;
        $this->lupakanPanel();
    }

    /**
     * Bekukan / lanjutkan penyegaran otomatis.
     */
    public function toggleJeda(): void
    {
        $this->jeda = ! $this->jeda;
    }

    private function lupakanPanel(): void
    {
        unset($this->timTerpilih, $this->anggota, $this->rencanaTerbaru, $this->kodeAcuan, $this->analisisKonsep, $this->submissionTerbaru, $this->bugTerbaru);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiKelas(): array
    {
        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->orderBy('class_name')
            ->pluck('class_name', 'class_id')
            ->all();
    }

    #[Computed]
    public function totalLevel(): int
    {
        return (int) (Level::query()->max('level_number') ?? 0);
    }

    #[Computed]
    public function jedaDetik(): int
    {
        return (int) config('teater.poll_interval_seconds', 7);
    }

    /**
     * @return Collection<int, Team>
     */
    #[Computed]
    public function kelompok(): Collection
    {
        if ($this->kelasId === null) {
            return collect();
        }

        return Team::query()
            ->where('class_id', $this->kelasId)
            ->withCount('teamMembers')
            ->orderBy('team_name')
            ->get();
    }

    /**
     * @return Collection<int, TeamProgress>  kunci = team_id
     */
    #[Computed]
    public function progres(): Collection
    {
        return app(TeamProgressResolver::class)->resolveKelas($this->kelompok, $this->totalLevel);
    }

    #[Computed]
    public function timTerpilih(): ?Team
    {
        if ($this->teamId === null) {
            return null;
        }

        return $this->kelompok->firstWhere('team_id', $this->teamId)
            ?? Team::query()->with('schoolClass')->findOrFail($this->teamId);
    }

    /**
     * Anggota kelompok beserta peran yang dipegang.
     *
     * @return Collection<int, Student>
     */
    #[Computed]
    public function anggota(): Collection
    {
        $team = $this->timTerpilih;

        if ($team === null) {
            return collect();
        }

        return $team->students()->with('roleAssignments.role')->orderBy('full_name')->get();
    }

    /**
     * Rencana misi terakhir kelompok (kartu konsep yang dipilih).
     */
    #[Computed]
    public function rencanaTerbaru(): ?MissionPlan
    {
        $team = $this->timTerpilih;

        if ($team === null) {
            return null;
        }

        return MissionPlan::query()
            ->where('team_id', $team->team_id)
            ->with(['mission.level', 'concepts'])
            ->orderByDesc('planned_at')
            ->orderByDesc('plan_id')
            ->first();
    }

    /**
     * Kode submission acuan kelompok, untuk analisis konsep.
     */
    #[Computed]
    public function kodeAcuan(): ?string
    {
        $team = $this->timTerpilih;

        if ($team === null) {
            return null;
        }

        $query = Submission::query()->where('team_id', $team->team_id);

        $submission = config('teater.reference_submission', 'last') === 'first'
            ? $query->orderBy('submitted_at')->orderBy('submission_id')->first()
            : $query->orderByDesc('submitted_at')->orderByDesc('submission_id')->first();

        return $submission?->code_text;
    }

    /**
     * Perbandingan kartu konsep yang dipilih vs yang terdeteksi di kode.
     *
     * @return array{status: string, dipilih_tak_dipakai: array<int, string>, dipakai_tak_direncanakan: array<int, string>, terdeteksi: array<int, string>}|null
     */
    #[Computed]
    public function analisisKonsep(): ?array
    {
        $rencana = $this->rencanaTerbaru;

        if ($rencana === null) {
            return null;
        }

        return app(ConceptUsageAnalyzer::class)->analisisRencana($rencana, $this->kodeAcuan);
    }

    /**
     * @return Collection<int, Submission>
     */
    #[Computed]
    public function submissionTerbaru(): Collection
    {
        $team = $this->timTerpilih;

        if ($team === null) {
            return collect();
        }

        return Submission::query()
            ->where('team_id', $team->team_id)
            ->with(['mission.level', 'submittedBy', 'results'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->limit(6)
            ->get();
    }

    /**
     * @return Collection<int, BugAttempt>
     */
    #[Computed]
    public function bugTerbaru(): Collection
    {
        $team = $this->timTerpilih;

        if ($team === null) {
            return collect();
        }

        return BugAttempt::query()
            ->where('team_id', $team->team_id)
            ->with('bugCard')
            ->orderByDesc('attempted_at')
            ->limit(6)
            ->get();
    }

    /**
     * Opsi level untuk rilis kartu bug.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opsiLevelRilis(): array
    {
        return Level::query()
            ->orderBy('level_number')
            ->pluck('level_name', 'level_id')
            ->all();
    }

    /**
     * Kartu bug pada level terpilih yang belum dirilis ke tim ini.
     *
     * @return Collection<int, \App\Models\BugCard>
     */
    #[Computed]
    public function kartuBelumDirilis(): Collection
    {
        if ($this->rilisTeamId === null || $this->rilisLevelId === null) {
            return collect();
        }

        $team = Team::query()->with('schoolClass')->findOrFail($this->rilisTeamId);

        abort_unless(auth()->user()->can('view', $team->schoolClass), 403);

        return app(\App\Services\BugService::class)->kartuBelumDirilis($team, $this->rilisLevelId);
    }

    public function bukaRilis(int $teamId): void
    {
        $team = Team::query()->with('schoolClass')->findOrFail($teamId);

        abort_unless(auth()->user()->can('view', $team->schoolClass), 403);

        $this->rilisTeamId = $teamId;
        $this->rilisLevelId = array_key_first($this->opsiLevelRilis);
        $this->rilisBugId = null;

        unset($this->kartuBelumDirilis);

        $this->dispatch('modal-rilis-bug-open');
    }

    public function simpanRilis(): void
    {
        $this->validate([
            'rilisTeamId' => ['required', 'integer'],
            'rilisLevelId' => ['required', 'integer'],
            'rilisBugId' => ['required', 'integer'],
        ], [
            'rilisBugId.required' => 'Pilih salah satu kartu bug.',
        ]);

        $team = Team::query()->with('schoolClass')->findOrFail((int) $this->rilisTeamId);

        abort_unless(auth()->user()->can('view', $team->schoolClass), 403);

        $kartu = \App\Models\BugCard::findOrFail((int) $this->rilisBugId);

        $hasil = app(\App\Services\BugService::class)->rilis($team, $kartu, auth()->user());

        unset($this->kartuBelumDirilis, $this->bugTerbaru);

        if ($hasil['status'] === 'sudah_dirilis') {
            $this->dispatch('toast', type: 'peringatan', message: 'Kartu ini sudah pernah dirilis ke kelompok tersebut.');

            return;
        }

        $this->rilisTeamId = null;
        $this->rilisBugId = null;

        $this->dispatch('modal-rilis-bug-close');
        $this->dispatch('toast', type: 'sukses', message: 'Kartu bug berhasil dirilis.');
    }
}; ?>

<div
    @unless ($jeda) wire:poll.visible.{{ $this->jedaDetik }}s @endunless
    x-data="{ panel: false }"
    x-on:panel-tim-open.window="panel = true"
>
    <x-ui.page-header
        title="Pantau Live"
        description="Papan pantau kelompok yang menyegarkan diri sendiri — klik kelompok untuk melihat detail."
    >
        <x-slot:actions>
            @if (count($this->opsiKelas) > 0)
                <div class="w-full sm:w-56">
                    <x-ui.select name="kelasId" wire:model.live="kelasId" :options="$this->opsiKelas" placeholder="Pilih kelas" />
                </div>
            @endif

            <x-ui.button :variant="$jeda ? 'primary' : 'outline'" wire:click="toggleJeda">
                @if ($jeda)
                    <x-ui.icon.refresh class="size-4" />
                    Lanjutkan
                @else
                    <x-ui.icon.eye-off class="size-4" />
                    Jeda
                @endif
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        @if ($jeda)
            <x-ui.badge variant="peringatan">Penyegaran dijeda</x-ui.badge>
            <span>Data tidak diperbarui otomatis sampai Anda menekan Lanjutkan.</span>
        @else
            <span class="inline-flex items-center gap-1.5">
                <span class="relative flex size-2">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-primary-500"></span>
                </span>
                Menyegarkan setiap {{ $this->jedaDetik }} detik
            </span>
        @endif
    </div>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk memantau kelompok."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" href="{{ route('admin.kelas') }}" wire:navigate>
                        <x-ui.icon.plus class="size-4" />
                        Buat Kelas
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @elseif ($this->kelompok->isEmpty())
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="users-group"
                title="Belum ada kelompok"
                message="Bentuk kelompok pada kelas ini untuk mulai memantau."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" href="{{ route('admin.kelompok', ['kelas' => $kelasId]) }}" wire:navigate>
                        Kelola Kelompok
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->kelompok as $team)
                @php $p = $this->progres->get($team->team_id); @endphp
                <button
                    type="button"
                    wire:key="kartu-{{ $team->team_id }}"
                    wire:click="pilihTim({{ $team->team_id }})"
                    @class([
                        'group rounded-xl border bg-white p-4 text-start shadow-sm shadow-primary-900/5 transition hover:border-primary-300 hover:shadow-md',
                        'border-rose-300 ring-1 ring-rose-200' => $p?->macet,
                        'border-line' => ! $p?->macet,
                    ])
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $team->team_name }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $team->team_members_count }} anggota</p>
                        </div>
                        @if ($p)
                            <x-ui.badge :variant="$p->variant()">{{ $p->label() }}</x-ui.badge>
                        @endif
                    </div>

                    @if ($p)
                        <div class="mt-3">
                            <x-ui.progress-bar :value="$p->kemajuan()" :max="max(1, $p->totalLevel)" />
                            <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
                                <span>{{ $p->levelLulus }} / {{ $p->totalLevel }} level</span>
                                <span>{{ $p->jumlahSubmission }} submission</span>
                            </div>
                        </div>

                        @if ($p->macet)
                            <p class="mt-3 rounded-lg bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700">
                                Macet: {{ $p->alasanMacet }}
                            </p>
                        @else
                            <p class="mt-3 text-xs text-slate-400">
                                Aktivitas: {{ $p->aktivitasTerakhir?->diffForHumans() ?? 'belum ada' }}
                            </p>
                        @endif
                    @endif
                </button>
            @endforeach
        </div>

    {{-- Panel samping: detail kelompok terpilih --}}
    <div
        x-show="panel"
        x-cloak
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
        aria-label="Detail kelompok"
    >
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="panel = false; $wire.tutupPanel()"></div>

        <div
            x-transition:enter="transition duration-200"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            class="fixed inset-y-0 right-0 flex w-full max-w-xl flex-col border-s border-line bg-white shadow-xl"
        >
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Detail Kelompok</p>
                    <p class="truncate text-base font-semibold text-slate-800">
                        {{ $this->timTerpilih?->team_name ?? 'Memuat...' }}
                    </p>
                </div>
                <button
                    type="button"
                    x-on:click="panel = false; $wire.tutupPanel()"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Tutup panel"
                >
                    <x-ui.icon.x />
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                @if ($this->timTerpilih === null)
                    <x-ui.empty-state icon="users-group" title="Tidak ada kelompok dipilih" />
                @else
                    {{-- Anggota --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Anggota & Peran</p>
                        <ul class="space-y-1.5">
                            @forelse ($this->anggota as $siswa)
                                <li class="flex items-center justify-between gap-2 rounded-lg bg-page/70 px-3 py-2 text-sm">
                                    <span class="truncate text-slate-700">{{ $siswa->full_name }}</span>
                                    <span class="flex shrink-0 gap-1">
                                        @forelse ($siswa->roleAssignments as $penugasan)
                                            <x-ui.badge variant="netral">{{ $penugasan->role?->role_name }}</x-ui.badge>
                                        @empty
                                            <span class="text-xs text-slate-400">Belum ada peran</span>
                                        @endforelse
                                    </span>
                                </li>
                            @empty
                                <li class="text-sm text-slate-400">Belum ada anggota.</li>
                            @endforelse
                        </ul>
                    </div>

                    {{-- Rencana & konsep --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Rencana & Kartu Konsep</p>
                        @if ($this->rencanaTerbaru === null)
                            <p class="text-sm text-slate-400">Kelompok belum membuat rencana misi.</p>
                        @else
                            @php
                                $analisis = $this->analisisKonsep;
                                $penganalisis = app(\App\Services\ConceptUsageAnalyzer::class);
                            @endphp
                            <div class="rounded-lg border border-line p-3">
                                <p class="text-sm font-medium text-slate-700">
                                    {{ $this->rencanaTerbaru->mission?->level?->level_name ?? 'Level' }}
                                    <span class="text-xs font-normal text-slate-400">
                                        · {{ $this->rencanaTerbaru->planned_at?->diffForHumans() }}
                                    </span>
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @forelse ($this->rencanaTerbaru->concepts as $konsep)
                                        <x-ui.badge variant="info">{{ $konsep->concept_name }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-slate-400">Tidak ada kartu konsep dipilih.</span>
                                    @endforelse
                                </div>

                                @if ($analisis !== null)
                                    <div class="mt-3 border-t border-line pt-3">
                                        <x-ui.badge :variant="$penganalisis->variant($analisis)">
                                            {{ $penganalisis->label($analisis) }}
                                        </x-ui.badge>

                                        @if (count($analisis['dipilih_tak_dipakai']) > 0)
                                            <p class="mt-2 text-xs text-amber-700">
                                                Dipilih tapi tidak terdeteksi di kode:
                                                <strong>{{ implode(', ', $analisis['dipilih_tak_dipakai']) }}</strong>
                                            </p>
                                        @endif

                                        @if (count($analisis['dipakai_tak_direncanakan']) > 0)
                                            <p class="mt-2 text-xs text-sky-700">
                                                Terdeteksi di kode tapi tidak direncanakan:
                                                <strong>{{ implode(', ', $analisis['dipakai_tak_direncanakan']) }}</strong>
                                            </p>
                                        @endif

                                        <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                                            Perkiraan otomatis dari teks kode (komentar & string diabaikan), bukan
                                            hasil menjalankan program.
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Submission terbaru --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Submission Terbaru</p>
                        @forelse ($this->submissionTerbaru as $submission)
                            <div wire:key="panel-sub-{{ $submission->submission_id }}" class="mb-2 rounded-lg border border-line p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-700">
                                        {{ $submission->mission?->level?->level_name ?? 'Level' }}
                                    </p>
                                    <x-ui.badge :variant="$submission->status === 'menunggu_verifikasi' ? 'netral' : ($submission->all_passed ? 'sukses' : 'peringatan')">
                                        {{ $submission->results->where('is_passed', true)->count() }} /
                                        {{ $submission->results->count() }} lulus
                                    </x-ui.badge>
                                    @if ($submission->status === 'menunggu_verifikasi')
                                        <p class="mt-1 text-[11px] font-medium text-slate-500">Menunggu verifikasi guru</p>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ $submission->submittedBy?->full_name ?? 'Siswa' }} ·
                                    {{ $submission->submitted_at?->diffForHumans() }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Belum ada submission.</p>
                        @endforelse
                    </div>

                    {{-- Percobaan kartu bug --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Percobaan Kartu Bug</p>
                        @forelse ($this->bugTerbaru as $attempt)
                            <div wire:key="panel-bug-{{ $attempt->attempt_id }}" class="mb-2 flex items-center justify-between gap-2 rounded-lg border border-line px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-slate-700">{{ $attempt->bugCard?->bug_title ?? 'Kartu bug' }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $attempt->attempted_at?->diffForHumans() }} ·
                                        {{ $attempt->hints_used }} petunjuk dipakai
                                    </p>
                                </div>
                                <x-ui.badge :variant="$attempt->is_solved ? 'sukses' : 'bahaya'">
                                    {{ $attempt->is_solved ? 'Terpecahkan' : 'Belum' }}
                                </x-ui.badge>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">Belum ada percobaan kartu bug.</p>
                        @endforelse
                    </div>

                @endif
            </div>

            <div class="border-t border-line px-5 py-4">
                <div class="flex items-center justify-between gap-2">
                    <x-ui.button variant="outline" x-on:click="panel = false; $wire.tutupPanel()">Tutup</x-ui.button>
                    @if ($this->timTerpilih !== null)
                        <x-ui.button variant="primary" wire:click="bukaRilis({{ $this->timTerpilih->team_id }})">
                            <x-ui.icon.bug class="size-4" />
                            Rilis Kartu Bug
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @endif

    @include('pages.admin.partials.rilis-bug')

