@props([
    'icon' => 'list-check',
    'title' => 'Belum ada data',
    'message' => null,
    'variant' => 'netral',
])

@php
    $variants = [
        'netral' => 'bg-slate-100 text-slate-500',
        'info' => 'bg-sky-50 text-sky-600',
        'peringatan' => 'bg-amber-50 text-amber-600',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="grid size-12 place-items-center rounded-full {{ $variants[$variant] }}">
        <x-dynamic-component :component="'ui.icon.'.$icon" />
    </span>

    <p class="mt-3 text-sm font-medium text-slate-700">{{ $title }}</p>

    @if (filled($message))
        <p class="mt-1 max-w-md text-xs leading-relaxed text-slate-400">{{ $message }}</p>
    @endif

    @isset($actions)
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
