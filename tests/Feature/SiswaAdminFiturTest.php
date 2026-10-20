<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatGuru(): User
{
    return User::factory()->create(['role' => 'guru', 'is_active' => true]);
}

test('halaman kontrol kelas dapat dirender', function () {
    $this->actingAs(buatGuru())
        ->get('/admin/kontrol-kelas')
        ->assertOk()
        ->assertSee('Kontrol Kelas');
});

test('halaman cetak kartu akses dapat dirender', function () {
    $this->actingAs(buatGuru())
        ->get('/admin/kartu-akses')
        ->assertOk()
        ->assertSee('Cetak Kartu Akses');
});

test('halaman siswa menampilkan kolom Kode Akses', function () {
    $guru = buatGuru();
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026', 'user_id' => $guru->id]);
    Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'access_code' => 'ABCD2345']);

    $this->actingAs($guru)
        ->get('/admin/siswa')
        ->assertOk()
        ->assertSee('Kode Akses');
});