{{--
    Penanda posisi scroll (terinspirasi gaya "kokpit" pada template acuan).

    - Layar >= 1024px: panel titik vertikal di sisi kanan tengah. Label muncul
      saat hover/fokus. Titik aktif mengikuti scroll (IntersectionObserver di
      landing.js) dan diberi aria-current="true".
    - Layar < 1024px: panel disembunyikan; sebagai gantinya dipakai bilah
      progres tipis di atas (lihat layouts/landing.blade.php).

    Setiap titik adalah <a> sungguhan, jadi tetap berfungsi tanpa JavaScript.
--}}
@php
    $titik = [
        ['id' => 'beranda', 'label' => 'Beranda'],
        ['id' => 'peran', 'label' => 'Peran'],
        ['id' => 'penilaian', 'label' => 'Penilaian'],
        ['id' => 'keunggulan', 'label' => 'Keunggulan'],
    ];
@endphp

<nav
    aria-label="Posisi halaman"
    data-landing-penanda
    class="fixed end-4 top-1/2 z-30 hidden -translate-y-1/2 lg:block"
>
    <ul class="flex flex-col items-end gap-1.5">
        @foreach ($titik as $satu)
            <li class="group relative flex items-center justify-end">
                <a
                    href="#{{ $satu['id'] }}"
                    data-landing-titik="{{ $satu['id'] }}"
                    aria-label="Ke bagian {{ $satu['label'] }}"
                    class="peer flex items-center gap-2 rounded-full p-1.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                    {{-- Label: muncul saat hover/fokus, tersembunyi untuk pembaca
                         layar karena aria-label pada tautan sudah cukup. --}}
                    <span
                        aria-hidden="true"
                        class="pointer-events-none rounded-md border border-line bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 opacity-0 shadow-sm transition group-hover:opacity-100 group-focus-within:opacity-100"
                    >
                        {{ $satu['label'] }}
                    </span>

                    <span
                        data-landing-titik-tanda
                        class="block size-2.5 rounded-full bg-slate-300 ring-1 ring-white transition data-[aktif=true]:bg-primary-600 data-[aktif=true]:ring-primary-200"
                    ></span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
