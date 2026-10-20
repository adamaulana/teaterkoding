<?php

use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Kontrol Kelas')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public int $editorClassId = 0;

    public int $editorLevelId = 0;

    public ?string $waktuTutup = null;

    public string $pesanBukaRencana = '';

    public ?int $teamRencanaId = null;

    // ---- Rilis Kartu Bug ----
    public ?int $rilisTeamId = null;

    public ?int $rilisLevelId = null;

    public ?int $rilisBugId = null;

    /** @return array<int, string> */
    #[Computed]
    public function opsiKelas(): array
    {
        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->orderBy('class_name')
            ->pluck('class_name', 'class_id')
            ->all();
    }

    /** @return Collection<int, SchoolClass> */
    #[Computed]
    public function kelasList(): Collection
    {
        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->when($this->kelasId !== null, fn ($q) => $q->where('class_id', $this->kelasId))
            ->orderBy('class_name')
            ->get();
    }

    /** @return Collection<int, Level> */
    #[Computed]
    public function levelList(): Collection
    {
        return Level::query()->orderBy('level_number')->get();
    }

    /**
     * Peta kelas_id|level_id → ClassLevelAccess.
     *
     * @return Collection<string, ClassLevelAccess>
     */
    #[Computed]
    public function aksesMap(): Collection
    {
        $classIds = $this->kelasList->pluck('class_id')->all();

        return ClassLevelAccess::query()
            ->whereIn('class_id', $classIds)
            ->get()
            ->keyBy(fn (ClassLevelAccess $a) => $a->class_id.'|'.$a->level_id);
    }

    /**
     * Kelompok yang punya setidaknya satu rencana terkunci.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function daftarTim(): Collection
    {
        $classIds = $this->kelasList->pluck('class_id')->all();

        return Team::query()
            ->whereIn('class_id', $classIds)
            ->with(['schoolClass', 'missionPlans' => fn ($q) => $q->whereNotNull('locked_at')->with('mission')])
            ->orderBy('team_name')
            ->get()
            ->filter(fn (Team $team) => $team->missionPlans->isNotEmpty())
            ->values();
    }

    public function updatedKelasId(): void
    {
        unset($this->kelasList, $this->aksesMap, $this->daftarTim);
    }

    public function aksesUntuk(int $classId, int $levelId): ?ClassLevelAccess
    {
        return $this->aksesMap->get($classId.'|'.$levelId);
    }

    // ---------- Matriks buka/tutup ----------

    public function toggleLevel(int $classId, int $levelId): void
    {
        $this->pastikanBolehKelola($classId);

        $akses = ClassLevelAccess::query()
            ->where('class_id', $classId)
            ->where('level_id', $levelId)
            ->first();

        if ($akses !== null) {
            $akses->update(['is_open' => ! $akses->is_open]);
        } else {
            ClassLevelAccess::create(['class_id' => $classId, 'level_id' => $levelId, 'is_open' => true]);
        }

        unset($this->aksesMap);

        $this->dispatch('toast', type: 'sukses', message: 'Status level diperbarui.');
    }

    public function bukaSemua(): void
    {
        $this->setSemua(true);
        $this->dispatch('toast', type: 'sukses', message: 'Semua level dibuka.');
    }

    public function tutupSemua(): void
    {
        $this->setSemua(false);
        $this->dispatch('toast', type: 'sukses', message: 'Semua level ditutup.');
    }

    private function setSemua(bool $buka): void
    {
        foreach ($this->kelasList as $kelas) {
            foreach ($this->levelList as $level) {
                ClassLevelAccess::updateOrCreate(
                    ['class_id' => $kelas->class_id, 'level_id' => $level->level_id],
                    ['is_open' => $buka],
                );
            }
        }

        unset($this->aksesMap);
    }

    // ---------- Waktu tutup ----------

    public function bukaWaktu(int $classId, int $levelId): void
    {
        $this->pastikanBolehKelola($classId);

        $akses = $this->aksesUntuk($classId, $levelId);

        $this->editorClassId = $classId;
        $this->editorLevelId = $levelId;
        $this->waktuTutup = $akses?->closes_at?->format('Y-m-d\TH:i');

        $this->dispatch('modal-waktu-open');
    }

    public function simpanWaktuTutup(): void
    {
        $this->validate(['waktuTutup' => ['nullable', 'date']]);

        $this->pastikanBolehKelola($this->editorClassId);

        $akses = ClassLevelAccess::query()
            ->where('class_id', $this->editorClassId)
            ->where('level_id', $this->editorLevelId)
            ->first();

        if ($akses === null) {
            $akses = new ClassLevelAccess([
                'class_id' => $this->editorClassId,
                'level_id' => $this->editorLevelId,
                'is_open' => false,
            ]);
        }

        $akses->closes_at = $this->waktuTutup !== null && $this->waktuTutup !== ''
            ? Carbon::parse($this->waktuTutup)
            : null;
        $akses->save();

        unset($this->aksesMap);

        $this->dispatch('modal-waktu-close');
        $this->dispatch('toast', type: 'sukses', message: 'Waktu tutup disimpan.');
    }

    public function hapusWaktuTutup(int $classId, int $levelId): void
    {
        $this->pastikanBolehKelola($classId);

        $akses = $this->aksesUntuk($classId, $levelId);

        if ($akses !== null) {
            $akses->update(['closes_at' => null]);
        }

        unset($this->aksesMap);

        $this->dispatch('toast', type: 'sukses', message: 'Waktu tutup dihapus.');
    }

    // ---------- Buka kembali rencana ----------

    public function mintaBukaRencana(int $teamId): void
    {
        $team = Team::with('schoolClass')->findOrFail($teamId);

        $this->pastikanBolehKelola($team->class_id);

        $jumlah = MissionPlan::query()
            ->where('team_id', $team->team_id)
            ->whereNotNull('locked_at')
            ->count();

        $this->teamRencanaId = $teamId;
        $this->pesanBukaRencana = "Buka kembali {$jumlah} rencana yang terkunci di kelompok {$team->team_name}?";

        $this->dispatch('konfirmasi-rencana-buka');
    }

    public function bukaRencana(): void
    {
        if ($this->teamRencanaId === null) {
            return;
        }

        $team = Team::with('schoolClass')->findOrFail($this->teamRencanaId);

        $this->pastikanBolehKelola($team->class_id);

        MissionPlan::query()
            ->where('team_id', $team->team_id)
            ->whereNotNull('locked_at')
            ->update(['locked_at' => null]);

        unset($this->daftarTim);
        $this->teamRencanaId = null;

        $this->dispatch('toast', type: 'sukses', message: 'Rencana berhasil dibuka kembali.');
    }

    private function pastikanBolehKelola(int $classId): SchoolClass
    {
        $kelas = SchoolClass::findOrFail($classId);

        abort_unless(auth()->user()->can('update', $kelas), 403);

        return $kelas;
    }

    // ---------- Rilis Kartu Bug ----------

    /** @return array<int, string> */
    #[Computed]
    public function opsiLevelRilis(): array
    {
        return Level::query()->orderBy('level_number')->pluck('level_name', 'level_id')->all();
    }

    /** @return Collection<int, \App\Models\BugCard> */
    #[Computed]
    public function kartuBelumDirilis(): Collection
    {
        if ($this->rilisTeamId === null || $this->rilisLevelId === null) {
            return collect();
        }

        $team = Team::with('schoolClass')->findOrFail($this->rilisTeamId);

        $this->pastikanBolehKelola($team->class_id);

        return app(\App\Services\BugService::class)->kartuBelumDirilis($team, $this->rilisLevelId);
    }

    public function bukaRilis(int $teamId): void
    {
        $team = Team::with('schoolClass')->findOrFail($teamId);

        $this->pastikanBolehKelola($team->class_id);

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

        $team = Team::with('schoolClass')->findOrFail((int) $this->rilisTeamId);

        $this->pastikanBolehKelola($team->class_id);

        $kartu = \App\Models\BugCard::findOrFail((int) $this->rilisBugId);

        $hasil = app(\App\Services\BugService::class)->rilis($team, $kartu, auth()->user());

        unset($this->kartuBelumDirilis);

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

<div>
    <x-ui.page-header title="Kontrol Kelas" description="Bukakan/tutup level per kelas dan atur ulang rencana yang terkunci.">
        <x-slot:actions>
            <x-ui.button variant="outline" wire:click="bukaSemua">
                <x-ui.icon.lock class="size-4" />
                Buka Semua
            </x-ui.button>
            <x-ui.button variant="danger" wire:click="tutupSemua">
                Tutup Semua
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

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

    <x-ui.card :padding="false" class="mb-6">
        <div class="border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Matriks Buka Level</p>
            <p class="text-xs text-slate-400">Klik sel untuk membuka/menutup level. Waktu tutup bersifat opsional.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-page/60">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Kelas</th>
                        @foreach ($this->levelList as $level)
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">
                                {{ $level->level_name }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-white">
                    @forelse ($this->kelasList as $kelas)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $kelas->class_name }}</td>
                            @foreach ($this->levelList as $level)
                                @php
                                    $akses = $this->aksesUntuk($kelas->class_id, $level->level_id);
                                    $terbuka = $akses !== null && $akses->is_open;
                                @endphp
                                <td class="px-3 py-3 text-center">
                                    <button
                                        type="button"
                                        wire:click="toggleLevel({{ $kelas->class_id }}, {{ $level->level_id }})"
                                        title="{{ $terbuka ? 'Klik untuk menutup' : 'Klik untuk membuka' }}"
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition {{ $terbuka ? 'bg-primary-50 text-primary-700 ring-primary-200 hover:bg-primary-100' : 'bg-slate-100 text-slate-600 ring-slate-200 hover:bg-slate-200' }}"
                                    >
                                        <x-dynamic-component :component="'ui.icon.'.($terbuka ? 'circle-check' : 'lock')" class="size-3.5" />
                                        {{ $terbuka ? 'Buka' : 'Tutup' }}
                                    </button>

                                    <div class="mt-2 flex flex-col items-center gap-1">
                                        @if ($akses?->closes_at)
                                            <span class="text-[11px] text-slate-400">Tutup: {{ $akses->closes_at->format('d/m H:i') }}</span>
                                        @endif
                                        <button type="button" wire:click="bukaWaktu({{ $kelas->class_id }}, {{ $level->level_id }})" class="text-[11px] font-medium text-primary-600 hover:text-primary-700">
                                            {{ $akses?->closes_at ? 'Ubah waktu' : 'Atur waktu tutup' }}
                                        </button>
                                        @if ($akses?->closes_at)
                                            <button type="button" wire:click="hapusWaktuTutup({{ $kelas->class_id }}, {{ $level->level_id }})" class="text-[11px] text-rose-500 hover:text-rose-600">
                                                Hapus
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->levelList->count() + 1 }}" class="px-4 py-12 text-center text-sm text-slate-400">
                                Belum ada kelas yang bisa dikelola.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card :padding="false">
        <div class="border-b border-line px-4 py-3">
            <p class="text-sm font-semibold text-slate-800">Rencana Terkunci</p>
            <p class="text-xs text-slate-400">Buka kembali rencana bila kelompok salah pilih kartu konsep.</p>
        </div>

        @if ($this->daftarTim->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-slate-400">Tidak ada rencana yang terkunci.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->daftarTim as $tim)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ $tim->team_name }}</p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $tim->schoolClass?->class_name }}
                                · {{ $tim->missionPlans->count() }} rencana terkunci
                            </p>
                        </div>
                        <x-ui.button variant="outline" size="sm" wire:click="mintaBukaRencana({{ $tim->team_id }})">
                            <x-ui.icon.refresh class="size-4" />
                            Buka Kembali Rencana
                        </x-ui.button>
                        <x-ui.button variant="outline" size="sm" wire:click="bukaRilis({{ $tim->team_id }})">
                            <x-ui.icon.bug class="size-4" />
                            Rilis Kartu Bug
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.modal name="modal-waktu" title="Atur Waktu Tutup Level">
        <form wire:submit="simpanWaktuTutup" class="space-y-4">
            <input
                type="datetime-local"
                wire:model="waktuTutup"
                class="block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            >
            <p class="text-xs text-slate-400">Kosongkan untuk menghapus waktu tutup (level tetap terbuka sampai ditutup manual).</p>

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" type="button" x-on:click="$dispatch('modal-waktu-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="bukaRencana"
        event="konfirmasi-rencana-buka"
        title="Buka Kembali Rencana"
        property="pesanBukaRencana"
        confirm-label="Ya, buka kembali"
    />

    @include('pages.admin.partials.rilis-bug')
</div>