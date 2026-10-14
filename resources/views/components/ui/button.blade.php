@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary-600 text-white shadow-sm shadow-primary-600/20 hover:bg-primary-700 focus-visible:outline-primary-600 disabled:bg-primary-300 disabled:shadow-none',
        'soft' => 'bg-primary-50 text-primary-700 shadow-sm hover:bg-primary-100 focus-visible:outline-primary-600 disabled:text-primary-300',
        'danger' => 'bg-rose-600 text-white shadow-sm shadow-rose-600/20 hover:bg-rose-700 focus-visible:outline-rose-600 disabled:bg-rose-300 disabled:shadow-none',
        'ghost' => 'bg-transparent text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-slate-400 disabled:text-slate-300',
        'outline' => 'border border-line bg-white text-slate-700 shadow-sm hover:bg-page focus-visible:outline-slate-400 disabled:text-slate-300',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];
@endphp

<button
    {{ $attributes->merge([
        'type' => $type,
        'class' => 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed '.$variants[$variant].' '.$sizes[$size],
    ]) }}
>
    {{ $slot }}
</button>
