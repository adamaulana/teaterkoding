<?php

use App\Livewire\Siswa\Masuk;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function buatSiswaDgnKode(string $kode = 'ABCD2345'): Student
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);

    return Student::create([
        'class_id' => $kelas->class_id,
        'full_name' => 'Budi Santoso',
        'access_code' => $kode,
    ]);
}

test('siswa bisa masuk dengan kode valid', function () {
    buatSiswaDgnKode('ABCD2345');

    Livewire::test(Masuk::class)
        ->set('kode', 'ABCD2345')
        ->call('masuk')
        ->assertRedirect(route('siswa.beranda'));

    $this->assertAuthenticated('siswa');
});

test('kode dinormalisasi: huruf kecil, spasi, dan tanda hubung dibuang', function () {
    buatSiswaDgnKode('ABCD2345');

    Livewire::test(Masuk::class)
        ->set('kode', ' ab-cd - 23 45 ')
        ->call('masuk')
        ->assertRedirect(route('siswa.beranda'));

    $this->assertAuthenticated('siswa');
});

test('kode tidak valid menampilkan pesan generik', function () {
    buatSiswaDgnKode('ABCD2345');

    Livewire::test(Masuk::class)
        ->set('kode', 'ZZZZ9999')
        ->call('masuk')
        ->assertSet('pesan', 'Kode tidak ditemukan');

    $this->assertGuest('siswa');
});

test('login di-throttle per IP setelah melebihi batas', function () {
    buatSiswaDgnKode('ABCD2345');

    RateLimiter::clear('siswa-login:ip:127.0.0.1');

    foreach (range(1, 10) as $i) {
        Livewire::test(Masuk::class)
            ->set('kode', 'ZZZZ9999')
            ->call('masuk');
    }

    // Setelah 10 percobaan gagal, kode yang BENAR pun ditolak (throttle).
    Livewire::test(Masuk::class)
        ->set('kode', 'ABCD2345')
        ->call('masuk')
        ->assertSet('pesan', 'Kode tidak ditemukan');

    $this->assertGuest('siswa');
});

test('tamu diarahkan ke halaman masuk siswa', function () {
    $this->get('/siswa/beranda')->assertRedirect(route('siswa.masuk'));
});

test('guru yang sedang login tidak bisa memakai sesi web untuk rute siswa', function () {
    $guru = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    $this->actingAs($guru)->get('/siswa/beranda')->assertStatus(403);
});

test('siswa hanya terautentikasi pada guard siswa, bukan web', function () {
    $siswa = buatSiswaDgnKode();

    $this->actingAs($siswa, 'siswa');

    $this->assertAuthenticated('siswa');
    $this->assertGuest('web');
});

test('siswa tidak bisa membuka halaman admin', function () {
    $siswa = buatSiswaDgnKode();

    $this->actingAs($siswa, 'siswa');
    auth()->shouldUse('web');

    $this->get('/admin/dashboard')->assertRedirect(route('login'));
});