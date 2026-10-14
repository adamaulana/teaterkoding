<?php

use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LevelsSeeder::class);
});

// ---------- Akses materi (global: admin DAN guru) ----------

test('guru dan admin sama-sama boleh membuka keempat halaman materi', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['/admin/level', '/admin/misi', '/admin/kartu-konsep', '/admin/kartu-bug'] as $path) {
        $this->actingAs($guru)->get($path)->assertOk();
        $this->actingAs($admin)->get($path)->assertOk();
    }
});

test('guru boleh membuat dan mengubah materi', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    $this->actingAs($guru);

    Livewire::test('pages::admin.level')
        ->call('bukaUbah', $level->level_id)
        ->set('form.level_name', 'Level Diubah Guru')
        ->call('simpanLevel')
        ->assertHasNoErrors();

    expect($level->fresh()->level_name)->toBe('Level Diubah Guru');

    Livewire::test('pages::admin.misi')
        ->call('bukaTambah')
        ->set('form.level_id', $level->level_id)
        ->set('form.unlock_code', 'GURU-BUAT-1')
        ->set('form.client_brief', 'Brief dari guru.')
        ->call('simpanMisi')
        ->assertHasNoErrors();

    expect(Mission::query()->where('unlock_code', 'GURU-BUAT-1')->exists())->toBeTrue();
});

// ---------- Kepemilikan fungsi dibatasi kelas guru ----------

test('kepemilikan fungsi hanya untuk kelas milik guru', function () {
    $guruA = User::factory()->create(['role' => 'guru']);
    $guruB = User::factory()->create(['role' => 'guru']);

    $kelasB = SchoolClass::create(['class_name' => 'Kelas B', 'school_year' => '2026/2027', 'user_id' => $guruB->id]);
    $teamB = Team::create(['class_id' => $kelasB->class_id, 'team_name' => 'Kelompok B']);

    $this->actingAs($guruA);

    // Guru A tidak boleh memuat kelas guru B pada tab kepemilikan fungsi.
    Livewire::test('pages::admin.misi')
        ->set('fungsiKelasId', $kelasB->class_id)
        ->assertForbidden();

    expect($teamB->teamMembers()->count())->toBe(0);
});

test('opsi kelas pada kepemilikan fungsi hanya menampilkan kelas guru', function () {
    $guruA = User::factory()->create(['role' => 'guru']);
    $guruB = User::factory()->create(['role' => 'guru']);

    SchoolClass::create(['class_name' => 'Kelas A', 'school_year' => '2026/2027', 'user_id' => $guruA->id]);
    SchoolClass::create(['class_name' => 'Kelas B', 'school_year' => '2026/2027', 'user_id' => $guruB->id]);

    // Misi pada level sumatif → tab Kepemilikan Fungsi terbuka.
    $levelSumatif = Level::query()->where('is_summative', true)->firstOrFail();
    $misi = Mission::create(['level_id' => $levelSumatif->level_id, 'unlock_code' => 'SUM-1', 'client_brief' => 'Brief.']);
    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Total',
        'check_target' => 'hitung_total(100)',
        'expected_value' => '1000',
        'tolerance' => 0,
    ]);

    $this->actingAs($guruA);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->assertSee('Kelas A')
        ->assertDontSee('Kelas B');
});

test('admin melihat semua kelas pada kepemilikan fungsi', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $guru = User::factory()->create(['role' => 'guru']);

    SchoolClass::create(['class_name' => 'Kelas Guru', 'school_year' => '2026/2027', 'user_id' => $guru->id]);

    $levelSumatif = Level::query()->where('is_summative', true)->firstOrFail();
    $misi = Mission::create(['level_id' => $levelSumatif->level_id, 'unlock_code' => 'SUM-2', 'client_brief' => 'Brief.']);
    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Total',
        'check_target' => 'hitung_total(100)',
        'expected_value' => '1000',
        'tolerance' => 0,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->assertSee('Kelas Guru');
});
