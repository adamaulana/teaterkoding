<?php

namespace App\Services;

use App\Models\BugAttempt;
use App\Models\BugTestCase;
use App\Models\Submission;
use App\Models\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun PAKET PENILAIAN yang dikirim ke browser siswa (Fase 6).
 *
 * === APA YANG BOLEH DAN TIDAK BOLEH MASUK PAKET ===
 * BOLEH  : kode siswa sendiri, check_target, setup_code, daftar modul impor
 *          yang diizinkan, batas waktu, dan nonce sekali pakai.
 * DILARANG: expected_value, tolerance, label kasus uji guru, dan apa pun yang
 *          bisa dipakai menebak jawaban. Browser HANYA melaporkan nilai mentah;
 *          server yang memutuskan lulus/gagal lewat App\Actions\BandingkanNilai.
 *
 * === PENGELOMPOKAN setup_code ===
 * Kasus uji dengan setup_code berbeda harus dijalankan di NAMESPACE TERPISAH
 * (mis. Level 2 menyuntik `saldo = 12000000` lalu `saldo = 10000000`). Bila
 * semuanya dijalankan di satu namespace, kode siswa hanya melihat nilai
 * terakhir dan hasilnya salah. Karena itu paket berisi "kelompok": satu
 * kelompok = satu setup_code = satu eksekusi.
 *
 * === NONCE ===
 * Nonce 64 hex sekali pakai, dibuat saat submit (SubmissionService) dan
 * disegarkan di sini bila sudah kedaluwarsa — inilah mekanisme "Nilai Ulang".
 */
final class PaketPenilaian
{
    /**
     * Paket untuk submission misi (null bila submission tidak bisa dinilai).
     *
     * @return array<string, mixed>|null
     */
    public function untukSubmission(Submission $submission): ?array
    {
        if (! $this->bolehDinilai($submission->status)) {
            return null;
        }

        $kasus = TestCase::query()
            ->where('mission_id', $submission->mission_id)
            ->orderBy('test_case_id')
            ->get(['test_case_id', 'setup_code', 'check_target']);

        if ($kasus->isEmpty()) {
            return null;
        }

        $this->segarkanNonce($submission);

        return $this->paket(
            jenis: 'misi',
            id: (int) $submission->submission_id,
            kode: (string) $submission->code_text,
            kelompok: $this->kelompokkan($kasus, 'test_case_id'),
            nonce: (string) $submission->grading_nonce,
            kedaluwarsa: $submission->nonce_expires_at,
        );
    }

    /**
     * Paket untuk percobaan Kartu Bug (null bila tidak bisa dinilai).
     *
     * @return array<string, mixed>|null
     */
    public function untukBugAttempt(BugAttempt $attempt): ?array
    {
        if (! $this->bolehDinilai($attempt->grading_status)) {
            return null;
        }

        $kasus = BugTestCase::query()
            ->where('bug_id', $attempt->bug_id)
            ->orderBy('id')
            ->get(['id', 'setup_code', 'check_target']);

        if ($kasus->isEmpty()) {
            return null;
        }

        $this->segarkanNonce($attempt);

        return $this->paket(
            jenis: 'bug',
            id: (int) $attempt->attempt_id,
            kode: (string) ($attempt->fixed_code ?? ''),
            kelompok: $this->kelompokkan($kasus, 'id'),
            nonce: (string) $attempt->grading_nonce,
            kedaluwarsa: $attempt->nonce_expires_at,
        );
    }

    /**
     * Paket untuk verifikasi GURU (penilaian ulang di perangkat guru).
     *
     * Beda dengan paket siswa:
     *   - batas waktu dikali verify_timeout_multiplier (laptop guru bisa lebih
     *     lambat, dan guru perlu waktu memeriksa),
     *   - nonce dibuat baru sekali pakai (tidak mengubah status apa pun),
     *   - tidak mengubah grading_nonce milik siswa, sehingga siswa tetap bisa
     *     menyelesaikan penilaiannya sendiri.
     *
     * expected_value TETAP tidak dikirim: server yang membandingkan, guru hanya
     * melihat hasil banding lewat endpoint periksa.
     *
     * @return array<string, mixed>|null
     */
    public function untukVerifikasi(Submission|BugAttempt $model): ?array
    {
        $submission = $model instanceof Submission;

        $kasus = $submission
            ? TestCase::query()
                ->where('mission_id', $model->mission_id)
                ->orderBy('test_case_id')
                ->get(['test_case_id', 'setup_code', 'check_target'])
            : BugTestCase::query()
                ->where('bug_id', $model->bug_id)
                ->orderBy('id')
                ->get(['id', 'setup_code', 'check_target']);

        if ($kasus->isEmpty()) {
            return null;
        }

        $paket = $this->paket(
            jenis: $submission ? 'misi' : 'bug',
            id: (int) $model->getKey(),
            kode: $submission ? (string) $model->code_text : (string) ($model->fixed_code ?? ''),
            kelompok: $this->kelompokkan($kasus, $submission ? 'test_case_id' : 'id'),
            nonce: bin2hex(random_bytes(32)),
            kedaluwarsa: now()->addMinutes((int) config('teater.pyodide.nonce_ttl_minutes', 15)),
        );

        // Guru diberi waktu lebih longgar untuk memeriksa hasil.
        $pengali = max(1, (int) config('teater.pyodide.verify_timeout_multiplier', 2));

        $paket['batas_kelompok_ms'] = (int) $paket['batas_kelompok_ms'] * $pengali;
        $paket['batas_total_ms'] = (int) $paket['batas_total_ms'] * $pengali;
        $paket['mode'] = 'verifikasi';

        return $paket;
    }

