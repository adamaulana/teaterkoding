/**
 * Komponen Alpine `penilaiMassal` — verifikasi seluruh antrean di browser guru.
 *
 * === ALASAN SNAPSHOT ===
 * Antrean diambil sebagai SNAPSHOT saat tombol ditekan. Selama proses, tabel
 * Livewire di-morph ulang (baris yang sudah diverifikasi hilang), jadi
 * mengandalkan indeks baris yang dirender akan melompati item. Snapshot
 * memastikan setiap id diproses tepat sekali dalam satu sesi.
 *
 * === JEDA / BATAL / LANJUT ===
 * Loop memeriksa `dijeda` dan `dibatalkan` di antara item. Item yang sedang
 * berjalan tidak dipotong (Pyodide berjalan sinkron di worker); pembatalan
 * berlaku pada item berikutnya.
 *
 * === AMAN SETELAH RELOAD ===
 * DB adalah sumber kebenaran. Setelah reload, `jumlahMenunggu` dihitung ulang
 * dari status 'menunggu_verifikasi', sehingga menekan tombol lagi hanya
 * memproses sisa antrean.
 */
export function daftarPenilaiMassalAlpine(Alpine) {
    Alpine.data('penilaiMassal', ({ antrean, jumlahMenunggu }) => ({
        antrean,
        jumlahMenunggu,
        berjalan: false,
        dijeda: false,
        dibatalkan: false,
        selesai: false,
        total: 0,
        selesaiJumlah: 0,
        statusTeks: '',
        masalah: [],

        get persen() {
            if (this.total === 0) {
                return 0;
            }

            return Math.min(100, Math.round((this.selesaiJumlah / this.total) * 100));
        },

        /**
         * Dipanggil saat Livewire memperbarui jumlah antrean (mis. setelah
         * satu item diverifikasi lewat tombol per baris).
         */
        inisialisasi() {
            this.$watch('jumlahMenunggu', () => {
                if (! this.berjalan && this.jumlahMenunggu === 0) {
                    this.selesai = true;
                }
            });
        },

        async mulai() {
            if (this.berjalan) {
                return;
            }

            const periksaUlang = window.TeaterKoding?.periksaUlang;

            if (! periksaUlang) {
                this.masalah = ['Modul penilai belum termuat. Muat ulang halaman.'];

                return;
            }

            const daftar = Array.isArray(this.antrean) ? [...this.antrean] : [];

            if (daftar.length === 0) {
                this.selesai = true;

                return;
            }

            this.berjalan = true;
            this.dijeda = false;
            this.dibatalkan = false;
            this.selesai = false;
            this.total = daftar.length;
            this.selesaiJumlah = 0;
            this.masalah = [];

            for (const item of daftar) {
                if (this.dibatalkan) {
                    break;
                }

                // Jeda: tunggu sampai guru menekan "Lanjutkan".
                while (this.dijeda && ! this.dibatalkan) {
                    this.statusTeks = 'Dijeda. Tekan "Lanjutkan" untuk melanjutkan.';

                    await new Promise((r) => setTimeout(r, 250));
                }

                if (this.dibatalkan) {
                    break;
                }

                this.statusTeks = `Memeriksa ${item.jenis === 'bug' ? 'kartu bug' : 'misi'} #${item.id}…`;

                try {
                    const periksa = await periksaUlang(item.jenis, item.id);

                    if (! periksa.ok) {
                        this.masalah.push(`#${item.id}: ${periksa.pesan ?? 'pemeriksaan gagal'}`);
                        this.selesaiJumlah += 1;

                        continue;
                    }

                    // Server tetap pemutus: verifikasiSatu() memakai VerifikasiHasil.
                    const hasil = await this.$wire.verifikasiSatu(item.jenis, item.id, {
                        hasil: (periksa.hasil ?? []).map((satu) => ({
                            id: satu.id,
                            lulus: satu.lulus,
                            aktual: satu.aktual,
                            harapan: satu.harapan,
                        })),
                        galat: periksa.galat ?? null,
                    });

                    if (hasil?.status !== 'ok') {
                        this.masalah.push(`#${item.id}: sudah diverifikasi pihak lain.`);
                    } else if (hasil.selisih) {
                        this.masalah.push(`#${item.id}: terverifikasi, tetapi ada selisih hasil.`);
                    }
                } catch (error) {
                    this.masalah.push(`#${item.id}: ${String(error?.message ?? error).slice(0, 120)}`);
                } finally {
                    this.selesaiJumlah += 1;
                }
            }

            this.berjalan = false;
            this.dijeda = false;
            this.selesai = ! this.dibatalkan;
            this.statusTeks = this.dibatalkan
                ? `Dibatalkan setelah ${this.selesaiJumlah} dari ${this.total} item.`
                : `Selesai memproses ${this.selesaiJumlah} item.`;
        },

        jeda() {
            this.dijeda = true;
        },

        lanjut() {
            this.dijeda = false;
        },

        batalkan() {
            this.dibatalkan = true;
            this.dijeda = false;
        },
    }));
}
