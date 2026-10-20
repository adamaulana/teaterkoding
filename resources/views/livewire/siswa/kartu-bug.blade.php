<div class="space-y-6">
    <x-ui.card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="grid size-9 place-items-center rounded-lg bg-rose-50 text-rose-600">
                        <x-ui.icon.bug class="size-5" />
                    </span>
                    <h1 class="text-xl font-semibold text-slate-800">{{ $this->attempt->bugCard?->bug_title }}</h1>
                </div>
                <p class="mt-2 text-sm text-slate-500">Perbaiki kode berikut agar semua kasus uji lulus.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.badge variant="peringatan">+{{ $this->attempt->bugCard?->points }} poin</x-ui.badge>
                @if ($this->attempt->is_solved)
                    <x-ui.badge variant="sukses">Terpecahkan</x-ui.badge>
                @elseif ($this->attempt->grading_status === 'menunggu_verifikasi')
                    <x-ui.badge variant="netral">Menunggu verifikasi</x-ui.badge>
                @elseif ($this->attempt->grading_status === 'menunggu')
                    <x-ui.badge variant="info">Menunggu penilaian</x-ui.badge>
                @elseif ($this->attempt->grading_status === 'galat')
                    <x-ui.badge variant="bahaya">Galat</x-ui.badge>
                @endif
            </div>
        </div>

        {{-- Gejala yang dilihat siswa --}}
        @if (filled($this->attempt->bugCard?->symptom_text))
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-700">Gejala</p>
                <p class="mt-1 whitespace-pre-line text-sm text-amber-900">{{ $this->attempt->bugCard->symptom_text }}</p>
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between gap-3">
            <p class="text-xs text-slate-400">
                {{ $this->attempt->hints_used > 0 ? 'Petunjuk sudah dibuka.' : 'Belum pakai petunjuk.' }}
            </p>
            @if (! $this->attempt->is_solved)
                <x-ui.button variant="outline" wire:click="mintaPetunjuk" wire:loading.attr="disabled">
                    <x-ui.icon.info-circle class="size-4" />
                    Minta Petunjuk (-{{ config('teater.hint_penalty', 5) }} poin)
                </x-ui.button>
            @endif
        </div>

        @if ($petunjukTerbuka && filled($petunjuk))
            <x-ui.alert variant="info" class="mt-3">
                <span class="font-semibold">Petunjuk:</span> {{ $petunjuk }}
            </x-ui.alert>
        @endif
    </x-ui.card>

    {{-- Editor kode --}}
    <x-ui.card>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-800">Perbaikan Kode</h2>
            @if (! $this->bolehKerjakan)
                <x-ui.badge variant="netral">Read-only</x-ui.badge>
            @endif
        </div>

        <div wire:ignore x-data="bugEditor(@js($kode))" class="mt-3">
            <div x-ref="editor" x-show="! cadangan" class="overflow-hidden rounded-lg border border-line"></div>

            {{-- Cadangan: hanya tampil bila editor lanjutan gagal dimuat. --}}
            <div x-show="cadangan" x-cloak data-editor-cadangan>
                <p class="mb-2 text-xs font-medium text-amber-700">
                    Editor lanjutan gagal dimuat, kamu tetap bisa mengetik di kolom ini.
                </p>
                <textarea
                    x-ref="cadanganArea"
                    rows="14"
                    spellcheck="false"
                    autocomplete="off"
                    autocapitalize="off"
                    x-on:input="onCadanganInput($event)"
                    class="block w-full rounded-lg border border-line bg-white px-3 py-2 font-mono text-sm leading-6 text-slate-800 shadow-sm transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                >{{ $kode }}</textarea>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <x-ui.button variant="primary" wire:click="submit" wire:loading.attr="disabled" @disabled(! $this->bolehKerjakan || $this->attempt->is_solved)>
                <x-ui.icon.check class="size-4" />
                Kirim Perbaikan
            </x-ui.button>
        </div>
    </x-ui.card>

    {{-- Hasil per kasus uji --}}
    @if (in_array($this->attempt->grading_status, ['dinilai', 'menunggu_verifikasi'], true) && $this->hasil->isNotEmpty())
        @include('livewire.siswa.partials.bug-kasus', [
            'attempt' => $this->attempt,
            'hasil' => $this->hasil,
            'sementara' => $this->attempt->grading_status === 'menunggu_verifikasi',
        ])
    @elseif ($this->attempt->grading_status === 'galat')
        <x-ui.card>
            <x-ui.alert variant="bahaya" title="Ada galat pada kodemu">
                <pre class="mt-2 overflow-x-auto whitespace-pre-wrap font-mono text-xs">{{ mb_substr((string) $this->attempt->error_message, 0, 1500) }}</pre>
            </x-ui.alert>
        </x-ui.card>
    @elseif ($this->attempt->grading_status === 'menunggu' && app(\App\Services\SubmissionService::class)->memakaiPyodide())
        {{-- Penilaian di BROWSER siswa: jangan tampilkan spinner antrean job. --}}
        <x-ui.card>
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-600">
                    <x-ui.icon.braces class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Menunggu penilaian di perangkat</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Perbaikan kalian diuji di browser ini. Bila terputus, tekan
                        <span class="font-medium text-slate-700">Nilai Ulang</span>.
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <x-ui.penilai-pyodide jenis="bug" :id="$this->attempt->attempt_id" />
            </div>
        </x-ui.card>
    @elseif ($this->attempt->grading_status === 'menunggu')
        @php
            $detikBug = (int) ($this->attempt->attempted_at?->diffInSeconds(now()) ?? 0);
            $lamaBug = $detikBug > 60;
            $pesanBug = $lamaBug ? app(\App\Services\DiagnosaPenilaian::class)->pesanUntukPenantian() : null;
        @endphp

        {{-- Poll cepat hanya 60 detik pertama; setelah itu berhenti agar tidak
             membebani server tanpa hasil. --}}
        <x-ui.card>
            <div class="flex flex-col items-center py-6 text-center" @if (! $lamaBug) wire:poll.2s @endif>
                <span class="grid size-12 place-items-center rounded-full bg-primary-50 text-primary-600">
                    <x-ui.icon.refresh class="size-6 {{ $lamaBug ? '' : 'animate-spin' }}" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-800">
                    {{ $lamaBug ? 'Penilaian belum selesai' : 'Sedang dinilai...' }}
                </p>
            </div>

            @if ($lamaBug)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                    <p class="font-semibold">Penyebab</p>
                    <p class="mt-0.5">{{ $pesanBug ?? 'Penilai otomatis belum mengolah perbaikan ini. Kode kalian aman tersimpan.' }}</p>
                </div>

                <div class="mt-3 flex justify-end">
                    <x-ui.button variant="outline" size="sm" wire:click="$refresh">
                        <x-ui.icon.refresh class="size-4" />
                        Periksa Status
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @endif

    {{-- Penjelasan perbaikan: HANYA setelah lulus --}}
    @if ($this->attempt->is_solved && filled($this->attempt->bugCard?->fix_explanation))
        <x-ui.card>
            <div class="rounded-xl border border-primary-200 bg-primary-50 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">Penjelasan Perbaikan</p>
                <p class="mt-1 whitespace-pre-line text-sm text-primary-900">{{ $this->attempt->bugCard->fix_explanation }}</p>
            </div>
            <div class="mt-4">
                <a href="{{ route('siswa.beranda') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-page">
                    <x-ui.icon.home class="size-4" />
                    Kembali ke Beranda
                </a>
            </div>
        </x-ui.card>
    @endif
