<?php

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('keempat halaman akademik dapat dirender tanpa data (empty state)', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['/admin/kelas', '/admin/siswa', '/admin/kelompok', '/admin/penugasan-peran'] as $path) {
        $this->actingAs($admin)->get($path)->assertOk();
    }
});

test('keempat halaman akademik dapat dirender dengan data kelas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $admin->id]);

    foreach (['/admin/kelas', '/admin/siswa', '/admin/kelompok', '/admin/penugasan-peran'] as $path) {
        $this->actingAs($admin)->get($path.'?kelas='.$kelas->class_id)->assertOk();
    }
});

test('menu akademik tampil sebagai tautan aktif di sidebar', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/kelas')
        ->assertOk()
        ->assertSee(route('admin.kelas'), false)
        ->assertSee(route('admin.siswa'), false)
        ->assertSee(route('admin.kelompok'), false)
        ->assertSee(route('admin.penugasan-peran'), false);
});
