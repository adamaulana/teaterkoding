<?php

namespace App\Services;

use App\Models\BugAttempt;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * Diagnosa kesehatan alur penilaian.
 *
 * Latar belakang: submission bisa berhenti di status 'menunggu' TANPA galat
 * apa pun bila konfigurasi tidak cocok — mis. runner 'fake' tetapi queue
 * worker tidak berjalan, atau runner 'pyodide' tetapi aset belum disalin.
 * Siswa lalu hanya melihat "Sedang dinilai..." selamanya.
 *
 * Service ini membaca kondisi NYATA (config, tabel jobs, aset publik, isi
 * tabel submission) dan menerjemahkannya menjadi pesan yang bisa ditindaklanjuti.
 * Dipakai oleh:
 *   - perintah `php artisan teater:diagnosa-penilaian`
 *   - halaman Hasil / Kartu Bug setelah penantian terlalu lama
 */
final class DiagnosaPenilaian
{
    public const RUNNER_PYODIDE = 'pyodide';

    public const RUNNER_FAKE = 'fake';

    public const RUNNER_NULL = 'null';

    public const TINGKAT_BAHAYA = 'bahaya';

    public const TINGKAT_PERINGATAN = 'peringatan';

    public const TINGKAT_INFO = 'info';

    /** Runner yang aktif menurut config. */
    public function runner(): string
    {
        $runner = config('teater.runner');

        return is_string($runner) && $runner !== '' ? $runner : self::RUNNER_NULL;
    }

    public function memakaiPyodide(): bool
    {
        return $this->runner() === self::RUNNER_PYODIDE;
    }

    /** Driver queue yang dipakai (database, sync, redis, ...). */
    public function queueDriver(): string
    {
        return (string) config('queue.default', 'sync');
    }

    /**
     * Apakah ada job yang tampak tidak pernah diproses worker?
     *
     * Job dengan attempts = 0 yang lebih tua dari retry_after berarti tidak ada
     * worker yang mengambilnya. Ini pembeda penting antara "worker mati" dan
     * "job gagal".
     */
    public function workerDidugaMati(): bool
    {
        if ($this->queueDriver() !== 'database') {
            return false;
        }

        $retryAfter = (int) config('queue.connections.database.retry_after', 90);
        $batas = now()->subSeconds($retryAfter)->getTimestamp();

        return DB::table('jobs')
            ->where('attempts', 0)
            ->where('created_at', '<=', $batas)
            ->exists();
    }

    /** Jumlah job yang masih menunggu di tabel jobs. */
    public function jobMenunggu(): int
    {
        if ($this->queueDriver() !== 'database') {
            return 0;
        }

        return (int) DB::table('jobs')->count();
    }

    /** Submission berstatus 'menunggu' (semua umur). */
    public function submissionMenunggu(): int
    {
        return (int) Submission::query()
            ->where('status', Submission::STATUS_MENUNGGU)
            ->count();
    }

    /** Submission 'menunggu' yang lebih tua dari $menit. */
    public function submissionMenggantung(int $menit = 5): int
    {
        return (int) Submission::query()
            ->where('status', Submission::STATUS_MENUNGGU)
            ->where('submitted_at', '<=', now()->subMinutes($menit))
            ->count();
    }

    /**
     * Submission 'menunggu' TANPA nonce.
     *
     * Hanya relevan untuk runner pyodide: tanpa nonce, laporan dari browser
     * tidak akan pernah diterima. Nonce dibuat ulang otomatis saat paket
     * penilaian diminta (PaketPenilaian::segarkanNonce), jadi cukup dengan
     * membuka halaman Hasil.
     */
    public function submissionTanpaNonce(): int
    {
        return (int) Submission::query()
            ->where('status', Submission::STATUS_MENUNGGU)
            ->whereNull('grading_nonce')
            ->count();
    }

    /** Bug attempt berstatus 'menunggu'. */
    public function bugMenunggu(): int
    {
        return (int) BugAttempt::query()
            ->where('grading_status', BugAttempt::STATUS_MENUNGGU)
            ->count();
    }

    /**
     * Apakah aset Pyodide (wasm + penilai.py) tersedia di public/.
     */
    public function asetLengkap(): bool
    {
        return $this->asetHilang() === [];
    }

