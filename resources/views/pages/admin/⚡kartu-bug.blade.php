<?php

use App\Concerns\InteractsWithTable;
use App\Livewire\Forms\KartuBugForm;
use App\Models\BugCard;
use App\Models\Level;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Kartu Bug')]
class extends Component
{
    use InteractsWithTable;

    public KartuBugForm $form;

    #[Url(as: 'level', nullable: true)]
    public ?int $filterLevelId = null;

    public ?int $bugId = null;

    public string $pesanKonfirmasi = '';

    /** Apakah kunci perbaikan ditampilkan pada pratinjau. */
    public bool $tampilkanKunci = false;

    private const SORTABLE = ['bug_title', 'level_id', 'points', 'bug_attempts_count'];

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'bug_id';

        return BugCard::query()
            ->with('level')
            ->withCount('bugAttempts')
            ->when($this->filterLevelId !== null, fn ($q) => $q->where('level_id', $this->filterLevelId))
            ->when(filled($this->search), fn ($q) => $q->where('bug_title', 'like', "%{$this->search}%"))
            ->orderBy($sortBy, $this->sortBy ? $this->sortDir : 'asc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
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
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'bug_title',
                'label' => 'Judul Bug',
                'sortable' => true,
                'format' => fn (BugCard $bug) => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600">
                            <x-ui.icon.bug class="size-4" />
                        </span>
                        <span class="font-medium text-slate-800">{{ $judul }}</span>
                    </div>
                    BLADE, ['judul' => $bug->bug_title]),
            ],
            [
                'key' => 'level_id',
                'label' => 'Level',
                'sortable' => true,
                'format' => fn (BugCard $bug) => Blade::render(
                    '<x-ui.badge variant="netral">{{ $n }}</x-ui.badge>',
                    ['n' => $bug->level?->level_name ?? '—'],
                ),
            ],
            [
                'key' => 'points',
                'label' => 'Poin',
                'sortable' => true,
                'class' => 'w-24',
                'format' => fn (BugCard $bug) => Blade::render(
                    '<x-ui.badge variant="peringatan">{{ $n }} poin</x-ui.badge>',
                    ['n' => (int) $bug->points],
                ),
            ],
            [
                'key' => 'bug_attempts_count',
                'label' => 'Percobaan Tim',
                'sortable' => true,
                'format' => fn (BugCard $bug) => Blade::render(
                    '<x-ui.badge variant="info">{{ $n }} percobaan</x-ui.badge>',
                    ['n' => (int) $bug->bug_attempts_count],
                ),
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (BugCard $bug) => Blade::render(<<<'BLADE'
                    <x-ui.dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" aria-label="Aksi">
                                <x-ui.icon.dots-vertical />
                            </button>
                        </x-slot:trigger>

                        <button type="button" wire:click="bukaUbah({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.pencil class="size-4 text-slate-400" />
                            Ubah
                        </button>
                        <button type="button" wire:click="mintaHapus({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-rose-600 transition hover:bg-rose-50">
                            <x-ui.icon.trash class="size-4 text-rose-500" />
                            Hapus
                        </button>
                    </x-ui.dropdown>
                    BLADE, ['id' => $bug->bug_id]),
            ],
        ];
    }

    public function updatedFilterLevelId(): void
    {
        $this->page = 1;
        unset($this->rows);
    }

    public function bukaTambah(): void
    {
        $this->authorize('create', BugCard::class);

        $this->form->resetForm();
        $this->form->level_id = $this->filterLevelId ?? array_key_first($this->opsiLevel);
        $this->tampilkanKunci = false;
        $this->resetValidation();

        $this->dispatch('modal-bug-open');
    }

    public function bukaUbah(int $bugId): void
    {
        $bug = BugCard::findOrFail($bugId);

        $this->authorize('update', $bug);

        $this->form->setKartu($bug);
        $this->tampilkanKunci = false;
        $this->resetValidation();

        $this->dispatch('modal-bug-open');
    }

    public function simpanBug(): void
    {
        $data = $this->form->validate();

        if ($this->form->bugId === null) {
            $this->authorize('create', BugCard::class);

            BugCard::create([
                'level_id' => $data['level_id'],
                'bug_title' => $data['bug_title'],
                'buggy_code' => $data['buggy_code'],
                'hint_text' => $data['hint_text'] ?? null,
                'fix_explanation' => $data['fix_explanation'],
                'points' => $data['points'],
            ]);

            $pesan = 'Kartu bug berhasil ditambahkan.';
        } else {
            $bug = BugCard::findOrFail($this->form->bugId);

            $this->authorize('update', $bug);

            $bug->fill([
                'level_id' => $data['level_id'],
                'bug_title' => $data['bug_title'],
                'buggy_code' => $data['buggy_code'],
                'hint_text' => $data['hint_text'] ?? null,
                'fix_explanation' => $data['fix_explanation'],
                'points' => $data['points'],
            ])->save();

            $pesan = 'Kartu bug berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->rows);

        $this->dispatch('modal-bug-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapus(int $bugId): void
    {
        $bug = BugCard::query()->withCount('bugAttempts')->findOrFail($bugId);

        $this->authorize('delete', $bug);

        if ((int) $bug->bug_attempts_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: "Kartu bug \"{$bug->bug_title}\" sudah punya {$bug->bug_attempts_count} percobaan tim — hapus percobaannya dulu sebelum menghapus kartu.");

            return;
        }

        $this->bugId = $bug->bug_id;
        $this->pesanKonfirmasi = "Hapus kartu bug \"{$bug->bug_title}\"?";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusBug(): void
    {
        if ($this->bugId === null) {
            return;
        }

        $bug = BugCard::query()->withCount('bugAttempts')->findOrFail($this->bugId);

        $this->authorize('delete', $bug);

        if ((int) $bug->bug_attempts_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kartu bug masih punya percobaan tim.');

            return;
        }

        $bug->delete();

        $this->bugId = null;
        $this->page = 1;
        unset($this->rows);

        $this->dispatch('toast', type: 'sukses', message: 'Kartu bug berhasil dihapus.');
    }
}; ?>

<div>
    <x-ui.page-header title="Kartu Bug" description="Kelola kartu bug per level: kode bermasalah, petunjuk, dan kunci perbaikan.">
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="bukaTambah">
                <x-ui.icon.plus class="size-4" />
                Tambah Kartu Bug
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert variant="info" class="mb-4">
        Perubahan materi berlaku untuk semua kelas dan semua guru.
    </x-ui.alert>

    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select
                label="Filter Level"
                name="filterLevelId"
                :options="$this->opsiLevel"
                placeholder="Semua level"
                wire:model.live="filterLevelId"
            />
        </div>
    </x-ui.card>

    <x-ui.data-table
        :rows="$this->rows"
        :columns="$this->columns"
        searchable
        search-placeholder="Cari judul bug..."
        :sort-property="$sortBy"
        :sort-direction="$sortDir"
        empty-title="Belum ada kartu bug"
        empty-message="Tambahkan kartu bug untuk dipakai siswa saat sesi debugging."
    />

    {{-- Modal tambah/ubah kartu bug + pratinjau --}}
    <x-ui.modal name="modal-bug" title="Kartu Bug">
        <div class="grid gap-5 lg:grid-cols-2">
            <form wire:submit="simpanBug" class="space-y-4">
                <x-ui.input label="Judul Bug" name="form.bug_title" wire:model="form.bug_title" placeholder="Contoh: Salah tanda kurang-lebih" required autofocus />

                <x-ui.select label="Level" name="form.level_id" :options="$this->opsiLevel" wire:model="form.level_id" required />

                <x-ui.textarea
                    label="Kode Bermasalah"
                    name="form.buggy_code"
                    rows="8"
                    wire:model="form.buggy_code"
                    class="font-mono text-xs"
                    x-on:keydown.tab.prevent="$event.target.setRangeText('    ', $event.target.selectionStart, $event.target.selectionEnd, 'end')"
                    help="Tekan Tab untuk indentasi 4 spasi. Ditampilkan sebagai teks (di-escape), bukan HTML."
                    required
                />

                <x-ui.textarea
                    label="Teks Petunjuk"
                    name="form.hint_text"
                    rows="3"
                    wire:model="form.hint_text"
                    placeholder="Petunjuk bertahap yang bisa dibuka siswa."
                />

                <x-ui.textarea
                    label="Penjelasan Perbaikan"
                    name="form.fix_explanation"
                    rows="4"
                    wire:model="form.fix_explanation"
                    placeholder="Penjelasan kunci perbaikan (disembunyikan di balik tombol)."
                    required
                />

                <x-ui.input
                    label="Poin"
                    name="form.points"
                    type="number"
                    min="1"
                    max="100"
                    wire:model="form.points"
                    help="Antara 1 sampai 100. Default 10."
                    required
                />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" x-on:click="$dispatch('modal-bug-close')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit">Simpan Kartu Bug</x-ui.button>
                </div>
            </form>

            {{-- Pratinjau Kartu Bug --}}
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pratinjau Kartu Bug</p>

                <div class="overflow-hidden rounded-2xl border-2 border-rose-200 bg-white shadow-lg shadow-rose-900/10">
                    <div class="flex items-center justify-between gap-3 bg-rose-600 px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-rose-100">Kartu Bug</p>
                            <p class="mt-1 truncate text-lg font-bold text-white">{{ $form->bug_title !== '' ? $form->bug_title : 'Judul Bug' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-white/20 px-3 py-1 text-xs font-bold text-white">{{ $form->points }} poin</span>
                    </div>

                    <div class="space-y-4 p-5 text-sm">
                        <div>
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kode Bermasalah</p>
                            <div class="overflow-hidden rounded-lg bg-slate-900">
                                <table class="w-full font-mono text-xs">
                                    <tbody>
                                        @foreach (preg_split('/\r\n|\r|\n/', $form->buggy_code !== '' ? $form->buggy_code : ' ') ?: [] as $i => $barisKode)
                                            <tr>
                                                <td class="w-8 select-none border-e border-slate-700 px-2 py-0.5 text-end text-slate-500">{{ $i + 1 }}</td>
                                                <td class="whitespace-pre px-3 py-0.5 text-slate-100">{{ $barisKode }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if (filled($form->hint_text))
                            <details class="rounded-lg border border-line">
                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-slate-600 transition hover:text-primary-700">
                                    Petunjuk (klik untuk buka)
                                </summary>
                                <p class="whitespace-pre-line border-t border-line px-3 py-2 text-slate-600">{{ $form->hint_text }}</p>
                            </details>
                        @endif

                        <div>
                            @if ($tampilkanKunci)
                                <div class="rounded-lg border border-primary-200 bg-primary-50 p-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">Kunci Perbaikan</p>
                                    <p class="mt-1 whitespace-pre-line text-primary-900">{{ $form->fix_explanation !== '' ? $form->fix_explanation : '—' }}</p>
                                </div>
                            @else
                                <x-ui.button variant="outline" size="sm" wire:click="$toggle('tampilkanKunci')">
                                    <x-ui.icon.eye class="size-4" />
                                    Tampilkan kunci
                                </x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusBug"
        title="Hapus Kartu Bug"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />
</div>
</div>
