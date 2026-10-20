{{-- Bagian PERAN: lima kartu peran + ilustrasi perputaran peran. --}}
@php
    $daftarPeran = [
        [
            'nama' => 'Analyst',
            'julukan' => 'Pembaca Misi',
            'berkas' => 'Analyst.png',
            'tugas' => 'Membaca pesanan klien, memimpin pemilihan Kartu Konsep, mengunci rencana, dan menulis penjelasan cara kerja kode.',
            'aksen' => 'text-violet-700',
            'cincin' => 'ring-violet-200',
            'latar' => 'from-violet-100 to-violet-50',
        ],
        [
            'nama' => 'Coder',
            'julukan' => 'Penulis Kode',
            'berkas' => 'Coder.png',
            'tugas' => 'Mengetik dan mengumpulkan kode. Di Level 4, setiap anggota menulis fungsinya sendiri.',
            'aksen' => 'text-primary-700',
            'cincin' => 'ring-primary-200',
            'latar' => 'from-primary-100 to-primary-50',
        ],
        [
            'nama' => 'Tester',
            'julukan' => 'Pencari Celah',
            'berkas' => 'Tester.png',
            'tugas' => 'Mencoba input lain, terutama nilai batas, dan menulis catatan uji.',
            'aksen' => 'text-sky-700',
            'cincin' => 'ring-sky-200',
            'latar' => 'from-sky-100 to-sky-50',
        ],
        [
            'nama' => 'Reviewer',
            'julukan' => 'Pemeriksa Kode',
            'berkas' => 'Reviewer.png',
            'tugas' => 'Membaca kode sebelum dikumpulkan dan menandainya sudah ditinjau.',
            'aksen' => 'text-amber-700',
            'cincin' => 'ring-amber-200',
            'latar' => 'from-amber-100 to-amber-50',
        ],
        [
            'nama' => 'Bug Hunter',
            'julukan' => 'Pemburu Bug',
            'berkas' => 'Bug_Hunter.png',
            'tugas' => 'Memimpin perbaikan Kartu Bug untuk poin bonus.',
            'aksen' => 'text-rose-700',
            'cincin' => 'ring-rose-200',
            'latar' => 'from-rose-100 to-rose-50',
        ],
    ];
@endphp

<section id="peran" class="border-t border-line bg-white">
    <div class="mx-auto w-full max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Peran</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                Setiap siswa punya peran, dan perannya bergiliran.
            </h2>
            <p class="mt-3 text-base text-slate-500">
                Dalam kelompok 4-5 orang, peran bergeser tiap level sehingga semua siswa merasakan
                semua kompetensi. Kelompok 4 orang boleh menggabungkan Reviewer dan Bug Hunter.
            </p>
        </div>

        <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($daftarPeran as $peran)
                <li class="reveal flex flex-col rounded-xl border border-line bg-white p-5 shadow-sm shadow-primary-900/5 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md motion-reduce:hover:translate-y-0">
                    <div class="flex items-center gap-3">
                        <div @class(['grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-gradient-to-br ring-1', $peran['latar'], $peran['cincin']])>
                            <img
                                src="{{ asset('images/'.$peran['berkas']) }}"
                                alt="Ilustrasi peran {{ $peran['nama'] }}"
                                width="500"
                                height="500"
                                loading="lazy"
                                decoding="async"
                                class="size-full object-contain p-0.5"
                            >
                        </div>

                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-800">{{ $peran['nama'] }}</h3>
                            <p class="text-xs font-medium {{ $peran['aksen'] }}">{{ $peran['julukan'] }}</p>
                        </div>
                    </div>

                    <p class="mt-4 flex-1 text-sm leading-relaxed text-slate-500">{{ $peran['tugas'] }}</p>
                </li>
            @endforeach

            @include('landing.partials.peran-putaran')
        </ul>
    </div>
</section>
