<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('halaman admin dapat diakses oleh admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([
        '/admin/dashboard',
        '/admin/profil',
        '/admin/ganti-password',
        '/admin/pengguna',
        // Fase 6 — verifikasi hasil penilaian di perangkat siswa.
        '/admin/verifikasi-penilaian',
    ] as $path) {
        $this->actingAs($admin)->get($path)->assertOk();
    }
});

test('guru dapat mengakses panel kecuali halaman pengguna', function () {
    $guru = User::factory()->create(['role' => 'guru']);

    $this->actingAs($guru)->get('/admin/dashboard')->assertOk();
    $this->actingAs($guru)->get('/admin/profil')->assertOk();
    $this->actingAs($guru)->get('/admin/ganti-password')->assertOk();

    $this->actingAs($guru)->get('/admin/pengguna')->assertForbidden();
});

test('tamu diarahkan ke halaman login', function () {
    $this->get('/admin/dashboard')->assertRedirect(route('login'));
});

test('halaman login bertema tamu dapat dirender', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Selamat datang kembali')
        ->assertSee('Teater Koding');
});
