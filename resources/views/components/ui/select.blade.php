@props(['label' => null, 'name', 'options' => [], 'placeholder' => null, 'value' => null, 'help' => null])

@php
    $id = $attributes->get('id') ?? $name;
    $invalid = $errors->has($name);
    $selected = old($name, $value);
@endphp

<div {{ $attributes->only('class') }}>
    @if (filled($label))
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }}</label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        {{ $attributes->except(['class', 'id']) }}
        @class([
            'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30 disabled:cursor-not-allowed disabled:bg-slate-50',
            'border-rose-300' => $invalid,
            'border-line' => ! $invalid,
        ])
    >
        @if (filled($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    @error ($name)
        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror

    @if (filled($help) && ! $invalid)
        <p class="mt-1.5 text-xs text-slate-400">{{ $help }}</p>
    @endif
</div>
