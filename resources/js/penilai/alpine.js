/**
 * Komponen Alpine `penilaiPyodide` (Fase 6).
 *
 * === KENAPA DIDAFTARKAN DI SINI, BUKAN LEWAT @script ===
 * Komponen Blade <x-ui.penilai-pyodide> dirender sebagai view komponen, jadi
 * `$this` di dalamnya BUKAN komponen Livewire dan `@script` tidak bisa memakai
 * store Livewire. Mendaftarkan Alpine.data secara global juga membuat komponen
 * ini bisa dipakai di mana pun (halaman Hasil maupun Kartu Bug).
 *
 * === PENJAGA AUTO-RUN ===
 * Livewire bisa me-morph ulang elemen (mis. setelah wire:poll), dan Alpine
 * akan menginisialisasi ulang x-data. Tanpa penjaga, penilaian bisa terkirim
 * dua kali. `sudahDijalankan` menyimpan id yang sudah diproses pada halaman ini;
 * tombol "Nilai Ulang" tetap boleh memaksa lewat jalankan(true).
 */

/** id yang sudah pernah dinilai otomatis pada halaman ini. */
const sudahDijalankan = new Set();

/**
 * @param {import('alpinejs').Alpine} Alpine
 */
export function daftarPenilaiAlpine(Alpine) {
    Alpine.data('penilaiPyodide', ({ jenis, id, otomatis }) => ({
        jenis,
        id,
        otomatis,
        sedangJalan: false,
        selesai: false,
        pernahJalan: false,
        persen: 0,
        pesanGalat: '',
        keterangan: 'Kode dijalankan di perangkatmu. Hasil tetap diperiksa server.',
        judul: 'Menilai kode di perangkat…',

        async mulai() {
            if (! this.otomatis) {
                this.judul = 'Penilaian di perangkat';
                this.keterangan = 'Tekan "Nilai Sekarang" untuk memulai.';

                return;
            }

            // Auto-resume: siswa bisa menutup tab sebelum penilaian selesai,
            // jadi status 'menunggu' langsung dinilai saat halaman dibuka.
            const kunci = `${this.jenis}:${this.id}`;

            if (sudahDijalankan.has(kunci)) {
                return;
            }

            sudahDijalankan.add(kunci);

            await this.jalankan();
        },

        async jalankan(paksa = false) {
            if (this.sedangJalan) {
                return;
            }

            if (paksa) {
                sudahDijalankan.add(`${this.jenis}:${this.id}`);
            }

            const penilai = window.TeaterKoding?.nilaiDanLapor;

            if (! penilai) {
                this.pesanGalat = 'Modul penilai belum termuat. Muat ulang halaman.';

                return;
            }

            this.sedangJalan = true;
            this.pernahJalan = true;
            this.selesai = false;
            this.pesanGalat = '';
            this.persen = 5;
            this.judul = 'Menilai kode di perangkat…';
            this.keterangan = 'Jangan tutup halaman sampai penilaian selesai.';

            try {
                const hasil = await penilai(this.jenis, this.id, (tahap, info) => {
                    if (tahap === 'kelompok' && info.dari > 0) {
                        this.persen = Math.round((info.ke / info.dari) * 90);
                        this.keterangan = `Menjalankan kasus uji ${info.ke} dari ${info.dari}…`;
                    } else if (tahap === 'melaporkan') {
                        this.persen = 95;
                        this.keterangan = 'Mengirim hasil ke server untuk diperiksa…';
                    }
                });

                if (! hasil.ok) {
                    this.pesanGalat = hasil.pesan ?? 'Penilaian gagal.';
                    this.judul = 'Penilaian belum selesai';
                    this.keterangan = 'Kodemu tetap tersimpan.';

                    return;
                }

                this.selesai = true;
                this.persen = 100;
                this.judul = 'Selesai dinilai';
                this.keterangan = 'Memuat hasil…';

                // Hasil resmi (lulus/gagal) SELALU berasal dari server.
                this.$wire.$refresh();
            } catch (error) {
                this.pesanGalat = String(error?.message ?? error).slice(0, 200);
            } finally {
                this.sedangJalan = false;
            }
        },
    }));
}
