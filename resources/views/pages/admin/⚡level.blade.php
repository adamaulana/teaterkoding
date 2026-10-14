<?php

use App\Concerns\InteractsWithTable;
use App\Livewire\Forms\LevelForm;
use App\Models\Level;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Level')]
class extends Component
{
    use InteractsWithTable;

    public LevelForm $form;

    public ?int $levelId = null;

    public string $pesanKonfirmasi = '';

    private const SORTABLE = ['level_number', 'level_name', 'missions_count', 'bug_cards_count'];

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'level_number';

        return Level::query()
            ->withCount(['missions', 'bugCards'])
            ->when(filled($this->search), fn ($query) => $query
                ->where('level_name', 'like', "%{$this->search}%"))
            ->orderBy($sortBy, $this->sortBy ? $this->sortDir : 'asc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
    }

    /**
     * Nomor level berikutnya (otomatis saat menambah level baru).
     */
    #[Computed]
    public function nomorBerikutnya(): int
    {
        return ((int) Level::query()->max('level_number')) + 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'level_number',
                'label' => 'Nomor',
                'sortable' => true,
                'class' => 'w-20',
                'format' => fn (Level $level) => Blade::render(
                    '<x-ui.badge variant="netral">{{ $n }}</x-ui.badge>',
                    ['n' => $level->level_number],
                ),
            ],
            [
                'key' => 'level_name',
                'label' => 'Nama Level',
                'sortable' => true,
                'format' => fn (Level $level) => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600">
                            <x-ui.icon.stairs class="size-4" />
                        </span>
                        <span class="font-medium text-slate-800">{{ $nama }}</span>
                    </div>
                    BLADE, ['nama' => $level->level_name]),
            ],
            [
                'key' => 'is_summative',
                'label' => 'Penanda',
                'class' => 'w-32',
                'format' => fn (Level $level) => $level->is_summative
                    ? Blade::render('<x-ui.badge variant="peringatan">Sumatif</x-ui.badge>')
                    : Blade::render('<x-ui.badge variant="netral">Formatif</x-ui.badge>'),
            ],
            [
                'key' => 'missions_count',
                'label' => 'Jumlah Misi',
                'sortable' => true,
                'format' => fn (Level $level) => Blade::render(
                    '<x-ui.badge variant="info">{{ $n }} misi</x-ui.badge>',
                    ['n' => (int) $level->missions_count],
                ),
            ],
            [
                'key' => 'bug_cards_count',
                'label' => 'Jumlah Kartu Bug',
                'sortable' => true,
                'format' => fn (Level $level) => Blade::render(
                    '<x-ui.badge variant="sukses">{{ $n }} kartu</x-ui.badge>',
                    ['n' => (int) $level->bug_cards_count],
                ),
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (Level $level) => Blade::render(<<<'BLADE'
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
                    BLADE, ['id' => $level->level_id]),
            ],
        ];
    }

    public function bukaTambah(): void
    {
        $this->authorize('create', Level::class);

        $this->form->resetForm();
        $this->resetValidation();

        $this->dispatch('modal-level-open');
    }

    public function bukaUbah(int $levelId): void
    {
        $level = Level::findOrFail($levelId);

        $this->authorize('update', $level);

        $this->form->setLevel($level);
        $this->resetValidation();

        $this->dispatch('modal-level-open');
    }

    public function simpanLevel(): void
    {
        $data = $this->form->validate();

        if ($this->form->levelId === null) {
            $this->authorize('create', Level::class);

            Level::create([
                'level_number' => $this->nomorBerikutnya,
                'level_name' => $data['level_name'],
                'is_summative' => (bool) $data['is_summative'],
            ]);

            $pesan = 'Level baru berhasil ditambahkan.';
        } else {
            $level = Level::findOrFail($this->form->levelId);

            $this->authorize('update', $level);

            // Nomor level tidak dapat diubah.
            $level->fill([
                'level_name' => $data['level_name'],
                'is_summative' => (bool) $data['is_summative'],
            ])->save();

            $pesan = 'Level berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->rows, $this->nomorBerikutnya);

        $this->dispatch('modal-level-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapus(int $levelId): void
    {
        $level = Level::query()->withCount(['missions', 'bugCards'])->findOrFail($levelId);

        $this->authorize('delete', $level);

        $jumlahMisi = (int) $level->missions_count;
        $jumlahKartu = (int) $level->bug_cards_count;

        if ($jumlahMisi > 0 || $jumlahKartu > 0) {
            $this->dispatch('toast', type: 'bahaya', message: "Level \"{$level->level_name}\" masih memiliki {$jumlahMisi} misi dan {$jumlahKartu} kartu bug — hapus isinya dulu sebelum menghapus level.");

            return;
        }

        $this->levelId = $level->level_id;
        $this->pesanKonfirmasi = "Hapus level \"{$level->level_name}\"? Tindakan ini tidak bisa dibatalkan.";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusLevel(): void
    {
        if ($this->levelId === null) {
            return;
        }

        $level = Level::query()->withCount(['missions', 'bugCards'])->findOrFail($this->levelId);

        $this->authorize('delete', $level);

        if ((int) $level->missions_count > 0 || (int) $level->bug_cards_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Level masih memiliki misi atau kartu bug.');

            return;
        }

        $level->delete();

        $this->levelId = null;
        $this->page = 1;
        unset($this->rows, $this->nomorBerikutnya);

        $this->dispatch('toast', type: 'sukses', message: 'Level berhasil dihapus.');
    }
}; ?>

<div>
    <x-ui.page-header title="Level" description="Kelola level permainan, penanda sumatif, dan isi misi/kartu bug per level.">
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="bukaTambah">
                <x-ui.icon.plus class="size-4" />
                Tambah Level
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert variant="info" class="mb-4">
        Perubahan materi berlaku untuk semua kelas dan semua guru.
    </x-ui.alert>

    <x-ui.data-table
        :rows="$this->rows"
        :columns="$this->columns"
        searchable
        search-placeholder="Cari nama level..."
        :sort-property="$sortBy"
        :sort-direction="$sortDir"
        empty-title="Belum ada level"
        empty-message="Tambahkan level permainan untuk mulai menyusun misi dan kartu bug."
    />

    <x-ui.modal name="modal-level" title="Level">
        <form wire:submit="simpanLevel" class="space-y-4">
            @if ($form->levelId === null)
                <x-ui.alert variant="info">
                    Level baru akan bernomor <strong>{{ $this->nomorBerikutnya }}</strong> (otomatis).
                </x-ui.alert>
            @else
                <x-ui.input
                    label="Nomor Level"
                    name="nomor"
                    :value="$form->levelId !== null ? \App\Models\Level::find($form->levelId)?->level_number : ''"
                    disabled
                    help="Nomor level tidak dapat diubah."
                />
            @endif

            <x-ui.input label="Nama Level" name="form.level_name" wire:model="form.level_name" placeholder="Contoh: Level 1 — Pengenalan Variabel" required autofocus />

            <x-ui.checkbox
                label="Tandai sebagai level sumatif"
                name="form.is_summative"
                wire:model="form.is_summative"
                help="Level sumatif membuka tab Kepemilikan Fungsi pada misinya."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-level-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Level</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusLevel"
        title="Hapus Level"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />
</div>
