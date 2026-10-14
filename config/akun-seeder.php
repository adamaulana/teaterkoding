<?php

/*
|--------------------------------------------------------------------------
| Kredensial akun seeder (AdminGuruSeeder)
|--------------------------------------------------------------------------
| Diambil dari .env — GANTI SEBELUM PRODUKSI.
*/

return [
    'admin_name' => env('ADMIN_NAME', 'Admin Teater Koding'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@teaterkoding.test'),
    'admin_password' => env('ADMIN_PASSWORD', 'ganti-sebelum-produksi'),

    'guru_name' => env('GURU_NAME', 'Budi Santoso'),
    'guru_email' => env('GURU_EMAIL', 'guru@teaterkoding.test'),
    'guru_password' => env('GURU_PASSWORD', 'ganti-sebelum-produksi'),
];
