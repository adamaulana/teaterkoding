<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Kelas milik guru lain tidak boleh diintip lewat query string ?kelas=ID.
 */
test('guru tidak bisa membuka halaman siswa/kelompok/penugasan kelas guru lain', function () {
    $guruA = User::factory()->create(['role' => 'guru']);
    $guruB = User::factory()->create(['role' => 'guru']);

    $kelasB = SchoolClass::create([
        'class_name' => 'XI RPL 2',
        'school_year' => '2026/2027',
        'user_id' => $guruB->id,
    ]);

    foreach (['/admin/siswa', '/admin/kelompok', '/admin/penugasan-peran'] as $path) {
        $this->actingAs($guruA)->get($path.'?kelas='.$kelasB->class_id)->assertForbidden();
    }
});

test('admin bisa membuka kelas milik guru mana pun', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $guru = User::factory()->create(['role' => 'guru']);

    $kelas = SchoolClass::create([
        'class_name' => 'XI RPL 3',
        'school_year' => '2026/2027',
        'user_id' => $guru->id,
    ]);

    foreach (['/admin/siswa', '/admin/kelompok', '/admin/penugasan-peran'] as $path) {
        $this->actingAs($admin)->get($path.'?kelas='.$kelas->class_id)->assertOk();
    }
});

test('guru membuat kelas menjadi pemiliknya otomatis', function () {
    $guru = User::factory()->create(['role' => 'guru']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelas')
        ->call('bukaTambah')
        ->set('form.class_name', 'X RPL 1')
        ->set('form.school_year', '2026/2027')
        ->call('simpanKelas')
        ->assertHasNoErrors();

    $kelas = SchoolClass::query()->where('class_name', 'X RPL 1')->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->user_id)->toBe($guru->id);
});

test('admin bisa memilih pemilik kelas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $guru = User::factory()->create(['role' => 'guru']);

    $this->actingAs($admin);

    Livewire::test('pages::admin.kelas')
        ->call('bukaTambah')
        ->set('form.class_name', 'XII RPL 1')
        ->set('form.school_year', '2026/2027')
        ->set('form.user_id', $guru->id)
        ->call('simpanKelas')
        ->assertHasNoErrors();

    $kelas = SchoolClass::query()->where('class_name', 'XII RPL 1')->first();

    expect($kelas?->user_id)->toBe($guru->id);
});

test('guru hanya melihat kelas miliknya pada tabel', function () {
    $guruA = User::factory()->create(['role' => 'guru']);
    $guruB = User::factory()->create(['role' => 'guru']);

    SchoolClass::create(['class_name' => 'Kelas A', 'school_year' => '2026/2027', 'user_id' => $guruA->id]);
    SchoolClass::create(['class_name' => 'Kelas B', 'school_year' => '2026/2027', 'user_id' => $guruB->id]);

    $this->actingAs($guruA);

    Livewire::test('pages::admin.kelas')
        ->assertSee('Kelas A')
        ->assertDontSee('Kelas B');
});

test('hapus kelas ditolak saat masih ada siswa', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'Kelas A', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => null]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelas')
        ->call('hapusKelas')
        ->assertHasNoErrors();

    expect($kelas->fresh())->not->toBeNull();
});

test('hapus kelas berhasil saat kosong', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'Kosong', 'school_year' => '2026/2027', 'user_id' => $guru->id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelas')
        ->call('mintaHapus', $kelas->class_id)
        ->call('hapusKelas');

    expect(SchoolClass::query()->where('class_name', 'Kosong')->exists())->toBeFalse();
});

test('hapus kelas ditolak saat masih ada kelompok', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'Ada Kelompok', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelas')
        ->call('mintaHapus', $kelas->class_id)
        ->call('hapusKelas');

    expect($kelas->fresh())->not->toBeNull();
});

test('relasi kelas-kelompok-siswa tetap konsisten untuk visibilitas', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'Kelas A', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => '1']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->set('kelasId', $kelas->class_id)
        ->assertSee('Budi')
        ->assertSee('Kelompok 1');
});
