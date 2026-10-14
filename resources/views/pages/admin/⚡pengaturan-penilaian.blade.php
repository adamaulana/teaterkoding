<?php

use App\Models\GradingSetting;
use App\Models\SchoolClass;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan Penilaian — bobot tiap komponen nilai sumatif per kelas.
 *
 * Total bobot WAJIB tepat 100. Validasi dilakukan di aplikasi (bukan
 * constraint DB) agar pesan kesalahannya ramah untuk guru. Total dihitung
 * langsung saat guru mengetik, tanpa menyimpan dulu.
 */
new #[Layout('layouts.admin'), Title('Pengaturan Penilaian')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public int $w_individual = 50;

    public int $w_team = 20;

    public int $w_bug_plan = 10;

    public int $w_reflection = 10;

    public int $w_observation = 10;

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->muatBobot();
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
     * Isi properti bobot dari baris tersimpan, atau dari default config.
     */
    private function muatBobot(): void
    {
        $kelas = $this->kelasTerpilih();

        $bobot = $kelas !== null ? $kelas->bobotEfektif() : GradingSetting::bobotDefault();

        $this->w_individual = (int) ($bobot['w_individual'] ?? 50);
        $this->w_team = (int) ($bobot['w_team'] ?? 20);
        $this->w_bug_plan = (int) ($bobot['w_bug_plan'] ?? 10);
        $this->w_reflection = (int) ($bobot['w_reflection'] ?? 10);
        $this->w_observation = (int) ($bobot['w_observation'] ?? 10);
    }

    public function updatedKelasId(): void
    {
        $this->muatBobot();
        $this->resetValidation();

        unset($this->total, $this->tersimpan);
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
     * Label komponen (dari config, agar konsisten dengan halaman lain).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function label(): array
    {
        return config('teater.komponen_labels', [
            'individu' => 'Fungsi Individu',
            'kelompok' => 'Kelompok',
            'bug_plan' => 'Kartu Bug & Rencana',
            'refleksi' => 'Refleksi/Rubrik',
            'observasi' => 'Observasi',
        ]);
    }

    /**
     * Total bobot saat ini (harus tepat 100 saat disimpan).
     */
    #[Computed]
    public function total(): int
    {
        return $this->w_individual + $this->w_team + $this->w_bug_plan + $this->w_reflection + $this->w_observation;
    }

    /**
     * Apakah kelas sudah punya baris bobot tersimpan (bukan default).
     */
    #[Computed]
    public function tersimpan(): bool
    {
        if ($this->kelasId === null) {
            return false;
        }

        return GradingSetting::query()->where('class_id', $this->kelasId)->exists();
    }

    /**
     * Isi ulang form dengan bobot default (belum disimpan sampai ditekan Simpan).
     */
    public function pakaiDefault(): void
    {
        $default = GradingSetting::bobotDefault();

        $this->w_individual = (int) $default['w_individual'];
        $this->w_team = (int) $default['w_team'];
        $this->w_bug_plan = (int) $default['w_bug_plan'];
        $this->w_reflection = (int) $default['w_reflection'];
        $this->w_observation = (int) $default['w_observation'];

        $this->resetValidation();
    }

    /**
     * Simpan bobot kelas. Total harus tepat 100.
     */
    public function simpan(): void
    {
        $this->kelasTerpilih();

        $this->validate([
            'w_individual' => ['required', 'integer', 'min:0', 'max:100'],
            'w_team' => ['required', 'integer', 'min:0', 'max:100'],
            'w_bug_plan' => ['required', 'integer', 'min:0', 'max:100'],
            'w_reflection' => ['required', 'integer', 'min:0', 'max:100'],
            'w_observation' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if ($this->total !== 100) {
            $this->addError('w_individual', 'Total bobot harus tepat 100%. Saat ini '.$this->total.'%.');

            return;
        }

        if ($this->kelasId === null) {
            return;
        }

        GradingSetting::updateOrCreate(
            ['class_id' => $this->kelasId],
            [
                'w_individual' => $this->w_individual,
                'w_team' => $this->w_team,
                'w_bug_plan' => $this->w_bug_plan,
                'w_reflection' => $this->w_reflection,
                'w_observation' => $this->w_observation,
            ],
        );

        unset($this->total, $this->tersimpan);

        $this->dispatch('toast', type: 'sukses', message: 'Bobot penilaian berhasil disimpan.');
    }
}; ?>

<div>
    <x-ui.page-header
        title="Pengaturan Penilaian"
        description="Atur bobot tiap komponen nilai sumatif untuk kelas yang dipilih."
    />

    @if (count($this->opsiKelas) === 0)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk mengatur bobot penilaian."
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
        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card class="lg:col-span-2">
                <form wire:submit="simpan" class="space-y-5">
                    <x-ui.select
                        label="Kelas"
                        name="kelasId"
                        wire:model.live="kelasId"
                        :options="$this->opsiKelas"
                        placeholder="Pilih kelas"
                        help="Bobot berlaku untuk semua perhitungan nilai sumatif kelas ini."
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input
                            label="{{ $this->label['individu'] }} (%)"
                            name="w_individual"
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="w_individual"
                        />
                        <x-ui.input
                            label="{{ $this->label['kelompok'] }} (%)"
                            name="w_team"
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="w_team"
                        />
                        <x-ui.input
                            label="{{ $this->label['bug_plan'] }} (%)"
                            name="w_bug_plan"
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="w_bug_plan"
                        />
                        <x-ui.input
                            label="{{ $this->label['refleksi'] }} (%)"
                            name="w_reflection"
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="w_reflection"
                        />
                        <x-ui.input
                            label="{{ $this->label['observasi'] }} (%)"
                            name="w_observation"
                            type="number"
                            min="0"
                            max="100"
                            wire:model.live="w_observation"
                        />
                    </div>

                    {{-- Total dihitung langsung tanpa menyimpan --}}
                    <div @class([
                        'rounded-xl border p-4',
                        'border-primary-200 bg-primary-50' => $this->total === 100,
                        'border-rose-200 bg-rose-50' => $this->total !== 100,
                    ])>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Total Bobot</p>
                                <p class="text-xs text-slate-500">Harus tepat 100% sebelum dapat disimpan.</p>
                            </div>
                            <p @class([
                                'text-2xl font-semibold tabular-nums',
                                'text-primary-700' => $this->total === 100,
                                'text-rose-700' => $this->total !== 100,
                            ])>{{ $this->total }}%</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <x-ui.button variant="outline" wire:click="pakaiDefault">Pakai Bobot Default</x-ui.button>
                        <x-ui.button variant="primary" type="submit" @disabled($this->total !== 100)>
                            Simpan Bobot
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <div class="space-y-4">
                <x-ui.card>
                    <p class="text-sm font-semibold text-slate-800">Status Kelas</p>
                    <dl class="mt-3 space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Sumber bobot</dt>
                            <dd>
                                @if ($this->tersimpan)
                                    <x-ui.badge variant="sukses">Tersimpan</x-ui.badge>
                                @else
                                    <x-ui.badge variant="netral">Default</x-ui.badge>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Total</dt>
                            <dd>
                                <x-ui.badge :variant="$this->total === 100 ? 'sukses' : 'bahaya'">
                                    {{ $this->total }}%
                                </x-ui.badge>
                            </dd>
                        </div>
                    </dl>
                </x-ui.card>

                <x-ui.alert variant="info" title="Cara nilai akhir dihitung">
                    Nilai akhir = jumlah (nilai komponen × bobot komponen) ÷ 100.
                    Komponen refleksi &amp; observasi diisi guru pada halaman
                    Rekap Nilai Sumatif; tiga komponen lain dihitung otomatis dari
                    data permainan.
                </x-ui.alert>
            </div>
        </div>
    @endif
</div>

