<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

/**
 * Tombol aksi landing page menurut status login.
 *
 * Dipakai navbar, CTA penutup, dan hero agar SELALU konsisten. Sebelumnya
 * logika ini ditulis di partial Blade dan diteruskan lewat @include, tetapi
 * variabel @include tidak mengalir ke pemanggil sehingga memicu
 * "Undefined variable $aksiUtama". Helper ini menghilangkan masalah scope itu.
 *
 * Dua guard dipakai terpisah (lihat config/auth.php): `web` untuk guru/admin,
 * `siswa` untuk siswa. Keduanya sengaja tidak berbagi sesi.
 *
 * Guru yang login TIDAK diberi tautan siswa: middleware siswa.auth menolaknya
 * dengan 403.
 */
final class AksiLanding
{
    /**
     * @return array{
     *     guru_login: bool,
     *     siswa_login: bool,
     *     utama: array{label: string, url: string, ikon: string},
     *     sekunder: array{label: string, url: string}|null
     * }
     */
    public static function tombol(): array
    {
        $guruLogin = Auth::guard('web')->check();
        $siswaLogin = Auth::guard('siswa')->check();

        $utama = match (true) {
            $siswaLogin => [
                'label' => 'Lanjut Bermain',
                'url' => route('siswa.beranda'),
                'ikon' => 'chevron-right',
            ],
            $guruLogin => [
                'label' => 'Buka Dashboard',
                'url' => route('admin.dashboard'),
                'ikon' => 'chevron-right',
            ],
            default => [
                'label' => 'Masuk dengan Kode Akses',
                'url' => route('siswa.masuk'),
                'ikon' => 'key',
            ],
        };

        return [
            'guru_login' => $guruLogin,
            'siswa_login' => $siswaLogin,
            'utama' => $utama,
            'sekunder' => $guruLogin
                ? null
                : ['label' => 'Masuk sebagai Guru', 'url' => route('login')],
        ];
    }
}
