@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-white shadow-sm shadow-primary-900/5 '.($padding ? 'p-5 sm:p-6' : '')]) }}>
    {{ $slot }}
</div>
