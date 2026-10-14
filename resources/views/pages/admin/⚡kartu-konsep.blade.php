<?php

use App\Livewire\Forms\KartuKonsepForm;
use App\Models\ConceptCard;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Kartu Konsep')]
class extends Component
{
    public KartuKonsepForm $form;

    /**
     * Kelima kartu konsep beserta indikator kelengkapan isi.
     *
     * @return \Illuminate\Support\Collection<int, ConceptCard>
     */
    #[Computed]
    public function kartu()
    {
        return ConceptCard::query()->orderBy('concept_id')->get();
    }

    public function bukaTambah(): void
    {
        $this->authorize('create', ConceptCard::class);

        $this->form->resetForm();
        $this->resetValidation();

        $this->dispatch('modal-konsep-open');
    }

    public function bukaUbah(int $conceptId): void
    {
        $kartu = ConceptCard::findOrFail($conceptId);

        $this->authorize('update', $kartu);

        $this->form->setKartu($kartu);
        $this->resetValidation();

        $this->dispatch('modal-konsep-open');
    }

    public function simpanKartu(): void
    {
        $data = $this->form->validate();

        if ($this->form->conceptId === null) {
            $this->authorize('create', ConceptCard::class);

            ConceptCard::create([
                'concept_name' => $data['concept_name'],
                'function_desc' => $data['function_desc'] ?? null,
                'syntax_example' => $data['syntax_example'] ?? null,
                'when_to_use' => $data['when_to_use'] ?? null,
                'common_pitfalls' => $data['common_pitfalls'] ?? null,
                'trigger_question' => $data['trigger_question'] ?? null,
            ]);

            $pesan = 'Kartu konsep berhasil ditambahkan.';
        } else {
            $kartu = ConceptCard::findOrFail($this->form->conceptId);

            $this->authorize('update', $kartu);

            $kartu->fill([
                'concept_name' => $data['concept_name'],
                'function_desc' => $data['function_desc'] ?? null,
                'syntax_example' => $data['syntax_example'] ?? null,
                'when_to_use' => $data['when_to_use'] ?? null,
                'common_pitfalls' => $data['common_pitfalls'] ?? null,
                'trigger_question' => $data['trigger_question'] ?? null,
            ])->save();

            $pesan = 'Kartu konsep berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->kartu);

        $this->dispatch('modal-konsep-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    /**
     * Jumlah bidang isi yang sudah terisi (untuk indikator kelengkapan).
     */
    public function kelengkapan(ConceptCard $kartu): int
    {
        $bidang = [
            $kartu->function_desc,
            $kartu->syntax_example,
            $kartu->when_to_use,
            $kartu->common_pitfalls,
            $kartu->trigger_question,
        ];

        return count(array_filter($bidang, fn ($nilai) => filled($nilai)));
    }
}; ?>

<div>
    <x-ui.page-header title="Kartu Konsep" description="Kelola isi kelima kartu konsep yang dipakai siswa saat menyusun rencana misi.">
        <x-slot:actions>
            @if (auth()->user()->isAdmin())
                <x-ui.button variant="primary" wire:click="bukaTambah">
                    <x-ui.icon.plus class="size-4" />
                    Tambah Kartu
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert variant="info" class="mb-4">
        Perubahan materi berlaku untuk semua kelas dan semua guru.
        Jumlah kartu tetap <strong>5</strong> karena dipakai oleh rencana misi, sehingga tidak ada tombol hapus.
    </x-ui.alert>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->kartu as $kartu)
            @php
                $ikon = config('materi.concept_icons')[$kartu->concept_name] ?? config('materi.concept_icon_default', 'cards');
                $lengkap = $this->kelengkapan($kartu);
                $variant = $lengkap === 5 ? 'sukses' : ($lengkap >= 3 ? 'peringatan' : 'bahaya');
            @endphp

            <x-ui.card wire:key="konsep-{{ $kartu->concept_id }}" class="flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-600">
                        <x-dynamic-component :component="'ui.icon.'.$ikon" />
                    </span>
                    <x-ui.badge :variant="$variant">{{ $lengkap }}/5 terisi</x-ui.badge>
                </div>

                <p class="mt-3 font-semibold text-slate-800">{{ $kartu->concept_name }}</p>

                <p class="mt-1.5 flex-1 text-sm leading-relaxed text-slate-500">
                    {{ filled($kartu->function_desc) ? \Illuminate\Support\Str::limit($kartu->function_desc, 110) : 'Belum ada ringkasan fungsi.' }}
                </p>

                <x-ui.button variant="soft" size="sm" class="mt-4" wire:click="bukaUbah({{ $kartu->concept_id }})">
                    <x-ui.icon.pencil class="size-4" />
                    Ubah Isi Kartu
                </x-ui.button>
            </x-ui.card>
        @endforeach
    </div>

    {{-- Modal ubah isi kartu + pratinjau gaya kartu cetak --}}
    <x-ui.modal name="modal-konsep" title="Isi Kartu Konsep">
        <div class="grid gap-5 lg:grid-cols-2">
            <form wire:submit="simpanKartu" class="space-y-4">
                <x-ui.input label="Nama Kartu" name="form.concept_name" wire:model="form.concept_name" required autofocus />

                <x-ui.textarea
                    label="Ringkasan Fungsi"
                    name="form.function_desc"
                    rows="3"
                    wire:model="form.function_desc"
                    placeholder="Kegunaan konsep ini dalam satu-dua kalimat."
                />

                <x-ui.textarea
                    label="Contoh Sintaks"
                    name="form.syntax_example"
                    rows="4"
                    wire:model="form.syntax_example"
                    class="font-mono text-xs"
                    placeholder="saldo = 10_000_000"
                />

                <x-ui.textarea
                    label="Kapan Dipakai"
                    name="form.when_to_use"
                    rows="3"
                    wire:model="form.when_to_use"
                    placeholder="Situasi yang cocok memakai konsep ini."
                />

                <x-ui.textarea
                    label="Jebakan Umum"
                    name="form.common_pitfalls"
                    rows="3"
                    wire:model="form.common_pitfalls"
                    placeholder="Kesalahan yang sering terjadi."
                />

                <x-ui.textarea
                    label="Pertanyaan Pemicu"
                    name="form.trigger_question"
                    rows="2"
                    wire:model="form.trigger_question"
                    placeholder="Pertanyaan untuk memancing diskusi kelompok."
                />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" x-on:click="$dispatch('modal-konsep-close')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit">Simpan Kartu</x-ui.button>
                </div>
            </form>

            {{-- Pratinjau Kartu Konsep --}}
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pratinjau Kartu</p>

                <div class="overflow-hidden rounded-2xl border-2 border-primary-200 bg-white shadow-lg shadow-primary-900/10">
                    <div class="bg-primary-600 px-5 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-primary-100">Kartu Konsep</p>
                        <p class="mt-1 text-lg font-bold text-white">{{ $form->concept_name !== '' ? $form->concept_name : 'Nama Kartu' }}</p>
                    </div>

                    <div class="space-y-4 p-5 text-sm">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Fungsi</p>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ filled($form->function_desc) ? $form->function_desc : '—' }}</p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Sintaks</p>
                            <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-3 font-mono text-xs leading-relaxed text-slate-100">{{ filled($form->syntax_example) ? $form->syntax_example : '—' }}</pre>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kapan Dipakai</p>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ filled($form->when_to_use) ? $form->when_to_use : '—' }}</p>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jebakan Umum</p>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ filled($form->common_pitfalls) ? $form->common_pitfalls : '—' }}</p>
                        </div>

                        <div class="rounded-xl border border-dashed border-primary-300 bg-primary-50/60 px-4 py-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">Pertanyaan Pemicu</p>
                            <p class="mt-1 italic text-primary-800">{{ filled($form->trigger_question) ? $form->trigger_question : '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui.modal>
</div>
