<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    /**
     * Admin melihat semua kelas; guru hanya kelas miliknya.
     */
    public function view(User $user, SchoolClass $class): bool
    {
        return $user->isAdmin() || ($class->user_id !== null && $class->user_id === $user->id);
    }

    /**
     * Admin dan guru sama-sama boleh membuat kelas baru.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $this->view($user, $class);
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $this->view($user, $class);
    }
}
