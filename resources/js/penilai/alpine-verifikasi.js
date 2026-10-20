/**
 * Komponen Alpine `penilaiVerifikasi` — penilaian ulang di perangkat GURU.
 *
 * Dipakai di halaman Verifikasi Nilai. Guru menjalankan kode siswa yang sama
 * di perangkatnya sendiri, lalu server membandingkan hasilnya dengan
 * expected_value dan mengembalikan rincian per kasus (lulus/gagal, nilai
 * aktual, nilai harapan). Ini bukti independen di samping laporan siswa.
 *
 * Tidak pernah dipakai di sisi siswa: endpoint-nya khusus guru dan memeriksa
 * kepemilikan kelas lewat policy.
 */
export function daftarPenilaiVerifikasiAlpine(Alpine) {
    Alpine.data('penilaiVerifikasi', ({ jenis, id }) => ({
        jenis,
        id,
        sedangJalan: false,
        sudahDiperiksa: false,
        persen: 0,
        pesanGalat: '',
        ringkasan: null,
        rincian: [],

        async periksa() {
            if (this.sedangJalan) {
                return;
            }

            const periksaUlang = window.TeaterKoding?.periksaUlang;

            if (! periksaUlang) {
                this.pesanGalat = 'Modul penilai belum termuat. Muat ulang halaman.';

                return;
            }

            this.sedangJalan = true;
            this.sudahDiperiksa = false;
            this.pesanGalat = '';
            this.persen = 5;

            try {
                const hasil = await periksaUlang(this.jenis, this.id, (tahap, info) => {
                    if (tahap === 'kelompok' && info.dari > 0) {
                        this.persen = Math.round((info.ke / info.dari) * 90);
                    }
                });

                if (! hasil.ok) {
                    this.pesanGalat = hasil.pesan ?? 'Pemeriksaan gagal dijalankan.';

                    return;
                }

                this.sudahDiperiksa = true;
                this.persen = 100;
                this.ringkasan = { lulus: hasil.lulus ?? 0, total: hasil.total ?? 0 };
                this.rincian = hasil.hasil ?? [];

                // Simpan hasil pemeriksaan ke komponen Livewire agar tombol
                // "Verifikasi" mengirim bukti ini (bukan sekadar menyetujui).
                this.$wire.simpanPemeriksaan({
                    hasil: (hasil.hasil ?? []).map((satu) => ({
                        id: satu.id,
                        lulus: satu.lulus,
                        aktual: satu.aktual,
                        harapan: satu.harapan,
                    })),
                    galat: hasil.galat ?? null,
                });
            } catch (error) {
                this.pesanGalat = String(error?.message ?? error).slice(0, 200);
            } finally {
                this.sedangJalan = false;
            }
        },
    }));
}
