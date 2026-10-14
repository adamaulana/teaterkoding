<?php

use App\Actions\ParserCsvSiswa;
use App\Concerns\InteractsWithTable;
use App\Livewire\Forms\SiswaForm;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin'), Title('Siswa')]
class extends Component
{
    use InteractsWithTable;
    use WithFileUploads;

    public SiswaForm $form;

    /** Filter kelas dari URL (?kelas=ID). */
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public ?int $siswaId = null;

    public string $pesanKonfirmasi = '';

    // ---- Keadaan impor CSV ----
    public string $imporTahap = 'pilih';

    public mixed $berkas = null;

    public ?int $imporKelasId = null;

    /** @var array<int, array{row: int, name: string, nis: string|null}> */
    public array $pratinjauValid = [];

    /** @var array<int, array{row: int, name: string, nis: string|null, reason: string}> */
    public array $pratinjauInvalid = [];

    public bool $pratinjauTerpotong = false;

    private const SORTABLE = ['full_name', 'student_no', 'class_id', 'student_id'];

    public function mount(): void
    {
        if ($this->kelasId !== null) {
            $this->pastikanBolehLihatKelas($this->kelasId);
        }
    }

    /**
     * Kelas terpilih harus terlihat oleh pengguna (guru lain → 403).
     */
    private function pastikanBolehLihatKelas(int $kelasId): SchoolClass
    {
        $kelas = SchoolClass::findOrFail($kelasId);

        abort_unless(auth()->user()->can('view', $kelas), 403);

        return $kelas;
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'student_id';

        return Student::query()
            ->visibleTo(auth()->user())
            ->when($this->kelasId !== null, fn ($query) => $query->where('class_id', $this->kelasId))
            ->with(['schoolClass', 'teamMembers.team'])
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($sub) => $sub
                    ->where('full_name', 'like', "%{$this->search}%")
                    ->orWhere('student_no', 'like', "%{$this->search}%")))
            ->orderBy($sortBy, $this->sortBy ? $this->sortDir : 'desc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
    }

    /**
     * Kelas yang boleh dipilih pengguna.
     *
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
     * Boleh menambah/mengimpor siswa? Wajib ada kelas yang dipilih.
     */
    #[Computed]
    public function bolehUbah(): bool
    {
        return $this->kelasId !== null || count($this->opsiKelas) > 0;
    }

    public function updatedKelasId(): void
    {
        if ($this->kelasId !== null) {
            $this->pastikanBolehLihatKelas($this->kelasId);
        }

        $this->page = 1;
        unset($this->rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'full_name',
                'label' => 'Nama',
                'sortable' => true,
                'format' => fn (Student $student) => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$nama" size="sm" />
                        <span class="font-medium text-slate-800">{{ $nama }}</span>
                    </div>
                    BLADE, ['nama' => $student->full_name]),
            ],
            [
                'key' => 'student_no',
                'label' => 'NIS/NISN',
                'sortable' => true,
                'format' => fn (Student $student) => e($student->student_no ?? '—'),
            ],
            [
                'key' => 'class_id',
                'label' => 'Kelas',
                'sortable' => true,
                'format' => fn (Student $student) => e($student->schoolClass?->class_name ?? '—'),
            ],
            [
                'key' => 'kelompok',
                'label' => 'Kelompok Saat Ini',
                'format' => function (Student $student) {
                    $team = $student->teamMembers->first()?->team;

                    return $team === null
                        ? Blade::render('<x-ui.badge variant="peringatan">Belum ada kelompok</x-ui.badge>')
                        : Blade::render('<x-ui.badge variant="sukses">{{ $n }}</x-ui.badge>', ['n' => $team->team_name]);
                },
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (Student $student) => Blade::render(<<<'BLADE'
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
                    BLADE, ['id' => $student->student_id]),
            ],
        ];
    }

    // ---------- CRUD ----------

    public function bukaTambah(): void
    {
        $this->authorize('create', Student::class);

        $this->form->resetForm();
        $this->form->class_id = $this->kelasId ?? array_key_first($this->opsiKelas);
        $this->resetValidation();

        $this->dispatch('modal-siswa-open');
    }

    public function bukaUbah(int $siswaId): void
    {
        $siswa = Student::findOrFail($siswaId);

        $this->authorize('update', $siswa);

        $this->form->setSiswa($siswa);
        $this->resetValidation();

        $this->dispatch('modal-siswa-open');
    }

    public function simpanSiswa(): void
    {
        if ($this->form->class_id !== null) {
            $this->pastikanBolehLihatKelas($this->form->class_id);
        }

        $data = $this->form->validate();

        if ($this->form->studentId === null) {
            $this->authorize('create', Student::class);

            Student::create([
                'class_id' => $data['class_id'],
                'full_name' => $data['full_name'],
                'student_no' => $data['student_no'] !== '' ? $data['student_no'] : null,
            ]);

            $pesan = 'Siswa berhasil ditambahkan.';
        } else {
            $siswa = Student::findOrFail($this->form->studentId);

            $this->authorize('update', $siswa);

            $siswa->fill([
                'class_id' => $data['class_id'],
                'full_name' => $data['full_name'],
                'student_no' => $data['student_no'] !== '' ? $data['student_no'] : null,
            ])->save();

            $pesan = 'Data siswa berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->rows);

        $this->dispatch('modal-siswa-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapus(int $siswaId): void
    {
        $siswa = Student::query()->findOrFail($siswaId);

        $this->authorize('delete', $siswa);

        $relasi = $this->hitungRelasiSiswa($siswa);

        if ($relasi !== []) {
            $this->dispatch('toast', type: 'bahaya', message: 'Siswa "'.$siswa->full_name.'" masih terhubung ke '.implode(', ', $relasi).' — lepaskan dulu sebelum menghapus.');

            return;
        }

        $this->siswaId = $siswa->student_id;
        $this->pesanKonfirmasi = "Hapus siswa \"{$siswa->full_name}\"? Tindakan ini tidak bisa dibatalkan.";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusSiswa(): void
    {
        if ($this->siswaId === null) {
            return;
        }

        $siswa = Student::findOrFail($this->siswaId);

        $this->authorize('delete', $siswa);

        $relasi = $this->hitungRelasiSiswa($siswa);

        if ($relasi !== []) {
            $this->dispatch('toast', type: 'bahaya', message: 'Siswa masih terhubung ke '.implode(', ', $relasi).' — lepaskan dulu sebelum menghapus.');

            return;
        }

        $siswa->delete();

        $this->siswaId = null;
        $this->page = 1;
        unset($this->rows);

        $this->dispatch('toast', type: 'sukses', message: 'Siswa berhasil dihapus.');
    }

    /**
     * Nama relasi yang menghalangi penghapusan (pesan ramah, bukan error FK).
     *
     * @return array<int, string>
     */
    private function hitungRelasiSiswa(Student $siswa): array
    {
        $cek = [
            'keanggotaan kelompok' => $siswa->teamMembers()->count(),
            'submission' => $siswa->submissions()->count(),
            'penugasan peran' => $siswa->roleAssignments()->count(),
            'kepemilikan fungsi' => $siswa->functionOwnerships()->count(),
            'catatan skor' => $siswa->scoreEntries()->count(),
        ];

        $hasil = [];

        foreach ($cek as $label => $jumlah) {
            if ($jumlah > 0) {
                $hasil[] = $jumlah.' '.$label;
            }
        }

        return $hasil;
    }
    // ---------- Impor CSV ----------

    public function bukaImpor(): void
    {
        $this->authorize('create', Student::class);

        $this->imporTahap = 'pilih';
        $this->berkas = null;
        $this->imporKelasId = $this->kelasId ?? array_key_first($this->opsiKelas);
        $this->pratinjauValid = [];
        $this->pratinjauInvalid = [];
        $this->pratinjauTerpotong = false;
        $this->resetValidation();

        $this->dispatch('modal-impor-open');
    }

    public function unduhTemplat()
    {
        return response()->streamDownload(function () {
            echo "nama,nis\n";
            echo "Budi Santoso,24001\n";
            echo "Siti Aminah,24002\n";
        }, 'templat-impor-siswa.csv', ['Content-Type' => 'text/csv']);
    }

    public function pratinjauImpor(): void
    {
        $this->authorize('create', Student::class);

        $this->validate([
            'imporKelasId' => ['required', 'integer'],
            'berkas' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ], [
            'imporKelasId.required' => 'Pilih kelas tujuan terlebih dahulu.',
            'berkas.required' => 'Pilih berkas CSV terlebih dahulu.',
            'berkas.mimes' => 'Berkas harus berformat CSV.',
            'berkas.max' => 'Ukuran berkas maksimal 1 MB.',
        ]);

        $kelas = $this->pastikanBolehLihatKelas((int) $this->imporKelasId);

        $nisEksisting = Student::query()
            ->where('class_id', $kelas->class_id)
            ->whereNotNull('student_no')
            ->pluck('student_no')
            ->all();

        $hasil = (new ParserCsvSiswa)->parse(
            (string) $this->berkas->get(),
            $nisEksisting,
        );

        $this->pratinjauValid = $hasil['valid'];
        $this->pratinjauInvalid = $hasil['invalid'];
        $this->pratinjauTerpotong = $hasil['truncated'];
        $this->imporTahap = 'pratinjau';
    }

    public function imporBarisValid(): void
    {
        $this->authorize('create', Student::class);

        if ($this->pratinjauValid === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Tidak ada baris valid untuk diimpor.');

            return;
        }

        $kelas = $this->pastikanBolehLihatKelas((int) $this->imporKelasId);

        DB::transaction(function () use ($kelas) {
            foreach ($this->pratinjauValid as $baris) {
                Student::create([
                    'class_id' => $kelas->class_id,
                    'full_name' => $baris['name'],
                    'student_no' => $baris['nis'],
                ]);
            }
        });

        $jumlah = count($this->pratinjauValid);
        $bermasalah = count($this->pratinjauInvalid);

        $this->imporTahap = 'pilih';
        $this->berkas = null;
        $this->pratinjauValid = [];
        $this->pratinjauInvalid = [];
        $this->pratinjauTerpotong = false;
        unset($this->rows);

        $this->dispatch('modal-impor-close');
        $this->dispatch('toast', type: 'sukses', message: "{$jumlah} siswa berhasil diimpor".($bermasalah > 0 ? ", {$bermasalah} baris bermasalah dilewati." : '.'));
    }

    public function unduhBarisBermasalah()
    {
        $baris = $this->pratinjauInvalid;

        return response()->streamDownload(function () use ($baris) {
            $keluaran = fopen('php://output', 'w');

            if ($keluaran === false) {
                return;
            }

            fputcsv($keluaran, ['baris', 'nama', 'nis', 'alasan']);

            foreach ($baris as $item) {
                fputcsv($keluaran, [$item['row'], $item['name'], $item['nis'] ?? '', $item['reason']]);
            }

            fclose($keluaran);
        }, 'baris-bermasalah.csv', ['Content-Type' => 'text/csv']);
    }

    public function kembaliKePilih(): void
    {
        $this->imporTahap = 'pilih';
        $this->pratinjauValid = [];
        $this->pratinjauInvalid = [];
        $this->pratinjauTerpotong = false;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-ui.page-header title="Siswa" description="Kelola data siswa, pilih kelas, dan impor massal dari berkas CSV.">
        <x-slot:actions>
            <x-ui.button variant="outline" wire:click="bukaImpor" @disabled(! $this->bolehUbah)>
                <x-ui.icon.download class="size-4" />
                Impor CSV
            </x-ui.button>
            <x-ui.button variant="primary" wire:click="bukaTambah" @disabled(! $this->bolehUbah)>
                <x-ui.icon.plus class="size-4" />
                Tambah Siswa
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (count($this->opsiKelas) === 0)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu sebelum menambahkan siswa."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" :href="route('admin.kelas')" wire:navigate>
                        <x-ui.icon.plus class="size-4" />
                        Buka Halaman Kelas
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.card class="mb-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.select
                    label="Filter Kelas"
                    name="kelasId"
                    :options="$this->opsiKelas"
                    placeholder="Semua kelas"
                    wire:model.live="kelasId"
                />
            </div>
        </x-ui.card>

        <x-ui.data-table
            :rows="$this->rows"
            :columns="$this->columns"
            searchable
            search-placeholder="Cari nama atau NIS..."
            :sort-property="$sortBy"
            :sort-direction="$sortDir"
            empty-title="Belum ada siswa"
            empty-message="Tambahkan siswa satu per satu atau impor massal dari berkas CSV."
        />
    @endif

    <x-ui.modal name="modal-siswa" title="Siswa">
        <form wire:submit="simpanSiswa" class="space-y-4">
            <x-ui.select
                label="Kelas"
                name="form.class_id"
                :options="$this->opsiKelas"
                wire:model="form.class_id"
                required
            />
            <x-ui.input label="Nama Lengkap" name="form.full_name" wire:model="form.full_name" placeholder="Contoh: Budi Santoso" required autofocus />
            <x-ui.input
                label="NIS/NISN (opsional)"
                name="form.student_no"
                wire:model="form.student_no"
                placeholder="Contoh: 24001"
                help="Harus unik di dalam kelas yang sama."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-siswa-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Siswa</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="modal-impor" title="Impor Siswa dari CSV">
        @if ($imporTahap === 'pilih')
            <form wire:submit="pratinjauImpor" class="space-y-4">
                <x-ui.select
                    label="Kelas Tujuan"
                    name="imporKelasId"
                    :options="$this->opsiKelas"
                    wire:model="imporKelasId"
                    required
                />

                <x-ui.input
                    label="Berkas CSV"
                    name="berkas"
                    type="file"
                    wire:model="berkas"
                    accept=".csv,text/csv"
                    help="Maksimal 1 MB, maksimal 500 baris. Format kolom: nama,nis (NIS opsional)."
                />

                <x-ui.button variant="outline" type="button" wire:click="unduhTemplat">
                    <x-ui.icon.download class="size-4" />
                    Unduh Templat CSV
                </x-ui.button>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" x-on:click="$dispatch('modal-impor-close')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="pratinjauImpor">Pratinjau</span>
                        <span wire:loading wire:target="pratinjauImpor">Membaca berkas...</span>
                    </x-ui.button>
                </div>
            </form>
        @else
            <div class="space-y-4">
                <x-ui.alert :variant="count($pratinjauInvalid) > 0 ? 'peringatan' : 'sukses'">
                    <strong>{{ count($pratinjauValid) }}</strong> baris valid,
                    <strong>{{ count($pratinjauInvalid) }}</strong> baris bermasalah.
                    @if ($pratinjauTerpotong)
                        Hanya 500 baris pertama yang dibaca.
                    @endif
                </x-ui.alert>

                @if (count($pratinjauInvalid) > 0)
                    <div class="max-h-64 overflow-y-auto rounded-lg border border-line">
                        <table class="min-w-full divide-y divide-line text-xs">
                            <thead class="bg-page/60">
                                <tr>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Baris</th>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Nama</th>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">NIS</th>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Alasan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-white">
                                @foreach ($pratinjauInvalid as $baris)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-500">{{ $baris['row'] }}</td>
                                        <td class="px-3 py-2 text-slate-700">{{ $baris['name'] !== '' ? $baris['name'] : '—' }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $baris['nis'] ?? '—' }}</td>
                                        <td class="px-3 py-2 text-rose-600">{{ $baris['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" wire:click="kembaliKePilih">Kembali</x-ui.button>

                    @if (count($pratinjauInvalid) > 0)
                        <x-ui.button variant="outline" wire:click="unduhBarisBermasalah">
                            <x-ui.icon.download class="size-4" />
                            Unduh Baris Bermasalah
                        </x-ui.button>
                    @endif

                    <x-ui.button variant="primary" wire:click="imporBarisValid" @disabled(count($pratinjauValid) === 0)>
                        Impor {{ count($pratinjauValid) }} Baris Valid
                    </x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusSiswa"
        title="Hapus Siswa"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />
</div>


