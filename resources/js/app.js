import '@fontsource-variable/inter';
import { createCodeEditor } from './code-editor.js';
import { daftarPenilaiAlpine } from './penilai/alpine.js';
import { daftarPenilaiMassalAlpine } from './penilai/alpine-massal.js';
import { daftarPenilaiVerifikasiAlpine } from './penilai/alpine-verifikasi.js';
import { nilaiDanLapor, periksaUlang, pramuat as pramuatPenilai } from './penilai/index.js';

// Ekspos ke window agar dipakai komponen Alpine sisi siswa (dibungkus wire:ignore).
window.TeaterKoding = window.TeaterKoding || {};
window.TeaterKoding.createCodeEditor = createCodeEditor;
window.TeaterKoding.nilaiDanLapor = nilaiDanLapor;
window.TeaterKoding.periksaUlang = periksaUlang;

/**
 * Daftarkan komponen Alpine penilai.
 *
 * === URUTAN PEMUATAN (penting) ===
 * @vite menaruh app.js sebagai <script type="module"> (deferred) di <head>,
 * sedangkan livewire.js adalah skrip klasik di akhir <body>. Skrip klasik
 * berjalan saat parsing; modul berjalan SETELAH parsing selesai tetapi SEBELUM
 * DOMContentLoaded — dan Livewire baru memanggil Alpine.start() pada
 * DOMContentLoaded. Jadi `window.Alpine` sudah tersedia saat baris ini jalan.
 *
 * Karena itu pendaftaran dilakukan LANGSUNG, bukan hanya lewat 'alpine:init'.
 * Listener 'alpine:init' tetap dipasang sebagai jaring pengaman bila urutan
 * skrip berubah; Alpine.data dengan nama sama hanya menimpa definisi lama.
 */
let penilaiTerdaftar = false;

function daftarkanPenilai() {
    if (penilaiTerdaftar || ! window.Alpine) {
        return;
    }

    daftarPenilaiAlpine(window.Alpine);
    daftarPenilaiVerifikasiAlpine(window.Alpine);
    daftarPenilaiMassalAlpine(window.Alpine);
    penilaiTerdaftar = true;
}

daftarkanPenilai();

document.addEventListener('alpine:init', daftarkanPenilai);

/**
 * Penilaian di browser (Pyodide) — hanya aktif di halaman yang memintanya.
 *
 * Dua penanda:
 *   [data-penilai-indikator]  topbar siswa (sekaligus tempat status tampil)
 *   [data-penilai-aktifkan]   halaman guru (verifikasi) yang butuh runtime
 *                             tanpa indikator topbar
 *
 * Halaman admin lain tidak pernah mengunduh runtime ±10 MB. Skrip ini dimuat
 * di <head>, jadi penanda baru dicari setelah DOM siap. Pramuat berjalan di
 * latar belakang supaya pemeriksaan pertama tidak perlu menunggu unduhan wasm.
 */
document.addEventListener('DOMContentLoaded', () => {
    const indikator = document.querySelector('[data-penilai-indikator]');
    const pemicu = indikator ?? document.querySelector('[data-penilai-aktifkan]');

    if (! pemicu) {
        return;
    }

    pramuatPenilai({
        aset: pemicu.dataset.penilaiAset || undefined,
        versi: pemicu.dataset.penilaiVersi || undefined,
    });
});

