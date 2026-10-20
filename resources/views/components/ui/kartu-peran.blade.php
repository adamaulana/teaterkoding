@props([
    'peran' => null,
    'nama' => null,
    'varian' => 'anggota',
    'milikSaya' => false,
    'keterangan' => null,
])

@php
    /**
     * Peta peran → berkas gambar di public/images.
     *
     * Nama peran di database memakai SPASI ("Bug Hunter"), sedangkan berkas
     * memakai GARIS BAWAH ("Bug_Hunter.png"). Normalisasi dilakukan di sini
     * supaya pemanggil tidak perlu tahu detail penamaan berkas.
     */
    $berkasPeran = [
        'Coder' => 'Coder.png',
        'Tester' => 'Tester.png',
        'Analyst' => 'Analyst.png',
        'Reviewer' => 'Reviewer.png',
        'Bug Hunter' => 'Bug_Hunter.png',
    ];

    // Tugas singkat tiap peran (dipakai varian "utama").
    $tugasPeran = [
        'Coder' => 'Menulis dan mengirim kode program utama.',
        'Tester' => 'Menguji hasil dan mencari kasus yang masih gagal.',
        'Analyst' => 'Menyusun rencana dan memilih kartu konsep.',
        'Reviewer' => 'Meninjau kode tim sebelum dikirim.',
        'Bug Hunter' => 'Memperbaiki kartu bug yang dirilis guru.',
    ];

    // Aksen warna selaras palet aplikasi.
    $aksenPeran = [
        'Coder' => ['cincin' => 'ring-primary-200', 'latar' => 'from-primary-100 to-primary-50', 'teks' => 'text-primary-700'],
        'Tester' => ['cincin' => 'ring-sky-200', 'latar' => 'from-sky-100 to-sky-50', 'teks' => 'text-sky-700'],
        'Analyst' => ['cincin' => 'ring-violet-200', 'latar' => 'from-violet-100 to-violet-50', 'teks' => 'text-violet-700'],
        'Reviewer' => ['cincin' => 'ring-amber-200', 'latar' => 'from-amber-100 to-amber-50', 'teks' => 'text-amber-700'],
        'Bug Hunter' => ['cincin' => 'ring-rose-200', 'latar' => 'from-rose-100 to-rose-50', 'teks' => 'text-rose-700'],
    ];

    // Normalisasi: "Bug_Hunter" → "Bug Hunter"; nilai kosong dianggap null.
    $peranBersih = trim(str_replace('_', ' ', (string) $peran));
    $peranDikenal = ($peranBersih !== '' && isset($berkasPeran[$peranBersih])) ? $peranBersih : null;

    $berkas = $peranDikenal !== null ? $berkasPeran[$peranDikenal] : null;
    $aksen = $peranDikenal !== null
        ? $aksenPeran[$peranDikenal]
        : ['cincin' => 'ring-slate-200', 'latar' => 'from-slate-100 to-slate-50', 'teks' => 'text-slate-500'];

    $labelPeran = $peranDikenal ?? 'Belum ada peran';
    $deskripsi = $peranDikenal !== null ? ($keterangan ?? $tugasPeran[$peranDikenal]) : null;
    $utama = $varian === 'utama';
@endphp

@if ($utama)
    {{-- Varian besar: menyorot peran milik siswa sendiri. --}}
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-xl border border-line bg-white p-5 shadow-sm shadow-primary-900/5 sm:flex-row sm:items-center sm:gap-6 sm:p-6']) }}>
        <div @class(['grid size-28 shrink-0 place-items-center overflow-hidden rounded-2xl bg-gradient-to-br ring-1 sm:size-32', $aksen['latar'], $aksen['cincin']])>
            @if ($berkas !== null)
                <img
                    src="{{ asset('images/'.$berkas) }}"
                    alt="Ilustrasi peran {{ $peranDikenal }}"
                    width="500"
                    height="500"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-contain p-1.5"
                >
            @else
                <x-ui.icon.user class="size-12 text-slate-400" />
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Peranmu saat ini</p>

            <div class="mt-1 flex flex-wrap items-center gap-2">
                <p class="text-xl font-semibold {{ $aksen['teks'] }}">{{ $labelPeran }}</p>
                @if ($milikSaya)
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200">Kamu</span>
                @endif
            </div>

            @if (filled($nama))
                <p class="mt-0.5 text-sm font-medium text-slate-700">{{ $nama }}</p>
            @endif

            <p class="mt-2 text-sm text-slate-500">
                {{ $deskripsi ?? 'Peranmu belum ditetapkan untuk level ini. Hubungi gurumu bila sudah seharusnya ada.' }}
            </p>
        </div>
    </div>
@else
    {{-- Varian kecil: satu kartu per anggota di grid. --}}
    <div {{ $attributes->merge(['class' => 'flex items-center gap-3 rounded-xl border border-line bg-white p-3 shadow-sm shadow-primary-900/5 transition hover:border-primary-300 hover:shadow-md']) }}>
        <div @class(['grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-gradient-to-br ring-1', $aksen['latar'], $aksen['cincin']])>
            @if ($berkas !== null)
                <img
                    src="{{ asset('images/'.$berkas) }}"
                    alt="Ilustrasi peran {{ $peranDikenal }}"
                    width="500"
                    height="500"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-contain p-0.5"
                >
            @else
                <x-ui.icon.user class="size-6 text-slate-400" />
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-800">{{ $nama ?? 'Anggota' }}</p>
            <p @class(['mt-0.5 truncate text-xs font-medium', $aksen['teks']])>{{ $labelPeran }}</p>
        </div>

        @if ($milikSaya)
            <span class="ms-auto shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-inset ring-slate-200">Kamu</span>
        @endif
    </div>
@endif
