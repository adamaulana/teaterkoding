<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Landing page publik (Fase 7).
 *
 * Yang dijaga di sini:
 *   - rute "/" tetap 200 untuk tamu dan memakai nama rute 'home',
 *   - tidak ada tautan registrasi (registrasi publik dimatikan),
 *   - tidak ada aset eksternal (tanpa CDN),
 *   - struktur SEO/aksesibilitas dasar: tepat satu <h1>, meta description,
 *     skip-link, dan id bagian untuk anchor.
 */
uses(RefreshDatabase::class);

test('tamu dapat membuka landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Belajar Python dasar jadi misi seru bersama tim.')
        ->assertSee('Teater Koding');
});

test('nama rute home tetap dipertahankan', function () {
    expect(route('home', absolute: false))->toBe('/');
});

test('landing memuat anchor bagian yang dituju penanda scroll', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Bagian Tahap 2 belum ada; hero + panel penanda sudah memakai id ini.
    expect($html)->toContain('id="beranda"')
        ->and($html)->toContain('id="konten"')
        ->and($html)->toContain('href="#peran"')
        ->and($html)->toContain('href="#penilaian"')
        ->and($html)->toContain('href="#keunggulan"');
});

test('tamu melihat tombol masuk siswa dan guru', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Masuk dengan Kode Akses')
        ->assertSee('Masuk sebagai Guru')
        ->assertDontSee('Buka Dashboard')
        ->assertDontSee('Lanjut Bermain');
});

test('guru yang login melihat tombol buka dashboard', function () {
    $guru = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    $this->actingAs($guru)
        ->get('/')
        ->assertOk()
        ->assertSee('Buka Dashboard')
        ->assertDontSee('Masuk sebagai Guru')
        // Guru tidak diberi tautan siswa: middleware siswa.auth menolaknya (403).
        ->assertDontSee('Lanjut Bermain');
});

test('siswa yang login melihat tombol lanjut bermain', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $siswa = Student::create([
        'class_id' => $kelas->class_id,
        'full_name' => 'Budi Santoso',
        'access_code' => 'ABCD2345',
    ]);

    $this->actingAs($siswa, 'siswa')
        ->get('/')
        ->assertOk()
        ->assertSee('Lanjut Bermain')
        ->assertDontSee('Masuk dengan Kode Akses');
});

test('landing tidak menautkan halaman registrasi', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('register')
        ->and($html)->not->toContain('Daftar');
});

test('landing tidak memuat aset eksternal (tanpa CDN)', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Semua src/href aset harus relatif atau menunjuk domain sendiri.
    preg_match_all('/(?:src|href)="(https?:\/\/[^"]+)"/', $html, $cocok);

    $asing = array_values(array_filter(
        $cocok[1] ?? [],
        fn (string $url) => ! str_starts_with($url, (string) config('app.url')),
    ));

    expect($asing)->toBe([]);
});

test('landing memiliki tepat satu h1 dan meta description', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count(strtolower($html), '<h1'))->toBe(1)
        ->and($html)->toContain('name="description"')
        ->and($html)->toContain('property="og:title"');
});

test('landing menyediakan tautan lewati ke konten', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Lewati ke konten')
        ->assertSee('href="#konten"', false);
});

test('landing tidak memuat bundel editor atau penilai', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Landing punya entri JS sendiri; jangan sampai app.js (CodeMirror,
    // Pyodide, Alpine penilai) ikut termuat.
    expect($html)->not->toContain('codemirror')
        ->and($html)->not->toContain('pyodide')
        ->and($html)->not->toContain('penilai/worker');
});

// ============================================================
// TAHAP 2: tiga bagian isi + CTA penutup
// ============================================================

test('landing memuat ketiga bagian dengan id anchor', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('id="peran"')
        ->and($html)->toContain('id="penilaian"')
        ->and($html)->toContain('id="keunggulan"');
});

test('bagian peran memuat lima peran beserta julukannya', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Setiap siswa punya peran, dan perannya bergiliran.')
        ->assertSee('Pembaca Misi')
        ->assertSee('Penulis Kode')
        ->assertSee('Pencari Celah')
        ->assertSee('Pemeriksa Kode')
        ->assertSee('Pemburu Bug')
        ->assertSee('peran bergeser');
});

test('kartu peran memakai ilustrasi dari public/images', function () {
    $html = $this->get('/')->assertOk()->getContent();

    foreach (['Analyst.png', 'Coder.png', 'Tester.png', 'Reviewer.png', 'Bug_Hunter.png'] as $berkas) {
        expect($html)->toContain('images/'.$berkas);
    }
});

test('bagian penilaian memuat sumber poin misi', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Poin misi (Level 1-3, latihan)')
        ->assertSee('Bonus percobaan pertama')
        ->assertSee('Bonus rencana')
        ->assertSee('Bonus Kartu Bug')
        ->assertSee('Penalti petunjuk');
});

test('bobot sumatif diambil dari config, bukan angka hardcode', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Lima komponen dengan label Indonesia (bukan nama kolom mentah).
    expect($html)->toContain('Fungsi Individu')
        ->and($html)->toContain('Kartu Bug &amp; Rencana')
        ->and($html)->toContain('Refleksi/Rubrik')
        ->and($html)->toContain('Observasi')
        // Nama kolom bobot tidak boleh bocor ke halaman publik.
        ->and($html)->not->toContain('w_individual')
        ->and($html)->not->toContain('w_bug_plan');
});

test('total bobot sumatif berjumlah 100 persen', function () {
    $bobot = config('teater.default_weights');

    expect(array_sum($bobot))->toBe(100);
});

test('bagian keunggulan memuat enam kartu', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Kenapa Teater Koding?')
        ->assertSee('Kartu fisik + aplikasi saling terhubung')
        ->assertSee('Satu tema, satu cerita')
        ->assertSee('Nilai individu yang adil di kerja kelompok')
        ->assertSee('Umpan balik per kasus uji dan latihan debugging')
        ->assertSee('Tanpa instalasi')
        ->assertSee('Guru memantau secara langsung');
});

test('CTA penutup memakai tombol dinamis yang sama dengan navbar', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Tamu: kedua label muncul di navbar DAN CTA penutup.
    expect(substr_count($html, 'Masuk dengan Kode Akses'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, 'Masuk sebagai Guru'))->toBeGreaterThanOrEqual(2);
});

test('urutan heading landing benar (h1 lalu h2, tanpa lompatan)', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match_all('/<h([1-3])[ >]/', $html, $cocok);

    $level = array_map('intval', $cocok[1] ?? []);

    expect($level[0] ?? null)->toBe(1);

    // Tidak boleh ada h3 sebelum h2 pertama.
    $posisiH2 = array_search(2, $level, true);
    $posisiH3 = array_search(3, $level, true);

    if ($posisiH3 !== false) {
        expect($posisiH2)->not->toBeFalse()
            ->and($posisiH2)->toBeLessThan($posisiH3);
    }
});

test('footer menampilkan nama aplikasi dan tahun berjalan', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Game Edukasi Python Dasar untuk Kelas')
        ->assertSee((string) now()->year);
});

test('footer hanya menampilkan identitas sekolah bila dikonfigurasi', function () {
    config(['teater.landing.sekolah_nama' => '', 'teater.landing.kontak_email' => '']);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('SMK Contoh Nusantara')
        ->assertDontSee('mailto:');

    config([
        'teater.landing.sekolah_nama' => 'SMK Contoh Nusantara',
        'teater.landing.kontak_email' => 'guru@contoh.test',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('SMK Contoh Nusantara')
        ->assertSee('guru@contoh.test');
});
