{{--
    Layout publik (landing page).

    Dipakai lewat @extends('layouts.landing') + @section('konten').

    === KENAPA TIDAK MEMAKAI resources/js/app.js ===
    app.js meng-import CodeMirror, Pyodide, dan seluruh Alpine penilai (Fase 6).
    Landing page tidak butuh satu pun dari itu. Karena itu layout ini memuat
    HANYA:
      - resources/css/app.css  (token tema + Tailwind, sudah ada)
      - resources/js/landing.js (kecil: menu, scroll-spy, reveal)

    Tanpa Livewire, tanpa CDN, tanpa skrip pihak ketiga.

    === PROGRESSIVE ENHANCEMENT ===
    Kelas `js` dipasang oleh skrip inline kecil di <head>. CSS hanya
    menyembunyikan elemen .reveal bila `html.js` ada, sehingga konten tetap
    terlihat bila JavaScript gagal dimuat/dijalankan.
--}}
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('judul', 'Teater Koding - Belajar Python Dasar Lewat Game Kelompok')</title>
        <meta name="description" content="@yield('deskripsi', 'Teater Koding adalah game kelompok berbasis kartu dan studi kasus untuk belajar Python dasar: variabel, percabangan, perulangan, dan function, dengan penilaian otomatis yang adil untuk tiap individu.')">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Teater Koding') }}">
        <meta property="og:title" content="@yield('judul', 'Teater Koding - Belajar Python Dasar Lewat Game Kelompok')">
        <meta property="og:description" content="@yield('deskripsi', 'Game kelompok berbasis kartu dan studi kasus untuk belajar Python dasar, dengan penilaian otomatis dan adil untuk tiap individu.')">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        {{-- Tandai bahwa JS aktif SEBELUM render, agar .reveal bisa disembunyikan
             hanya ketika JS benar-benar tersedia. --}}
        <script>document.documentElement.classList.add('js');</script>

        @vite(['resources/css/app.css', 'resources/js/landing.js'])

        {{-- Gaya khusus landing: hanya animasi & scroll-margin. --}}
        <style>
            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto !important; }
                *, *::before, *::after {
                    animation-duration: 0.001ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.001ms !important;
                }
            }

            /* Judul bagian tidak tertutup navbar lengket. */
            [id] { scroll-margin-top: 5.5rem; }

            /* Reveal: hanya disembunyikan bila JS aktif. */
            html.js .reveal {
                opacity: 0;
                transform: translateY(12px);
                transition: opacity 500ms ease-out, transform 500ms ease-out;
            }

            html.js .reveal.reveal-tampil {
                opacity: 1;
                transform: none;
            }

            @media (prefers-reduced-motion: reduce) {
                html.js .reveal {
                    opacity: 1;
                    transform: none;
                    transition: none;
                }
            }
        </style>
    </head>

    <body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
        {{-- Tautan lewati: terlihat saat fokus keyboard. --}}
        <a
            href="#konten"
            class="sr-only rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary-700 shadow-sm focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:ring-2 focus:ring-primary-500"
        >
            Lewati ke konten
        </a>

        {{-- Bilah progres scroll (layar kecil; panel titik disembunyikan). --}}
        <div class="fixed inset-x-0 top-0 z-50 h-0.5 bg-transparent lg:hidden" aria-hidden="true">
            <div data-landing-progres class="h-full w-0 bg-primary-500 transition-[width] duration-150"></div>
        </div>

        @include('landing.partials.navbar')

        <main id="konten">
            @yield('konten')
        </main>

        @include('landing.partials.footer')

        {{-- Panel penanda scroll: titik kanan di layar besar. --}}
        @include('landing.partials.penanda-scroll')
    </body>
</html>
