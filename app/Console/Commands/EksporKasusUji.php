<?php

namespace App\Console\Commands;

use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\Mission;
use App\Models\TestCase;
use Illuminate\Console\Command;

/**
 * Ekspor kasus uji & kartu bug dari database ke berkas JSON untuk uji Node.
 *
 * Dipakai oleh `npm run test:pyodide` (Bagian E): uji runtime Pyodide harus
 * memakai data kasus uji NYATA dari seeder, bukan data buatan. Berkas hasil
 * ekspor ditulis ke tests/Pyodide/data/kasus.json.
 *
 * SENGAJA menyertakan expected_value: berkas ini hanya dibaca oleh uji Node
 * (pembanding yang sama dengan server), tidak pernah disajikan ke browser.
 * Jangan pernah menyajikan berkas ini lewat HTTP.
 */
class EksporKasusUji extends Command
{
    protected $signature = 'teater:ekspor-kasus-uji {--path= : Berkas tujuan (default tests/Pyodide/data/kasus.json)}';

    protected $description = 'Ekspor kasus uji misi & kartu bug ke JSON untuk uji runtime Pyodide';

    public function handle(): int
    {
        $tujuan = (string) ($this->option('path') ?: base_path('tests/Pyodide/data/kasus.json'));

        $misi = Mission::query()
            ->with(['testCases' => fn ($q) => $q->orderBy('test_case_id')])
            ->orderBy('mission_id')
            ->get()
            ->map(fn (Mission $m) => [
                'kode' => (string) $m->unlock_code,
                'mission_id' => (int) $m->mission_id,
                'starter_code' => (string) ($m->starter_code ?? ''),
                'kasus' => $m->testCases->map(fn (TestCase $k) => [
                    'id' => (int) $k->test_case_id,
                    'label' => (string) $k->label,
                    'setup_code' => (string) ($k->setup_code ?? ''),
                    'check_target' => (string) $k->check_target,
                    'expected_value' => (string) $k->expected_value,
                    'tolerance' => (float) ($k->tolerance ?? 0),
                ])->all(),
            ])
            ->all();

        $bug = BugCard::query()
            ->with(['testCases' => fn ($q) => $q->orderBy('id')])
            ->orderBy('bug_id')
            ->get()
            ->map(fn (BugCard $b) => [
                'id' => (int) $b->bug_id,
                'level_number' => (int) ($b->level?->level_number ?? 0),
                'judul' => (string) $b->bug_title,
                'buggy_code' => (string) $b->buggy_code,
                'fix_explanation' => (string) $b->fix_explanation,
                'kasus' => $b->testCases->map(fn (BugTestCase $k) => [
                    'id' => (int) $k->id,
                    'label' => (string) $k->label,
                    'setup_code' => (string) ($k->setup_code ?? ''),
                    'check_target' => (string) $k->check_target,
                    'expected_value' => (string) $k->expected_value,
                    'tolerance' => (float) ($k->tolerance ?? 0),
                ])->all(),
            ])
            ->all();

        $folder = dirname($tujuan);

        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($tujuan, json_encode([
            'dibuat_pada' => now()->toIso8601String(),
            'misi' => $misi,
            'bug' => $bug,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $jumlahKasus = array_sum(array_map(fn (array $m) => count($m['kasus']), $misi));
        $jumlahKasusBug = array_sum(array_map(fn (array $b) => count($b['kasus']), $bug));

        $this->info("Ekspor selesai: {$tujuan}");
        $this->line('  - '.count($misi)." misi, {$jumlahKasus} kasus uji");
        $this->line('  - '.count($bug)." kartu bug, {$jumlahKasusBug} kasus uji");

        return self::SUCCESS;
    }
}
