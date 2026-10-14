<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Fase 4: tombol Nonaktifkan/Aktifkan pada halaman Pengguna.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->guru = User::factory()->create(['role' => 'guru']);

    $this->actingAs($this->admin);
});

test('halaman pengguna menampilkan status aktif pengguna', function () {
    Livewire::test('pages::admin.pengguna')
        ->assertSee('Aktif')
        ->assertSee($this->guru->name);
});

test('admin dapat menonaktifkan akun guru', function () {
    Livewire::test('pages::admin.pengguna')
        ->call('toggleAktif', $this->guru->id)
        ->assertHasNoErrors();

    expect((bool) $this->guru->fresh()->is_active)->toBeFalse();
});

test('admin dapat mengaktifkan kembali akun nonaktif', function () {
    $this->guru->forceFill(['is_active' => false])->save();

    Livewire::test('pages::admin.pengguna')
        ->call('toggleAktif', $this->guru->id)
        ->assertHasNoErrors();

    expect((bool) $this->guru->fresh()->is_active)->toBeTrue();
});

test('admin tidak dapat menonaktifkan akun sendiri', function () {
    Livewire::test('pages::admin.pengguna')
        ->call('toggleAktif', $this->admin->id)
        ->assertHasErrors('is_active');

    expect((bool) $this->admin->fresh()->is_active)->toBeTrue();
});

test('guru tidak dapat mengakses halaman pengguna', function () {
    $this->actingAs($this->guru);

    $this->get(route('admin.pengguna'))->assertForbidden();
});

test('akun nonaktif tidak dapat login', function () {
    $this->guru->forceFill(['is_active' => false])->save();

    // Mulai dari sesi tamu (bukan admin yang login di beforeEach).
    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $this->guru->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
