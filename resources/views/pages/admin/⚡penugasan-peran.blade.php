<?php

use App\Actions\BagiPeranAwal;
use App\Actions\RotasiPeran;
use App\Models\Level;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Penugasan Peran')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    #[Url(as: 'level', nullable: true)]
    public ?int $levelId = null;

    /**
     * State peran: [teamId][studentId] => roleId.
     *
     * @var array<int, array<int, int|string|null>>
     */
    public array $peran = [];

    /** Pratinjau rotasi: [teamId => [studentId => ['lama' => roleId|null, 'baru' => roleId|null]]] */
    public array $rotasiPratinjau = [];

    public function mount(): void
    {
        if ($this->kelasId !== null) {
            $this->kelasTerpilih();
        }

        $this->levelId ??= Level::query()->orderBy('level_number')->value('level_id');

        $this->muatPeran();
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
     * @return \Illuminate\Support\Collection<int, Level>
     */
    #[Computed]
    public function opsiLevel()
    {
        return Level::query()->orderBy('level_number')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Role>
     */
    #[Computed]
    public function opsiRole()
    {
        return Role::query()->orderBy('role_id')->get();
    }

    /**
     * Kelompok kelas terpilih beserta anggotanya.
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
            ->orderBy('team_id')
            ->get();
    }

    /**
     * Muat peran level terpilih ke dalam state.
     */
    public function muatPeran(): void
    {
        $this->peran = [];
        $this->rotasiPratinjau = [];

        if ($this->kelasId === null || $this->levelId === null) {
            return;
        }

        $teamIds = Team::query()->where('class_id', $this->kelasId)->pluck('team_id')->all();

        $assignments = RoleAssignment::query()
            ->whereIn('team_id', $teamIds)
            ->where('level_id', $this->levelId)
            ->get();

        foreach ($assignments as $assignment) {
            $this->peran[$assignment->team_id][$assignment->student_id] = $assignment->role_id;
        }
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();
        $this->muatPeran();
        unset($this->kelompok);
    }

    public function updatedLevelId(): void
    {
        $this->muatPeran();
    }

    // ---------- Validasi & simpan ----------

    /**
     * Peta nama peran => role_id untuk pencocokan aturan.
     *
     * @return array<string, int>
     */
    private function roleIdByNama(): array
    {
        return Role::query()->pluck('role_id', 'role_name')->all();
    }

    /**
     * Validasi peran satu kelompok. Mengembalikan pesan error (null bila lolos).
     *
     * @param  array<int, int|string|null>  $peranTeam
     */
    private function validasiKelompok(Team $team, array $peranTeam): ?string
    {
        $roleIds = $this->roleIdByNama();
        $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        $terisi = array_filter(
            array_intersect_key($peranTeam, array_flip($anggota)),
            fn ($roleId) => filled($roleId),
        );

        // Setiap siswa maksimal satu peran (struktur array menjamin ini), dan
        // setiap peran wajib dipegang tepat satu orang.
        $hitung = array_count_values(array_map('intval', array_values($terisi)));

        foreach (['Coder', 'Tester', 'Analyst'] as $nama) {
            $roleId = $roleIds[$nama] ?? null;

            if ($roleId === null) {
                continue;
            }

            $jumlah = $hitung[$roleId] ?? 0;

            if ($jumlah !== 1) {
                return "Peran {$nama} wajib diisi tepat satu orang di kelompok \"{$team->team_name}\".";
            }
        }

        foreach (['Reviewer', 'Bug Hunter'] as $nama) {
            $roleId = $roleIds[$nama] ?? null;

            if ($roleId === null) {
                continue;
            }

            if (($hitung[$roleId] ?? 0) > 1) {
                return "Peran {$nama} maksimal satu orang di kelompok \"{$team->team_name}\".";
            }
        }

        return null;
    }

    /**
     * Simpan peran satu kelompok.
     */
    public function simpanKelompok(int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        $this->authorize('update', $team);

        $peranTeam = $this->peran[$teamId] ?? [];
        $error = $this->validasiKelompok($team, $peranTeam);

        if ($error !== null) {
            $this->dispatch('toast', type: 'bahaya', message: $error);

            return;
        }

        try {
            $this->simpanPeranKeDb($team, $peranTeam);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'bahaya', message: $e->validator->errors()->first());

            return;
        }

        $this->dispatch('toast', type: 'sukses', message: "Peran kelompok \"{$team->team_name}\" berhasil disimpan.");
    }

    /**
     * Simpan peran semua kelompok.
     */
    public function simpanSemua(): void
    {
        $errors = [];

        foreach ($this->kelompok as $team) {
            $peranTeam = $this->peran[$team->team_id] ?? [];
            $error = $this->validasiKelompok($team, $peranTeam);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors !== []) {
            $this->dispatch('toast', type: 'bahaya', message: implode(' ', $errors));

            return;
        }

        try {
            DB::transaction(function () {
                foreach ($this->kelompok as $team) {
                    $this->simpanPeranKeDb($team, $this->peran[$team->team_id] ?? []);
                }
            });
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'bahaya', message: $e->validator->errors()->first());

            return;
        }

        $this->dispatch('toast', type: 'sukses', message: 'Semua peran berhasil disimpan.');
    }

    /**
     * @param  array<int, int|string|null>  $peranTeam
     */
    private function simpanPeranKeDb(Team $team, array $peranTeam): void
    {
        if ($this->levelId === null) {
            return;
        }

        $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        foreach ($anggota as $studentId) {
            $roleId = $peranTeam[$studentId] ?? null;

            if (! filled($roleId)) {
                continue;
            }

            $sudahAda = RoleAssignment::query()
                ->where('team_id', $team->team_id)
                ->where('student_id', $studentId)
                ->where('level_id', $this->levelId)
                ->exists();

            if ($sudahAda) {
                RoleAssignment::query()
                    ->where('team_id', $team->team_id)
                    ->where('student_id', $studentId)
                    ->where('level_id', $this->levelId)
                    ->update(['role_id' => (int) $roleId]);

                continue;
            }

            try {
                RoleAssignment::create([
                    'team_id' => $team->team_id,
                    'student_id' => $studentId,
                    'level_id' => $this->levelId,
                    'role_id' => (int) $roleId,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                throw ValidationException::withMessages([
                    'peran' => 'Siswa ini sudah punya peran di level ini. Muat ulang halaman lalu coba lagi.',
                ]);
            }
        }
    }

    // ---------- Status kartu ----------

    /**
     * Status kelengkapan peran satu kelompok: lengkap | belum_lengkap | kosong.
     */
    public function statusKelompok(int $teamId): string
    {
        $team = $this->kelompok->firstWhere('team_id', $teamId);

        if ($team === null) {
            return 'kosong';
        }

        $peranTeam = array_filter($this->peran[$teamId] ?? [], fn ($v) => filled($v));

        if ($peranTeam === []) {
            return 'kosong';
        }

        return $this->validasiKelompok($team, $this->peran[$teamId] ?? []) === null
            ? 'lengkap'
            : 'belum_lengkap';
    }

    // ---------- Rotasi dari level sebelumnya ----------

    /**
     * Level sebelumnya (null bila level terpilih adalah level 1).
     */
    private function levelSebelumnya(): ?Level
    {
        if ($this->levelId === null) {
            return null;
        }

        $level = Level::find($this->levelId);

        if ($level === null || $level->level_number <= 1) {
            return null;
        }

        return Level::query()
            ->where('level_number', '<', $level->level_number)
            ->orderByDesc('level_number')
            ->first();
    }

    public function bolehRotasi(): bool
    {
        return $this->kelasId !== null && $this->levelSebelumnya() !== null;
    }

    /**
     * Siapkan pratinjau rotasi dari level sebelumnya.
     */
    public function siapkanRotasi(): void
    {
        $levelSebelumnya = $this->levelSebelumnya();

        if ($this->kelasId === null || $levelSebelumnya === null || $this->levelId === null) {
            $this->dispatch('toast', type: 'peringatan', message: 'Rotasi hanya tersedia mulai Level 2.');

            return;
        }

        $action = new RotasiPeran;
        $this->rotasiPratinjau = [];

        $namaRole = Role::query()->pluck('role_name', 'role_id')->all();

        foreach ($this->kelompok as $team) {
            $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

            $peranLama = RoleAssignment::query()
                ->where('team_id', $team->team_id)
                ->where('level_id', $levelSebelumnya->level_id)
                ->pluck('role_id', 'student_id')
                ->all();

            $peranLama = array_map('intval', $peranLama);
            $hasil = $action->geser($anggota, $peranLama);

            foreach ($anggota as $studentId) {
                $this->rotasiPratinjau[$team->team_id][$studentId] = [
                    'lama' => $peranLama[$studentId] ?? null,
                    'baru' => $hasil[$studentId] ?? null,
                ];
            }
        }

        $this->dispatch('rotasi-open');
    }

    public function tutupRotasi(): void
    {
        $this->rotasiPratinjau = [];

        $this->dispatch('rotasi-close');
    }

    /**
     * Terapkan pratinjau rotasi ke state peran (belum disimpan).
     */
    public function terapkanRotasi(): void
    {
        if ($this->rotasiPratinjau === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Belum ada pratinjau rotasi.');

            return;
        }

        foreach ($this->rotasiPratinjau as $teamId => $anggota) {
            foreach ($anggota as $studentId => $item) {
                if ($item['baru'] !== null) {
                    $this->peran[$teamId][$studentId] = $item['baru'];
                }
            }
        }

        $this->rotasiPratinjau = [];

        $this->dispatch('rotasi-close');
        $this->dispatch('toast', type: 'info', message: 'Pratinjau rotasi diterapkan. Klik "Simpan Semua" untuk menyimpan.');
    }

    // ---------- Isi otomatis level 1 ----------

    public function isiOtomatisLevel1(): void
    {
        if ($this->kelasId === null || $this->levelId === null) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih kelas dan level terlebih dahulu.');

            return;
        }

        $level = Level::find($this->levelId);

        if ($level === null || $level->level_number !== 1) {
            $this->dispatch('toast', type: 'peringatan', message: 'Isi otomatis hanya tersedia di Level 1.');

            return;
        }

        $roleIds = $this->roleIdByNama();
        $petaRole = [];

        foreach (array_merge(BagiPeranAwal::WAJIB, BagiPeranAwal::OPSIONAL) as $nama) {
            $petaRole[$nama] = $roleIds[$nama] ?? 0;
        }

        $action = new BagiPeranAwal;

        foreach ($this->kelompok as $team) {
            $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

            $hasil = $action->bagi($anggota, $petaRole);

            foreach ($hasil as $studentId => $roleId) {
                $this->peran[$team->team_id][$studentId] = $roleId;
            }
        }

        $this->dispatch('toast', type: 'info', message: 'Peran Level 1 diisi otomatis. Klik "Simpan Semua" untuk menyimpan.');
    }

    /**
     * Matriks siswa × level untuk pemeriksaan cepat.
     *
     * @return array<int, array{student: Student, peran: array<int, string|null>}>
     */
    #[Computed]
    public function matriks(): array
    {
        if ($this->kelasId === null) {
            return [];
        }

        $levels = $this->opsiLevel;
        $namaRole = Role::query()->pluck('role_name', 'role_id')->all();

        $assignments = RoleAssignment::query()
            ->whereIn('team_id', Team::query()->where('class_id', $this->kelasId)->select('team_id'))
            ->get()
            ->groupBy('student_id');

        return Student::query()
            ->where('class_id', $this->kelasId)
            ->orderBy('full_name')
            ->get()
            ->map(function (Student $student) use ($levels, $namaRole, $assignments): array {
                $perLevel = [];

                foreach ($levels as $level) {
                    $cocok = $assignments->get($student->student_id)?->firstWhere('level_id', $level->level_id);
                    $perLevel[$level->level_id] = $cocok !== null
                        ? ($namaRole[$cocok->role_id] ?? '—')
                        : null;
                }

                return ['student' => $student, 'peran' => $perLevel];
            })
            ->all();
    }
}; ?>

