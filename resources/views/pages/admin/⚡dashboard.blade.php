<?php

use App\Models\BugAttempt;
use App\Models\Level;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
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

new #[Layout('layouts.admin'), Title('Dashboard')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->kelasTerpilih();
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

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();

        unset($this->progres, $this->ringkasan, $this->aktivitasTerbaru, $this->kelompok);
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

    /**
     * @return array<string, int|float>
     */
    #[Computed]
    public function ringkasan(): array
    {
        $progres = $this->progres;
        $teamIds = $this->kelompok->pluck('team_id')->all();

        return [
            'aktif' => $progres->filter(fn (TeamProgress $p) => $p->sudahMulai())->count(),
            'total_kelompok' => $this->kelompok->count(),
            'rata_level' => $progres->isEmpty() ? 0.0 : round((float) $progres->avg('levelLulus'), 1),
            'macet' => $progres->filter(fn (TeamProgress $p) => $p->macet)->count(),
            'bug_terbuka' => BugAttempt::query()
                ->whereIn('team_id', $teamIds)
                ->where('is_solved', false)
                ->count(),
            'submission_hari_ini' => Submission::query()
                ->whereIn('team_id', $teamIds)
                ->whereDate('submitted_at', today())
                ->count(),
        ];
    }

    /**
     * @return Collection<int, Submission>
     */
    #[Computed]
    public function aktivitasTerbaru(): Collection
    {
        $teamIds = $this->kelompok->pluck('team_id')->all();

        if ($teamIds === []) {
            return collect();
        }

        return Submission::query()
            ->whereIn('team_id', $teamIds)
            ->with(['team', 'mission.level', 'submittedBy'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->limit(8)
            ->get();
    }

    /**
     * Perbandingan rencana (kartu konsep dipilih) vs kode acuan tiap kelompok.
     *
     * Kunci = team_id, nilai = peta hasil dari ConceptUsageAnalyzer
     * (status, dipilih_tak_dipakai, dipakai_tak_direncanakan, terdeteksi).
     *
     * @return array<int, array{status: string, dipilih_tak_dipakai: array<int, string>, dipakai_tak_direncanakan: array<int, string>, terdeteksi: array<int, string>}>
     */
    #[Computed]
    public function rencanaVsKode(): array
    {
        $teamIds = $this->kelompok->pluck('team_id')->all();

        if ($teamIds === []) {
            return [];
        }

        // Rencana terbaru per tim (beserta kartu konsep yang dipilih).
        $rencanaPerTim = MissionPlan::query()
            ->whereIn('team_id', $teamIds)
            ->with('concepts')
            ->orderBy('planned_at')
            ->orderBy('plan_id')
            ->get()
            ->groupBy('team_id')
            ->map(fn (Collection $grup) => $grup->last());

        // Kode submission acuan per tim (mengikuti config reference_submission).
        $kodePerTim = $this->kodeAcuanPerTim($teamIds);

        $penganalisis = app(ConceptUsageAnalyzer::class);
        $hasil = [];

        foreach ($teamIds as $teamId) {
            $id = (int) $teamId;

            $hasil[$id] = $penganalisis->analisisRencana(
                $rencanaPerTim->get($id),
                $kodePerTim[$id] ?? null,
            );
        }

        return $hasil;
    }

    /**
     * Kode submission acuan per tim (satu query untuk semua tim).
     *
     * @param  array<int, int>  $teamIds
     * @return array<int, string|null>
     */
    private function kodeAcuanPerTim(array $teamIds): array
    {
        $semua = Submission::query()
            ->whereIn('team_id', $teamIds)
            ->orderBy('submitted_at')
            ->orderBy('submission_id')
            ->get(['submission_id', 'team_id', 'code_text'])
            ->groupBy('team_id');

        $pertama = (string) config('teater.reference_submission', 'last') === 'first';

        $hasil = [];

        foreach ($semua as $teamId => $grup) {
            $submission = $pertama ? $grup->first() : $grup->last();

            $hasil[(int) $teamId] = $submission?->code_text;
        }

        return $hasil;
    }
}; ?>

<div>
    <x-ui.page-header
        title="Dashboard"
        description="Ringkasan aktivitas kelas & kelompok di seluruh level permainan."
    >
        <x-slot:actions>
            @if (count($this->opsiKelas) > 0)
                <div class="w-full sm:w-64">
                    <x-ui.select
                        name="kelasId"
                        wire:model.live="kelasId"
                        :options="$this->opsiKelas"
                        placeholder="Pilih kelas"
                    />
                </div>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk melihat ringkasan aktivitas."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" href="{{ route('admin.kelas') }}" wire:navigate>
                        <x-ui.icon.plus class="size-4" />
                        Buat Kelas
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card
                label="Kelompok Aktif"
                :value="$this->ringkasan['aktif'].' / '.$this->ringkasan['total_kelompok']"
                icon="users-group"
                hint="Sudah merencanakan atau mengirim kode"
            />
            <x-ui.stat-card
                label="Rata-rata Level Tim"
                :value="$this->ringkasan['rata_level']"
                icon="stairs"
                :hint="'Dari '.$this->totalLevel.' level'"
            />
            <x-ui.stat-card
                label="Kartu Bug Terbuka"
                :value="$this->ringkasan['bug_terbuka']"
                icon="bug"
                :hint="$this->ringkasan['macet'] > 0
                    ? $this->ringkasan['macet'].' kelompok perlu dibantu'
                    : 'Tidak ada kelompok macet'"
            />
            <x-ui.stat-card
                label="Submission Hari Ini"
                :value="$this->ringkasan['submission_hari_ini']"
                icon="code"
                :hint="$this->aktivitasTerbaru->first()?->submitted_at?->diffForHumans() ?? 'Belum ada submission'"
            />

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Tabel progres kelompok (2/3 lebar di desktop) --}}
            <x-ui.card :padding="false" class="min-w-0 lg:col-span-2">
                <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">Progres Kelompok</p>
                    <x-ui.button variant="ghost" size="sm" href="{{ route('admin.pantau-live', ['kelas' => $kelasId]) }}" wire:navigate>
                        Pantau Live
                        <x-ui.icon.chevron-right class="size-4" />
                    </x-ui.button>
                </div>

                @if ($this->kelompok->isEmpty())
                    <x-ui.empty-state
                        icon="users-group"
                        title="Belum ada kelompok"
                        message="Bentuk kelompok pada kelas ini untuk mulai memantau progres."
                    >
                        <x-slot:actions>
                            <x-ui.button variant="primary" href="{{ route('admin.kelompok', ['kelas' => $kelasId]) }}" wire:navigate>
                                Kelola Kelompok
                            </x-ui.button>
                        </x-slot:actions>
                    </x-ui.empty-state>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-line text-sm">
                            <thead class="bg-page/60">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Rencana vs Kode</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Level</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Aktivitas Terakhir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-white">
                                @foreach ($this->kelompok as $team)
                                    @php $p = $this->progres->get($team->team_id); @endphp
                                    <tr wire:key="tim-{{ $team->team_id }}" class="transition hover:bg-page/70">
                                        <td class="px-4 py-3 align-middle font-medium text-slate-800">{{ $team->team_name }}</td>
                                        <td class="px-4 py-3 align-middle">
                                            @if ($p)
                                                <x-ui.badge :variant="$p->variant()">{{ $p->label() }}</x-ui.badge>
                                                @if ($p->macet)
                                                    <p class="mt-1 text-xs text-rose-600">{{ $p->alasanMacet }}</p>
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="w-32 px-4 py-3 align-middle">
                                            @if ($p)
                                                <x-ui.progress-bar :value="$p->kemajuan()" :max="max(1, $p->totalLevel)" />
                                                <p class="mt-1 text-xs text-slate-400">{{ $p->levelLulus }} / {{ $p->totalLevel }} level</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-middle">
                                            @php
                                                $rvk = $this->rencanaVsKode[$team->team_id] ?? null;
                                                $penganalisis = app(ConceptUsageAnalyzer::class);
                                            @endphp
                                            @if ($rvk !== null)
                                                <x-ui.badge :variant="$penganalisis->variant($rvk)">
                                                    {{ $penganalisis->label($rvk) }}
                                                </x-ui.badge>
                                                @if (count($rvk['dipilih_tak_dipakai']) > 0)
                                                    <p class="mt-1 text-xs text-amber-700">
                                                        Tak dipakai: {{ implode(', ', $rvk['dipilih_tak_dipakai']) }}
                                                    </p>
                                                @endif
                                                @if (count($rvk['dipakai_tak_direncanakan']) > 0)
                                                    <p class="mt-1 text-xs text-sky-700">
                                                        Tak direncanakan: {{ implode(', ', $rvk['dipakai_tak_direncanakan']) }}
                                                    </p>
                                                @endif
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 align-middle text-xs text-slate-500">
                                            {{ $p?->aktivitasTerakhir?->diffForHumans() ?? 'Belum ada aktivitas' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>

            {{-- Aktivitas terbaru (1/3 lebar di desktop, bertumpuk di layar kecil) --}}
            <x-ui.card :padding="false" class="min-w-0 lg:col-span-1">
                <div class="border-b border-line px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">Aktivitas Terbaru</p>
                </div>

                @if ($this->aktivitasTerbaru->isEmpty())
                    <x-ui.empty-state icon="history" title="Belum ada submission" message="Aktivitas kelompok akan tampil di sini." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($this->aktivitasTerbaru as $submission)
                            <li wire:key="sub-{{ $submission->submission_id }}" class="px-4 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ $submission->team?->team_name }}</p>
                                        <p class="truncate text-xs text-slate-500">
                                            {{ $submission->mission?->level?->level_name ?? 'Level' }} ·
                                            {{ $submission->submittedBy?->full_name ?? 'Siswa' }}
                                        </p>
                                    </div>
                                    <x-ui.badge :variant="$submission->all_passed ? 'sukses' : 'peringatan'">
                                        {{ $submission->all_passed ? 'Lulus' : 'Gagal' }}
                                    </x-ui.badge>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ $submission->submitted_at?->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
