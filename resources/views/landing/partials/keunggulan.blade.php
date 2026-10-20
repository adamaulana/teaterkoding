{{-- Bagian KEUNGGULAN: enam kartu. --}}
@php
    $keunggulan = [
        [
            'ikon' => 'cards',
            'warna' => 'text-primary-600',
            'latar' => 'bg-primary-50',
            'judul' => 'Kartu fisik + aplikasi saling terhubung',
            'teks' => 'Kode unlock di Kartu Misi, slot rencana untuk Kartu Konsep, dan peran yang bergiliran.',
        ],
        [
            'ikon' => 'book',
            'warna' => 'text-sky-600',
            'latar' => 'bg-sky-50',
            'judul' => 'Satu tema, satu cerita',
            'teks' => 'Seluruh level berpusat pada Koperasi Sejahtera, sehingga siswa fokus pada logika, bukan berganti konteks.',
        ],
        [
            'ikon' => 'users-group',
            'warna' => 'text-violet-600',
            'latar' => 'bg-violet-50',
            'judul' => 'Nilai individu yang adil di kerja kelompok',
            'teks' => 'Peran bergilir, dan di Level 4 setiap siswa dinilai dari fungsinya sendiri.',
        ],
        [
            'ikon' => 'bug',
            'warna' => 'text-rose-600',
            'latar' => 'bg-rose-50',
            'judul' => 'Umpan balik per kasus uji dan latihan debugging',
            'teks' => 'Siswa tahu bagian mana yang belum tepat, plus Kartu Bug untuk melatih membaca dan memperbaiki kode.',
        ],
        [
            'ikon' => 'braces',
            'warna' => 'text-amber-600',
            'latar' => 'bg-amber-50',
            'judul' => 'Tanpa instalasi',
            'teks' => 'Python berjalan langsung di browser laptop atau PC siswa, termasuk saat mengerjakan di rumah.',
        ],
        [
            'ikon' => 'layout-dashboard',
            'warna' => 'text-primary-600',
            'latar' => 'bg-primary-50',
            'judul' => 'Guru memantau secara langsung',
            'teks' => 'Kelompok yang macet terlihat, dan perbedaan antara rencana dan kode membantu menemukan miskonsepsi.',
        ],
    ];
@endphp

<section id="keunggulan" class="border-t border-line bg-white">
    <div class="mx-auto w-full max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Keunggulan</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                Kenapa Teater Koding?
            </h2>
        </div>

        <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($keunggulan as $satu)
                <li class="reveal flex flex-col rounded-xl border border-line bg-white p-5 shadow-sm shadow-primary-900/5 transition hover:border-primary-300 hover:shadow-md">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $satu['latar'] }} {{ $satu['warna'] }}">
                        <x-dynamic-component :component="'ui.icon.'.$satu['ikon']" class="size-5" />
                    </span>

                    <h3 class="mt-4 text-base font-semibold leading-snug text-slate-800">{{ $satu['judul'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $satu['teks'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
