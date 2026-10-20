# Penilaian di Perangkat dengan Pyodide

Dokumen ini menjelaskan cara kerja penilaian Fase 6, cara menyajikan aplikasi
untuk kelas nyata, alur verifikasi guru, dan pemecahan masalah.

---

## 1. Cara kerja singkat

```
Siswa menekan Submit
        │
        ▼
Server membuat submission (status 'menunggu') + nonce 64 hex
        │        (server TIDAK PERNAH menjalankan kode siswa)
        ▼
Browser siswa mengambil "paket penilaian"
        │   isi: kode siswa, check_target, setup_code, batas waktu, nonce
        │   TIDAK ADA: expected_value, tolerance, label kasus uji
        ▼
Web Worker (Pyodide) menjalankan kode siswa per kelompok setup_code
        │   keluaran: tipe + nilai MENTAH tiap kasus uji
        ▼
Browser mengirim laporan nilai mentah ke server
        │
        ▼
Server membandingkan sendiri dengan expected_value  ← keputusan ada di sini
        │
        ├─ formatif  → status 'dinilai', poin langsung diberikan
        └─ sumatif   → status 'menunggu_verifikasi', poin menunggu guru
```

Tiga jaminan keamanan:

1. `expected_value` dan `tolerance` tidak pernah dikirim ke browser.
2. Klaim lulus/gagal dari browser diabaikan; server menghitung ulang.
3. Level sumatif baru memberi poin setelah guru memverifikasi.

Pemeriksaan AST di `resources/py/penilai.py` (menolak `import os`,
`__import__`, `eval`, `__class__`, dll.) adalah **lapisan integritas**, bukan
batas keamanan: kode berjalan di perangkat siswa sendiri.

---

## 2. Menyajikan aplikasi untuk kelas nyata

`php artisan serve` **hanya untuk satu orang**. Server bawaan PHP memproses satu
permintaan sekaligus; dengan 30 siswa, halaman akan saling menunggu dan
penilaian tampak "macet".

Gunakan web server sungguhan:

**Laragon (Windows, paling mudah)**

1. Salin folder proyek ke `C:\laragon\www\teater-koding`.
2. Laragon → **Apache/Nginx → Start**.
3. Buka `http://teater-koding.test` (virtual host dibuat otomatis).
4. Jalankan queue worker terpisah: `php artisan queue:work` (tidak wajib untuk
   runner `pyodide`, tetapi dipakai fitur lain).

> **Penting sebelum kelas:** pastikan `public/hot` **tidak ada** dan jalankan
> `npm run build`. Selama `public/hot` ada, aplikasi memakai dev server Vite di
> port lain, dan penilaian Pyodide akan gagal dengan galat
> *"Failed to construct 'Worker' … cannot be accessed from origin"* (lihat §7.2).

### 2.1 Laravel Herd (Windows)

Herd memakai nginx sendiri dan **sudah** memetakan `application/wasm`, jadi
tidak perlu konfigurasi MIME untuk `.wasm`. Namun ada dua hal yang perlu
diperiksa karena Pyodide 314 memakai **ES module** (`.mjs`).

#### MIME `.mjs` wajib `application/javascript`

nginx bawaan Herd hanya memetakan `js`:

```nginx
# ~/.config/herd/config/nginx/nginx.conf (baris ~15) — SEBELUM
application/javascript    js;
```

Akibatnya `pyodide.mjs` disajikan sebagai `application/octet-stream` dan
browser menolaknya (*"Expected a JavaScript-or-Wasm module script…"*).
Perbaiki menjadi:

```nginx
# ~/.config/herd/config/nginx/nginx.conf (baris ~15) — SESUDAH
application/javascript    js mjs;
```

Lalu restart Herd (ikon tray → **Restart**, atau `herd restart`).

> Berkas statis Herd disajikan lewat `X-Accel-Redirect` (`ValetDriver.php`),
> sehingga MIME **ditentukan nginx**, bukan PHP. Karena itu `types { }` di
> config per-situs tidak menyelesaikannya — perbaikan harus di `nginx.conf`
> global. Cadangkan berkasnya lebih dulu:
> `Copy-Item nginx.conf nginx.conf.bak`.

Verifikasi:

