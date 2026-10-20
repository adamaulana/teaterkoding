<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Aplikasi ini TIDAK menyediakan pendaftaran publik: akun guru/admin dibuat
| oleh admin (AdminGuruSeeder / halaman Pengguna), sedangkan siswa masuk lewat
| kode akses. Karena itu `config/fortify.php` sengaja tidak mengaktifkan
| Features::registration(). Test di bawah MENGUNCI keputusan itu, sehingga
| fitur registrasi tidak diam-diam menyala kembali tanpa disadari.
*/

test('halaman registrasi tidak tersedia', function () {
    $respons = $this->get('/register');

    // Fortify tidak mendaftarkan rute ini, jadi 404. Bila suatu saat fitur
    // dinyalakan, rute akan ada dan test ini gagal (memaksa peninjauan ulang).
    $respons->assertNotFound();
});

test('permintaan registrasi tidak membuat user baru', function () {
    $sebelum = User::count();

    $respons = $this->post('/register', [
        'name' => 'Penyusup',
        'email' => 'penyusup@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $respons->assertNotFound();

    expect(User::count())->toBe($sebelum)
        ->and(User::query()->where('email', 'penyusup@example.com')->exists())->toBeFalse();
});