    /**
     * Status yang masih boleh menerima laporan dari browser.
     *
     * 'belum' (bug belum pernah dikirim) sengaja TIDAK termasuk: tidak ada
     * kode perbaikan untuk dinilai.
     */
    public function bolehDinilai(?string $status): bool
    {
        return $status === Submission::STATUS_MENUNGGU;
    }

    /**
     * Pastikan model punya nonce yang masih berlaku; buat baru bila perlu.
     *
     * Dipanggil setiap kali paket diminta, sehingga tombol "Nilai Ulang" cukup
     * meminta paket lagi setelah nonce kedaluwarsa.
     */
    public function segarkanNonce(Submission|BugAttempt $model): string
    {
        $masihBerlaku = filled($model->grading_nonce)
            && $model->nonce_expires_at !== null
            && $model->nonce_expires_at->isFuture();

        if ($masihBerlaku) {
            return (string) $model->grading_nonce;
        }

        $nonce = bin2hex(random_bytes(32));
        $kedaluwarsa = now()->addMinutes((int) config('teater.pyodide.nonce_ttl_minutes', 15));

        // Query builder dipakai agar kolom nonce tidak tercampur dengan
        // perubahan lain yang mungkin sedang berjalan pada model yang sama.
        DB::table($model->getTable())
            ->where($model->getKeyName(), $model->getKey())
            ->update([
                'grading_nonce' => $nonce,
                'nonce_expires_at' => $kedaluwarsa,
            ]);

        $model->grading_nonce = $nonce;
        $model->nonce_expires_at = $kedaluwarsa;

        return $nonce;
    }

    /**
     * Susun kelompok berdasarkan setup_code (urutan stabil seperti kasus uji).
     *
     * @param  iterable<int, TestCase|BugTestCase>  $kasus
     * @return array<int, array{setup_code: string, target: array<int, array{id: int, check_target: string}>}>
     */
    public function kelompokkan(iterable $kasus, string $kolomId): array
    {
        $kelompok = [];

        foreach ($kasus as $satu) {
            $setup = (string) ($satu->setup_code ?? '');

            if (! isset($kelompok[$setup])) {
                $kelompok[$setup] = ['setup_code' => $setup, 'target' => []];
            }

            $kelompok[$setup]['target'][] = [
                'id' => (int) $satu->{$kolomId},
                'check_target' => (string) $satu->check_target,
            ];
        }

        return array_values($kelompok);
    }

    /**
     * Bentuk akhir paket yang dikirim ke browser.
     *
     * @param  array<int, array{setup_code: string, target: array<int, array{id: int, check_target: string}>}>  $kelompok
     * @return array<string, mixed>
     */
    private function paket(
        string $jenis,
        int $id,
        string $kode,
        array $kelompok,
        string $nonce,
        \DateTimeInterface $kedaluwarsa,
    ): array {
        $versi = (string) config('teater.pyodide.version', '314.0.7');
        $aset = rtrim((string) config('teater.pyodide.asset_path', '/vendor/pyodide'), '/');

        return [
            'jenis' => $jenis,
            'id' => $id,
            'nonce' => $nonce,
            'kode' => $kode,
            'kelompok' => $kelompok,
            'modul_diizinkan' => array_values((array) config('teater.pyodide.allowed_modules', [])),
            'maks_keluaran' => (int) config('teater.pyodide.max_output_chars', 2000),
            'batas_kelompok_ms' => (int) config('teater.pyodide.setup_timeout_ms', 3000),
            'batas_total_ms' => (int) config('teater.pyodide.total_timeout_ms', 15000),
            'versi_pyodide' => $versi,
            // Aset SELF-HOSTED (tanpa CDN); worker memakai ini sebagai indexURL.
            'aset' => $aset.'/'.$versi.'/',
            'kedaluwarsa' => ($kedaluwarsa ?? now())->toIso8601String(),
        ];
    }
}
