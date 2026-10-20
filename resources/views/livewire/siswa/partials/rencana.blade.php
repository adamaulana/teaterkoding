<x-ui.card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">Rencana</h2>
            <p class="text-sm text-slate-500">Pilih kartu konsep yang akan kalian pakai, lalu kunci rencana.</p>
        </div>
        @if ($this->rencanaTerkunci)
            <x-ui.badge variant="sukses">Terkunci</x-ui.badge>
        @else
            <x-ui.badge variant="peringatan">Belum dikunci</x-ui.badge>
        @endif
    </div>

    @if ($this->rencanaTerkunci)
        <x-ui.alert variant="info" class="mt-4">
            Rencana sudah dikunci dan tidak bisa diubah.
        </x-ui.alert>
    @endif

    {{-- Lima kartu konsep --}}
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->kartuKonsep as $kartu)
            @php $terpilih = in_array($kartu->concept_id, $this->dipilih, true); @endphp
            <button
                type="button"
                wire:click="toggleKonsep({{ $kartu->concept_id }})"
                @disabled($this->rencanaTerkunci)
                @class([
                    'rounded-xl border p-4 text-start transition',
                    'border-primary-300 bg-primary-50 ring-2 ring-primary-300' => $terpilih,
                    'border-line bg-white hover:border-primary-200' => ! $terpilih,
                    'cursor-not-allowed opacity-70' => $this->rencanaTerkunci,
                ])
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-slate-800">{{ $kartu->concept_name }}</p>
                    @if ($terpilih)
                        <x-ui.icon.circle-check class="size-5 text-primary-600" />
                    @endif
                </div>
                @if (filled($kartu->function_desc))
                    <p class="mt-1 text-xs text-slate-500">{{ $kartu->function_desc }}</p>
                @endif

                {{-- Detail kartu --}}
                @if (filled($kartu->syntax_example) || filled($kartu->when_to_use) || filled($kartu->common_pitfalls) || filled($kartu->trigger_question))
                    <div class="mt-2 space-y-1 border-t border-line/70 pt-2 text-xs text-slate-500">
                        @if (filled($kartu->syntax_example))
                            <p><span class="font-medium text-slate-600">Contoh:</span> <code class="rounded bg-slate-100 px-1 font-mono">{{ $kartu->syntax_example }}</code></p>
                        @endif
                        @if (filled($kartu->when_to_use))
                            <p><span class="font-medium text-slate-600">Kapan dipakai:</span> {{ $kartu->when_to_use }}</p>
                        @endif
                        @if (filled($kartu->common_pitfalls))
                            <p><span class="font-medium text-slate-600">Sering salah:</span> {{ $kartu->common_pitfalls }}</p>
                        @endif
                        @if (filled($kartu->trigger_question))
                            <p class="italic text-slate-400">{{ $kartu->trigger_question }}</p>
                        @endif
                    </div>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Urutan langkah --}}
    <div class="mt-5">
        <label for="stepsNote" class="mb-1.5 block text-sm font-semibold text-slate-700">Urutan langkah (opsional, maks 1000 karakter)</label>
        <textarea
            id="stepsNote"
            wire:model.live="stepsNote"
            rows="4"
            @disabled($this->rencanaTerkunci)
            class="block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 disabled:bg-slate-50"
        ></textarea>
    </div>

    <div class="mt-4 flex items-center justify-between gap-3">
        <x-ui.button variant="outline" wire:click="$set('langkah', 'kartu')">
            <x-ui.icon.chevron-left class="size-4" />
            Kembali
        </x-ui.button>

        @if (! $this->rencanaTerkunci)
            <div class="flex items-center gap-2">
                <x-ui.button variant="outline" wire:click="simpanStepsNote">Simpan Catatan</x-ui.button>
                <x-ui.button variant="primary" wire:click="kunciRencana" wire:loading.attr="disabled">
                    <x-ui.icon.lock class="size-4" />
                    Kunci Rencana
                </x-ui.button>
            </div>
        @else
            <x-ui.button variant="primary" wire:click="$set('langkah', 'koding')">
                Lanjut ke Koding
                <x-ui.icon.chevron-right class="size-4" />
            </x-ui.button>
        @endif
    </div>
</x-ui.card>