# teaterkoding

Media Pembelajaran Interaktif Coding Python — Laravel 13 + Livewire 4.

Siswa mengerjakan misi Python dan Kartu Bug; kode siswa **dinilai di browser
siswa sendiri** memakai Pyodide (self-hosted, tanpa CDN), lalu server tetap
yang membandingkan nilai mentah dengan kunci jawaban. Level sumatif baru
memberi poin setelah guru memverifikasi.

## Mulai cepat

```bash
composer setup          # install, .env, key, migrate, npm install, build
php artisan db:seed --class=DemoDataSeeder   # data contoh + materi
composer dev            # server + queue + vite
```

Siswa masuk lewat `/siswa/masuk` dengan kode akses yang dicetak seeder; guru
masuk lewat `/login`.

> **Sebelum menguji penilaian:** jalankan `npm run build` dan pastikan
> `public/hot` tidak ada. Selama `npm run dev` aktif, aset JS dimuat dari port
> dev (mis. `:5173`) sehingga Web Worker Pyodide ditolak browser dengan galat
> *"Failed to construct 'Worker' … cannot be accessed from origin"*.
> Rincian: [`docs/pyodide.md` §7.2](docs/pyodide.md).

> **Laravel Herd:** nginx bawaan Herd perlu memetakan `.mjs` ke
> `application/javascript` agar `pyodide.mjs` bisa dimuat. Satu baris di
> `~/.config/herd/config/nginx/nginx.conf` (`application/javascript  js mjs;`)
> lalu `herd restart`. Lihat
> [`docs/pyodide.md` §2.1](docs/pyodide.md).

## Perintah penting

| Perintah | Kegunaan |
|---|---|
| `npm run vendor:pyodide` | Salin runtime Pyodide + `penilai.py` ke `public/vendor/` |
| `npm run build` | Build aset (otomatis menyalin Pyodide lebih dulu) |
| `npm run dev` | Dev server Vite — **membuat `public/hot`**, dan penilaian Pyodide tidak jalan selama berkas itu ada |
| `npm run test:pyodide` | Uji runtime Pyodide di Node (49 pemeriksaan) |
| `npm run uji:penilai` | Uji runtime + orkestrasi batas waktu |
| `npm run uji:jadwal` | Uji penjadwal pramuat Pyodide (21 pemeriksaan) |
| `php artisan test` | Seluruh test PHP |
| `php artisan teater:diagnosa-penilaian` | Cek runner, queue, dan aset Pyodide (exit 1 bila bermasalah) |
| `php artisan teater:ekspor-kasus-uji` | Ekspor kasus uji ke JSON untuk uji Node |
| `php artisan teater:hapus-demo` | Hapus kelas demo (materi global aman) |

## Dokumentasi

- [`docs/pyodide.md`](docs/pyodide.md) — cara kerja penilaian di perangkat,
  penyajian untuk kelas ramai (Laragon/Apache/Nginx), alur verifikasi guru,
  memperbarui versi Pyodide, pemecahan masalah.
- [`docs/uji-manual-browser.md`](docs/uji-manual-browser.md) — daftar uji manual
  Chrome/Edge/Firefox (CPU throttling, offline, tab ditutup, mode privat).

## Prinsip yang dijaga

1. Server **tidak pernah** menjalankan kode siswa.
2. `expected_value` dan `tolerance` tidak pernah dikirim ke browser siswa.
3. Klaim lulus/gagal dari browser diabaikan; server menghitung ulang.
4. Poin sumatif hanya diberikan setelah guru memverifikasi.
5. Tidak memakai CDN untuk aset apa pun.

## Aset gambar peran

Ilustrasi peran untuk sisi siswa ada di `public/images/`:

| Peran (database) | Berkas | Ukuran |
|---|---|---|
| `Coder` | `Coder.png` | 500×500, latar transparan |
| `Tester` | `Tester.png` | 500×500, latar transparan |
| `Analyst` | `Analyst.png` | 500×500, latar transparan |
| `Reviewer` | `Reviewer.png` | 500×500, latar transparan |
| `Bug Hunter` | `Bug_Hunter.png` | 500×500, latar transparan |

**Aturan penamaan:** nama peran di database memakai **spasi**, sedangkan berkas
memakai **garis bawah**. Pemetaan dan normalisasi (`Bug_Hunter` → `Bug Hunter`)
ada di satu tempat: `resources/views/components/ui/kartu-peran.blade.php`.

Dipakai oleh:

- **Beranda siswa** — kartu sorotan "Peranmu saat ini" (varian `utama`) dan
  grid anggota kelompok (varian `anggota`), lihat
  `resources/views/livewire/siswa/beranda.blade.php`.
- **Topbar siswa** — chip gambar mini per peran
  (`resources/views/layouts/siswa/topbar.blade.php`).

**Menambah peran baru:**

1. Tambahkan nama peran di `database/seeders/RolesSeeder.php`.
2. Simpan ilustrasinya di `public/images/<Nama_Peran>.png` (500×500, transparan).
3. Daftarkan pemetaannya di `kartu-peran.blade.php` (peta `$berkasPeran`,
   `$tugasPeran`, dan `$aksenPeran`).
4. Jalankan `php artisan test --filter=SiswaBerandaTest` — ada test yang
   memastikan setiap berkas yang dipetakan benar-benar ada.

Peran yang belum ditetapkan ditampilkan sebagai **"Belum ada peran"** dengan
ikon netral — sengaja tidak memakai ilustrasi apa pun agar tidak menyesatkan.
 