    /**
     * Apakah aplikasi sedang memakai dev server Vite (berkas `public/hot`).
     *
     * @return array{aktif: bool, url: string|null, origin_beda: bool}
     */
    public function devServer(): array
    {
        $berkas = public_path('hot');

        if (! is_file($berkas)) {
            return ['aktif' => false, 'url' => null, 'origin_beda' => false];
        }

        $url = trim((string) @file_get_contents($berkas));

        if ($url === '') {
            return ['aktif' => true, 'url' => null, 'origin_beda' => false];
        }

        return [
            'aktif' => true,
            'url' => $url,
            'origin_beda' => $this->originBeda($url),
        ];
    }

    /**
     * Bandingkan origin dev server dengan origin aplikasi (APP_URL).
     *
     * Beda port saja sudah berarti origin berbeda — dan Web Worker menolak
     * skrip dari origin lain ("cannot be accessed from origin").
     */
    private function originBeda(string $urlDev): bool
    {
        $dev = parse_url($urlDev);
        $app = parse_url((string) config('app.url'));

        if (! is_array($dev) || ! is_array($app)) {
            return false;
        }

        $skemaDev = strtolower((string) ($dev['scheme'] ?? ''));
        $skemaApp = strtolower((string) ($app['scheme'] ?? ''));
        $hostDev = strtolower((string) ($dev['host'] ?? ''));
        $hostApp = strtolower((string) ($app['host'] ?? ''));
        $portDev = $dev['port'] ?? ($skemaDev === 'https' ? 443 : 80);
        $portApp = $app['port'] ?? ($skemaApp === 'https' ? 443 : 80);

        return $skemaDev !== $skemaApp || $hostDev !== $hostApp || (int) $portDev !== (int) $portApp;
    }

    /**
     * Daftar berkas aset yang belum ada.
     *
     * @return array<int, string>
     */
    public function asetHilang(): array
    {
        $versi = (string) config('teater.pyodide.version', '314.0.7');
        $dasar = (string) config('teater.pyodide.asset_path', '/vendor/pyodide');

        $akar = public_path(trim($dasar, '/'));
        $hilang = [];

        foreach (['pyodide.mjs', 'pyodide.asm.wasm', 'pyodide-lock.json'] as $berkas) {
            if (! is_file($akar.DIRECTORY_SEPARATOR.$versi.DIRECTORY_SEPARATOR.$berkas)) {
                $hilang[] = "{$dasar}/{$versi}/{$berkas}";
            }
        }

        $skrip = (string) config('teater.pyodide.script_path', '/vendor/teater/penilai.py');

        if (! is_file(public_path(trim($skrip, '/')))) {
            $hilang[] = $skrip;
        }

        return $hilang;
    }

