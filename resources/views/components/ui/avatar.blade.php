@props([
    'name',
    'size' => 'md',
])

@php
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $first = $parts[0] ?? 'U';
    $last = end($parts) ?: '';
    $initials = strtoupper(substr($first, 0, 1).substr($last, 0, 1));

    $sizes = [
        'sm' => 'size-8 text-[10px]',
        'md' => 'size-9 text-xs',
        'lg' => 'size-12 text-sm',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700 '.$sizes[$size]]) }} title="{{ $name }}">
    {{ $initials }}
</span>
