# Uji Manual Browser — Penilaian di Perangkat (Fase 6)

Dokumen ini adalah daftar periksa untuk hal-hal yang **tidak bisa** diuji lewat
Pest atau Node: perilaku browser sungguhan, perangkat lambat, dan kondisi
jaringan. Jalankan sekali setiap kali versi Pyodide dinaikkan atau penilai
(`resources/py/penilai.py`) berubah.

## Persiapan

1. `.env`: `TEATER_RUNNER=pyodide`
2. `npm run vendor:pyodide && npm run build`
3. `php artisan db:seed --class=DemoDataSeeder`
4. `php artisan serve` **hanya untuk uji cepat**. Untuk uji kelas ramai, sajikan
   lewat Laragon/Apache/Nginx (lihat `docs/pyodide.md`).
5. Masuk sebagai siswa demo (kode akses dicetak seeder) dan sebagai guru.

## Matriks browser

Ulangi kelompok uji inti di **Chrome, Edge, dan Firefox** (versi terbaru).

| # | Skenario | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| B1 | Penilaian normal | Buka misi L1 → Koding → Submit | Halaman Hasil menampilkan "Menilai kode di perangkat", lalu hasil per kasus |
| B2 | Indikator topbar | Amati topbar saat halaman siswa dibuka | "Menyiapkan penilai" → "Penilai siap" |
| B3 | Muat ulang saat menilai | Tekan F5 saat bilah progres berjalan | Penilaian berlanjut otomatis (auto-resume), tidak ada hasil ganda |
| B4 | Tab ditutup di tengah | Tutup tab saat menilai, buka lagi halaman misi | Status tetap "menunggu"; tombol "Nilai Ulang" berhasil |
| B5 | Kode loop tak berhenti | Tulis `while True: pass` → Submit | Berhenti otomatis (< 5 detik), pesan "Kode melewati batas waktu", halaman tetap responsif |
| B6 | Offline setelah aset termuat | Muat halaman siswa, tunggu "Penilai siap", aktifkan mode offline, lalu submit | Penilaian tetap berjalan; pelaporan ke server gagal dengan pesan jelas |
| B7 | Mode privat / penyimpanan kosong | Buka di jendela privat, submit | Sama seperti B1 (tidak bergantung localStorage) |
| B8 | Throttling CPU 6x | DevTools → Performance → CPU 6x, lalu submit | Masih selesai < batas waktu total; bila tidak, pesan waktu habis muncul rapi |
| B9 | Level 2 menulis ulang `saldo` | Tulis `saldo = 5000000` di L2 | Pesan khusus: variabel `saldo` disediakan sistem |
| B10 | Import terlarang | Tulis `import os` | Ditolak dengan pesan "perintah yang tidak diizinkan" |
| B11 | Sumatif → verifikasi guru | Selesaikan L4 sebagai siswa, buka halaman guru | Item muncul di "Verifikasi Penilaian"; verifikasi memberi poin |
| B12 | Verifikasi massal | Guru menekan "Verifikasi yang Menunggu" dengan ≥2 item | Progres berjalan; "Jeda", "Lanjutkan", "Batalkan" berfungsi |
| B13 | Lanjut setelah reload | Batalkan di tengah, muat ulang halaman guru, tekan tombol lagi | Hanya sisa antrean yang diproses (tidak mengulang yang sudah terverifikasi) |

## Catatan hasil

Catat untuk setiap kegagalan: browser + versi, langkah, pesan galat, dan
tangkapan layar. Bila menyangkut batas waktu, sertakan nilai
`TEATER_PYODIDE_*` yang dipakai.

## Yang SUDAH diotomatiskan (jangan diulang manual)

`npm run test:pyodide` (49 pemeriksaan) sudah menguji hal-hal berikut di Node
memakai aset & skrip penilai yang sama dengan produksi:

- jawaban acuan L1–L4 lulus semua kasus uji nyata (5/16/5/13),
- 8 Kartu Bug: kode bermasalah gagal, versi perbaikan lulus,
- pesan khusus untuk `saldo = ...` di Level 2,
- penolakan `import os`, `__import__`, `import js`, `eval`, `__class__`,
- print palsu tidak mempengaruhi hasil,
- batas waktu + `terminate()` + pemulihan worker (poin 2f **berhasil** di
  `worker_threads` Node, jadi tidak perlu diserahkan ke uji manual).

Yang tetap manual di sini adalah perilaku **browser sungguhan** (B1–B13):
indikator topbar, reload, tab ditutup, mode offline, mode privat, CPU 6x,
dan alur verifikasi massal lewat UI.

