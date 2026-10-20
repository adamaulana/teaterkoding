{{--
    Daftar hasil kasus uji Kartu Bug.

    Dipakai untuk 'dinilai' (final) dan 'menunggu_verifikasi' (sumatif).
    Hanya actual_value yang ditampilkan — expected_value tidak pernah dikirim
    ke browser.
--}}
@php
    $total = $hasil->count();
    $lulus = $hasil->where('is_passed', true)->count();
    $keluaran = (string) data_get($attempt->client_report, 'keluaran', '');
@endphp

<x-ui.card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-slate-800">{{ $lulus }} dari {{ $total }} lulus</h2>
            @if ($sementara)
                <p class="mt-0.5 text-sm text-slate-500">Hasil sementara dari perangkat kalian; poin menunggu verifikasi guru.</p>
            @endif
        </div>
        @if ($sementara)
            <x-ui.badge variant="netral">Menunggu verifikasi</x-ui.badge>
        @endif
    </div>

    <ul class="mt-4 divide-y divide-line">
        @foreach ($hasil as $r)
            <li class="flex items-start justify-between gap-3 py-3">
                <div class="flex items-center gap-2">
                    @if ($r->is_passed)
                        <x-ui.icon.circle-check class="size-5 text-primary-600" />
                    @else
                        <x-ui.icon.circle-x class="size-5 text-rose-500" />
                    @endif
                    <span class="text-sm text-slate-700">{{ $r->testCase?->label ?? 'Kasus uji' }}</span>
                </div>
                @if (! $r->is_passed && filled($r->actual_value))
                    <code class="max-w-xs truncate rounded bg-rose-50 px-2 py-0.5 font-mono text-xs text-rose-700">{{ mb_substr((string) $r->actual_value, 0, 100) }}</code>
                @endif
            </li>
        @endforeach
    </ul>

    @if (filled($keluaran))
        <details class="mt-4 rounded-xl border border-line bg-page/50 p-3">
            <summary class="cursor-pointer text-sm font-medium text-slate-700">Keluaran program</summary>
            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap font-mono text-xs text-slate-700">{{ $keluaran }}</pre>
        </details>
    @endif

    @if ($sementara)
        <x-ui.alert variant="info" class="mt-4">
            Poin belum diberikan. Guru akan memverifikasi hasil ini lebih dulu.
        </x-ui.alert>
    @endif
</x-ui.card>
