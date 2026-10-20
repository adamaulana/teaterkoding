{{-- Editor tunggal L1-3 --}}
<div
    wire:ignore
    x-data="codeEditor(@js($this->kode))"
    class="mt-4"
>
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
        >{{ $this->kode }}</textarea>
    </div>
</div>

<div class="mt-4 flex items-center justify-between gap-3">
    <x-ui.button variant="outline" wire:click="$set('langkah', 'rencana')">
        <x-ui.icon.chevron-left class="size-4" />
        Rencana
    </x-ui.button>

    <div class="flex items-center gap-2">
        @if ($this->bolehTinjau)
            <x-ui.button variant="outline" wire:click="tandaiDitinjau">
                <x-ui.icon.check class="size-4" />
                Tandai Sudah Ditinjau
            </x-ui.button>
        @endif
        <x-ui.button variant="primary" wire:click="submit" wire:loading.attr="disabled">
            <x-ui.icon.check class="size-4" />
            Submit
        </x-ui.button>
    </div>
</div>

@script
<script>
    Alpine.data('codeEditor', (initial) => ({
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
            // memanggil onGagal, lalu mengembalikan null. Siswa tetap mendapat
            // tempat mengetik lewat textarea cadangan.
            this.editor = createCodeEditor(this.$refs.editor, initial, (value) => {
                $wire.kode = value;
                this.jadwalkanSimpan();
            }, {
                onGagal: () => this.pakaiCadangan(),
            });

            if (! this.editor) {
                this.pakaiCadangan();

                return;
            }

            $wire.on('draf-diperbarui', () => {
                this.setKode($wire.get('kode'));
            });
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

        /** Terapkan kode dari server ke jalur yang sedang aktif. */
        setKode(value) {
            if (this.editor) {
                this.editor.setValue(value);

                return;
            }

            if (this.$refs.cadanganArea) {
                this.$refs.cadanganArea.value = value ?? '';
            }
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
