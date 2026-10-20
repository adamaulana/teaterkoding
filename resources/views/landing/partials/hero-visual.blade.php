{{-- Visual hero: tumpukan kartu peran + mock editor kode (CSS/SVG murni). --}}
@php
    $kartuPeran = [
        ['nama' => 'Analyst', 'julukan' => 'Pembaca Misi', 'rotasi' => '-rotate-12', 'posisi' => 'left-0 top-8', 'warna' => 'text-violet-700', 'garis' => 'border-violet-200', 'ikon' => 'clipboard-list'],
        ['nama' => 'Coder', 'julukan' => 'Penulis Kode', 'rotasi' => '-rotate-3', 'posisi' => 'left-7 top-3', 'warna' => 'text-primary-700', 'garis' => 'border-primary-200', 'ikon' => 'code'],
        ['nama' => 'Tester', 'julukan' => 'Pencari Celah', 'rotasi' => 'rotate-6', 'posisi' => 'left-14 top-10', 'warna' => 'text-sky-700', 'garis' => 'border-sky-200', 'ikon' => 'checkup-list'],
    ];
@endphp

<div class="relative mx-auto w-full max-w-xl lg:max-w-none">
    {{-- Tumpukan kartu peran (dekoratif; disembunyikan dari pembaca layar). --}}
    <div class="pointer-events-none absolute -top-8 left-0 z-10 hidden sm:block lg:-left-8" aria-hidden="true">
        <div class="relative h-44 w-32">
            @foreach ($kartuPeran as $kartu)
                <div class="absolute {{ $kartu['posisi'] }} {{ $kartu['rotasi'] }} h-36 w-24 rounded-xl border-2 {{ $kartu['garis'] }} bg-white p-2.5 shadow-md shadow-primary-900/10">
                    <div class="flex items-center justify-between">
                        <span class="text-[9px] font-bold uppercase tracking-wide {{ $kartu['warna'] }}">{{ $kartu['nama'] }}</span>
                        <x-dynamic-component :component="'ui.icon.'.$kartu['ikon']" class="size-3.5 {{ $kartu['warna'] }}" />
                    </div>
                    <p class="mt-5 text-[10px] font-medium leading-tight text-slate-500">{{ $kartu['julukan'] }}</p>
                    <div class="mt-2 space-y-1">
                        <span class="block h-1 w-full rounded-full bg-slate-100"></span>
                        <span class="block h-1 w-3/4 rounded-full bg-slate-100"></span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Mock editor kode (statis; bukan CodeMirror). --}}
    <figure class="relative ms-auto w-full max-w-lg rounded-xl border border-line bg-white shadow-xl shadow-primary-900/10">
        <figcaption class="sr-only">Ilustrasi editor kode berisi fungsi cek_status dengan enam dari delapan kasus uji lulus.</figcaption>

        <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
            <span class="size-2.5 rounded-full bg-rose-200" aria-hidden="true"></span>
            <span class="size-2.5 rounded-full bg-amber-200" aria-hidden="true"></span>
            <span class="size-2.5 rounded-full bg-primary-200" aria-hidden="true"></span>
            <span class="ms-2 truncate font-mono text-[11px] text-slate-400">cek_status.py</span>

            <span class="ms-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">
                <x-ui.icon.circle-check class="size-3" />
                6 dari 8 kasus uji lulus
            </span>
        </div>

        <div class="overflow-hidden bg-[#f8fdfa] p-4 font-mono text-[12px] leading-6 sm:text-[13px]">
<pre class="overflow-x-auto"><code><span class="select-none text-slate-300">1 </span><span class="text-violet-700">def</span> <span class="text-primary-700">cek_status</span>(<span class="text-slate-700">saldo</span>):
<span class="select-none text-slate-300">2 </span>    <span class="text-violet-700">if</span> <span class="text-slate-700">saldo</span> <span class="text-sky-700">&gt;=</span> <span class="text-amber-700">100000</span>:
<span class="select-none text-slate-300">3 </span>        <span class="text-violet-700">return</span> <span class="text-rose-600">"Gold"</span>
<span class="select-none text-slate-300">4 </span>    <span class="text-violet-700">elif</span> <span class="text-slate-700">saldo</span> <span class="text-sky-700">&gt;=</span> <span class="text-amber-700">50000</span>:
<span class="select-none text-slate-300">5 </span>        <span class="text-violet-700">return</span> <span class="text-rose-600">"Silver"</span>
<span class="select-none text-slate-300">6 </span>    <span class="text-violet-700">return</span> <span class="text-rose-600">"Bronze"</span></code></pre>
        </div>

        <div class="border-t border-line px-4 py-3">
            <div class="flex items-center justify-between text-[11px] text-slate-400">
                <span>Hasil kasus uji</span>
                <span class="font-mono">6 / 8</span>
            </div>
            <div class="mt-2 flex gap-1" aria-hidden="true">
                @for ($i = 0; $i < 8; $i++)
                    <span class="h-1.5 flex-1 rounded-full {{ $i < 6 ? 'bg-primary-400' : 'bg-rose-200' }}"></span>
                @endfor
            </div>
        </div>
    </figure>
</div>
