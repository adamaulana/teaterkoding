<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Siswa mengikuti visibilitas kelas induknya.
     */
    public function view(User $user, Student $student): bool
    {
        $class = $student->schoolClass;

        return $user->isAdmin() || ($class !== null && $class->user_id !== null && $class->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }
}
