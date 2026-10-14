@props([
    'variant' => 'info',
    'title' => null,
])

@php
    $variants = [
        'sukses' => 'border-primary-200 bg-primary-50 text-primary-800',
        'peringatan' => 'border-amber-200 bg-amber-50 text-amber-800',
        'bahaya' => 'border-rose-200 bg-rose-50 text-rose-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    ];

    $icons = [
        'sukses' => 'circle-check',
        'peringatan' => 'alert-triangle',
        'bahaya' => 'circle-x',
        'info' => 'info-circle',
    ];

    $iconColors = [
        'sukses' => 'text-primary-600',
        'peringatan' => 'text-amber-500',
        'bahaya' => 'text-rose-600',
        'info' => 'text-sky-500',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border p-4 '.$variants[$variant]]) }}>
    <span class="mt-0.5 shrink-0">
        <x-dynamic-component :component="'ui.icon.'.$icons[$variant]" :class="$iconColors[$variant]" />
    </span>

    <div class="min-w-0 flex-1 text-sm">
        @if (filled($title))
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ filled($title) ? 'mt-0.5' : '' }} leading-relaxed">{{ $slot }}</div>
    </div>
</div>
