<?php

use App\Concerns\InteractsWithTable;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\Team;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Riwayat Submission — daftar kode yang dikirim setiap kelompok.
 *
 * Kode ditampilkan dalam blok <pre> sehingga otomatis di-escape Blade
 * (tidak ada {!! !!} di halaman ini). Tombol Sebelumnya/Berikutnya
 * memindahkan submission terpilih pada daftar yang sedang tampil.
 */
new #[Layout('layouts.admin'), Title('Riwayat Submission')]
class extends Component
{
    use InteractsWithTable;

    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    #[Url(as: 'level', nullable: true)]
    public ?int $levelId = null;

    #[Url(as: 'tim', nullable: true)]
    public ?int $teamId = null;

    /** Submission yang sedang dibuka (null = tampilan daftar). */
    #[Url(as: 'submission', nullable: true)]
    public ?int $submissionId = null;

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->kelasTerpilih();

        if ($this->submissionId !== null) {
            $this->pastikanSubmissionTerlihat();
        }
    }

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
     * Submission yang dibuka wajib berada di kelas yang boleh dilihat.
     */
    private function pastikanSubmissionTerlihat(): Submission
    {
        $submission = Submission::query()
            ->with('team.schoolClass')
            ->findOrFail($this->submissionId);

        abort_unless(auth()->user()->can('view', $submission->team->schoolClass), 403);

        return $submission;
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();
        $this->teamId = null;
        $this->submissionId = null;
        $this->page = 1;

        unset($this->rows, $this->opsiTim, $this->submissionTerpilih, $this->navigasi);
    }

    public function updatedLevelId(): void
    {
        $this->submissionId = null;
        $this->page = 1;

        unset($this->rows, $this->submissionTerpilih, $this->navigasi);
    }

    public function updatedTeamId(): void
    {
        $this->submissionId = null;
        $this->page = 1;

        unset($this->rows, $this->submissionTerpilih, $this->navigasi);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->submissionId = null;

        unset($this->rows, $this->submissionTerpilih, $this->navigasi);
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

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiLevel(): array
    {
        return Level::query()
            ->orderBy('level_number')
            ->pluck('level_name', 'level_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiTim(): array
    {
        if ($this->kelasId === null) {
            return [];
        }

        return Team::query()
            ->where('class_id', $this->kelasId)
            ->orderBy('team_name')
            ->pluck('team_name', 'team_id')
            ->all();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        if ($this->kelasId === null) {
            return new LengthAwarePaginator([], 0, max(1, $this->perPage));
        }

        $teamIds = Team::query()->where('class_id', $this->kelasId)->pluck('team_id')->all();

        return Submission::query()
            ->whereIn('team_id', $teamIds)
            ->when($this->teamId !== null, fn ($q) => $q->where('team_id', $this->teamId))
            ->when($this->levelId !== null, fn ($q) => $q->whereIn(
                'mission_id',
                Mission::query()->where('level_id', $this->levelId)->pluck('mission_id')->all(),
            ))
            ->when(filled($this->search), fn ($q) => $q->where('code_text', 'like', '%'.$this->search.'%'))
            ->with(['team', 'mission.level', 'submittedBy'])
            ->withCount('results')
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    #[Computed]
    public function submissionTerpilih(): ?Submission
    {
        if ($this->submissionId === null) {
            return null;
        }

        return Submission::query()
            ->with(['team', 'mission.level', 'submittedBy', 'results.testCase'])
            ->find($this->submissionId);
    }

    /**
     * Submission sebelumnya/berikutnya pada daftar yang sedang tampil.
     *
     * @return array{sebelumnya: int|null, berikutnya: int|null}
     */
    #[Computed]
    public function navigasi(): array
    {
        $ids = $this->rows->getCollection()
            ->pluck('submission_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $posisi = array_search($this->submissionId, $ids, true);

        return [
            'sebelumnya' => $posisi !== false && $posisi > 0 ? $ids[$posisi - 1] : null,
            'berikutnya' => $posisi !== false && $posisi < count($ids) - 1 ? $ids[$posisi + 1] : null,
        ];
    }

    public function bukaSubmission(int $submissionId): void
    {
        $this->submissionId = $submissionId;

        $this->pastikanSubmissionTerlihat();
    }

    public function tutupSubmission(): void
    {
        $this->submissionId = null;
    }

    /**
     * Jumlah kasus uji yang lulus pada satu submission.
     */
    public function jumlahLulus(Submission $submission): int
    {
        return $submission->results->where('is_passed', true)->count();
    }

    /**
     * Definisi kolom tabel riwayat (format sel berupa closure — bukan query).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'submitted_at',
                'label' => 'Waktu',
                'format' => fn (Submission $row) => $row->submitted_at?->format('d M Y H:i') ?? '-',
            ],
            [
                'key' => 'tim',
                'label' => 'Kelompok',
                'format' => fn (Submission $row) => Blade::render(
                    '<span class="font-medium text-slate-800">{{ $nama }}</span>',
                    ['nama' => $row->team?->team_name ?? '-'],
                ),
            ],
            [
                'key' => 'level',
                'label' => 'Level',
                'format' => fn (Submission $row) => $row->mission?->level?->level_name ?? '-',
            ],
            [
                'key' => 'pengirim',
                'label' => 'Pengirim',
                'format' => fn (Submission $row) => $row->submittedBy?->full_name ?? '-',
            ],
            [
                'key' => 'hasil',
                'label' => 'Hasil',
                'format' => fn (Submission $row) => Blade::render(
                    '<x-ui.badge :variant="$variant">{{ $label }}</x-ui.badge>',
                    [
                        'variant' => $row->all_passed ? 'sukses' : 'peringatan',
                        'label' => $row->all_passed ? 'Semua lulus' : 'Sebagian gagal',
                    ],
                ),
            ],
            [
                'key' => 'aksi',
                'label' => '',
                'class' => 'w-32 text-end',
                'format' => fn (Submission $row) => Blade::render(
                    '<x-ui.button variant="outline" size="sm" wire:click="bukaSubmission({{ $id }})">Lihat Kode</x-ui.button>',
                    ['id' => $row->submission_id],
                ),
            ],
        ];
    }
}; ?>

<div>
    <x-ui.page-header
        title="Riwayat Submission"
        description="Telusuri kode yang dikirim setiap kelompok, lengkap dengan hasil kasus uji."
    >
        <x-slot:actions>
            <x-ui.button variant="outline" href="{{ route('admin.pantau-live', ['kelas' => $kelasId]) }}" wire:navigate>
                <x-ui.icon.broadcast class="size-4" />
                Pantau Live
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select
                label="Kelas"
                name="kelasId"
                wire:model.live="kelasId"
                :options="$this->opsiKelas"
                placeholder="Pilih kelas"
            />
            <x-ui.select
                label="Level"
                name="levelId"
                wire:model.live="levelId"
                :options="$this->opsiLevel"
                placeholder="Semua level"
            />
            <x-ui.select
                label="Kelompok"
                name="teamId"
                wire:model.live="teamId"
                :options="$this->opsiTim"
                placeholder="Semua kelompok"
            />
        </div>
    </x-ui.card>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk melihat riwayat submission."
            />
        </x-ui.card>
    @else
        <x-ui.data-table
            :rows="$this->rows"
            :columns="$this->columns"
            searchable
            search-placeholder="Cari isi kode..."
            empty-title="Belum ada submission"
            empty-message="Riwayat kode dari kelompok akan tampil di sini."
        />
    @endif

    {{-- Panel detail kode submission --}}
    @if ($this->submissionTerpilih !== null)
        @php
            $sub = $this->submissionTerpilih;
            $nav = $this->navigasi;
        @endphp
        <div
            x-data="{ open: true }"
            x-cloak
            class="fixed inset-0 z-50"
            role="dialog"
            aria-modal="true"
            aria-label="Detail submission"
        >
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="tutupSubmission"></div>

            <div class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4">
                <div class="my-auto w-full max-w-3xl rounded-xl border border-line bg-white shadow-xl">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Detail Submission</p>
                            <p class="truncate text-base font-semibold text-slate-800">
                                {{ $sub->team?->team_name }} · {{ $sub->mission?->level?->level_name }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $sub->submittedBy?->full_name }} · {{ $sub->submitted_at?->format('d M Y H:i') }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="tutupSubmission"
                            class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Tutup"
                        >
                            <x-ui.icon.x />
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-5">
                        {{-- Kode siswa (otomatis di-escape oleh Blade) --}}
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Kode Terkirim</p>
                            <pre class="max-h-80 overflow-auto rounded-lg border border-line bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800">{{ $sub->code_text }}</pre>
                        </div>

                        {{-- Hasil kasus uji --}}
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Hasil Kasus Uji</p>
                            <ul class="space-y-1.5">
                                @forelse ($sub->results as $hasil)
                                    <li class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm">
                                        <span class="min-w-0 truncate text-slate-700">
                                            {{ $hasil->testCase?->label ?? 'Kasus uji' }}
                                        </span>
                                        <span class="flex shrink-0 items-center gap-2">
                                            @if (filled($hasil->actual_value))
                                                <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{{ $hasil->actual_value }}</code>
                                            @endif
                                            <x-ui.badge :variant="$hasil->is_passed ? 'sukses' : 'bahaya'">
                                                {{ $hasil->is_passed ? 'Lulus' : 'Gagal' }}
                                            </x-ui.badge>
                                        </span>
                                    </li>
                                @empty
                                    <li class="text-sm text-slate-400">Belum ada hasil kasus uji.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-line px-5 py-4">
                        <div class="flex items-center gap-2">
                            <x-ui.button
                                variant="outline"
                                size="sm"
                                wire:click="bukaSubmission({{ $nav['sebelumnya'] ?? 0 }})"
                                @disabled($nav['sebelumnya'] === null)
                            >
                                <x-ui.icon.chevron-left class="size-4" />
                                Sebelumnya
                            </x-ui.button>
                            <x-ui.button
                                variant="outline"
                                size="sm"
                                wire:click="bukaSubmission({{ $nav['berikutnya'] ?? 0 }})"
                                @disabled($nav['berikutnya'] === null)
                            >
                                Berikutnya
                                <x-ui.icon.chevron-right class="size-4" />
                            </x-ui.button>
                        </div>

                        <x-ui.button variant="primary" size="sm" wire:click="tutupSubmission">Tutup</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
