<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserByAdmin
{
    /**
     * Aturan validasi form tambah pengguna (khusus admin).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'guru'])],
        ];
    }

    /**
     * Buat akun guru/admin baru (langsung terverifikasi).
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        // email_verified_at bukan mass-assignable.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
