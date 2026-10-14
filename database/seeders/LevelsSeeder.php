<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelsSeeder extends Seeder
{
    /**
     * Isi tabel levels dengan level 1-4; hanya level 4 bersifat sumatif.
     */
    public function run(): void
    {
        foreach ([1, 2, 3, 4] as $levelNumber) {
            Level::firstOrCreate(
                ['level_number' => $levelNumber],
                [
                    'level_name' => 'Level '.$levelNumber,
                    'is_summative' => $levelNumber === 4,
                ],
            );
        }
    }
}
