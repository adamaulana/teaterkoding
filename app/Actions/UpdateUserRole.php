<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateUserRole
{
    /**
     * Aturan validasi ubah peran pengguna.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['admin', 'guru'])],
        ];
    }

    /**
     * Ubah peran pengguna. Admin tidak boleh menurunkan perannya sendiri.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $user, array $data): void
    {
        $role = (string) $data['role'];

        if ($user->id === Auth::id() && $role !== 'admin') {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ]);
        }

        $user->role = $role;
        $user->save();
    }
}
