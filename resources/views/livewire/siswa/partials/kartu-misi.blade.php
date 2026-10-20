<x-ui.card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">Kartu Misi</h2>
            <p class="text-sm text-slate-500">Baca baik-baik permintaan klien berikut.</p>
        </div>
        <x-ui.icon.cards class="size-6 text-primary-500" />
    </div>

    <div class="mt-4 whitespace-pre-line rounded-xl border border-line bg-page/60 p-5 text-sm leading-relaxed text-slate-700">
        {{ $this->misi->client_brief }}
    </div>

    <div class="mt-5 flex justify-end">
        <x-ui.button variant="primary" wire:click="$set('langkah', 'rencana')">
            Lanjut ke Rencana
            <x-ui.icon.chevron-right class="size-4" />
        </x-ui.button>
    </div>
</x-ui.card>