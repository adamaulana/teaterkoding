<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminGuruSeeder extends Seeder
{
    /**
     * Buat satu akun admin dan satu akun guru contoh dari variabel .env.
     *
     * Email & password dibaca dari .env (lihat blok "AKUN SEEDER" —
     * GANTI SEBELUM PRODUKSI).
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => (string) config('akun-seeder.admin_name'),
                'email' => (string) config('akun-seeder.admin_email'),
                'password' => (string) config('akun-seeder.admin_password'),
                'role' => 'admin',
            ],
            [
                'name' => (string) config('akun-seeder.guru_name'),
                'email' => (string) config('akun-seeder.guru_email'),
                'password' => (string) config('akun-seeder.guru_password'),
                'role' => 'guru',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                ],
            );

            // email_verified_at bukan mass-assignable; set langsung agar akun
            // segera bisa login (route admin memakai middleware 'verified').
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }
    }
}