```powershell
curl.exe -s -k -I https://teater-koding.test/vendor/pyodide/314.0.7/pyodide.mjs
# harapan: HTTP/1.1 200 OK + Content-Type: application/javascript; charset=utf-8

curl.exe -s -k -I https://teater-koding.test/vendor/pyodide/314.0.7/pyodide.asm.wasm
# harapan: Content-Type: application/wasm
```

#### Jangan mengandalkan `npm run dev` untuk menguji penilaian

Aturan yang sama seperti §7.2: `npm run dev` membuat `public/hot`, dan penilaian
Pyodide gagal bila origin dev server berbeda dari origin aplikasi. Selalu
`npm run build` sebelum menguji.

#### Catatan PHP

Herd memakai PHP 8.4 sendiri; `php` di terminal bisa menunjuk versi lain. Ini
tidak memengaruhi penilaian (yang berjalan di browser), tetapi bila ada
keanehan, bandingkan `php -v` dengan PHP yang dipakai Herd.


**Nginx (Linux)**

```nginx
server {
    listen 443 ssl;
    server_name teater.example.sch.id;
    root /var/www/teater-koding/public;

    index index.php;
    charset utf-8;

    # Aset Pyodide: 10 MB, cache panjang (jalur sudah berversi).
    location /vendor/pyodide/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        types { application/wasm wasm; }
        gzip on;
        gzip_types application/javascript application/json;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Apache** — pastikan `public/` menjadi DocumentRoot, lalu tambahkan:

```apache
<Directory "/var/www/teater-koding/public/vendor/pyodide">
    AddType application/wasm .wasm
    Header set Cache-Control "public, max-age=31536000, immutable"
