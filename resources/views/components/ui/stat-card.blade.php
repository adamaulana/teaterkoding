@props([
    'label',
    'value',
    'icon' => null,
    'hint' => null,
])

<x-ui.card>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-800 sm:text-3xl">{{ $value }}</p>
            @if (filled($hint))
                <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
            @endif
        </div>

        @if (filled($icon))
            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-600">
                <x-dynamic-component :component="'ui.icon.'.$icon" />
            </span>
        @endif
    </div>
</x-ui.card>
