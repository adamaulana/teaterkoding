{{--
    Jalankan ulang kode siswa di perangkat GURU (bukti independen).

    Hasilnya dikirim ke server (endpoint periksa) yang membandingkan dengan
    expected_value, lalu dikembalikan per kasus — termasuk nilai harapan, yang
    memang hanya boleh terlihat oleh guru.

    Rincian dirender dari STATE LIVEWIRE ($pemeriksaanGuru), bukan dari state
    Alpine: setelah Livewire me-morph ulang panel, state Alpine bisa hilang,
    sedangkan bukti pemeriksaan harus tetap tampil sampai verifikasi.
--}}
<div
    x-data="penilaiVerifikasi({
        jenis: @js($adalahBug ? 'bug' : 'misi'),
        id: @js((int) $data->getKey()),
    })"
    class="rounded-xl border border-line bg-page/40 p-4"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-800">Jalankan Ulang di Perangkat Guru</p>
            <p class="mt-0.5 text-xs text-slate-500">
                Kode yang sama dijalankan di browser Anda. Server membandingkan hasilnya dengan nilai harapan.
            </p>
        </div>

        <x-ui.button
            variant="outline"
            size="sm"
            x-on:click="periksa()"
            x-bind:disabled="sedangJalan"
        >
            <x-ui.icon.refresh class="size-4" />
            <span x-text="sudahDiperiksa ? 'Periksa Lagi' : 'Periksa Sekarang'"></span>
        </x-ui.button>
    </div>

    <div x-show="sedangJalan" class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-primary-500 transition-all" x-bind:style="`width: ${Math.max(5, persen)}%`"></div>
    </div>

    <template x-if="pesanGalat !== ''">
        <p class="mt-2 text-xs text-rose-700" x-text="pesanGalat"></p>
    </template>

    @if (is_array($pemeriksaanGuru) && ($pemeriksaanGuru['hasil'] ?? []) !== [])
        @php
            $jumlahLulusGuru = count(array_filter($pemeriksaanGuru['hasil'], fn ($s) => $s['lulus']));
        @endphp

        <div class="mt-3">
            <p class="text-xs font-medium text-slate-600">
                Hasil di perangkat guru:
                <span class="font-semibold text-slate-800">{{ $jumlahLulusGuru }} dari {{ count($pemeriksaanGuru['hasil']) }} lulus</span>
                <span class="text-slate-400">· {{ $pemeriksaanGuru['pada'] ?? '-' }}</span>
            </p>

            <ul class="mt-2 space-y-1">
                @foreach ($pemeriksaanGuru['hasil'] as $satu)
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-line bg-white px-3 py-1.5 text-xs">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="size-2 shrink-0 rounded-full {{ $satu['lulus'] ? 'bg-primary-500' : 'bg-rose-500' }}"></span>
                            <code class="truncate font-mono text-slate-600">{{ $satu['aktual'] }}</code>
                        </span>
                        <span class="shrink-0 text-slate-400">
                            harapan: <code class="font-mono">{{ $satu['harapan'] }}</code>
                        </span>
                    </li>
                @endforeach
            </ul>

            <x-ui.alert variant="info" class="mt-3">
                Bukti pemeriksaan tersimpan. Selisih terhadap laporan perangkat akan dicatat saat verifikasi.
            </x-ui.alert>
        </div>
    @endif
</div>