</Directory>
```

### Yang WAJIB benar

| Hal | Kenapa |
|---|---|
| `.wasm` disajikan sebagai `application/wasm` | Tanpa ini `WebAssembly.instantiateStreaming` gagal dan Pyodide tidak dimuat (gejala: "Penilai bermasalah") |
| Kompresi aktif (gzip/brotli) | `pyodide.asm.wasm` ±9,6 MB; terkompresi ±3 MB |
| Header cache panjang untuk `/vendor/pyodide/<versi>/` | Siswa tidak mengunduh ulang tiap halaman; jalur berversi membuat aman |
| HTTPS | Wajib bila siswa mengerjakan dari rumah (dan untuk passkey) |
| `TEATER_RUNNER=pyodide` | Menyalakan alur penilaian di browser |

---

## 3. Saran operasional di kelas

- **Minta siswa membuka halaman login 5–10 menit sebelum mulai.** Pyodide
  diunduh di latar belakang setelah halaman terbuka (indikator topbar:
  "Menyiapkan penilai" → "Penilai siap").
- Ukuran unduhan pertama: ±10 MB (wasm + stdlib). Setelah itu tersimpan di
  cache browser.
- Bila lab memakai proxy/filter, izinkan `/vendor/pyodide/*` dan
  `/vendor/teater/penilai.py`.
- Untuk pengerjaan di rumah, aplikasi harus dapat dijangkau dari internet
  (HTTPS), karena seluruh aset berasal dari server sendiri — **tanpa CDN**.

---

## 4. Kebutuhan perangkat & browser

| Perangkat | Status |
|---|---|
| Chrome / Edge 111+ | Didukung (diuji) |
| Firefox 111+ | Didukung (diuji) |
| Safari 16.4+ | Didukung (WebAssembly + module worker) |
| Tanpa WebAssembly / module worker | Tidak didukung — indikator penilai menampilkan galat |
| RAM | Minimal 2 GB bebas; Pyodide memakai ±200–400 MB |
| CPU | Laptop 5 tahun terakhir cukup; uji dengan CPU throttling 6x |

Mode privat didukung (tidak bergantung localStorage). Membuka dari `file://`
**tidak** didukung.

---

## 5. Alur verifikasi sumatif (guru)

1. Menu **Penilaian → Verifikasi Penilaian** (lencana angka = jumlah antrean).
2. Ringkasan di atas: *Menunggu Verifikasi*, *Menunggu Penilaian Perangkat*,
   *Selisih Terdeteksi*.
3. Filter kelas & misi; tab **Misi** / **Kartu Bug**.
4. **Periksa** membuka panel: kode siswa (bernomor baris), laporan perangkat,
   tabel *klaim perangkat vs verifikasi guru*, dan **nilai harapan** (hanya di
   halaman guru).
5. **Periksa Sekarang** menjalankan kode yang sama di perangkat guru sebagai
   bukti independen (batas waktu dikali `TEATER_PYODIDE_VERIFY_MULTIPLIER`).
6. **Verifikasi & Beri Poin** → status `dinilai`, poin diberikan, selisih (bila
   ada) dicatat pada `mismatch_detail`.
7. **Verifikasi yang Menunggu** memproses seluruh antrean dengan progres; bisa
   dijeda/dibatalkan. Bila halaman dibuka ulang, antrean dihitung ulang dari
   database sehingga hanya sisa item yang diproses.
8. **Minta Nilai Ulang** mengembalikan item ke siswa dengan nonce baru.

---

## 6. Memperbarui versi Pyodide

1. Ubah versi di `package.json` (`"pyodide": "<versi>"`), lalu `npm install`.
2. `npm run vendor:pyodide` — menyalin berkas inti ke
   `public/vendor/pyodide/<versi>/` (folder ini di-gitignore).
3. Set `TEATER_PYODIDE_VERSION=<versi>` di `.env` (atau biarkan default bila
   sama dengan config).
4. `npm run build`, lalu `php artisan config:clear`.
5. Jalankan `npm run test:pyodide` (memakai aset baru) dan
   `php artisan test --filter=Pyodide`.
6. Uji manual di ketiga browser (`docs/uji-manual-browser.md`).

`pyodide-lock.json` disalin bersama aset; tanpa itu Pyodide menolak dimuat.

---

## 7. Pemecahan masalah

### 7.1 "Sedang dinilai…" tidak pernah selesai (paling sering)

Gejala: siswa submit, lalu halaman Hasil menampilkan **"Sedang dinilai..."**
dan tidak berubah (dulu disertai teks *"penilai otomatis sedang sibuk atau
belum aktif"*). Tidak ada galat di console.

Penyebabnya **bukan Pyodide**, melainkan runner/queue yang tidak cocok:

```bash
php artisan teater:diagnosa-penilaian
```

Perintah itu menunjukkan runner aktif, kondisi queue, aset, dan saran konkret.

| Kondisi | Arti | Solusi |
|---|---|---|
| `Runner: fake/null` + `Job menunggu` naik terus, `attempts=0` | Runner menilai lewat **queue**, tetapi `php artisan queue:work` tidak berjalan | Jalankan `php artisan queue:work` di terminal terpisah, atau pakai `composer dev` yang sudah menjalankannya |
| `Runner: null` | `TEATER_RUNNER=null` (default) → tidak ada yang menilai | Set `TEATER_RUNNER=pyodide` lalu `php artisan config:clear` |
| `Runner: pyodide` + `Aset Pyodide: belum lengkap` | Runtime belum disalin | `npm run vendor:pyodide` lalu `npm run build` |
| `tanpa nonce: N` | Submission dibuat saat runner bukan pyodide | Buka halaman Hasil submission itu; nonce dibuat ulang otomatis |
| `menggantung > 5 menit: N` | Ada submission yang tidak diproses | Lihat baris di atas sesuai runner |

**Penting:** `TEATER_RUNNER=pyodide` **tidak** membutuhkan queue worker —
penilaian berjalan di browser siswa. Hanya `fake` (dan `null`) yang memakai
queue.

Setelah menunggu lebih dari 60 detik, halaman Hasil sekarang **berhenti
polling** dan menampilkan blok **"Penyebab"** berisi salah satu pesan di atas,
plus tombol **Periksa Status**. Ini sengaja: dulu `wire:poll.2s` berjalan
selamanya sampai `Maximum execution time of 30 seconds exceeded` membanjiri
`laravel.log`.

### 7.2 "Failed to construct 'Worker' … cannot be accessed from origin"

Gejala (persis di UI siswa):

```
Penilaian belum berhasil
Failed to construct 'Worker': Script at
'https://teater-koding.test:5173/resources/js/penilai/worker.js?worker_file&type=module'
cannot be accessed from origin 'https://teater-koding.test'.
```

**Ini bukan masalah koneksi dan bukan bug Pyodide.** Browser menolak membuat
Web Worker karena skrip worker berada di **origin berbeda** dari halaman.

Penyebab: aplikasi sedang **mode Vite dev**. Selama berkas `public/hot` ada,
`@vite(...)` mengeluarkan URL absolut ke dev server (port `5173`), sedangkan
halaman dibuka di port `443`. Beda port = beda origin, dan `new Worker(url)`
wajib satu origin. Penanda `?worker_file` adalah cara Vite menyajikan entry
worker pada dev server.

**Solusi:**

```powershell
# 1. Hentikan `npm run dev` (Ctrl+C)
# 2. Hapus penanda mode dev
Remove-Item public/hot

# 3. Bangun aset produksi
npm run build

# 4. Bersihkan cache agar @vite membaca manifest baru
php artisan view:clear
php artisan config:clear
```

Setelah `public/hot` hilang, semua aset — termasuk `worker-*.js` — dimuat
same-origin dari port aplikasi. Pada build produksi, Vite menulis
`new URL('/build/assets/worker-<hash>.js', import.meta.url)`, yaitu jalur
absolut tanpa `worker_file`, sehingga batasan same-origin terpenuhi.

**Cara memeriksa cepat:** jalankan `php artisan teater:diagnosa-penilaian`, atau
periksa isi `public/hot`:

```powershell
Get-Content public/hot      # mis. https://teater-koding.test:5173
```

Bila isinya memuat port/domain berbeda dari URL aplikasi, itulah penyebabnya.

**Aturan kerja praktis:**

| Ingin… | Jalankan |
|---|---|
| Mengembangkan tampilan (hot reload) | `npm run dev` — **penilaian Pyodide tidak akan jalan** |
| Menguji / memakai penilaian | `npm run build` (+ hapus `public/hot`) |
| Kelas nyata | `npm run build`, `public/hot` harus tidak ada |

Jangan menambahkan proxy dev-server untuk menyamakan origin: konfigurasinya
rapuh (banyak path Vite: `/@vite`, `/@id`, `/@fs`, `/node_modules/.vite`, plus
websocket HMR) dan tidak diperlukan karena build produksi sudah benar.

### 7.3 Tabel gejala lain

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| `Failed to construct 'Worker' … cannot be accessed from origin` | `public/hot` ada → aset JS dari port dev (5173), halaman di port 443 | Hapus `public/hot`, lalu `npm run build` (lihat §7.2) |
| `pyodide.mjs … MIME type of "application/octet-stream"` | nginx (Herd/Valet) tidak memetakan `.mjs` | Tambahkan `mjs` ke `application/javascript` di `nginx.conf`, restart server (lihat §7.4) |
| `requestIdleCallback … not of type 'IdleRequestOptions'` | Versi lama memanggil `requestIdleCallback(fn, 2000)` dengan angka | Perbarui aset (`npm run build`); lihat §7.5 |
| Indikator "Penilai bermasalah" | `.wasm` bukan `application/wasm`, atau aset belum disalin | Periksa Network tab; jalankan `npm run vendor:pyodide` |
| `Gagal memuat penilai.py (HTTP 404)` | `resources/py/penilai.py` belum disalin | `npm run vendor:pyodide` (menyalin ke `public/vendor/teater/`) |
| "Batas waktu tercapai saat menyiapkan penilai" | Unduhan lambat / jaringan sekolah | Pramuat lebih awal; pastikan kompresi aktif |
| "Kode melewati batas waktu" padahal kode benar | Batas terlalu ketat untuk laptop lambat | Naikkan `TEATER_PYODIDE_SETUP_TIMEOUT_MS` / `TOTAL_TIMEOUT_MS` |
| "Variabel `saldo` disediakan sistem" | Siswa menulis `saldo = ...` di Level 2 | Hapus baris tersebut; `saldo` disuntik sistem |
| Status tetap "menunggu" setelah tab ditutup | Penilaian terputus di tengah jalan | Buka halaman Hasil → **Nilai Ulang**; guru bisa **Minta Nilai Ulang** |
| Guru melihat "Laporan perangkat berubah" | Perangkat melaporkan nilai berbeda dari sebelumnya | Periksa tabel selisih sebelum memverifikasi |
| Poin sumatif belum masuk setelah siswa selesai | Menunggu verifikasi guru (memang by design) | Verifikasi di halaman Verifikasi Penilaian |
| Halaman admin lambat saat jam mulai | Semua guru membuka bersamaan | Wajar; angka antrean memakai satu query agregat |

Perintah diagnostik:

```bash
php artisan teater:diagnosa-penilaian          # sehat? (exit 1 bila ada bahaya)
php artisan teater:ekspor-kasus-uji            # cek kasus uji terbaca
npm run test:pyodide                           # 49 pemeriksaan runtime Pyodide
npm run uji:penilai                            # runtime + orkestrasi timeout
npm run uji:editor                             # 18 pemeriksaan editor kode
npm run uji:jadwal                             # 21 pemeriksaan penjadwal pramuat
php artisan test --filter=Pyodide              # test PHP Fase 6
```

---

## 8. Detail: MIME `application/octet-stream` pada `pyodide.mjs`

Gejala di console:

```
/vendor/pyodide/314.0.7/pyodide.mjs:1 Failed to load module script:
Expected a JavaScript-or-Wasm module script but the server responded
with a MIME type of "application/octet-stream".
Strict MIME type checking is enforced for module scripts per HTML spec.
```

**Penyebab.** Pyodide 314 dikirim sebagai **ES module** (`pyodide.mjs`,
`pyodide.asm.mjs`). Browser hanya mau memuat module script dengan MIME
JavaScript. Bila server tidak mengenal ekstensi `.mjs`, ia memakai default
`application/octet-stream` → modul ditolak.

Server yang perlu diperiksa:

| Server | Kondisi bawaan | Tindakan |
|---|---|---|
| nginx Herd/Valet | hanya `js` | tambahkan `mjs` ke `application/javascript` di `nginx.conf`, restart (§2.1) |
| nginx biasa | biasanya hanya `js` | `types { application/javascript mjs; }` di `http`/`server` |
| Apache | `mjs` sering tidak terdaftar | `AddType application/javascript .mjs` |
| `php artisan serve` | sudah benar (PHP built-in) | tidak perlu apa pun |

**Cek cepat (satu baris):**

```powershell
curl.exe -s -k -I https://<host>/vendor/pyodide/314.0.7/pyodide.mjs | Select-String Content-Type
# harus: Content-Type: application/javascript; charset=utf-8
```

Bila masih `application/octet-stream`, Pyodide **tidak akan pernah** dimuat,
sebesar apa pun bandwidth Anda — ini murni header MIME.

---

## 9. Detail: `requestIdleCallback … not of type 'IdleRequestOptions'`

Gejala di console:

```
Uncaught TypeError: Failed to execute 'requestIdleCallback' on 'Window':
The provided value is not of type 'IdleRequestOptions'.
    at … window.addEventListener.once
```

**Penyebab.** Kode lama memakai pola ringkas yang salah:

```js
(window.requestIdleCallback ?? window.setTimeout)(mulai, 2000);
```

Kedua API menerima parameter kedua dengan **jenis berbeda**:

| API | Parameter ke-2 |
|---|---|
| `requestIdleCallback(cb, options)` | **objek** `{ timeout: 2000 }` |
| `setTimeout(cb, delay)` | **angka** `2000` |

Ketika browser punya `requestIdleCallback`, ia menerima angka `2000` dan
melempar `TypeError`. Pramuat Pyodide gagal, penilaian tidak berjalan.

**Perbaikan.** Sejak rilis ini, penjadwalan dipindah ke
`resources/js/penilai/jadwal.js` (`jadwalkanSantai`), yang:

1. memanggil `requestIdleCallback(fn, { timeout: ms })` dengan **objek**,
2. jatuh ke `setTimeout(fn, ms)` bila API tidak ada **atau melempar**,
3. menjalankan `fn` langsung bila keduanya tidak tersedia (pekerjaan tidak hilang).

Diuji tanpa browser oleh `npm run uji:jadwal` (21 pemeriksaan), termasuk
memastikan angka `2000` **tidak pernah** dikirim sebagai opsi.

Bila Anda masih melihat galat ini: aset lama masih ter-cache. Jalankan
`npm run build` lalu hard-reload (Ctrl+Shift+R).


