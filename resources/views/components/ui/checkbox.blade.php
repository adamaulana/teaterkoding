@props(['label' => null, 'name' => null, 'help' => null])

@php
    $invalid = filled($name) && $errors->has($name);
@endphp

<label {{ $attributes->only('class')->merge(['class' => 'flex cursor-pointer items-start gap-2.5 text-sm']) }}>
    <input
        type="checkbox"
        @if (filled($name)) name="{{ $name }}" @endif
        {{ $attributes->except(['class', 'type', 'name']) }}
        @class([
            'mt-0.5 size-4 shrink-0 cursor-pointer rounded border-line text-primary-600 transition focus:ring-2 focus:ring-primary-500/30',
            'border-rose-300' => $invalid,
        ])
    >

    @if (filled($label) || filled($help))
        <span class="min-w-0 leading-tight">
            @if (filled($label))
                <span class="font-medium text-slate-700">{{ $label }}</span>
            @endif
            @if (filled($help))
                <span class="mt-0.5 block text-xs text-slate-400">{{ $help }}</span>
            @endif
        </span>
    @endif
</label>

@if (filled($name))
    @error ($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror
@endif
