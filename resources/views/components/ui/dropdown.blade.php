@props(['align' => 'start'])

<div
    {{ $attributes->merge(['class' => 'relative inline-block text-left']) }}
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    x-on:click.outside="open = false"
>
    <div x-on:click="open = !open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        @class([
            'absolute z-30 mt-2 min-w-56 rounded-xl border border-line bg-white py-1.5 shadow-lg shadow-slate-900/5',
            'right-0' => $align === 'end',
            'left-0' => $align === 'start',
        ])
    >
        {{ $slot }}
    </div>
</div>
