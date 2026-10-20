{{-- Laporan hasil yang dikirim perangkat siswa + galat/keluaran/selisih. --}}
@php
    $hasilTersimpan = $data->results;
@endphp

<div>
    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Laporan Perangkat Siswa</p>
    <ul class="space-y-1.5">
        @forelse ($hasilTersimpan as $hasil)
            <li class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm">
                <span class="min-w-0 truncate text-slate-700">{{ $hasil->testCase?->label ?? 'Kasus uji' }}</span>
                <span class="flex shrink-0 items-center gap-2">
                    @if (filled($hasil->actual_value))
                        <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-600">{{ $hasil->actual_value }}</code>
                    @endif
                    <x-ui.badge :variant="$hasil->is_passed ? 'sukses' : 'bahaya'">
                        {{ $hasil->is_passed ? 'Lulus' : 'Gagal' }}
                    </x-ui.badge>
                </span>
            </li>
        @empty
            <li class="text-sm text-slate-400">Belum ada hasil kasus uji.</li>
        @endforelse
    </ul>
</div>

@if (is_array($galatLaporan) && filled($galatLaporan['pesan'] ?? null))
    <x-ui.alert variant="bahaya" title="Perangkat melaporkan galat">
        <pre class="mt-1 whitespace-pre-wrap font-mono text-xs">{{ $galatLaporan['jenis'] ?? 'Galat' }}@if (! empty($galatLaporan['baris'])) (baris {{ $galatLaporan['baris'] }})@endif: {{ $galatLaporan['pesan'] }}</pre>
    </x-ui.alert>
@endif

@if (filled($keluaran))
    <details class="rounded-xl border border-line bg-page/50 p-3">
        <summary class="cursor-pointer text-sm font-medium text-slate-700">Keluaran program</summary>
        <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap font-mono text-xs text-slate-700">{{ $keluaran }}</pre>
    </details>
@endif

@if (is_array($selisih) && ($selisih['jumlah'] ?? 0) > 0)
    <x-ui.alert variant="peringatan" title="Laporan perangkat berubah">
        <p>{{ $selisih['jumlah'] }} kasus berbeda dari laporan sebelumnya. Periksa sebelum memverifikasi.</p>
    </x-ui.alert>
@endif
