<?php

namespace App\Policies;

use App\Models\Level;
use App\Models\User;

/**
 * Materi game bersifat GLOBAL (dipakai bersama semua kelas), sehingga admin
 * DAN guru sama-sama boleh mengelola. Gate::before di AppServiceProvider
 * sudah memberi admin akses penuh, jadi policy ini melayani jalur guru.
 */
class LevelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Level $level): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Level $level): bool
    {
        return true;
    }

    public function delete(User $user, Level $level): bool
    {
        return true;
    }
}
