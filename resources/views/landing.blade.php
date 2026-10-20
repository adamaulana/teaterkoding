{{--
    Landing page publik (Fase 7).

    Struktur satu halaman ber-anchor:
      Beranda (hero) → Peran → Penilaian → Keunggulan → CTA penutup.

    Tombol dinamis ditentukan di landing/partials/aksi.blade.php agar navbar
    dan CTA penutup SELALU konsisten.
--}}
@extends('layouts.landing')

@section('konten')
    @include('landing.partials.hero')
    @include('landing.partials.peran')
    @include('landing.partials.penilaian')
    @include('landing.partials.keunggulan')
    @include('landing.partials.cta')
@endsection
