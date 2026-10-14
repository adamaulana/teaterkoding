<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

/**
 * Regresi: kolom ber-`format` pada x-ui.data-table harus dirender sebagai
 * markup, bukan sebagai teks HTML yang ter-escape.
 */
test('kolom format pada tabel kelas dirender sebagai markup', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $admin->id]);

    $response = $this->actingAs($admin)->get('/admin/kelas')->assertOk();

    $response->assertSee('<div class="flex items-center gap-3">', false);
    $response->assertSee('X RPL 1');
    $response->assertSee('2026/2027');
    $response->assertDontSee('&lt;div', false);
    $response->assertDontSee('&lt;span', false);
    $response->assertDontSee('&lt;svg', false);
});

test('kolom format pada tabel siswa dirender sebagai markup', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $admin->id]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'student_no' => '24001']);

    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $response = $this->actingAs($admin)->get('/admin/siswa')->assertOk();

    $response->assertSee('Budi Santoso');
    $response->assertSee('24001');
    $response->assertSee('Kelompok 1');
    $response->assertDontSee('&lt;div', false);
    $response->assertDontSee('&lt;span', false);
});

test('kolom kelompok siswa menampilkan badge markup bukan teks escape', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $admin->id]);
    Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siti Aminah', 'student_no' => null]);

    $response = $this->actingAs($admin)->get('/admin/siswa')->assertOk();

    // Siswa tanpa kelompok → badge "Belum ada kelompok".
    $response->assertSee('Belum ada kelompok');
    $response->assertDontSee('&lt;span', false);
});

test('kolom format pada tabel pengguna dirender sebagai markup', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'guru', 'name' => 'Budi Santoso']);

    $response = $this->actingAs($admin)->get('/admin/pengguna')->assertOk();

    $response->assertSee('Budi Santoso');
    $response->assertSee('<div class="flex items-center gap-3">', false);
    $response->assertDontSee('&lt;div', false);
    $response->assertDontSee('&lt;span', false);
});

/**
 * Keamanan: kolom TANPA closure `format` harus tetap di-escape otomatis.
 */
test('kolom tanpa format tetap di-escape sehingga aman dari XSS', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    SchoolClass::create([
        'class_name' => '<script>alert(1)</script>',
        'school_year' => '2026/2027',
        'user_id' => $admin->id,
    ]);

    $out = Blade::render(
        '<x-ui.data-table :rows="$rows" :columns="$columns" />',
        [
            'rows' => [['nama' => '<script>alert(1)</script>']],
            'columns' => [['key' => 'nama', 'label' => 'Nama']],
        ],
    );

    expect($out)->toContain('&lt;script&gt;')
        ->and($out)->not->toContain('<script>');
});

test('seluruh halaman bertabel tidak memuat markup ter-escape', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $admin->id]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => '1']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    foreach (['/admin/kelas', '/admin/siswa', '/admin/pengguna', '/admin/kelompok', '/admin/penugasan-peran'] as $path) {
        $html = $this->actingAs($admin)->get($path)->assertOk()->getContent();

        foreach (['&lt;div', '&lt;span', '&lt;svg'] as $token) {
            expect(str_contains($html, $token))->toBeFalse("Halaman {$path} memuat markup ter-escape: {$token}");
        }
    }
});
