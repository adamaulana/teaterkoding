<div class="space-y-6">
    {{-- Header misi + stepper --}}
    <x-ui.card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">{{ $this->misi->level->level_name }} — Misi</h1>
                <p class="text-sm text-slate-500">Kode unlock: {{ $this->misi->unlock_code }}</p>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.badge variant="info">{{ $this->team->team_name }}</x-ui.badge>
                @if ($this->peranSaya)
                    <x-ui.badge variant="sukses">{{ $this->peranSaya }}</x-ui.badge>
                @endif
            </div>
        </div>

        {{-- Stepper 5 langkah --}}
        <ol class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4">
            @foreach ([['kartu', 'Kartu Misi'], ['rencana', 'Rencana'], ['koding', 'Koding'], ['hasil', 'Hasil'], ['catatan', 'Catatan']] as [$key, $label])
                <li class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="$set('langkah', '{{ $key }}')"
                        @class([
                            'rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset transition',
                            'bg-primary-600 text-white ring-primary-600' => $langkah === $key,
                            'bg-primary-50 text-primary-700 ring-primary-200 hover:bg-primary-100' => $langkah !== $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                    @if (! $loop->last)
                        <x-ui.icon.chevron-right class="size-4 text-slate-300" />
                    @endif
                </li>
            @endforeach
        </ol>
    </x-ui.card>

    @switch($langkah)
        @case('kartu')
            @include('livewire.siswa.partials.kartu-misi')
            @break

        @case('rencana')
            @include('livewire.siswa.partials.rencana')
            @break

        @case('koding')
            @include('livewire.siswa.partials.koding')
            @break

        @case('hasil')
            @include('livewire.siswa.partials.hasil')
            @break

        @case('catatan')
            @include('livewire.siswa.partials.catatan')
            @break
    @endswitch
</div>