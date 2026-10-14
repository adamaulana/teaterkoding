<?php

use App\Concerns\InteractsWithTable;
use App\Livewire\Forms\KelasForm;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Kelas')]
class extends Component
{
    use InteractsWithTable;

    public KelasForm $form;

    /** Kelas yang sedang dipilih untuk aksi hapus. */
    public ?int $kelasId = null;

    public string $pesanKonfirmasi = '';

    /** Kolom yang boleh dipakai untuk pengurutan (cegah injeksi SQL). */
    private const SORTABLE = ['class_name', 'school_year', 'students_count', 'teams_count', 'class_id'];

    /**
     * Daftar kelas yang terlihat oleh pengguna.
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'class_id';

        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->withCount(['students', 'teams'])
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($sub) => $sub
                    ->where('class_name', 'like', "%{$this->search}%")
                    ->orWhere('school_year', 'like', "%{$this->search}%")))
            ->orderBy($sortBy, $this->sortBy ? $this->sortDir : 'desc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
    }

    /**
     * Daftar pengguna untuk dropdown pemilik (khusus admin).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opsiPemilik(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
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
                'key' => 'class_name',
                'label' => 'Nama Kelas',
                'sortable' => true,
                'format' => fn (SchoolClass $class) => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600">
                            <x-ui.icon.school class="size-4" />
                        </span>
                        <span class="font-medium text-slate-800">{{ $nama }}</span>
                    </div>
                    BLADE, ['nama' => $class->class_name]),
            ],
            [
                'key' => 'school_year',
                'label' => 'Tahun Ajaran',
                'sortable' => true,
                'format' => fn (SchoolClass $class) => e($class->school_year),
            ],
            [
                'key' => 'students_count',
                'label' => 'Jumlah Siswa',
                'sortable' => true,
                'format' => fn (SchoolClass $class) => Blade::render(
                    '<x-ui.badge variant="info">{{ $n }} siswa</x-ui.badge>',
                    ['n' => (int) $class->students_count],
                ),
            ],
            [
                'key' => 'teams_count',
                'label' => 'Jumlah Kelompok',
                'sortable' => true,
                'format' => fn (SchoolClass $class) => Blade::render(
                    '<x-ui.badge variant="sukses">{{ $n }} kelompok</x-ui.badge>',
                    ['n' => (int) $class->teams_count],
                ),
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (SchoolClass $class) => Blade::render(<<<'BLADE'
                    <x-ui.dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" aria-label="Aksi">
                                <x-ui.icon.dots-vertical />
                            </button>
                        </x-slot:trigger>

                        <a href="{{ $siswaUrl }}" wire:navigate class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.users class="size-4 text-slate-400" />
                            Kelola Siswa
                        </a>
                        <a href="{{ $kelompokUrl }}" wire:navigate class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.users-group class="size-4 text-slate-400" />
                            Kelola Kelompok
                        </a>

                        <div class="my-1 border-t border-line"></div>

                        <button type="button" wire:click="bukaUbah({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.pencil class="size-4 text-slate-400" />
                            Ubah
                        </button>
                        <button type="button" wire:click="mintaHapus({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-rose-600 transition hover:bg-rose-50">
                            <x-ui.icon.trash class="size-4 text-rose-500" />
                            Hapus
                        </button>
                    </x-ui.dropdown>
                    BLADE, [
                        'id' => $class->class_id,
                        'siswaUrl' => route('admin.siswa', ['kelas' => $class->class_id]),
                        'kelompokUrl' => route('admin.kelompok', ['kelas' => $class->class_id]),
                    ]),
            ],
        ];
    }
    public function bukaTambah(): void
    {
        $this->authorize('create', SchoolClass::class);

        $this->form->resetForm();
        $this->resetValidation();

        $this->dispatch('modal-kelas-open');
    }

    public function bukaUbah(int $kelasId): void
    {
        $kelas = SchoolClass::findOrFail($kelasId);

        $this->authorize('update', $kelas);

        $this->form->setKelas($kelas);
        $this->resetValidation();

        $this->dispatch('modal-kelas-open');
    }

    public function simpanKelas(): void
    {
        $data = $this->form->validate();

        if ($this->form->classId === null) {
            $this->authorize('create', SchoolClass::class);
        } else {
            $this->authorize('update', SchoolClass::findOrFail($this->form->classId));
        }

        // Guru otomatis menjadi pemilik; admin boleh memilih atau memakai dirinya.
        $pemilik = auth()->user()->isAdmin()
            ? ($data['user_id'] ?? auth()->id())
            : auth()->id();

        if ($this->form->classId === null) {
            SchoolClass::create([
                'class_name' => $data['class_name'],
                'school_year' => $data['school_year'],
                'user_id' => $pemilik,
            ]);

            $pesan = 'Kelas berhasil ditambahkan.';
        } else {
            $kelas = SchoolClass::findOrFail($this->form->classId);
            $kelas->fill([
                'class_name' => $data['class_name'],
                'school_year' => $data['school_year'],
                'user_id' => $pemilik,
            ])->save();

            $pesan = 'Kelas berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->rows);

        $this->dispatch('modal-kelas-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapus(int $kelasId): void
    {
        $kelas = SchoolClass::query()->withCount(['students', 'teams'])->findOrFail($kelasId);

        $this->authorize('delete', $kelas);

        $this->kelasId = $kelas->class_id;

        $jumlahSiswa = (int) $kelas->students_count;
        $jumlahKelompok = (int) $kelas->teams_count;

        if ($jumlahSiswa > 0 || $jumlahKelompok > 0) {
            $this->dispatch('toast', type: 'bahaya', message: "Kelas \"{$kelas->class_name}\" masih memiliki {$jumlahSiswa} siswa dan {$jumlahKelompok} kelompok — kosongkan dulu sebelum menghapus.");

            return;
        }

        $this->pesanKonfirmasi = "Hapus kelas \"{$kelas->class_name}\"? Tindakan ini tidak bisa dibatalkan.";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusKelas(): void
    {
        if ($this->kelasId === null) {
            return;
        }

        $kelas = SchoolClass::query()->withCount(['students', 'teams'])->findOrFail($this->kelasId);

        $this->authorize('delete', $kelas);

        if ((int) $kelas->students_count > 0 || (int) $kelas->teams_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kelas masih memiliki siswa atau kelompok — kosongkan dulu sebelum menghapus.');

            return;
        }

        $kelas->delete();

        $this->kelasId = null;
        $this->page = 1;
        unset($this->rows);

        $this->dispatch('toast', type: 'sukses', message: 'Kelas berhasil dihapus.');
    }
}; ?>

<div>
    <x-ui.page-header title="Kelas" description="Kelola kelas, tahun ajaran, serta jumlah siswa dan kelompok di dalamnya.">
        <x-slot:actions>
            <x-ui.button variant="primary" wire:click="bukaTambah">
                <x-ui.icon.plus class="size-4" />
                Tambah Kelas
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :rows="$this->rows"
        :columns="$this->columns"
        searchable
        search-placeholder="Cari nama kelas atau tahun ajaran..."
        :sort-property="$sortBy"
        :sort-direction="$sortDir"
        empty-title="Belum ada kelas"
        empty-message="Tambahkan kelas pertama Anda untuk mulai mengelola siswa dan kelompok."
    />

    <x-ui.modal name="modal-kelas" title="Kelas">
        <form wire:submit="simpanKelas" class="space-y-4">
            <x-ui.input label="Nama Kelas" name="form.class_name" wire:model="form.class_name" placeholder="Contoh: X RPL 1" required autofocus />
            <x-ui.input label="Tahun Ajaran" name="form.school_year" wire:model="form.school_year" placeholder="Contoh: 2026/2027" required />

            @if (auth()->user()->isAdmin())
                <x-ui.select
                    label="Pemilik Kelas"
                    name="form.user_id"
                    :options="$this->opsiPemilik"
                    placeholder="— Milik saya (admin) —"
                    wire:model="form.user_id"
                    help="Biarkan kosong untuk menjadikan diri Anda sendiri sebagai pemilik."
                />
            @else
                <x-ui.alert variant="info">
                    Kelas ini otomatis menjadi milik Anda. Hanya Anda (dan admin) yang dapat melihat dan mengelolanya.
                </x-ui.alert>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-kelas-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Kelas</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusKelas"
        title="Hapus Kelas"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />
</div>

