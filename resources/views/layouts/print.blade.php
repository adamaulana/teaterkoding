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

        <style>
            @media print {
                .no-print { display: none !important; }
                body { background: #ffffff !important; }
                .halaman { page-break-after: always; }
                .kartu { page-break-inside: avoid; }
            }
        </style>
    </head>

    <body class="min-h-screen bg-page font-sans text-slate-800 antialiased">
        {{ $slot }}

        @livewireScripts
    </body>
</html>