</div>

@script
<script>
    Alpine.data('bugEditor', (initial) => ({
        editor: null,
        cadangan: false,
        waktuSimpan: null,

        init() {
            // Alpine memanggil init() sekali; penjaga ini mencegah editor ganda
            // bila Livewire me-morph ulang atau init() terpanggil lagi.
            if (this.editor || this.cadangan) {
                return;
            }

            const createCodeEditor = window.TeaterKoding?.createCodeEditor;
            const $wire = this.$wire;

            if (! createCodeEditor) {
                console.error('[teater] Modul editor tidak termuat (window.TeaterKoding.createCodeEditor).');
                this.pakaiCadangan();

                return;
            }

            // createCodeEditor tidak melempar galat: ia mencatat console.error,
            // memanggil onGagal, lalu mengembalikan null. Textarea cadangan
            // menjaga siswa tetap bisa mengetik.
            this.editor = createCodeEditor(this.$refs.editor, initial, (value) => {
                $wire.kode = value;
                this.jadwalkanSimpan();
            }, {
                onGagal: () => this.pakaiCadangan(),
            });

            if (! this.editor) {
                this.pakaiCadangan();
            }
        },

        /** Sembunyikan CodeMirror, tampilkan textarea cadangan. */
        pakaiCadangan() {
            this.editor = null;
            this.cadangan = true;

            // Textarea berada di dalam wire:ignore: samakan isinya dengan state
            // Livewire supaya kode yang sudah ada tidak tampak hilang.
            this.$nextTick(() => {
                if (this.$refs.cadanganArea) {
                    this.$refs.cadanganArea.value = this.$wire?.get('kode') ?? '';
                }
            });
        },

        /** Input dari textarea cadangan — perilaku sama dengan editor. */
        onCadanganInput(event) {
            this.$wire.kode = event.target.value;
            this.jadwalkanSimpan();
        },

        /** Autosave debounce yang dipakai kedua jalur (editor & cadangan). */
        jadwalkanSimpan() {
            clearTimeout(this.waktuSimpan);
            this.waktuSimpan = setTimeout(
                () => this.$wire.call('simpanDraf'),
                {{ (int) config('teater.draft_autosave_ms', 1500) }},
            );
        },
    }));
</script>
@endscript