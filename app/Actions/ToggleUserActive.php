<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Aktifkan / nonaktifkan akun pengguna.
 *
 * Akun nonaktif tetap ada (tidak dihapus) sehingga riwayat penilaiannya utuh,
 * tetapi tidak bisa login — pengecekannya ada di FortifyServiceProvider.
 */
class ToggleUserActive
{
    /**
     * Balik status aktif akun. Admin tidak boleh menonaktifkan dirinya sendiri.
     */
    public function __invoke(User $user): bool
    {
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages([
                'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ]);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return (bool) $user->is_active;
    }
}
