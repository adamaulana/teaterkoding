<?php

namespace App\Actions;

use App\Models\Mission;
use App\Models\TestCase;

/**
 * Duplikat misi beserta seluruh kasus ujinya (tanpa kepemilikan fungsi).
 *
 * Kepemilikan fungsi sengaja TIDAK disalin: kepemilikan terikat pada kelompok
 * kelas tertentu, sehingga tidak relevan untuk misi/varian baru.
 */
class DuplikatMisi
{
    /**
     * @return array{mission: Mission, jumlah_kasus_uji: int}
     */
    public function __invoke(Mission $sumber, string $kodeBaru): array
    {
        $baru = Mission::create([
            'level_id' => $sumber->level_id,
            'unlock_code' => $kodeBaru,
            'client_brief' => $sumber->client_brief,
        ]);

        $kasusUji = TestCase::query()
            ->where('mission_id', $sumber->mission_id)
            ->orderBy('test_case_id')
            ->get();

        foreach ($kasusUji as $kasus) {
            TestCase::create([
                'mission_id' => $baru->mission_id,
                'label' => $kasus->label,
                'setup_code' => $kasus->setup_code,
                'check_target' => $kasus->check_target,
                'expected_value' => $kasus->expected_value,
                'tolerance' => $kasus->tolerance,
            ]);
        }

        return ['mission' => $baru, 'jumlah_kasus_uji' => $kasusUji->count()];
    }
}
