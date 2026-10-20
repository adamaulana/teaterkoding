<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Teater Koding — Pemantauan & Penilaian
|--------------------------------------------------------------------------
| Nilai yang bisa disetel tanpa mengubah kode. Dipakai Service
| (TeamProgressResolver, ConceptUsageAnalyzer, SumativeScoreCalculator)
| dan halaman Pantau Live / Rekap Nilai Sumatif.
*/

return [

    /*
    | Ambang "macet" per kelompok. Kelompok dianggap macet bila salah satu
    | terpenuhi DAN belum lulus misi yang sedang dikerjakan.
    |
    | - stuck_no_submission_minutes: tidak ada submission selama N menit
    |   setelah kelompok mulai mengerjakan (punya submission/rencana).
    | - stuck_consecutive_failures: N submission gagal beruntun pada misi
    |   yang sama (semuanya belum all_passed).
    */
    'stuck' => [
        'no_submission_minutes' => 10,
        'consecutive_failures' => 5,
    ],

    /*
    | Interval polling halaman Pantau Live (detik). Dipakai wire:poll.visible.
    */
    'poll_interval_seconds' => 7,

    /*
    | Aturan "submission acuan" per tim per misi:
    |   last  = submission TERAKHIR (default)
    |   first = submission PERTAMA
    |   best  = submission dengan kasus uji lulus terbanyak
    */
    'reference_submission' => 'last',

    /*
    | Poin maksimal bonus rencana misi. Dipakai sebagai pembagi tambahan pada
    | komponen "Kartu bug & rencana". Belum ditetapkan di dokumen konten,
    | sehingga dijadikan konfigurasi agar mudah disesuaikan.
    */
    'plan_bonus_points' => 10,

    /*
    | Awalan yang ditempelkan pada sel CSV yang dimulai salah satu karakter
    | berikut, untuk mencegah CSV injection saat dibuka di Excel/Sheets.
    */
    'csv_injection_chars' => ['=', '+', '-', '@'],
    'csv_injection_prefix' => "'",

    /*
    | Bobot default penilaian sumatif (harus berjumlah 100).
    */
    'default_weights' => [
        'w_individual' => 50,
        'w_team' => 20,
        'w_bug_plan' => 10,
        'w_reflection' => 10,
        'w_observation' => 10,
    ],

    /*
    | Label komponen penilaian (dipakai tabel & ekspor CSV).
    */
    'komponen_labels' => [
        'individu' => 'Fungsi Individu',
        'kelompok' => 'Kelompok',
        'bug_plan' => 'Kartu Bug & Rencana',
        'refleksi' => 'Refleksi/Rubrik',
        'observasi' => 'Observasi',
    ],

    /*
    | Pemetaan nama kolom bobot (grading_settings) ke kunci komponen.
    |
    | Dipakai agar tampilan TIDAK PERNAH menampilkan nama kolom mentah
    | (w_individual, dst) ke guru — semua lewat label Indonesia di atas.
    */
    'komponen_per_bobot' => [
        'w_individual' => 'individu',
        'w_team' => 'kelompok',
        'w_bug_plan' => 'bug_plan',
        'w_reflection' => 'refleksi',
        'w_observation' => 'observasi',
    ],

    /*
    | Nama kelas demo yang dibuat DemoDataSeeder (dipakai juga oleh
    | perintah teater:hapus-demo).
    */
    'demo_class_name' => '[DEMO] X RPL',

    /*
    |--------------------------------------------------------------------------
    | Fase 5 — Sisi Siswa
    |--------------------------------------------------------------------------
    | Semua nilai di bawah bisa diubah lewat .env tanpa menyentuh kode.
    */

    /*
    | Batas percobaan login siswa (per menit, per IP) dan pembukaan misi
    | (per menit, per siswa). Melindungi dari tebak-tebakan kode.
    */
    'login_throttle' => (int) env('TEATER_LOGIN_THROTTLE', 10),
    'unlock_throttle' => (int) env('TEATER_UNLOCK_THROTTLE', 5),

    /*
    | Jeda minimal (detik) antar-submit untuk kelompok yang sama pada misi
    | yang sama, agar tombol tidak ditekan berulang.
    */
    'submit_interval_seconds' => (int) env('TEATER_SUBMIT_INTERVAL', 15),

    /*
    | Ukuran maksimum kode siswa (dalam BYTE, bukan karakter). Byte NUL
    | selalu ditolak terlepas dari batas ini.
    */
    'max_code_bytes' => (int) env('TEATER_MAX_CODE_BYTES', 20000),

    /*
    | Aturan main:
    |   enforce_roles           — batasi aksi menurut peran (Analyst/Coder/dst).
    |                             Bila false, semua anggota boleh semua aksi.
    |   require_review          — wajib ditinjau Reviewer sebelum submit.
    |                             Bila false, hanya peringatan.
    |   require_sequential_levels — level sebelumnya harus lulus dulu.
    */
    'enforce_roles' => (bool) env('TEATER_ENFORCE_ROLES', true),
    'require_review' => (bool) env('TEATER_REQUIRE_REVIEW', false),
    'require_sequential_levels' => (bool) env('TEATER_REQUIRE_SEQUENTIAL', true),

    /*
    | Poin permainan (dipakai ScoringService).
    */
    'hint_penalty' => (int) env('TEATER_HINT_PENALTY', 5),
    'plan_bonus' => (int) env('TEATER_PLAN_BONUS', 10),
    'first_try_bonus' => (int) env('TEATER_FIRST_TRY_BONUS', 10),

    /*
    | Tampilkan papan peringkat di sisi siswa.
    */
    'show_leaderboard' => (bool) env('TEATER_SHOW_LEADERBOARD', true),

    /*
    | Jeda autosave draf kode (milidetik) di editor CodeMirror.
    */
    'draft_autosave_ms' => (int) env('TEATER_DRAFT_AUTOSAVE_MS', 1500),

    /*
    | Penilai otomatis yang dipakai (kontrak App\Contracts\GradingRunner):
    |   null    → NullGradingRunner  (tidak menilai, status 'menunggu')
    |   fake    → FakeGradingRunner  (HANYA local/testing: menilai alur UI)
    |   pyodide → penilaian di BROWSER siswa (lihat blok 'pyodide' di bawah)
    |
    | PENTING: aplikasi ini TIDAK PERNAH menjalankan kode siswa di server.
    | Runner 'pyodide' hanya menyiapkan paket penilaian + nonce; eksekusi
    | terjadi di perangkat siswa, dan server tetap membandingkan dengan
    | expected_value.
    |
    | Catatan: integrasi sandbox Piston/Docker DIBATALKAN (tidak berjalan di
    | laptop Windows) dan tidak pernah dibuat di repo ini.
    */
    'runner' => env('TEATER_RUNNER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Fase 6 — Penilaian di browser dengan Pyodide
    |--------------------------------------------------------------------------
    */

    'pyodide' => [
        // Versi dikunci agar aset berversi & cache browser aman.
        'version' => env('TEATER_PYODIDE_VERSION', '314.0.7'),

        // Jalur aset publik (TANPA CDN). Diisi npm run vendor:pyodide.
        'asset_path' => env('TEATER_PYODIDE_ASSET_PATH', '/vendor/pyodide'),

        // Skrip penilai Python di sisi browser (disalin npm run vendor:pyodide).
        'script_path' => env('TEATER_PYODIDE_SCRIPT_PATH', '/vendor/teater/penilai.py'),

        // Batas waktu per kelompok setup_code (ms) dan total seluruh paket (ms).
        'setup_timeout_ms' => (int) env('TEATER_PYODIDE_SETUP_TIMEOUT_MS', 3000),
        'total_timeout_ms' => (int) env('TEATER_PYODIDE_TOTAL_TIMEOUT_MS', 15000),

        // Verifikasi guru boleh lebih longgar (laptop guru bisa lebih lambat).
        'verify_timeout_multiplier' => (int) env('TEATER_PYODIDE_VERIFY_MULTIPLIER', 2),

        // Panjang keluaran (stdout) yang dikirim balik & disimpan.
        'max_output_chars' => (int) env('TEATER_PYODIDE_MAX_OUTPUT', 2000),

        // Modul yang diizinkan diimpor kode siswa (AST memeriksa ini).
        'allowed_modules' => [
            'math',
            'statistics',
            'random',
            'datetime',
            'decimal',
            'fractions',
        ],

        // Nonce sekali pakai untuk laporan dari browser.
        'nonce_ttl_minutes' => (int) env('TEATER_PYODIDE_NONCE_TTL', 15),

        // Level sumatif WAJIB diverifikasi guru sebelum poin diberikan.
        'require_verification_for_summative' => (bool) env('TEATER_PYODIDE_REQUIRE_VERIFICATION', true),
    ],

    /*
    | Penanda baris pemisah fungsi pada starter_code misi sumatif.
    | Contoh: "# === FUNGSI: hitung_bunga ==="
    */
    'fungsi_marker' => env('TEATER_FUNGSI_MARKER', '# === FUNGSI:'),

    /*
    | Identitas publik untuk landing page. Keduanya OPSIONAL: footer hanya
    | menampilkannya bila terisi. Biarkan kosong bila tidak ingin dipublikasikan.
    */
    'landing' => [
        'sekolah_nama' => env('TEATER_SEKOLAH_NAMA'),
        'kontak_email' => env('TEATER_KONTAK_EMAIL'),
    ],
];
