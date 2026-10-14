<?php

namespace App\Policies;

use App\Models\FunctionOwnership;
use App\Models\Team;
use App\Models\User;

/**
 * Kepemilikan fungsi adalah SATU-SATUNYA data materi yang terikat kelas
 * (lewat team_id), sehingga mengikuti pembatasan kelas milik guru seperti
 * pada Fase 2: guru hanya boleh mengelola kepemilikan untuk kelompok di
 * kelas miliknya; admin melihat semua (lewat Gate::before).
 */
class FunctionOwnershipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FunctionOwnership $ownership): bool
    {
        return $this->bolehKelolaTeam($user, $ownership->team);
    }

    public function create(User $user, ?Team $team = null): bool
    {
        return $team === null || $this->bolehKelolaTeam($user, $team);
    }

    public function update(User $user, FunctionOwnership $ownership): bool
    {
        return $this->bolehKelolaTeam($user, $ownership->team);
    }

    public function delete(User $user, FunctionOwnership $ownership): bool
    {
        return $this->bolehKelolaTeam($user, $ownership->team);
    }

    /**
     * Kelompok harus berada di kelas milik guru (atau pengguna admin).
     */
    public function bolehKelolaTeam(User $user, ?Team $team): bool
    {
        if ($team === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $kelas = $team->schoolClass;

        return $kelas !== null
            && $kelas->user_id !== null
            && $kelas->user_id === $user->id;
    }
}
