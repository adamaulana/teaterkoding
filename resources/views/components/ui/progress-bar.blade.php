@props([
    'value' => 0,
    'max' => 100,
    'variant' => 'primary',
    'label' => null,
])

@php
    $percent = $max > 0 ? max(0, min(100, (int) round(($value / $max) * 100))) : 0;

    $variants = [
        'primary' => 'bg-primary-500',
        'sukses' => 'bg-primary-500',
        'peringatan' => 'bg-amber-400',
        'bahaya' => 'bg-rose-500',
        'info' => 'bg-sky-400',
    ];
@endphp

<div {{ $attributes }}>
    @if (filled($label))
        <div class="mb-1.5 flex items-center justify-between text-xs">
            <span class="font-medium text-slate-600">{{ $label }}</span>
            <span class="text-slate-400">{{ $percent }}%</span>
        </div>
    @endif

    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
        <div @class(['h-full rounded-full transition-all duration-500', $variants[$variant]]) style="width: {{ $percent }}%"></div>
    </div>
</div>
