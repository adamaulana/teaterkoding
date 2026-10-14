<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Isi tabel roles dengan 5 peran kartu.
     */
    public function run(): void
    {
        $roles = ['Coder', 'Tester', 'Analyst', 'Reviewer', 'Bug Hunter'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['role_name' => $roleName]);
        }
    }
}
