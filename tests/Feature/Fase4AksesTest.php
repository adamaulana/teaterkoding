<?php

use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Kontrol akses halaman Fase 4: guru hanya kelas miliknya, admin semua,
 * dan tamu diarahkan ke login.
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->guru = User::factory()->create(['role' => 'guru']);
    $this->guruLain = User::factory()->create(['role' => 'guru']);
    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->kelasGuru = SchoolClass::create([
        'class_name' => 'X RPL 1',
        'school_year' => '2026/2027',
        'user_id' => $this->guru->id,
    ]);

    $this->kelasLain = SchoolClass::create([
        'class_name' => 'X RPL 2',
        'school_year' => '2026/2027',
        'user_id' => $this->guruLain->id,
    ]);
});

test('tamu diarahkan ke halaman login', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with([
    'admin.dashboard',
    'admin.pantau-live',
    'admin.riwayat-submission',
    'admin.skor-peringkat',
    'admin.rekap-nilai',
    'admin.pengaturan-penilaian',
]);

test('guru dapat membuka semua halaman Fase 4 kelasnya', function (string $route) {
    $this->actingAs($this->guru);

    $this->get(route($route, ['kelas' => $this->kelasGuru->class_id]))->assertOk();
})->with([
    'admin.dashboard',
    'admin.pantau-live',
    'admin.riwayat-submission',
    'admin.skor-peringkat',
    'admin.rekap-nilai',
    'admin.pengaturan-penilaian',
]);

test('admin dapat membuka halaman Fase 4 kelas guru lain', function (string $route) {
    $this->actingAs($this->admin);

    $this->get(route($route, ['kelas' => $this->kelasLain->class_id]))->assertOk();
})->with([
    'admin.dashboard',
    'admin.pantau-live',
    'admin.riwayat-submission',
    'admin.skor-peringkat',
    'admin.rekap-nilai',
    'admin.pengaturan-penilaian',
]);

test('guru lain ditolak membuka halaman kelas bukan miliknya', function (string $route) {
    $this->actingAs($this->guru);

    $this->get(route($route, ['kelas' => $this->kelasLain->class_id]))->assertForbidden();
})->with([
    'admin.dashboard',
    'admin.pantau-live',
    'admin.riwayat-submission',
    'admin.skor-peringkat',
    'admin.rekap-nilai',
    'admin.pengaturan-penilaian',
]);

test('guru hanya melihat kelasnya pada daftar pilihan', function () {
    $this->actingAs($this->guru);

    Livewire::test('pages::admin.dashboard')
        ->assertSet('kelasId', $this->kelasGuru->class_id)
        ->assertSee($this->kelasGuru->class_name)
        ->assertDontSee($this->kelasLain->class_name);
});

test('admin melihat semua kelas pada daftar pilihan', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.dashboard')
        ->assertSee($this->kelasGuru->class_name)
        ->assertSee($this->kelasLain->class_name);
});

test('halaman rekap nilai menolak misi yang bukan sumatif', function () {
    $this->actingAs($this->guru);

    $levelBiasa = Level::query()->where('is_summative', false)->firstOrFail();

    $misi = Mission::create([
        'level_id' => $levelBiasa->level_id,
        'unlock_code' => 'BIASA-1',
        'client_brief' => 'Brief.',
    ]);

    $this->get(route('admin.rekap-nilai', [
        'kelas' => $this->kelasGuru->class_id,
        'misi' => $misi->mission_id,
    ]))->assertNotFound();
});

test('halaman rekap nilai menolak misi yang tidak ada', function () {
    $this->actingAs($this->guru);

    $this->get(route('admin.rekap-nilai', [
        'kelas' => $this->kelasGuru->class_id,
        'misi' => 99999,
    ]))->assertNotFound();
});
