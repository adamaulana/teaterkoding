{{--
    Panel pemeriksaan & verifikasi satu hasil penilaian perangkat siswa.

    Menampilkan kode siswa (di-escape Blade), laporan perangkat, tombol
    "Jalankan Ulang di Perangkat Guru" (bukti independen), dan selisih bila ada.
    expected_value hanya tampil di halaman guru ini.
--}}
@php
    $data = $this->terpilih;
@endphp

@if ($data !== null)
    @php
        $adalahBug = $data instanceof \App\Models\BugAttempt;
        $kode = $adalahBug ? (string) $data->fixed_code : (string) $data->code_text;
        $hasilTersimpan = $data->results;
        $jumlahLulus = $hasilTersimpan->where('is_passed', true)->count();
        $jumlahKasus = $hasilTersimpan->count();
        $keluaran = (string) data_get($data->client_report, 'keluaran', '');
        $galatLaporan = data_get($data->client_report, 'galat');
        $selisih = $data->mismatch_detail;
        $pemeriksaanGuru = $this->pemeriksaan;
    @endphp

    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-label="Periksa hasil">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" wire:click="tutup"></div>

        <div class="relative mx-auto my-8 w-full max-w-4xl px-4">
            <div class="rounded-xl border border-line bg-white shadow-xl">
                <div class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                            {{ $adalahBug ? 'Percobaan Kartu Bug' : 'Submission Misi' }}
                        </p>
                        <p class="truncate text-base font-semibold text-slate-800">
                            {{ $data->team?->team_name }} ·
                            {{ $adalahBug ? $data->bugCard?->bug_title : $data->mission?->level?->level_name }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ $jumlahLulus }} dari {{ $jumlahKasus }} kasus lulus ·
                            {{ $data->client_runtime_ms !== null ? $data->client_runtime_ms.' ms' : 'durasi tidak tercatat' }}
                            @if (filled($data->pyodide_version))
                                · Pyodide {{ $data->pyodide_version }}
                            @endif
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="tutup"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup"
                    >
                        <x-ui.icon.x class="size-5" />
                    </button>
                </div>

                <div class="max-h-[65vh] space-y-5 overflow-y-auto px-5 py-5">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Kode yang Dikirim</p>
                        <x-ui.kode-bernomor :kode="$kode" class="max-h-64" />
                    </div>

                    @include('pages.admin.partials.verifikasi-laporan')

                    @include('pages.admin.partials.verifikasi-selisih')

                    @include('pages.admin.partials.verifikasi-jalankan')
                </div>

                <div class="flex flex-col gap-2 border-t border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <x-ui.button variant="outline" size="sm" wire:click="mintaUlang" wire:loading.attr="disabled">
                        <x-ui.icon.refresh class="size-4" />
                        Minta Nilai Ulang
                    </x-ui.button>

                    <div class="flex items-center gap-2">
                        <x-ui.button variant="ghost" size="sm" wire:click="tutup">Tutup</x-ui.button>
                        <x-ui.button variant="primary" size="sm" wire:click="verifikasi" wire:loading.attr="disabled">
                            <x-ui.icon.shield-check class="size-4" />
                            Verifikasi &amp; Beri Poin
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
