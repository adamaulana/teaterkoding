{{--
    Bagian PENILAIAN.

    Dua panel: (A) sumber poin misi Level 1-3, (B) bobot sumatif Level 4.
    Bobot diambil dari config('teater.default_weights') agar angka di halaman
    SELALU sama dengan yang dipakai perhitungan — bukan angka karangan.
--}}
@php
    $bobot = config('teater.default_weights');
    $label = config('teater.komponen_labels');

    // Urutan & warna segmen. Persentase diambil dari config (bukan hardcode).
    $komponenSumatif = [
        ['kunci' => 'w_individual', 'label' => $label['individu'], 'warna' => 'bg-primary-500', 'ket' => 'Kasus uji untuk fungsi milikmu'],
        ['kunci' => 'w_team', 'label' => $label['kelompok'], 'warna' => 'bg-primary-300', 'ket' => 'Hasil kerja kelompok'],
        ['kunci' => 'w_bug_plan', 'label' => $label['bug_plan'], 'warna' => 'bg-sky-400', 'ket' => 'Perbaikan bug & rencana'],
        ['kunci' => 'w_reflection', 'label' => $label['refleksi'], 'warna' => 'bg-amber-400', 'ket' => 'Diisi guru'],
        ['kunci' => 'w_observation', 'label' => $label['observasi'], 'warna' => 'bg-rose-300', 'ket' => 'Diisi guru'],
    ];

    $totalBobot = array_sum(array_map(fn ($k) => (int) $bobot[$k['kunci']], $komponenSumatif));

    $sumberPoin = [
        ['judul' => 'Hasil misi', 'nilai' => 'Persentase', 'ket' => 'Persentase kasus uji yang lulus.'],
        ['judul' => 'Bonus percobaan pertama', 'nilai' => '+'.config('teater.first_try_bonus', 10), 'ket' => 'Jika langsung lulus semua kasus uji.'],
        ['judul' => 'Bonus rencana', 'nilai' => '+'.config('teater.plan_bonus', 10), 'ket' => 'Jika rencana dikunci sebelum submit pertama dan sesuai kode.'],
        ['judul' => 'Bonus Kartu Bug', 'nilai' => 'Sesuai kartu', 'ket' => 'Diberikan saat perbaikan benar.'],
        ['judul' => 'Penalti petunjuk', 'nilai' => '-'.config('teater.hint_penalty', 5), 'ket' => 'Setiap membuka petunjuk bug.'],
    ];
@endphp

<section id="penilaian" class="border-t border-line bg-page">
    <div class="mx-auto w-full max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="reveal mx-auto max-w-2xl text-center">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Penilaian</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">
                Penilaian yang transparan dan adil untuk tiap individu.
            </h2>
        </div>

        <div class="mt-10 grid gap-5 lg:grid-cols-2">
            {{-- Panel A: poin misi --}}
            <div class="reveal rounded-xl border border-line bg-white p-5 shadow-sm shadow-primary-900/5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-600">
                        <x-ui.icon.chart-bar class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Poin misi (Level 1-3, latihan)</h3>
                        <p class="mt-1 text-sm text-slate-500">Sumber poin yang dijumlahkan tiap misi.</p>
                    </div>
                </div>

                <ul class="mt-5 divide-y divide-line">
                    @foreach ($sumberPoin as $poin)
                        <li class="flex items-start justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800">{{ $poin['judul'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $poin['ket'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-primary-50 px-2.5 py-0.5 font-mono text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200">
                                {{ $poin['nilai'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Panel B: bobot sumatif --}}
            <div class="reveal rounded-xl border border-line bg-white p-5 shadow-sm shadow-primary-900/5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-600">
                        <x-ui.icon.target class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Nilai sumatif Level 4</h3>
                        <p class="mt-1 text-sm text-slate-500">Lima komponen dengan bobot standar.</p>
                    </div>
                </div>

                @include('landing.partials.penilaian-sumatif')

                <p class="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-slate-500">
                    Bobot standar; guru dapat menyesuaikannya per kelas.
                    Hasil resmi Level 4 diverifikasi guru.
                </p>
            </div>
        </div>

        @include('landing.partials.penilaian-catatan')
    </div>
</section>
