{{-- Editor sumatif: daftar fungsi + pemilik + status --}}
<div class="mt-4 space-y-3">
    <p class="text-sm text-slate-500">Setiap fungsi dikerjakan oleh pemiliknya masing-masing. Draf masih berupa stub akan diganti otomatis saat submit tim.</p>

    @foreach ($this->drafFungsi as $fungsi)
        @php
            $draft = $fungsi['draft'];
            $owner = $fungsi['owner'];
            $kosong = $draft === null || trim((string) $draft->code_text) === '' || trim((string) $draft->code_text) === 'pass';
            $milikSaya = $owner !== null && (int) $owner->student_id === (int) $this->siswa->student_id;
        @endphp
        <div class="rounded-xl border border-line bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-sm text-slate-700">{{ $fungsi['function_name'] }}</code>
                    @if ($kosong)
                        <x-ui.badge variant="peringatan">Belum diisi</x-ui.badge>
                    @else
                        <x-ui.badge variant="sukses">Terisi</x-ui.badge>
                    @endif
                </div>
                <div class="text-xs text-slate-500">
                    @if ($owner)
                        Pemilik: <span class="font-medium text-slate-700">{{ $owner->full_name }}</span>{{ $milikSaya ? ' (kamu)' : '' }}
                    @else
                        <x-ui.badge variant="peringatan">Pemilik belum ditetapkan (hubungi guru)</x-ui.badge>
                    @endif
                </div>
            </div>

            @if ($draft?->updated_at)
                <p class="mt-1 text-[11px] text-slate-400">Terakhir diubah: {{ $draft->updated_at->format('d/m H:i') }}</p>
            @endif

            {{-- Hanya pemilik (atau semua anggota bila tanpa penugasan) boleh mengedit --}}
            <div class="mt-2">
                @if ($milikSaya || (! $owner && ! $this->penegakanAktif))
                    <textarea
                        wire:model.live="fungsiKode.{{ $fungsi['function_name'] }}"
                        rows="5"
                        class="block w-full rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                        placeholder="{{ $draft?->code_text ?? '' }}"
                    >{{ $draft?->code_text }}</textarea>
                @else
                    <pre class="overflow-x-auto rounded-lg border border-line bg-page/60 p-3 font-mono text-sm text-slate-600">{{ $draft?->code_text ?? '(kosong)' }}</pre>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="mt-4 flex items-center justify-between gap-3">
    <x-ui.button variant="outline" wire:click="$set('langkah', 'rencana')">
        <x-ui.icon.chevron-left class="size-4" />
        Rencana
    </x-ui.button>

    <x-ui.button variant="primary" wire:click="submit" wire:loading.attr="disabled">
        <x-ui.icon.check class="size-4" />
        Submit Tim
    </x-ui.button>
</div>