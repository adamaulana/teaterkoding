{{--
    Hero: teks + ilustrasi HTML/CSS/SVG murni.
    Tidak ada gambar eksternal, tidak ada CodeMirror — mock editor hanya
    potongan markup yang diwarnai CSS.
--}}
<section id="beranda" class="relative overflow-hidden">
    {{-- Latar dekoratif: gradasi lembut + pola titik halus (SVG inline). --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <div class="absolute inset-0 bg-gradient-to-b from-primary-50 via-page to-page"></div>
        <svg class="absolute inset-0 size-full opacity-[0.35]" aria-hidden="true">
            <defs>
                <pattern id="pola-titik" width="24" height="24" patternUnits="userSpaceOnUse">
                    <circle cx="1.5" cy="1.5" r="1.5" fill="#bde7cd"></circle>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#pola-titik)"></rect>
        </svg>
    </div>

    <div class="mx-auto grid w-full max-w-screen-xl gap-12 px-4 pb-16 pt-14 sm:px-6 sm:pt-20 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:items-center lg:gap-10 lg:pb-24 lg:pt-24 xl:gap-16">
        {{-- Kolom teks --}}
        <div class="reveal max-w-2xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-white/80 px-3 py-1 text-xs font-semibold text-primary-700">
                <x-ui.icon.star class="size-3.5" />
                Game kelompok untuk kelas Python dasar
            </span>

            <h1 class="mt-5 text-3xl font-bold leading-tight tracking-tight text-slate-800 sm:text-4xl lg:text-[2.75rem] lg:leading-[1.15]">
                Belajar Python dasar jadi misi seru bersama tim.
            </h1>

            <p class="mt-5 text-base leading-relaxed text-slate-500 sm:text-lg">
                Teater Koding adalah game kelompok berbasis kartu dan studi kasus.
                Siswa memecahkan pesanan klien dengan variabel,
                percabangan, perulangan, dan function, lalu dinilai otomatis dan adil
                untuk tiap individu.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.tautan-tombol href="#peran" variant="primary" size="lg">
                    Lihat peran &amp; penilaian
                    <x-ui.icon.chevron-down class="size-4" />
                </x-ui.tautan-tombol>

                <a
                    href="{{ route('siswa.masuk') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-primary-700 transition hover:text-primary-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    Masuk dengan kode akses
                    <x-ui.icon.chevron-right class="size-4" />
                </a>
            </div>

            {{-- Fakta singkat dari fitur yang SUDAH ada (bukan statistik karangan). --}}
            <dl class="mt-10 grid max-w-lg grid-cols-3 gap-4 border-t border-line pt-6">
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Level</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-700">1&ndash;3 latihan</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Level 4</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-700">Sumatif</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Python</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-700">Di browser</dd>
                </div>
            </dl>
        </div>

        {{-- Kolom visual --}}
        <div class="reveal relative">
            @include('landing.partials.hero-visual')
        </div>
    </div>
</section>
