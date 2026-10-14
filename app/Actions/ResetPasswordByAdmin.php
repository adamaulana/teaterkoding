<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

class ResetPasswordByAdmin
{
    /**
     * Aturan validasi reset password oleh admin.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Tetapkan password baru untuk pengguna (otomatis di-hash oleh cast model).
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): void
    {
        $user->password = (string) $data['password'];
        $user->save();
    }
}
