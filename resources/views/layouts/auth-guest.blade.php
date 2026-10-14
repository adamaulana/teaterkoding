<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ filled($title ?? null) ? $title.' — '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
        <div class="flex min-h-screen flex-col lg:flex-row">
            {{-- Panel hijau: identitas aplikasi --}}
            <div class="relative overflow-hidden bg-primary-700 px-8 py-10 text-white lg:w-[42%] lg:px-12 lg:py-14">
                {{-- Dekorasi --}}
                <div class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-primary-500/30 blur-2xl"></div>
                <div class="pointer-events-none absolute -bottom-28 -left-16 size-80 rounded-full bg-primary-400/20 blur-2xl"></div>

                <div class="relative flex h-full flex-col justify-between gap-10">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-xl bg-white/15 backdrop-blur">
                                <x-ui.icon.presentation class="size-6 text-white" />
                            </span>
                            <div>
                                <p class="text-base font-semibold">Teater Koding</p>
                                <p class="text-xs text-primary-200">Panel Guru & Admin</p>
                            </div>
                        </div>

                        <h1 class="mt-10 text-2xl font-semibold leading-snug lg:text-3xl">
                            Belajar Python dasar sambil bermain peran di kelas
                        </h1>
                        <p class="mt-4 max-w-sm text-sm leading-relaxed text-primary-100">
                            Game edukasi berbasis kartu: siswa bekerja dalam kelompok, menyelesaikan misi,
                            menguji kode otomatis, dan mengumpulkan skor. Anda memantau dan menilai semuanya
                            dari satu panel.
                        </p>
                    </div>

                    <p class="text-xs text-primary-200">© {{ date('Y') }} Teater Koding</p>
                </div>
            </div>

            {{-- Form --}}
            <div class="flex flex-1 items-center justify-center px-6 py-12">
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
