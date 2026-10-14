<?php

use App\Actions\AcakKelompok;
use App\Livewire\Forms\KelompokForm;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Kelompok')]
class extends Component
{
    public KelompokForm $form;

    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public ?int $teamId = null;

    public string $pesanKonfirmasi = '';

    // ---- Kelola anggota ----
    /** @var array<int, int> */
    public array $anggotaTerpilih = [];

    /** @var array<int, int> */
    public array $pindahKe = [];

    // ---- Acak otomatis ----
    public int $acakTarget = 4;

    public string $acakMode = 'belum';

    public string $acakKonfirmasi = '';

    /** @var array<int, array{name: string, student_ids: array<int, int>}> */
    public array $acakPratinjau = [];

    public function mount(): void
    {
        if ($this->kelasId !== null) {
            $this->kelasTerpilih();
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
     * Kelompok pada kelas terpilih beserta anggota & jumlah relasi.
     *
     * @return \Illuminate\Support\Collection<int, Team>
     */
    #[Computed]
    public function kelompok()
    {
        if ($this->kelasId === null) {
            return collect();
        }

        return Team::query()
            ->where('class_id', $this->kelasId)
            ->with(['teamMembers.student'])
            ->withCount(['submissions', 'missionPlans', 'roleAssignments'])
            ->orderBy('team_id')
            ->get();
    }

    /**
     * Siswa kelas terpilih yang belum punya kelompok.
     *
     * @return \Illuminate\Support\Collection<int, Student>
     */
    #[Computed]
    public function siswaTanpaKelompok()
    {
        if ($this->kelasId === null) {
            return collect();
        }

        return Student::query()
            ->where('class_id', $this->kelasId)
            ->whereDoesntHave('teamMembers')
            ->orderBy('full_name')
            ->get();
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();
        unset($this->kelompok, $this->siswaTanpaKelompok);
    }

    // ---------- CRUD kelompok ----------

    public function bukaTambah(): void
    {
        $this->authorize('create', Team::class);

        $this->form->resetForm();
        $this->form->class_id = $this->kelasId ?? array_key_first($this->opsiKelas);
        $this->resetValidation();

        $this->dispatch('modal-kelompok-open');
    }

    public function bukaUbah(int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        $this->authorize('update', $team);

        $this->form->setKelompok($team);
        $this->resetValidation();

        $this->dispatch('modal-kelompok-open');
    }

    public function simpanKelompok(): void
    {
        if ($this->form->class_id !== null) {
            $kelas = SchoolClass::findOrFail($this->form->class_id);
            abort_unless(auth()->user()->can('view', $kelas), 403);
        }

        $data = $this->form->validate();

        if ($this->form->teamId === null) {
            $this->authorize('create', Team::class);

            Team::create([
                'class_id' => $data['class_id'],
                'team_name' => $data['team_name'],
            ]);

            $pesan = 'Kelompok berhasil ditambahkan.';
        } else {
            $team = Team::findOrFail($this->form->teamId);

            $this->authorize('update', $team);

            $team->fill([
                'class_id' => $data['class_id'],
                'team_name' => $data['team_name'],
            ])->save();

            $pesan = 'Kelompok berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->kelompok);

        $this->dispatch('modal-kelompok-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapus(int $teamId): void
    {
        $team = Team::query()
            ->withCount(['submissions', 'missionPlans', 'roleAssignments'])
            ->findOrFail($teamId);

        $this->authorize('delete', $team);

        $alasan = [];

        if ((int) $team->submissions_count > 0) {
            $alasan[] = $team->submissions_count.' submission';
        }

        if ((int) $team->mission_plans_count > 0) {
            $alasan[] = $team->mission_plans_count.' rencana misi';
        }

        if ((int) $team->role_assignments_count > 0) {
            $alasan[] = $team->role_assignments_count.' penugasan peran';
        }

        if ($alasan !== []) {
            $this->dispatch('toast', type: 'bahaya', message: "Kelompok \"{$team->team_name}\" masih memiliki ".implode(', ', $alasan).' — hapus data tersebut dulu sebelum menghapus kelompok.');

            return;
        }

        $this->teamId = $team->team_id;
        $this->pesanKonfirmasi = "Hapus kelompok \"{$team->team_name}\"? Anggota akan dikeluarkan dari kelompok ini.";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusKelompok(): void
    {
        if ($this->teamId === null) {
            return;
        }

        $team = Team::query()
            ->withCount(['submissions', 'missionPlans', 'roleAssignments'])
            ->findOrFail($this->teamId);

        $this->authorize('delete', $team);

        if ((int) $team->submissions_count > 0 || (int) $team->mission_plans_count > 0 || (int) $team->role_assignments_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kelompok masih memiliki submission, rencana misi, atau penugasan peran.');

            return;
        }

        DB::transaction(function () use ($team) {
            $team->teamMembers()->delete();
            $team->delete();
        });

        $this->teamId = null;
        unset($this->kelompok, $this->siswaTanpaKelompok);

        $this->dispatch('toast', type: 'sukses', message: 'Kelompok berhasil dihapus.');
    }

    // ---------- Kelola anggota ----------

    public function bukaKelolaAnggota(int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        $this->authorize('update', $team);

        $this->teamId = $team->team_id;
        $this->anggotaTerpilih = [];
        $this->pindahKe = [];
        $this->resetValidation();

        $this->dispatch('modal-anggota-open');
    }

    public function tambahAnggota(): void
    {
        $team = Team::findOrFail($this->teamId);
        $this->authorize('update', $team);

        $dipilih = array_values(array_unique(array_map('intval', $this->anggotaTerpilih)));

        if ($dipilih === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih minimal satu siswa untuk ditambahkan.');

            return;
        }

        // Siswa harus berada di kelas yang sama dengan kelompok.
        $valid = Student::query()
            ->where('class_id', $team->class_id)
            ->whereIn('student_id', $dipilih)
            ->pluck('student_id')
            ->all();

        if (count($valid) !== count($dipilih)) {
            $this->dispatch('toast', type: 'bahaya', message: 'Ada siswa yang bukan anggota kelas ini.');

            return;
        }

        // Satu siswa hanya boleh berada di satu kelompok per kelas.
        $sudahPunya = TeamMember::query()
            ->whereIn('student_id', $valid)
            ->whereHas('team', fn ($query) => $query->where('class_id', $team->class_id))
            ->pluck('student_id')
            ->all();

        if ($sudahPunya !== []) {
            $nama = Student::query()->whereIn('student_id', $sudahPunya)->pluck('full_name')->all();
            $this->dispatch('toast', type: 'bahaya', message: 'Siswa berikut sudah punya kelompok: '.implode(', ', $nama).'.');

            return;
        }

        $jumlahSekarang = $team->teamMembers()->count();

        if ($jumlahSekarang + count($valid) > 5) {
            $this->dispatch('toast', type: 'bahaya', message: "Kelompok maksimal 5 anggota. Saat ini {$jumlahSekarang} anggota, akan ditambah ".count($valid).'.');

            return;
        }

        DB::transaction(function () use ($team, $valid) {
            foreach ($valid as $studentId) {
                TeamMember::create(['team_id' => $team->team_id, 'student_id' => $studentId]);
            }
        });

        $this->anggotaTerpilih = [];
        unset($this->kelompok, $this->siswaTanpaKelompok);

        $this->dispatch('toast', type: 'sukses', message: count($valid).' siswa ditambahkan ke kelompok.');
    }

    public function keluarkanAnggota(int $studentId): void
    {
        $team = Team::findOrFail($this->teamId);
        $this->authorize('update', $team);

        TeamMember::query()
            ->where('team_id', $team->team_id)
            ->where('student_id', $studentId)
            ->delete();

        unset($this->kelompok, $this->siswaTanpaKelompok);

        $this->dispatch('toast', type: 'sukses', message: 'Siswa dikeluarkan dari kelompok.');
    }

    public function pindahkanAnggota(int $studentId): void
    {
        $team = Team::findOrFail($this->teamId);
        $this->authorize('update', $team);

        $tujuanId = (int) ($this->pindahKe[$studentId] ?? 0);

        if ($tujuanId === 0) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih kelompok tujuan terlebih dahulu.');

            return;
        }

        $tujuan = Team::findOrFail($tujuanId);

        if ($tujuan->class_id !== $team->class_id) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kelompok tujuan harus berada di kelas yang sama.');

            return;
        }

        if ($tujuan->teamMembers()->count() >= 5) {
            $this->dispatch('toast', type: 'bahaya', message: "Kelompok \"{$tujuan->team_name}\" sudah penuh (5 anggota).");

            return;
        }

        DB::transaction(function () use ($team, $tujuan, $studentId) {
            TeamMember::query()
                ->where('team_id', $team->team_id)
                ->where('student_id', $studentId)
                ->delete();

            TeamMember::create(['team_id' => $tujuan->team_id, 'student_id' => $studentId]);
        });

        unset($this->kelompok, $this->siswaTanpaKelompok);

        $this->dispatch('toast', type: 'sukses', message: "Siswa dipindahkan ke \"{$tujuan->team_name}\".");
    }

    // ---------- Acak kelompok otomatis ----------

    public function bukaAcak(): void
    {
        $this->authorize('create', Team::class);

        $this->acakTarget = 4;
        $this->acakMode = 'belum';
        $this->acakKonfirmasi = '';
        $this->acakPratinjau = [];
        $this->resetValidation();

        $this->dispatch('acak-open');
    }

    public function tutupAcak(): void
    {
        $this->acakPratinjau = [];
        $this->acakKonfirmasi = '';
        $this->resetValidation();

        $this->dispatch('acak-close');
    }

    public function acakUlang(): void
    {
        $this->authorize('create', Team::class);

        $kelas = $this->kelasTerpilih();

        if ($kelas === null) {
            $this->dispatch('toast', type: 'bahaya', message: 'Pilih kelas terlebih dahulu.');

            return;
        }

        $this->acakPratinjau = $this->susunPratinjau($kelas);
    }

    /**
     * Susun pratinjau kelompok beserta nama anggotanya (untuk ditampilkan).
     *
     * @return array<int, array{name: string, student_ids: array<int, int>, nama_anggota: array<int, string>}>
     */
    private function susunPratinjau(SchoolClass $kelas): array
    {
        $action = new AcakKelompok;

        if ($this->acakMode === 'reset') {
            $ids = Student::query()->where('class_id', $kelas->class_id)->pluck('student_id')->all();
            $nomorAwal = 1;
        } else {
            $ids = Student::query()
                ->where('class_id', $kelas->class_id)
                ->whereDoesntHave('teamMembers')
                ->pluck('student_id')
                ->all();
            $nomorAwal = $action->deteksiNomorAwal(
                Team::query()->where('class_id', $kelas->class_id)->pluck('team_name'),
            );
        }

        $grup = $action->susun($ids, $this->acakTarget, $nomorAwal);

        $nama = Student::query()
            ->whereIn('student_id', $ids)
            ->pluck('full_name', 'student_id')
            ->all();

        return array_map(fn (array $item): array => [
            'name' => $item['name'],
            'student_ids' => $item['student_ids'],
            'nama_anggota' => array_map(
                fn (int $id): string => $nama[$id] ?? ('#'.$id),
                $item['student_ids'],
            ),
        ], $grup);
    }

    public function simpanAcak(): void
    {
        $this->authorize('create', Team::class);

        $kelas = $this->kelasTerpilih();

        if ($kelas === null) {
            $this->dispatch('toast', type: 'bahaya', message: 'Pilih kelas terlebih dahulu.');

            return;
        }

        if ($this->acakPratinjau === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Belum ada pratinjau. Klik "Acak Ulang" terlebih dahulu.');

            return;
        }

        if ($this->acakMode === 'reset') {
            if (trim($this->acakKonfirmasi) !== $kelas->class_name) {
                $this->addError('acakKonfirmasi', 'Nama kelas tidak cocok. Ketik persis: '.$kelas->class_name);

                return;
            }

            $adaSubmission = Team::query()
                ->where('class_id', $kelas->class_id)
                ->whereHas('submissions')
                ->exists();

            if ($adaSubmission) {
                $this->dispatch('toast', type: 'bahaya', message: 'Reset ditolak: sudah ada kelompok dengan submission di kelas ini.');

                return;
            }
        }

        $pratinjau = $this->acakPratinjau;
        $modeReset = $this->acakMode === 'reset';

        DB::transaction(function () use ($kelas, $pratinjau, $modeReset) {
            if ($modeReset) {
                $teamIds = Team::query()->where('class_id', $kelas->class_id)->pluck('team_id')->all();

                TeamMember::query()->whereIn('team_id', $teamIds)->delete();
                Team::query()->whereIn('team_id', $teamIds)->delete();
            }

            foreach ($pratinjau as $grup) {
                $team = Team::create([
                    'class_id' => $kelas->class_id,
                    'team_name' => $grup['name'],
                ]);

                foreach ($grup['student_ids'] as $studentId) {
                    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $studentId]);
                }
            }
        });

        $jumlahGrup = count($pratinjau);

        $this->acakPratinjau = [];
        $this->acakKonfirmasi = '';
        unset($this->kelompok, $this->siswaTanpaKelompok);

        $this->dispatch('acak-close');
        $this->dispatch('toast', type: 'sukses', message: "{$jumlahGrup} kelompok berhasil disimpan.");
    }
}; ?>

<div>
    <x-ui.page-header title="Kelompok" description="Bentuk dan kelola kelompok belajar, atur anggotanya, atau acak otomatis.">
        <x-slot:actions>
            @if ($kelasId !== null)
                <x-ui.button variant="outline" wire:click="bukaAcak">
                    <x-ui.icon.rotate class="size-4" />
                    Acak Otomatis
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="bukaTambah">
                    <x-ui.icon.plus class="size-4" />
                    Tambah Kelompok
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if (count($this->opsiKelas) === 0)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu sebelum membentuk kelompok."
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
                    label="Pilih Kelas"
                    name="kelasId"
                    :options="$this->opsiKelas"
                    placeholder="— Pilih kelas —"
                    wire:model.live="kelasId"
                    help="Kelompok ditampilkan per kelas."
                />
            </div>
        </x-ui.card>

        @if ($kelasId === null)
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="users-group"
                    title="Pilih kelas terlebih dahulu"
                    message="Kelompok selalu berada di dalam satu kelas. Pilih kelas di atas untuk melihat dan mengelolanya."
                />
            </x-ui.card>
        @elseif ($this->kelompok->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="users-group"
                    title="Belum ada kelompok di kelas ini"
                    message="Tambahkan kelompok secara manual, atau pakai Acak Otomatis untuk membagi siswa menjadi kelompok 4–5 orang."
                >
                    <x-slot:actions>
                        <x-ui.button variant="outline" wire:click="bukaAcak">
                            <x-ui.icon.rotate class="size-4" />
                            Acak Otomatis
                        </x-ui.button>
                        <x-ui.button variant="primary" wire:click="bukaTambah">
                            <x-ui.icon.plus class="size-4" />
                            Tambah Kelompok
                        </x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->kelompok as $team)
                    @php
                        $jumlah = $team->teamMembers->count();
                        $variant = $jumlah >= 4 && $jumlah <= 5 ? 'sukses' : ($jumlah < 4 ? 'peringatan' : 'bahaya');
                    @endphp

                    <x-ui.card wire:key="team-{{ $team->team_id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800">{{ $team->team_name }}</p>
                                <div class="mt-1.5">
                                    <x-ui.badge :variant="$variant">{{ $jumlah }} anggota</x-ui.badge>
                                </div>
                            </div>

                            <x-ui.dropdown align="end">
                                <x-slot:trigger>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" aria-label="Aksi">
                                        <x-ui.icon.dots-vertical />
                                    </button>
                                </x-slot:trigger>

                                <button type="button" wire:click="bukaKelolaAnggota({{ $team->team_id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                                    <x-ui.icon.user-cog class="size-4 text-slate-400" />
                                    Kelola Anggota
                                </button>
                                <button type="button" wire:click="bukaUbah({{ $team->team_id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                                    <x-ui.icon.pencil class="size-4 text-slate-400" />
                                    Ubah Nama
                                </button>
                                <button type="button" wire:click="mintaHapus({{ $team->team_id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-rose-600 transition hover:bg-rose-50">
                                    <x-ui.icon.trash class="size-4 text-rose-500" />
                                    Hapus
                                </button>
                            </x-ui.dropdown>
                        </div>

                        <ul class="mt-4 space-y-2">
                            @forelse ($team->teamMembers as $member)
                                <li class="flex items-center gap-2.5">
                                    <x-ui.avatar :name="$member->student?->full_name ?? '?'" size="sm" />
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ $member->student?->full_name ?? '—' }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-slate-400">Belum ada anggota.</li>
                            @endforelse
                        </ul>

