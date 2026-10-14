<aside
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-e border-line bg-white transition-transform duration-200"
    :class="{
        'translate-x-0': mobileOpen,
        '-translate-x-full': ! mobileOpen,
        'lg:translate-x-0': sidebarOpen,
    }"
>
    {{-- Logo & nama aplikasi --}}
    <div class="flex h-16 items-center gap-3 border-b border-line px-5">
        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary-600 text-white shadow-sm shadow-primary-600/30">
            <x-ui.icon.presentation class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">Teater Koding</p>
            <p class="truncate text-[11px] text-slate-400">Panel Guru & Admin</p>
        </div>
        <button
            type="button"
            class="hidden rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700 lg:inline-flex"
            x-on:click="sidebarOpen = false"
            title="Ciutkan sidebar"
        >
            <x-ui.icon.chevron-left />
        </button>
    </div>

    {{-- Menu --}}
    <nav class="flex-1 overflow-y-auto pb-6">
        @foreach (config('admin-menu.groups') as $group)
            @php
                $items = collect($group['items'])
                    ->reject(fn (array $item) => ($item['admin_only'] ?? false) && ! auth()->user()?->isAdmin())
                    ->values();
            @endphp

            @if ($items->isEmpty())
                @continue
            @endif

            @if (filled($group['label']))
                <p class="px-3 pb-2 pt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                    {{ $group['label'] }}
                </p>
            @else
                <div class="pt-4"></div>
            @endif

            <ul class="space-y-1 px-3">
                @foreach ($items as $item)
                    @if (filled($item['route']))
                        @php $aktif = request()->routeIs($item['route']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                @class([
                                    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                    'bg-primary-100 text-primary-700' => $aktif,
                                    'text-slate-600 hover:bg-primary-50 hover:text-primary-700' => ! $aktif,
                                ])
                            >
                                <x-dynamic-component
                                    :component="'ui.icon.'.$item['icon']"
                                    @class([$aktif ? 'text-primary-700' : 'text-slate-400 group-hover:text-primary-600'])
                                />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @else
                        <li>
                            <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-300 whitespace-nowrap">
                                <x-dynamic-component :component="'ui.icon.'.$item['icon']" class="text-slate-300" />
                                <span>{{ $item['label'] }}</span>
                                @if (! $item['route'])
                                    <x-ui.badge class="ms-auto text-[10px] px-1.5">Segera</x-ui.badge>
                                @endif
                            </span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endforeach
    </nav>

    {{-- Kaki sidebar --}}
    <div class="border-t border-line px-5 py-3">
        <p class="text-[11px] text-slate-400">v1.0 · Bahasa: Python dasar</p>
    </div>
</aside>
