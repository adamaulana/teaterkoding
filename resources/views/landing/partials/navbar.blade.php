@php
    $tautan = [
        ['label' => 'Peran', 'anchor' => '#peran'],
        ['label' => 'Penilaian', 'anchor' => '#penilaian'],
        ['label' => 'Keunggulan', 'anchor' => '#keunggulan'],
    ];

    $aksi = \App\Support\AksiLanding::tombol();
    $aksiUtama = $aksi['utama'];
    $aksiSekunder = $aksi['sekunder'];
@endphp

<header class="sticky top-0 z-40 border-b border-line/80 bg-white/85 backdrop-blur">
    <div class="mx-auto flex h-16 w-full max-w-screen-xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        {{-- Logo + nama --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2.5 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary-600 text-white shadow-sm shadow-primary-600/30">
                <x-ui.icon.presentation class="size-5" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold leading-tight text-slate-800">Teater Koding</span>
                <span class="block text-[11px] leading-tight text-slate-400">Game Edukasi Python</span>
            </span>
        </a>

        {{-- Tautan anchor (layar >= md) --}}
        <nav aria-label="Navigasi bagian halaman" class="ms-6 hidden items-center gap-1 md:flex">
            @foreach ($tautan as $satu)
                <a
                    href="{{ $satu['anchor'] }}"
                    data-landing-tautan="{{ $satu['anchor'] }}"
                    class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-page hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    {{ $satu['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Aksi (layar >= md) --}}
        <div class="ms-auto hidden items-center gap-2 md:flex">
            @if ($aksiSekunder !== null)
                <x-ui.tautan-tombol :href="$aksiSekunder['url']" variant="ghost">
                    {{ $aksiSekunder['label'] }}
                </x-ui.tautan-tombol>
            @endif

            <x-ui.tautan-tombol :href="$aksiUtama['url']" variant="primary">
                {{ $aksiUtama['label'] }}
                <x-dynamic-component :component="'ui.icon.'.$aksiUtama['ikon']" class="size-4" />
            </x-ui.tautan-tombol>
        </div>

        {{-- Tombol hamburger (layar < md) --}}
        <button
            type="button"
            data-landing-menu-tombol
            aria-expanded="false"
            aria-controls="menu-landing"
            aria-label="Buka menu navigasi"
            class="ms-auto inline-flex size-10 items-center justify-center rounded-lg border border-line bg-white text-slate-600 transition hover:bg-page focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 md:hidden"
        >
            <svg data-landing-menu-ikon-buka viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg data-landing-menu-ikon-tutup viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="hidden size-5" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>

    {{-- Panel menu mobile --}}
    <div id="menu-landing" data-landing-menu class="hidden border-t border-line bg-white md:hidden">
        <nav aria-label="Navigasi bagian halaman (seluler)" class="mx-auto flex max-w-screen-xl flex-col gap-1 px-4 py-3 sm:px-6">
            @foreach ($tautan as $satu)
                <a
                    href="{{ $satu['anchor'] }}"
                    data-landing-menu-tautan
                    class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-page focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    {{ $satu['label'] }}
                </a>
            @endforeach

            <div class="mt-2 flex flex-col gap-2 border-t border-line pt-3">
                <x-ui.tautan-tombol :href="$aksiUtama['url']" variant="primary" class="w-full">
                    {{ $aksiUtama['label'] }}
                </x-ui.tautan-tombol>

                @if ($aksiSekunder !== null)
                    <x-ui.tautan-tombol :href="$aksiSekunder['url']" variant="outline" class="w-full">
                        {{ $aksiSekunder['label'] }}
                    </x-ui.tautan-tombol>
                @endif
            </div>
        </nav>
    </div>
</header>
