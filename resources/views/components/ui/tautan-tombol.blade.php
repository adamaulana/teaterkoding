{{--
    Tautan bergaya tombol.

    `x-ui.button` merender <button>, yang salah semantiknya untuk navigasi.
    Komponen ini memakai kelas gaya yang sama agar tampilan konsisten, tetapi
    tetap <a> (bisa di-bookmark, dibuka di tab baru, dan terbaca pembaca layar
    sebagai tautan).

    @props variant  primary | soft | danger | ghost | outline
    @props size     sm | md | lg
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $variants = [
        'primary' => 'bg-primary-600 text-white shadow-sm shadow-primary-600/20 hover:bg-primary-700 focus-visible:outline-primary-600',
        'soft' => 'bg-primary-50 text-primary-700 shadow-sm hover:bg-primary-100 focus-visible:outline-primary-600',
        'danger' => 'bg-rose-600 text-white shadow-sm shadow-rose-600/20 hover:bg-rose-700 focus-visible:outline-rose-600',
        'ghost' => 'bg-transparent text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-slate-400',
        'outline' => 'border border-line bg-white text-slate-700 shadow-sm hover:bg-page focus-visible:outline-slate-400',
    ];

    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-sm',
    ];
@endphp

<a
    {{ $attributes->merge([
        'class' => 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 '.$variants[$variant].' '.$sizes[$size],
    ]) }}
>
    {{ $slot }}
</a>
