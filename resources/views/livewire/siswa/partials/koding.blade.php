<x-ui.card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">Koding</h2>
            <p class="text-sm text-slate-500">Tulis kode Python kalian di sini.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($this->simpanInfo !== '')
                <span class="text-xs text-slate-400">{{ $this->simpanInfo }}</span>
            @endif
            @if (! $this->bolehEditKode)
                <x-ui.badge variant="netral">Read-only</x-ui.badge>
            @endif
        </div>
    </div>

    @if (! $this->penegakanAktif)
        <p class="mt-2 text-xs text-slate-400">Semua anggota boleh mengedit dan submit pada level ini.</p>
    @endif

    @if ($this->konflik)
        <x-ui.alert variant="peringatan" class="mt-4">
            Rekan timmu baru mengubah kode. Pilih salah satu:
            <div class="mt-2 flex gap-2">
                <x-ui.button variant="outline" size="sm" wire:click="muatVersiTerbaru">Muat versi terbaru</x-ui.button>
                <x-ui.button variant="danger" size="sm" wire:click="timpaVersiku">Timpa dengan versiku</x-ui.button>
            </div>
        </x-ui.alert>
    @endif

    @if ($this->isSumatif)
        @include('livewire.siswa.partials.koding-sumatif')
    @else
        @include('livewire.siswa.partials.koding-tunggal')
    @endif
</x-ui.card>