{{-- Tiga poin penjelas di bawah panel penilaian. --}}
<ul class="mt-6 grid gap-3 sm:grid-cols-3">
    @php
        $catatanPenilaian = [
            ['ikon' => 'list-check', 'warna' => 'text-primary-600', 'latar' => 'bg-primary-50', 'teks' => 'Hasil per kasus uji terlihat jelas (lulus atau gagal beserta nilai dari kodemu)'],
            ['ikon' => 'lock', 'warna' => 'text-sky-600', 'latar' => 'bg-sky-50', 'teks' => 'Kunci jawaban tidak pernah ditampilkan ke siswa'],
            ['ikon' => 'home', 'warna' => 'text-amber-600', 'latar' => 'bg-amber-50', 'teks' => 'Level 4 bisa dikerjakan di rumah, lalu diverifikasi guru'],
        ];
    @endphp

    @foreach ($catatanPenilaian as $catatan)
        <li class="reveal flex items-start gap-3 rounded-xl border border-line bg-white p-4 shadow-sm shadow-primary-900/5">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $catatan['latar'] }} {{ $catatan['warna'] }}">
                <x-dynamic-component :component="'ui.icon.'.$catatan['ikon']" class="size-4" />
            </span>
            <p class="text-sm leading-relaxed text-slate-600">{{ $catatan['teks'] }}</p>
        </li>
    @endforeach
</ul>
