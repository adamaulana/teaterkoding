<?php

namespace Database\Seeders;

use App\Models\ConceptCard;
use Illuminate\Database\Seeder;

class ConceptCardsSeeder extends Seeder
{
    /**
     * Isi tabel concept_cards dengan 5 kartu konsep.
     */
    public function run(): void
    {
        $concepts = ['Variabel', 'Operator', 'If/Else', 'Loop', 'Function'];

        foreach ($concepts as $conceptName) {
            ConceptCard::firstOrCreate(['concept_name' => $conceptName]);
        }
    }
}
