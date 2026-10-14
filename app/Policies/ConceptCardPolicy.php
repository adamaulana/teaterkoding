<?php

namespace App\Policies;

use App\Models\ConceptCard;
use App\Models\User;

/**
 * Materi game bersifat GLOBAL — admin dan guru sama-sama boleh mengubah isi
 * kartu konsep. Penghapusan sengaja tidak disediakan (jumlah kartu tetap 5
 * karena dipakai mission_plans); penambahan kartu hanya untuk admin, dan
 * pembatasannya dilakukan di halaman (gate 'admin_only'), bukan di sini.
 */
class ConceptCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ConceptCard $conceptCard): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ConceptCard $conceptCard): bool
    {
        return true;
    }

    /**
     * Kartu konsep tidak boleh dihapus karena direferensikan mission_plans.
     */
    public function delete(User $user, ConceptCard $conceptCard): bool
    {
        return false;
    }
}
