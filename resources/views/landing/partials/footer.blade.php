@php
    $sekolah = trim((string) config('teater.landing.sekolah_nama', ''));
    $kontak = trim((string) config('teater.landing.kontak_email', ''));

    $tautanFooter = [
        ['label' => 'Peran', 'anchor' => '#peran'],
        ['label' => 'Penilaian', 'anchor' => '#penilaian'],
        ['label' => 'Keunggulan', 'anchor' => '#keunggulan'],
    ];
@endphp

<footer class="border-t border-line bg-white">
    <div class="mx-auto w-full max-w-screen-xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-sm">
                <div class="flex items-center gap-2.5">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary-600 text-white">
                        <x-ui.icon.presentation class="size-5" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Teater Koding</p>
                        <p class="text-[11px] text-slate-400">Game Edukasi Python Dasar untuk Kelas</p>
                    </div>
                </div>

                {{-- Hanya tampil bila diisi di config/teater.php → landing. --}}
                @if ($sekolah !== '' || $kontak !== '')
                    <ul class="mt-4 space-y-1 text-sm text-slate-500">
                        @if ($sekolah !== '')
                            <li class="flex items-start gap-2">
                                <x-ui.icon.school class="mt-0.5 size-4 shrink-0 text-slate-400" />
                                <span>{{ $sekolah }}</span>
                            </li>
                        @endif

                        @if ($kontak !== '')
                            <li class="flex items-start gap-2">
                                <x-ui.icon.mail class="mt-0.5 size-4 shrink-0 text-slate-400" />
                                <a
                                    href="mailto:{{ $kontak }}"
                                    class="rounded transition hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                >
                                    {{ $kontak }}
                                </a>
                            </li>
                        @endif
                    </ul>
                @endif
            </div>

            <nav aria-label="Tautan footer" class="flex flex-col gap-2 sm:items-end">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Bagian halaman</p>
                <ul class="flex flex-wrap gap-x-4 gap-y-1 sm:justify-end">
                    @foreach ($tautanFooter as $satu)
                        <li>
                            <a
                                href="{{ $satu['anchor'] }}"
                                class="rounded text-sm font-medium text-slate-600 transition hover:text-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                            >
                                {{ $satu['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>

        <div class="mt-8 border-t border-line pt-6">
            <p class="text-xs text-slate-400">
                &copy; {{ now()->year }} Teater Koding. Game Edukasi Python Dasar untuk Kelas.
            </p>
        </div>
    </div>
</footer>
