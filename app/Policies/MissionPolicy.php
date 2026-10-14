<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;

/**
 * Materi game bersifat GLOBAL — admin dan guru sama-sama boleh mengelola.
 */
class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mission $mission): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Mission $mission): bool
    {
        return true;
    }

    public function delete(User $user, Mission $mission): bool
    {
        return true;
    }

    public function duplicate(User $user, Mission $mission): bool
    {
        return true;
    }
}
