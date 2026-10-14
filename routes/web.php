<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Nama route 'dashboard' dipertahankan (target Fortify setelah login);
// guest diarahkan ke halaman login oleh middleware 'auth'.
Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/admin/dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('/profil', 'pages::admin.profil')->name('profil');

    Route::livewire('/ganti-password', 'pages::admin.ganti-password')->name('ganti-password');

    Route::livewire('/pengguna', 'pages::admin.pengguna')
        ->middleware('role:admin')
        ->name('pengguna');

    // Akademik — Kelas, Siswa, Kelompok, Penugasan Peran
    Route::livewire('/kelas', 'pages::admin.kelas')->name('kelas');

    Route::livewire('/siswa', 'pages::admin.siswa')->name('siswa');

    Route::livewire('/kelompok', 'pages::admin.kelompok')->name('kelompok');

    Route::livewire('/penugasan-peran', 'pages::admin.penugasan-peran')->name('penugasan-peran');

    // Materi Game — Level, Misi & Kasus Uji, Kartu Konsep, Kartu Bug
    Route::livewire('/level', 'pages::admin.level')->name('level');

    Route::livewire('/misi', 'pages::admin.misi')->name('misi');

    Route::livewire('/kartu-konsep', 'pages::admin.kartu-konsep')->name('kartu-konsep');

    Route::livewire('/kartu-bug', 'pages::admin.kartu-bug')->name('kartu-bug');

    // Pemantauan — Pantau Live, Riwayat Submission
    Route::livewire('/pantau-live', 'pages::admin.pantau-live')->name('pantau-live');

    Route::livewire('/riwayat-submission', 'pages::admin.riwayat-submission')->name('riwayat-submission');

    // Penilaian — Skor & Peringkat, Rekap Nilai Sumatif, Pengaturan Penilaian
    Route::livewire('/skor-peringkat', 'pages::admin.skor-peringkat')->name('skor-peringkat');

    Route::livewire('/rekap-nilai', 'pages::admin.rekap-nilai')->name('rekap-nilai');

    Route::livewire('/pengaturan-penilaian', 'pages::admin.pengaturan-penilaian')->name('pengaturan-penilaian');
});

require __DIR__.'/settings.php';
