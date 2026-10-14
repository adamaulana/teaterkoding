<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Kelompok mengikuti visibilitas kelas induknya.
     */
    public function view(User $user, Team $team): bool
    {
        $class = $team->schoolClass;

        return $user->isAdmin() || ($class !== null && $class->user_id !== null && $class->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $this->view($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->view($user, $team);
    }
}