                        <x-ui.button variant="soft" size="sm" class="mt-4 w-full" wire:click="bukaKelolaAnggota({{ $team->team_id }})">
                            <x-ui.icon.user-plus class="size-4" />
                            Kelola Anggota
                        </x-ui.button>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    @endif

    <x-ui.modal name="modal-kelompok" title="Kelompok">
        <form wire:submit="simpanKelompok" class="space-y-4">
            <x-ui.select label="Kelas" name="form.class_id" :options="$this->opsiKelas" wire:model="form.class_id" required />
            <x-ui.input label="Nama Kelompok" name="form.team_name" wire:model="form.team_name" placeholder="Contoh: Kelompok 1" required autofocus />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-kelompok-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Kelompok</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="modal-anggota" title="Kelola Anggota Kelompok">
        @php $teamAktif = $this->kelompok->firstWhere('team_id', $teamId); @endphp

        @if ($teamAktif !== null)
            <div class="space-y-5">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Anggota saat ini ({{ $teamAktif->teamMembers->count() }}/5)</p>

                    <ul class="mt-3 space-y-2">
                        @forelse ($teamAktif->teamMembers as $member)
                            <li class="flex items-center gap-2 rounded-lg border border-line px-3 py-2">
                                <x-ui.avatar :name="$member->student?->full_name ?? '?'" size="sm" />
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ $member->student?->full_name ?? '—' }}</span>

                                <select wire:model="pindahKe.{{ $member->student_id }}" class="rounded-lg border-line py-1 text-xs text-slate-700 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30">
                                    <option value="">Pindah ke...</option>
                                    @foreach ($this->kelompok as $lain)
                                        @if ($lain->team_id !== $teamAktif->team_id)
                                            <option value="{{ $lain->team_id }}">{{ $lain->team_name }}</option>
                                        @endif
                                    @endforeach
                                </select>

                                <x-ui.button variant="soft" size="sm" wire:click="pindahkanAnggota({{ $member->student_id }})">Pindah</x-ui.button>
                                <x-ui.button variant="ghost" size="sm" wire:click="keluarkanAnggota({{ $member->student_id }})">
                                    <x-ui.icon.user-minus class="size-4" />
                                </x-ui.button>
                            </li>
                        @empty
                            <li class="text-sm text-slate-400">Belum ada anggota.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="border-t border-line pt-4">
                    <p class="text-sm font-semibold text-slate-800">Tambah siswa (belum punya kelompok)</p>

                    @if ($this->siswaTanpaKelompok->isEmpty())
                        <p class="mt-2 text-xs text-slate-400">Semua siswa di kelas ini sudah punya kelompok.</p>
                    @else
                        <div class="mt-3 max-h-52 space-y-1 overflow-y-auto">
                            @foreach ($this->siswaTanpaKelompok as $siswa)
                                <x-ui.checkbox
                                    :label="$siswa->full_name"
                                    :help="$siswa->student_no"
                                    wire:model="anggotaTerpilih"
                                    value="{{ $siswa->student_id }}"
                                    wire:key="pilih-{{ $siswa->student_id }}"
                                />
                            @endforeach
                        </div>

                        <x-ui.button variant="primary" size="sm" class="mt-3" wire:click="tambahAnggota">
                            <x-ui.icon.user-plus class="size-4" />
                            Tambahkan yang Dipilih
                        </x-ui.button>
                    @endif
                </div>

                <div class="flex justify-end border-t border-line pt-4">
                    <x-ui.button variant="outline" x-on:click="$dispatch('modal-anggota-close')">Tutup</x-ui.button>
                </div>
            </div>
        @else
            <p class="text-sm text-slate-500">Pilih kelompok terlebih dahulu.</p>
        @endif
    </x-ui.modal>

    {{-- Acak otomatis: modal Alpine khusus karena mode "reset" butuh konfirmasi ketik nama kelas. --}}
    <div
        x-data="{ open: false }"
        x-on:acak-open.window="open = true"
        x-on:acak-close.window="open = false"
        x-on:keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="open = false"></div>

        <div class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div class="my-auto w-full max-w-2xl rounded-xl border border-line bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Acak Kelompok Otomatis</h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                        <x-ui.icon.x />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select
                            label="Target Anggota per Kelompok"
                            name="acakTarget"
                            :options="[4 => '4 orang', 5 => '5 orang']"
                            wire:model="acakTarget"
                        />

                        <x-ui.select
                            label="Sumber Siswa"
                            name="acakMode"
                            :options="[
                                'belum' => 'Hanya siswa yang belum punya kelompok',
                                'reset' => 'Reset semua kelompok kelas ini',
                            ]"
                            wire:model.live="acakMode"
                        />
                    </div>

                    @if ($acakMode === 'reset')
                        <x-ui.alert variant="peringatan" title="Mode reset bersifat destruktif">
                            Seluruh kelompok di kelas ini akan dihapus lalu dibentuk ulang. Ditolak bila sudah ada kelompok
                            dengan submission. Ketik nama kelas <strong>{{ $this->opsiKelas[$kelasId] ?? '' }}</strong> untuk konfirmasi.
                        </x-ui.alert>

                        <x-ui.input
                            label="Ketik nama kelas untuk konfirmasi"
                            name="acakKonfirmasi"
                            wire:model="acakKonfirmasi"
                            placeholder="{{ $this->opsiKelas[$kelasId] ?? '' }}"
                        />
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button variant="outline" wire:click="acakUlang">
                            <x-ui.icon.rotate class="size-4" />
                            {{ count($acakPratinjau) > 0 ? 'Acak Ulang' : 'Acak' }}
                        </x-ui.button>
                    </div>

                    @if (count($acakPratinjau) > 0)
                        <div class="max-h-72 overflow-y-auto rounded-lg border border-line">
                            <table class="min-w-full divide-y divide-line text-sm">
                                <thead class="bg-page/60">
                                    <tr>
                                        <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Anggota</th>
                                        <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line bg-white">
                                    @foreach ($acakPratinjau as $grup)
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-700">{{ $grup['name'] }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ implode(', ', $grup['nama_anggota'] ?? []) }}</td>
                                            <td class="px-3 py-2 text-slate-500">{{ count($grup['student_ids']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                    <x-ui.button variant="outline" wire:click="tutupAcak">Batal</x-ui.button>
                    <x-ui.button variant="primary" wire:click="simpanAcak" @disabled(count($acakPratinjau) === 0)>
                        Simpan Kelompok
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>

    <x-ui.confirm-dialog
        action="hapusKelompok"
        title="Hapus Kelompok"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />
</div>

