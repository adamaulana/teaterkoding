{{--
    Daftar hasil per kasus uji (misi).

    Dipakai untuk status 'dinilai' (final) DAN 'menunggu_verifikasi' (sumatif:
    hasil perangkat sudah masuk, poin menunggu guru). Label "sementara"
    mencegah siswa mengira poin sudah resmi.

    PENTING: yang ditampilkan hanya actual_value (nilai yang dihitung dari kode
    siswa) — expected_value tidak pernah dikirim ke browser.
--}}
@php
    $hasil = $sub->results()->with('testCase')->get();
    $total = $hasil->count();
    $lulus = $hasil->where('is_passed', true)->count();
    $persen = $total > 0 ? (int) round(($lulus / $total) * 100) : 0;
    $keluaran = (string) data_get($sub->client_report, 'keluaran', '');
@endphp

<x-ui.card>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ $lulus }} dari {{ $total }} lulus</h2>
            <p class="text-sm text-slate-500">
                @if ($sementara)
                    Hasil sementara dari perangkat kalian. Guru akan memverifikasi sebelum poin diberikan.
                @else
                    Lihat detail tiap kasus uji di bawah.
                @endif
            </p>
        </div>
        <div class="flex flex-col items-end gap-1.5">
            @if ($sementara)
                <x-ui.badge variant="netral">Menunggu verifikasi</x-ui.badge>
            @elseif ($sub->all_passed)
                <x-ui.badge variant="sukses">Lulus Semua</x-ui.badge>
            @endif
            <span class="text-[11px] text-slate-400">{{ $persen }}%</span>
        </div>
    </div>

    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $persen }}%"></div>
    </div>

    <ul class="mt-5 divide-y divide-line">
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

    {{-- Keluaran program (stdout) — berguna untuk melihat hasil print siswa. --}}
    @if (filled($keluaran))
        <details class="mt-4 rounded-xl border border-line bg-page/50 p-3">
            <summary class="cursor-pointer text-sm font-medium text-slate-700">Keluaran program</summary>
            <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap font-mono text-xs text-slate-700">{{ $keluaran }}</pre>
        </details>
    @endif

    @if (! $sementara && $sub->all_passed)
        <x-ui.alert variant="sukses" class="mt-4">
            Selamat! Kalian berhasil menyelesaikan misi ini.
        </x-ui.alert>
    @endif

    @if ($sementara)
        <x-ui.alert variant="info" class="mt-4">
            Poin belum diberikan. Setelah guru menekan "Verifikasi", hasil ini menjadi resmi.
        </x-ui.alert>
    @elseif (filled($sub->verified_at))
        {{-- Terverifikasi guru: tampilkan jejak agar siswa tahu hasilnya resmi. --}}
        <p class="mt-4 text-xs text-slate-400">
            Diverifikasi guru {{ $sub->verified_at->format('d M Y H:i') }}
            @if ($sub->has_mismatch)
                · <span class="text-amber-600">hasil pemeriksaan guru berbeda dari laporan perangkat</span>
            @endif
        </p>
    @endif

    <div class="mt-5 flex items-center justify-between gap-3">
        <a href="{{ route('siswa.beranda') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-page">
            <x-ui.icon.home class="size-4" />
            Kembali ke Beranda
        </a>
        @if (! $this->isSumatif)
            <a href="{{ route('siswa.misi.riwayat', $this->misi->mission_id) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
                Riwayat Submission
                <x-ui.icon.chevron-right class="size-4" />
            </a>
        @endif
    </div>
</x-ui.card>
