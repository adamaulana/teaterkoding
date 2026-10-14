@props(['name', 'title' => null])

<div
    x-data="{ open: false }"
    x-on:{{ $name }}-open.window="open = true"
    x-on:{{ $name }}-close.window="open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
>
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="open = false"></div>

    <div class="fixed inset-0 flex items-center justify-center overflow-y-auto p-4">
        <div
            x-transition:enter="transition duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="my-auto w-full max-w-lg rounded-xl border border-line bg-white shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="text-base font-semibold text-slate-800">{{ $title ?? $name }}</h3>
                <button
                    type="button"
                    x-on:click="open = false"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Tutup"
                >
                    <x-ui.icon.x />
                </button>
            </div>

            <div class="px-5 py-4">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
