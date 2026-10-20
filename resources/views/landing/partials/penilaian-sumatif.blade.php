{{--
    Grafik batang bersegmen untuk bobot sumatif.

    Angka diambil dari config('teater.default_weights') (lihat penilaian.blade.php).
    Aksesibilitas:
      - batang bersegmen ditandai aria-hidden (murni visual),
      - disediakan DAFTAR BERLABEL yang terbaca pembaca layar,
      - legenda memuat label + persen sebagai teks biasa.
--}}

{{-- Batang bersegmen --}}
<div class="mt-5" aria-hidden="true">
    <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100">
        @foreach ($komponenSumatif as $satu)
            <div
                data-landing-segmen
                data-persen="{{ (int) $bobot[$satu['kunci']] }}"
                class="{{ $satu['warna'] }} h-full w-0 transition-[width] duration-700 ease-out motion-reduce:transition-none"
                style="--lebar: {{ (int) $bobot[$satu['kunci']] }}%"
            ></div>
        @endforeach
    </div>
</div>

{{-- Legenda + daftar berlabel (terbaca pembaca layar) --}}
<dl class="mt-4 space-y-2">
    @foreach ($komponenSumatif as $satu)
        <div class="flex items-start justify-between gap-3">
            <dt class="flex min-w-0 items-center gap-2 text-sm text-slate-600">
                <span class="size-2.5 shrink-0 rounded-full {{ $satu['warna'] }}" aria-hidden="true"></span>
                <span class="min-w-0">
                    {{ $satu['label'] }}
                    <span class="block text-[11px] text-slate-400">{{ $satu['ket'] }}</span>
                </span>
            </dt>
            <dd class="shrink-0 font-mono text-sm font-semibold text-slate-800">
                {{ (int) $bobot[$satu['kunci']] }}%
            </dd>
        </div>
    @endforeach

    <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
        <dt class="text-sm font-semibold text-slate-700">Total</dt>
        <dd class="font-mono text-sm font-semibold text-primary-700">{{ $totalBobot }}%</dd>
    </div>
</dl>
