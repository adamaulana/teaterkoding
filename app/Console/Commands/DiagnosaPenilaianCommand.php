<?php

namespace App\Console\Commands;

use App\Services\DiagnosaPenilaian;
use Illuminate\Console\Command;

/**
 * Periksa kesehatan alur penilaian sebelum kelas dimulai.
 *
 * Exit code 1 bila ada masalah berTingkat 'bahaya' (mis. runner null, aset
 * Pyodide hilang, atau queue worker mati) sehingga bisa dipakai di skrip/CI.
 *
 *   php artisan teater:diagnosa-penilaian
 */
class DiagnosaPenilaianCommand extends Command
{
    protected $signature = 'teater:diagnosa-penilaian';

    protected $description = 'Periksa apakah penilaian (Pyodide/queue) siap dipakai';

    public function handle(DiagnosaPenilaian $diagnosa): int
    {
        $r = $diagnosa->ringkasan();

        $this->newLine();
        $this->line('  <options=bold>Diagnosa penilaian</>');
        $this->newLine();

        $this->components->twoColumnDetail('Runner', $r['runner'].($r['memakai_pyodide'] ? ' (penilaian di browser)' : ' (lewat queue)'));
        $this->components->twoColumnDetail('Queue driver', $r['queue']);
        $this->components->twoColumnDetail('Aset Pyodide', $r['aset_lengkap'] ? '<fg=green>lengkap</>' : '<fg=red>belum lengkap</>');

        if (! $r['aset_lengkap']) {
            foreach ($r['aset_hilang'] as $hilang) {
                $this->components->twoColumnDetail('  hilang', '<fg=red>'.$hilang.'</>');
            }
        }

        $this->components->twoColumnDetail('Job menunggu', (string) $r['job_menunggu']);
        $this->components->twoColumnDetail('Submission menunggu', (string) $r['submission_menunggu']);
        $this->components->twoColumnDetail('  menggantung > 5 menit', (string) $r['submission_menggantung']);
        $this->components->twoColumnDetail('  tanpa nonce', (string) $r['submission_tanpa_nonce']);
        $this->components->twoColumnDetail('Bug menunggu', (string) $r['bug_menunggu']);

        // Mode dev: penyebab paling sering Web Worker ditolak browser.
        $dev = $diagnosa->devServer();

        $this->components->twoColumnDetail(
            'Dev server Vite (public/hot)',
            ! $dev['aktif']
                ? '<fg=green>tidak aktif</>'
                : ($dev['origin_beda']
                    ? '<fg=red>'.$dev['url'].' (origin BERBEDA)</>'
                    : '<fg=yellow>'.$dev['url'].'</>'),
        );

        if ($dev['aktif'] && $dev['origin_beda']) {
            $this->line('    <fg=gray>→ Hapus `public/hot`, lalu `npm run build` agar Web Worker same-origin.</>');
        }

        $masalah = $diagnosa->masalah();

        if ($masalah === []) {
            $this->newLine();
            $this->components->info('Tidak ada masalah. Penilaian siap dipakai.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <options=bold>Masalah ditemukan</>');
        $this->newLine();

        $bahaya = 0;

        foreach ($masalah as $satu) {
            $warna = $satu['tingkat'] === DiagnosaPenilaian::TINGKAT_BAHAYA ? 'red' : 'yellow';
            $this->line("  <fg={$warna}>•</> <options=bold>{$satu['pesan']}</>");
            $this->line("    <fg=gray>→ {$satu['saran']}</>");

            if ($satu['tingkat'] === DiagnosaPenilaian::TINGKAT_BAHAYA) {
                $bahaya++;
            }
        }

        $this->newLine();

        return $bahaya > 0 ? self::FAILURE : self::SUCCESS;
    }
}
