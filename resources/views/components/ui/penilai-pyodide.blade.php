{{--
    Penilai di browser (Fase 6).

    Dipakai di halaman Hasil misi dan halaman Kartu Bug. Penilaian hanya
    dijalankan selama status masih 'menunggu' (belum ada hasil final), dan
    komponen ini TIDAK pernah menerima expected_value — server yang
    membandingkan nilai mentah yang dilaporkan.

    Props:
      jenis  'misi' | 'bug'
      id     submission_id / attempt_id

    Perilaku:
      - 'menunggu'      → jalankan otomatis sekali (auto-resume), karena siswa
                          bisa menutup tab sebelum penilaian selesai.
      - "Nilai Ulang"   → dipakai bila nonce kedaluwarsa / runtime gagal.
      - setelah selesai → muat ulang komponen Livewire agar hasil server tampil.

    Logika Alpine-nya ada di resources/js/penilai/alpine.js (didaftarkan global),
    bukan @script, karena komponen ini dirender sebagai view komponen Blade —
    bukan komponen Livewire — sehingga store Livewire tidak tersedia.
--}}
@props([
    'jenis' => 'misi',
    'id',
    'otomatis' => true,
])

<div
    x-data="penilaiPyodide({
        jenis: @js($jenis),
        id: @js((int) $id),
        otomatis: @js((bool) $otomatis),
    })"
    x-init="mulai()"
    class="rounded-xl border border-line bg-white p-4"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-600">
                <x-ui.icon.braces class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-800" x-text="judul"></p>
                <p class="mt-0.5 text-xs text-slate-500" x-text="keterangan"></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span x-show="sedangJalan" class="text-xs text-slate-400" x-text="persen > 0 ? persen + '%' : ''"></span>

            <x-ui.button
                variant="outline"
                size="sm"
                x-on:click="jalankan(true)"
                x-bind:disabled="sedangJalan"
                x-show="! selesai"
            >
                <x-ui.icon.refresh class="size-4" />
                <span x-text="pernahJalan ? 'Nilai Ulang' : 'Nilai Sekarang'"></span>
            </x-ui.button>
        </div>
    </div>

    {{-- Bilah kemajuan; tidak pernah menampilkan nilai atau status lulus. --}}
    <div x-show="sedangJalan" class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-primary-500 transition-all" x-bind:style="`width: ${Math.max(5, persen)}%`"></div>
    </div>

    <template x-if="pesanGalat !== ''">
        <div class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
            <p class="font-semibold">Penilaian belum berhasil</p>
            <p class="mt-0.5" x-text="pesanGalat"></p>
            <p class="mt-1 text-rose-600/80">Kodemu tetap tersimpan. Periksa koneksi lalu tekan "Nilai Ulang".</p>
        </div>
    </template>
</div>
