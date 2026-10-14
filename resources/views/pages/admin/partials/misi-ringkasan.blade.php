{{--
    Tab Ringkasan misi: brief, kode unlock (tombol salin), dan pratinjau
    tampilan Kartu Misi seperti yang akan dilihat siswa.
--}}
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <x-ui.card>
            <p class="text-sm font-semibold text-slate-800">Brief Klien</p>
            <div class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $misi->client_brief }}</div>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm font-semibold text-slate-800">Informasi Misi</p>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Level</dt>
                    <dd>
                        <x-ui.badge variant="netral">{{ $misi->level?->level_name ?? '—' }}</x-ui.badge>
                        @if ($misi->level?->is_summative)
                            <x-ui.badge variant="peringatan" class="ms-1">Sumatif</x-ui.badge>
                        @endif
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Kode Unlock</dt>
                    <dd class="flex items-center gap-2">
                        <code class="rounded bg-slate-100 px-2.5 py-1 font-mono text-sm font-semibold tracking-wide text-slate-700">{{ $misi->unlock_code }}</code>
                        <span
                            x-data="{ tersalin: false }"
                            x-on:click="navigator.clipboard.writeText('{{ $misi->unlock_code }}'); tersalin = true; setTimeout(() => tersalin = false, 1500)"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700"
                            title="Salin kode unlock"
                        >
                            <span x-show="! tersalin"><x-ui.icon.clipboard-list class="size-4" /></span>
                            <span x-show="tersalin" x-cloak><x-ui.icon.check class="size-4 text-primary-600" /></span>
                        </span>
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Jumlah Kasus Uji</dt>
                    <dd><x-ui.badge variant="info">{{ $this->kasusUji->count() }} kasus</x-ui.badge></dd>
                </div>
            </dl>
        </x-ui.card>
    </div>

    {{-- Pratinjau Kartu Misi --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pratinjau Kartu Misi</p>

        <div class="overflow-hidden rounded-2xl border-2 border-primary-200 bg-white shadow-lg shadow-primary-900/10">
            <div class="bg-primary-600 px-5 py-4">
                <p class="text-[11px] font-semibold uppercase tracking-widest text-primary-100">Kartu Misi</p>
                <p class="mt-1 text-lg font-bold text-white">{{ $misi->level?->level_name ?? 'Misi' }}</p>
            </div>

            <div class="space-y-4 p-5">
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $misi->client_brief }}</p>

                <div class="rounded-xl border border-dashed border-primary-300 bg-primary-50/60 px-4 py-3 text-center">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-primary-700">Kode Unlock</p>
                    <p class="mt-1 font-mono text-xl font-bold tracking-[0.2em] text-primary-800">{{ $misi->unlock_code }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
