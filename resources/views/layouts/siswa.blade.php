<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Dipakai penilai di browser (Pyodide) untuk mengirim laporan nilai. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ filled($title ?? null) ? $title.' — '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>

    <body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
        @include('layouts.siswa.topbar')

        <main class="mx-auto w-full max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        @include('layouts.siswa.toast')

        @livewireScripts
    </body>
</html>