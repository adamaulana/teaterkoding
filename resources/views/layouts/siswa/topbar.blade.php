@php
    $siswa = auth('siswa')->user();
    $team = $siswa?->team();
    $peran = [];

    if ($siswa !== null && $team !== null) {
        $peran = \App\Models\RoleAssignment::query()
            ->where('team_id', $team->team_id)
            ->where('student_id', $siswa->student_id)
            ->with('role')
            ->get()
            ->pluck('role.role_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // Indikator penilai Pyodide (Fase 6). Nilai aset ikut dikirim agar worker
    // memakai jalur self-hosted yang sama dengan config server.
    $versiPyodide = (string) config('teater.pyodide.version', '314.0.7');
    $asetPyodide = rtrim((string) config('teater.pyodide.asset_path', '/vendor/pyodide'), '/').'/'.$versiPyodide.'/';
@endphp

<header class="sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 w-full max-w-screen-2xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('siswa.beranda') }}" wire:navigate class="flex items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary-600 text-white shadow-sm shadow-primary-600/30">
                <x-ui.icon.presentation class="size-5" />
            </span>
            <span class="hidden min-w-0 sm:block">
                <span class="block text-sm font-semibold text-slate-800">Teater Koding</span>
                <span class="block text-[11px] text-slate-400">Sisi Siswa</span>
            </span>
        </a>

        <div class="ms-auto flex items-center gap-2 sm:gap-3">
            @if ($team)
                <x-ui.badge variant="info">{{ $team->team_name }}</x-ui.badge>
            @endif

            @if ($peran !== [])
                <span class="hidden text-xs text-slate-400 lg:inline">Peran:</span>

                {{-- Chip gambar per peran: lebih cepat dipindai daripada teks. --}}
                @php
                    $berkasPeranTopbar = [
                        'Coder' => 'Coder.png',
                        'Tester' => 'Tester.png',
                        'Analyst' => 'Analyst.png',
                        'Reviewer' => 'Reviewer.png',
                        'Bug Hunter' => 'Bug_Hunter.png',
                    ];
                @endphp

                <div class="hidden items-center gap-1.5 lg:flex">
                    @foreach ($peran as $namaPeran)
                        @php
                            $bersih = trim(str_replace('_', ' ', (string) $namaPeran));
                            $berkas = $berkasPeranTopbar[$bersih] ?? null;
                        @endphp

                        @if ($berkas !== null)
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-page/70 py-1 pe-2.5 ps-1"
                                title="Peran: {{ $bersih }}"
                            >
                                <img
                                    src="{{ asset('images/'.$berkas) }}"
                                    alt=""
                                    width="500"
                                    height="500"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-6 rounded-full bg-white object-contain ring-1 ring-line"
                                >
                                <span class="text-[11px] font-medium text-slate-600">{{ $bersih }}</span>
                            </span>
                        @else
                            <x-ui.badge variant="sukses">{{ $bersih }}</x-ui.badge>
                        @endif
                    @endforeach
                </div>

                {{-- Layar kecil: tetap tampilkan teks ringkas seperti sebelumnya. --}}
                <x-ui.badge variant="sukses" class="lg:hidden">{{ implode(', ', $peran) }}</x-ui.badge>
            @endif

            {{-- Indikator penilai di browser. Hanya informatif; tidak pernah
                 menampilkan nilai harapan atau status lulus/gagal. --}}
            <div
                data-penilai-indikator
                data-penilai-aset="{{ $asetPyodide }}"
                data-penilai-versi="{{ $versiPyodide }}"
                data-penilai-status="idle"
                title="Penilai siap dipakai"
                class="hidden items-center gap-1.5 rounded-full border border-line bg-page/70 px-2.5 py-1 text-[11px] font-medium text-slate-500 md:inline-flex"
            >
                <span data-penilai-titik class="size-2 rounded-full bg-slate-300"></span>
                <span data-penilai-label>Penilai siap dipakai</span>
            </div>

            <div class="flex items-center gap-2">
                <x-ui.avatar :name="$siswa?->full_name ?? 'Siswa'" size="sm" />
                <span class="hidden max-w-36 truncate text-sm font-medium text-slate-800 sm:block">{{ $siswa?->full_name }}</span>
            </div>

            <form method="POST" action="{{ route('siswa.keluar') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-slate-500 transition hover:bg-rose-50 hover:text-rose-600"
                    title="Keluar"
                >
                    <x-ui.icon.logout class="size-4" />
                    <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>
    </div>
</header>