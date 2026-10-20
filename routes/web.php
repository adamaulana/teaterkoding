<?php

use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\SiswaLogoutController;
use App\Http\Controllers\VerifikasiPenilaianController;
use App\Livewire\Siswa\Beranda;
use App\Livewire\Siswa\KartuBug;
use App\Livewire\Siswa\Masuk;
use App\Livewire\Siswa\Misi;
use App\Livewire\Siswa\Peringkat;
use App\Livewire\Siswa\RiwayatMisi;
use Illuminate\Support\Facades\Route;

// Landing page publik (Fase 7). Nama rute 'home' DIPERTAHANKAN karena dipakai
// sebagai target redirect setelah login/verifikasi (lihat Fortify & test).
Route::view('/', 'landing')->name('home');

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

    // Verifikasi hasil penilaian di perangkat siswa (level sumatif, Fase 6).
    Route::livewire('/verifikasi-penilaian', 'pages::admin.verifikasi-penilaian')->name('verifikasi-penilaian');

    // Endpoint penilaian ulang oleh guru (paket + pemeriksaan nilai mentah).
    Route::get('/verifikasi-penilaian/{jenis}/{id}/paket', [VerifikasiPenilaianController::class, 'paket'])
        ->whereIn('jenis', ['misi', 'bug'])
        ->name('verifikasi-penilaian.paket');
    Route::post('/verifikasi-penilaian/{jenis}/{id}/periksa', [VerifikasiPenilaianController::class, 'periksa'])
        ->whereIn('jenis', ['misi', 'bug'])
        ->name('verifikasi-penilaian.periksa');

    Route::livewire('/pengaturan-penilaian', 'pages::admin.pengaturan-penilaian')->name('pengaturan-penilaian');

    Route::livewire('/kontrol-kelas', 'pages::admin.kontrol-kelas')->name('kontrol-kelas');

    Route::livewire('/kartu-akses', 'pages::admin.kartu-akses')->name('kartu-akses');
});

// ============================================================
// SISI SISWA (guard `siswa`, layout terpisah tanpa sidebar)
// ============================================================
Route::get('/siswa/masuk', Masuk::class)->name('siswa.masuk');

Route::middleware('siswa.auth')->prefix('siswa')->name('siswa.')->group(function () {
    Route::get('/beranda', Beranda::class)->name('beranda');
    Route::get('/misi/{mission}', Misi::class)->name('misi');
    Route::get('/misi/{mission}/riwayat', RiwayatMisi::class)->name('misi.riwayat');
    Route::get('/bug/{attempt}', KartuBug::class)->name('bug');
    Route::get('/peringkat', Peringkat::class)->name('peringkat');

    // Penilaian di browser (Pyodide) — paket tanpa expected_value + laporan nilai mentah.
    Route::get('/penilaian/misi/{submission}', [PenilaianController::class, 'paketSubmission'])
        ->name('penilaian.paket-misi');
    Route::get('/penilaian/bug/{attempt}', [PenilaianController::class, 'paketBug'])
        ->name('penilaian.paket-bug');
    Route::post('/penilaian/lapor', [PenilaianController::class, 'lapor'])
        ->name('penilaian.lapor');

    Route::post('/keluar', SiswaLogoutController::class)->name('keluar');
});

require __DIR__.'/settings.php';
