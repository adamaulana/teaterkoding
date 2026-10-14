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
        <div
            x-data="{
                sidebarOpen: localStorage.getItem('tk-sidebar') !== '0',
                mobileOpen: false,
            }"
            x-init="$watch('sidebarOpen', (value) => localStorage.setItem('tk-sidebar', value ? '1' : '0'))"
            @resize.window="if (window.innerWidth >= 1024) mobileOpen = false"
            class="min-h-screen flex flex-col lg:flex-row"
        >
            @include('layouts.admin.sidebar')

            <div class="flex-1 lg:w-auto transition-[padding] duration-200" :class="sidebarOpen ? 'lg:pl-72' : 'lg:pl-0'">
                <div class="min-h-full flex flex-col bg-page">
                    @include('layouts.admin.topbar')

                    <main class="flex-1 px-4 py-4 sm:px-6 lg:px-8 max-w-screen-2xl w-full">
                        {{ $slot }}
                    </main>

                    @include('layouts.admin.footer')
                </div>
            </div>

            {{-- Latar gelap saat drawer terbuka di mobile --}}
            <div
                x-show="mobileOpen"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"
                x-on:click="mobileOpen = false"
            ></div>
        </div>

        @include('layouts.admin.toast')

        @livewireScripts
    </body>
</html>
