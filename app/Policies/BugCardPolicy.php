<?php

namespace App\Policies;

use App\Models\BugCard;
use App\Models\User;

/**
 * Materi game bersifat GLOBAL — admin dan guru sama-sama boleh mengelola
 * kartu bug. `buggy_code` selalu ditampilkan ter-escape, tidak pernah
 * dirender sebagai HTML.
 */
class BugCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BugCard $bugCard): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BugCard $bugCard): bool
    {
        return true;
    }

    public function delete(User $user, BugCard $bugCard): bool
    {
        return true;
    }
}
