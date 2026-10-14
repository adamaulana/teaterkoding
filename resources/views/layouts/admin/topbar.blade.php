@php
    $pengguna = auth()->user();

    // Cari item menu aktif untuk breadcrumb.
    $crumb = null;
    foreach (config('admin-menu.groups') as $group) {
        foreach ($group['items'] as $item) {
            if (filled($item['route']) && request()->routeIs($item['route'])) {
                $crumb = ['group' => $group['label'], 'item' => $item['label']];
                break 2;
            }
        }
    }
@endphp

<header class="sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
    <div class="flex h-16 w-full items-center gap-3 px-4 sm:px-6 lg:px-8">
        {{-- Toggle sidebar: desktop = ciut/buka, mobile = drawer --}}
        <button
            type="button"
            class="rounded-lg p-2 text-slate-500 transition hover:bg-primary-50 hover:text-primary-700"
            x-on:click="window.innerWidth >= 1024 ? (sidebarOpen = !sidebarOpen) : (mobileOpen = true)"
            title="Buka/tutup menu"
        >
            <x-ui.icon.menu-2 />
        </button>

        {{-- Breadcrumb --}}
        <nav class="hidden min-w-0 items-center gap-1.5 text-sm sm:flex" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-slate-400 transition hover:text-primary-700">
                Beranda
            </a>
            @if ($crumb)
                <x-ui.icon.chevron-right class="size-4 text-slate-300" />
                @if (filled($crumb['group']) && $crumb['item'] !== 'Dashboard')
                    <span class="text-slate-400">{{ $crumb['group'] }}</span>
                    <x-ui.icon.chevron-right class="size-4 text-slate-300" />
                @endif
                <span class="font-medium text-slate-700">{{ $crumb['item'] }}</span>
            @else
                <x-ui.icon.chevron-right class="size-4 text-slate-300" />
                <span class="font-medium text-slate-700">Teater Koding</span>
            @endif
        </nav>

        <div class="flex-1"></div>

        {{-- Dropdown profil --}}
        <x-ui.dropdown align="end" class="hidden sm:block">
            <x-slot:trigger>
                <button
                    type="button"
                    class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-start transition hover:bg-primary-50"
                >
                    <x-ui.avatar :name="$pengguna->name" />
                    <span class="hidden min-w-0 leading-tight lg:block">
                        <span class="block truncate text-sm font-medium text-slate-800">{{ $pengguna->name }}</span>
                        <span class="block truncate text-[11px] text-slate-400">
                            {{ $pengguna->isAdmin() ? 'Admin' : 'Guru' }}
                        </span>
                    </span>
                    <x-ui.icon.chevron-down class="size-4 text-slate-400" />
                </button>
            </x-slot:trigger>

            <div class="px-4 py-3">
                <p class="truncate text-sm font-semibold text-slate-800">{{ $pengguna->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ $pengguna->email }}</p>
                <x-ui.badge :variant="$pengguna->isAdmin() ? 'info' : 'sukses'" class="mt-2">
                    {{ $pengguna->isAdmin() ? 'Admin' : 'Guru' }}
                </x-ui.badge>
            </div>

            <div class="my-1 border-t border-line"></div>

            <a
                href="{{ route('admin.profil') }}"
                wire:navigate
                class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700"
            >
                <x-ui.icon.user-circle class="text-slate-400" />
                Profil Saya
            </a>
            <a
                href="{{ route('admin.ganti-password') }}"
                wire:navigate
                class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700"
            >
                <x-ui.icon.key class="text-slate-400" />
                Ganti Password
            </a>

            <div class="my-1 border-t border-line"></div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-rose-600 transition hover:bg-rose-50"
                >
                    <x-ui.icon.logout class="text-rose-500" />
                    Keluar
                </button>
            </form>
        </x-ui.dropdown>
    </div>
</header>