<div>
    <x-ui.page-header title="Penugasan Peran" description="Tetapkan peran Coder, Tester, Analyst, Reviewer, dan Bug Hunter per kelompok di tiap level.">
        <x-slot:actions>
            @if ($kelasId !== null && $levelId !== null)
                <x-ui.button variant="outline" wire:click="siapkanRotasi" @disabled(! $this->bolehRotasi())>
                    <x-ui.icon.rotate class="size-4" />
                    Rotasi dari Level Sebelumnya
                </x-ui.button>
                <x-ui.button variant="outline" wire:click="isiOtomatisLevel1">
                    <x-ui.icon.refresh class="size-4" />
                    Isi Otomatis
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="simpanSemua">
                    <x-ui.icon.check class="size-4" />
                    Simpan Semua
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
                message="Buat kelas dan kelompok terlebih dahulu sebelum menetapkan peran."
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
                    label="Kelas"
                    name="kelasId"
                    :options="$this->opsiKelas"
                    placeholder="— Pilih kelas —"
                    wire:model.live="kelasId"
                />
                <x-ui.select
                    label="Level"
                    name="levelId"
                    :options="$this->opsiLevel->pluck('level_name', 'level_id')->all()"
                    wire:model.live="levelId"
                />
            </div>
        </x-ui.card>

        @if ($kelasId === null || $levelId === null)
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="id-badge-2"
                    title="Pilih kelas dan level"
                    message="Peran ditetapkan per kelompok untuk setiap level permainan."
                />
            </x-ui.card>
        @elseif ($this->kelompok->isEmpty())
            <x-ui.card :padding="false">
                <x-ui.empty-state
                    icon="users-group"
                    title="Belum ada kelompok di kelas ini"
                    message="Bentuk kelompok terlebih dahulu, lalu kembali ke halaman ini untuk menetapkan peran."
                >
                    <x-slot:actions>
                        <x-ui.button variant="primary" :href="route('admin.kelompok', ['kelas' => $kelasId])" wire:navigate>
                            <x-ui.icon.plus class="size-4" />
                            Buka Halaman Kelompok
                        </x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($this->kelompok as $team)
                    @php
                        $status = $this->statusKelompok($team->team_id);
                        $badge = match ($status) {
                            'lengkap' => ['sukses', 'Lengkap'],
                            'belum_lengkap' => ['peringatan', 'Belum lengkap'],
                            default => ['netral', 'Belum diatur'],
                        };
                    @endphp

                    <x-ui.card wire:key="peran-{{ $team->team_id }}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate font-semibold text-slate-800">{{ $team->team_name }}</p>
                            <x-ui.badge :variant="$badge[0]">{{ $badge[1] }}</x-ui.badge>
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ($team->teamMembers as $member)
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$member->student?->full_name ?? '?'" size="sm" />
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ $member->student?->full_name ?? '—' }}</span>

                                    <select
                                        wire:model="peran.{{ $team->team_id }}.{{ $member->student_id }}"
                                        class="rounded-lg border-line py-1.5 text-sm text-slate-700 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                    >
                                        <option value="">— Belum diatur —</option>
                                        @foreach ($this->opsiRole as $role)
                                            <option value="{{ $role->role_id }}">{{ $role->role_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>

                        <x-ui.button variant="soft" size="sm" class="mt-4" wire:click="simpanKelompok({{ $team->team_id }})">
                            <x-ui.icon.check class="size-4" />
                            Simpan Kelompok Ini
                        </x-ui.button>
                    </x-ui.card>
                @endforeach
            </div>

            <details class="mt-6">
                <summary class="cursor-pointer text-sm font-medium text-slate-600 transition hover:text-primary-700">
                    Ringkasan matriks siswa × level
                </summary>

                <x-ui.card :padding="false" class="mt-3">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead class="bg-page/60">
                                <tr>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Siswa</th>
                                    @foreach ($this->opsiLevel as $level)
                                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $level->level_name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-white">
                                @foreach ($this->matriks as $baris)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-slate-700">{{ $baris['student']->full_name }}</td>
                                        @foreach ($this->opsiLevel as $level)
                                            @php $peran = $baris['peran'][$level->level_id] ?? null; @endphp
                                            <td class="px-4 py-2">
                                                @if ($peran !== null)
                                                    <x-ui.badge variant="info">{{ $peran }}</x-ui.badge>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            </details>
        @endif
    @endif

    {{-- Pratinjau rotasi (modal Alpine karena menampilkan tabel perbandingan). --}}
    <div
        x-data="{ open: false }"
        x-on:rotasi-open.window="open = true"
        x-on:rotasi-close.window="open = false"
        x-on:keydown.escape.window="open = false"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="open = false"></div>

        <div class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4">
            <div class="my-auto w-full max-w-3xl rounded-xl border border-line bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Pratinjau Rotasi Peran</h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                        <x-ui.icon.x />
                    </button>
                </div>

                <div class="space-y-4 px-5 py-4">
                    <x-ui.alert variant="info">
                        Peran digeser melingkar mengikuti urutan anggota kelompok dari level sebelumnya.
                        Pratinjau belum disimpan — klik "Terapkan" lalu "Simpan Semua".
                    </x-ui.alert>

                    @php
                        $namaRole = $this->opsiRole->pluck('role_name', 'role_id')->all();
                        $namaSiswa = \App\Models\Student::query()
                            ->where('class_id', $kelasId)
                            ->pluck('full_name', 'student_id')
                            ->all();
                    @endphp

                    <div class="max-h-80 overflow-y-auto rounded-lg border border-line">
                        <table class="min-w-full divide-y divide-line text-sm">
                            <thead class="bg-page/60">
                                <tr>
                                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Siswa</th>
                                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Peran Lama</th>
                                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Peran Baru</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-white">
                                @foreach ($rotasiPratinjau as $teamId => $anggota)
                                    @php $team = $this->kelompok->firstWhere('team_id', $teamId); @endphp
                                    @foreach ($anggota as $studentId => $item)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-500">{{ $team?->team_name ?? '—' }}</td>
                                            <td class="px-3 py-2 text-slate-700">{{ $namaSiswa[$studentId] ?? '#'.$studentId }}</td>
                                            <td class="px-3 py-2">
                                                @if ($item['lama'] !== null)
                                                    <x-ui.badge variant="netral">{{ $namaRole[$item['lama']] ?? '—' }}</x-ui.badge>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                @if ($item['baru'] !== null)
                                                    <x-ui.badge variant="sukses">{{ $namaRole[$item['baru']] ?? '—' }}</x-ui.badge>
                                                @else
                                                    <span class="text-slate-300">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                    <x-ui.button variant="outline" wire:click="tutupRotasi">Batal</x-ui.button>
                    <x-ui.button variant="primary" wire:click="terapkanRotasi" @disabled($rotasiPratinjau === [])>
                        Terapkan ke Form
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</div>