    /**
     * Ringkasan untuk ditampilkan/di-log.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(): array
    {
        return [
            'runner' => $this->runner(),
            'memakai_pyodide' => $this->memakaiPyodide(),
            'queue' => $this->queueDriver(),
            'worker_diduga_mati' => $this->workerDidugaMati(),
            'job_menunggu' => $this->jobMenunggu(),
            'submission_menunggu' => $this->submissionMenunggu(),
            'submission_menggantung' => $this->submissionMenggantung(),
            'submission_tanpa_nonce' => $this->submissionTanpaNonce(),
            'bug_menunggu' => $this->bugMenunggu(),
            'aset_lengkap' => $this->asetLengkap(),
            'aset_hilang' => $this->asetHilang(),
            'dev_server' => $this->devServer(),
        ];
    }

    /**
     * Daftar masalah yang bisa ditindaklanjuti (kosong = sehat).
     *
     * @return array<int, array{kode: string, tingkat: string, pesan: string, saran: string}>
     */
    public function masalah(): array
    {
        $masalah = [];

        if ($this->runner() === self::RUNNER_NULL) {
            $masalah[] = [
                'kode' => 'runner_null',
                'tingkat' => self::TINGKAT_BAHAYA,
                'pesan' => 'Penilaian tidak aktif (TEATER_RUNNER=null); submission akan menunggu selamanya.',
                'saran' => 'Set TEATER_RUNNER=pyodide di .env lalu jalankan `php artisan config:clear`.',
            ];
        }

        if ($this->memakaiPyodide() && ! $this->asetLengkap()) {
            $masalah[] = [
                'kode' => 'aset_pyodide',
                'tingkat' => self::TINGKAT_BAHAYA,
                'pesan' => 'Aset Pyodide belum lengkap: '.implode(', ', $this->asetHilang()),
                'saran' => 'Jalankan `npm run vendor:pyodide` lalu `npm run build`.',
            ];
        }

        // Dev server Vite di origin lain membuat Web Worker ditolak browser:
        // "Failed to construct 'Worker' … cannot be accessed from origin".
        $dev = $this->devServer();

        if ($this->memakaiPyodide() && $dev['aktif'] && $dev['origin_beda']) {
            $masalah[] = [
                'kode' => 'dev_origin',
                'tingkat' => self::TINGKAT_BAHAYA,
                'pesan' => "Dev server Vite aktif di {$dev['url']} — origin berbeda dari aplikasi. Web Worker Pyodide akan ditolak browser.",
                'saran' => 'Hapus `public/hot` (atau hentikan `npm run dev`), lalu jalankan `npm run build`.',
            ];
        } elseif ($this->memakaiPyodide() && $dev['aktif']) {
            $masalah[] = [
                'kode' => 'dev_aktif',
                'tingkat' => self::TINGKAT_PERINGATAN,
                'pesan' => "Dev server Vite aktif ({$dev['url']}).",
                'saran' => 'Penilaian Pyodide sebaiknya diuji pada build produksi (`npm run build`).',
            ];
        }

        if (! $this->memakaiPyodide() && $this->queueDriver() === 'database') {
            if ($this->workerDidugaMati()) {
                $masalah[] = [
                    'kode' => 'worker_mati',
                    'tingkat' => self::TINGKAT_BAHAYA,
                    'pesan' => "Runner '{$this->runner()}' butuh queue worker, tetapi {$this->jobMenunggu()} job tidak pernah diproses.",
                    'saran' => 'Jalankan `php artisan queue:work` di terminal terpisah (atau pakai `composer dev`).',
                ];
            } elseif ($this->jobMenunggu() > 0) {
                $masalah[] = [
                    'kode' => 'job_antre',
                    'tingkat' => self::TINGKAT_PERINGATAN,
                    'pesan' => "Ada {$this->jobMenunggu()} job menunggu diproses.",
                    'saran' => 'Pastikan `php artisan queue:work` tetap berjalan selama kelas.',
                ];
            }
        }

        if ($this->memakaiPyodide() && $this->submissionTanpaNonce() > 0) {
            $masalah[] = [
                'kode' => 'tanpa_nonce',
                'tingkat' => self::TINGKAT_PERINGATAN,
                'pesan' => $this->submissionTanpaNonce().' submission berstatus menunggu belum punya nonce penilaian.',
                'saran' => 'Buka halaman Hasil submission tersebut; nonce dibuat ulang otomatis saat paket diminta.',
            ];
        }

        if ($this->submissionMenggantung() > 0) {
            $masalah[] = [
                'kode' => 'menggantung',
                'tingkat' => self::TINGKAT_PERINGATAN,
                'pesan' => $this->submissionMenggantung().' submission menunggu lebih dari 5 menit.',
                'saran' => $this->memakaiPyodide()
                    ? 'Minta siswa membuka tab Hasil (penilaian berjalan di browser), atau tekan "Nilai Ulang".'
                    : 'Periksa queue worker: `php artisan queue:work`.',
            ];
        }

        return $masalah;
    }

    /**
     * Pesan siap-tampil untuk penantian yang terlalu lama.
     *
     * Null bila tidak ada masalah yang relevan.
     */
    public function pesanUntukPenantian(): ?string
    {
        $masalah = $this->masalah();

        foreach ([self::TINGKAT_BAHAYA, self::TINGKAT_PERINGATAN] as $tingkat) {
            foreach ($masalah as $satu) {
                if ($satu['tingkat'] === $tingkat) {
                    return $satu['pesan'].' '.$satu['saran'];
                }
            }
        }

        return null;
    }
}
