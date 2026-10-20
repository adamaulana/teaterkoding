{{--
    Verifikasi massal: jalankan seluruh antrean di browser guru, satu per satu.

    Alur per item (sama dengan tombol "Verifikasi" per baris):
      1. ambil paket verifikasi (tanpa expected_value),
      2. jalankan kode siswa di perangkat guru (Pyodide),
      3. kirim nilai MENTAH ke server; server membandingkan,
      4. minta Livewire memverifikasi item itu (poin diberikan server).

    === LANJUT AMAN SETELAH HALAMAN DIBUKA ULANG ===
    Sumber kebenaran antrean adalah DATABASE: saat halaman dimuat ulang,
    antreanMassal() dihitung lagi dari status 'menunggu_verifikasi'. Item yang
    sudah terverifikasi otomatis tidak ada lagi, sehingga "Lanjutkan" tidak
    pernah melewati item yang belum selesai. Tidak perlu localStorage.

    === KENAPA wire:ignore ===
    Livewire me-morph ulang tabel setiap satu item diverifikasi. Tanpa
    wire:ignore, state Alpine (progres, jeda, batal) akan ikut ter-reset.
--}}
@php
    $antrean = $this->antreanMassal;
    $jumlahAntrean = count($antrean);
@endphp

<div
    wire:ignore
    x-data="penilaiMassal({
        antrean: @js($antrean),
        jumlahMenunggu: @js($jumlahAntrean),
    })"
    x-init="inisialisasi()"
    class="mb-4"
>
    <x-ui.card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800">Verifikasi yang Menunggu</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Menjalankan kode tiap item di browser Anda, lalu server memutuskan hasilnya.
                    <span x-show="jumlahMenunggu === 0">Tidak ada item untuk diproses.</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button
                    variant="primary"
                    size="sm"
                    x-on:click="mulai()"
                    x-bind:disabled="berjalan || selesai || jumlahMenunggu === 0"
                    x-show="! berjalan"
                >
                    <x-ui.icon.shield-check class="size-4" />
                    <span x-text="selesai ? 'Selesai' : 'Verifikasi yang Menunggu'"></span>
                </x-ui.button>

                <template x-if="berjalan">
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" size="sm" x-on:click="jeda()" x-show="! dijeda">
                            <x-ui.icon.lock class="size-4" />
                            Jeda
                        </x-ui.button>
                        <x-ui.button variant="primary" size="sm" x-on:click="lanjut()" x-show="dijeda">
                            <x-ui.icon.check class="size-4" />
                            Lanjutkan
                        </x-ui.button>
                        <x-ui.button variant="danger" size="sm" x-on:click="batalkan()">
                            <x-ui.icon.x class="size-4" />
                            Batalkan
                        </x-ui.button>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="berjalan || selesai" class="mt-3">
            <div class="mb-1.5 flex items-center justify-between text-xs">
                <span class="font-medium text-slate-600" x-text="statusTeks"></span>
                <span class="text-slate-400" x-text="`${selesaiJumlah} / ${total}`"></span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary-500 transition-all duration-300" x-bind:style="`width: ${persen}%`"></div>
            </div>
        </div>

        {{-- Item yang dilewati / gagal (mis. sudah diverifikasi guru lain). --}}
        <template x-if="masalah.length > 0">
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                <p class="font-semibold">Sebagian item tidak selesai:</p>
                <ul class="mt-1 space-y-0.5">
                    <template x-for="(satu, i) in masalah" :key="i">
                        <li x-text="satu"></li>
                    </template>
                </ul>
            </div>
        </template>
    </x-ui.card>
</div>
