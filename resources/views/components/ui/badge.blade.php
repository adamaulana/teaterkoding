@props(['variant' => 'netral'])

@php
    $variants = [
        'sukses' => 'bg-primary-50 text-primary-700 ring-primary-200',
        'peringatan' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'bahaya' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'netral' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$variants[$variant]]) }}>
    {{ $slot }}
</span>
