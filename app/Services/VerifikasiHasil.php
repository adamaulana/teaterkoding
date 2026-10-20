<?php

namespace App\Services;

use App\Actions\BandingkanNilai;
use App\Models\BugAttempt;
use App\Models\BugTestCase;
use App\Models\Submission;
use App\Models\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi guru atas hasil penilaian di perangkat siswa (Fase 6 — Bagian C).
 *
 * === MENGAPA VERIFIKASI ADA ===
 * Kode siswa dijalankan di BROWSER siswa. Server sudah membandingkan sendiri
 * dengan expected_value (jadi nilai tidak bisa "dikarang"), tetapi perangkat
 * siswa tetap bisa memodifikasi penilai Python sehingga melaporkan nilai yang
 * bukan hasil kode sebenarnya. Untuk level SUMATIF, poin baru diberikan setelah
 * guru memverifikasi. Guru bisa:
 *   1. melihat nilai mentah yang dilaporkan + hasil banding server, dan
 *   2. menjalankan ulang kode yang sama di perangkat GURU (bukti independen).
 *
 * === DUA AKSI ===
 *   verifikasi()   menunggu_verifikasi → dinilai  (poin diberikan di sini)
 *   mintaUlang()   menunggu_verifikasi → menunggu (nonce baru, siswa menilai lagi)
 *
 * Keduanya idempoten: memanggil dua kali tidak menggandakan poin, karena
 * ScoringService memakai award_key unik.
 */
final class VerifikasiHasil
{
    public const ERR_TIDAK_DITEMUKAN = 'tidak_ditemukan';

    public const ERR_STATUS = 'status';

    public function __construct(
        private readonly BandingkanNilai $banding = new BandingkanNilai,
    ) {}

    /**
     * Verifikasi hasil submission misi dan beri poin.
     *
     * @param  array<string, mixed>|null  $pemeriksaan  hasil penilaian ulang guru (opsional)
     * @return array{status: string, submission?: Submission, selisih?: array<string, mixed>|null}
     */
    public function submission(Submission $submission, User $guru, ?array $pemeriksaan = null): array
    {
        if ($submission->status !== Submission::STATUS_MENUNGGU_VERIFIKASI) {
            return ['status' => self::ERR_STATUS];
        }

        $selisih = $pemeriksaan === null
            ? null
            : $this->selisihPemeriksaan($submission, $pemeriksaan, 'test_case_id', 'submission_id');

        $submission = DB::transaction(function () use ($submission, $guru, $selisih): Submission {
            $lulus = $this->semuaLulusDariHasil($submission);

            $submission->update([
                'status' => Submission::STATUS_DINILAI,
                'all_passed' => $lulus,
                'grading_source' => Submission::SUMBER_TERVERIFIKASI,
                'verified_at' => now(),
                'verified_by' => $guru->id,
                // Selisih hasil guru vs perangkat disimpan agar bisa ditelusuri.
                'has_mismatch' => $selisih !== null,
                'mismatch_detail' => $selisih ?? $submission->mismatch_detail,
            ]);

            return $submission->fresh();
        });

        // Poin diberikan SETELAH status menjadi 'dinilai' (syarat ScoringService).
        $scoring = app(ScoringService::class);

        $scoring->missionResult($submission);
        $scoring->missionResultIndividu($submission);
        $scoring->firstTryBonus($submission);
        $scoring->planBonus($submission);

        return ['status' => 'ok', 'submission' => $submission, 'selisih' => $selisih];
    }

    /**
     * Verifikasi hasil percobaan Kartu Bug dan beri bonus bila lulus.
     *
     * @param  array<string, mixed>|null  $pemeriksaan
     * @return array{status: string, attempt?: BugAttempt, selisih?: array<string, mixed>|null}
     */
    public function bugAttempt(BugAttempt $attempt, User $guru, ?array $pemeriksaan = null): array
    {
        if ($attempt->grading_status !== BugAttempt::STATUS_MENUNGGU_VERIFIKASI) {
            return ['status' => self::ERR_STATUS];
        }

        $selisih = $pemeriksaan === null
            ? null
            : $this->selisihPemeriksaan($attempt, $pemeriksaan, 'bug_test_case_id', 'attempt_id');

        $attempt = DB::transaction(function () use ($attempt, $guru, $selisih): BugAttempt {
            $lulus = $this->semuaLulusDariHasil($attempt);

            $attempt->update([
                'grading_status' => BugAttempt::STATUS_DINILAI,
                'is_solved' => $lulus,
                'solved_at' => $lulus ? now() : null,
                'grading_source' => BugAttempt::SUMBER_TERVERIFIKASI,
                'verified_at' => now(),
                'verified_by' => $guru->id,
                'has_mismatch' => $selisih !== null,
                'mismatch_detail' => $selisih ?? $attempt->mismatch_detail,
            ]);

            return $attempt->fresh();
        });

        if ($attempt->is_solved) {
            app(ScoringService::class)->bugBonus($attempt);
        }

        return ['status' => 'ok', 'attempt' => $attempt, 'selisih' => $selisih];
    }

    /**
     * Minta siswa menilai ulang: status kembali 'menunggu' + nonce baru.
     *
     * Nonce lama otomatis diganti oleh PaketPenilaian saat paket diminta, tetapi
     * di sini nonce dibuat langsung supaya status dan nonce konsisten.
     *
     * @return array{status: string}
     */
    public function mintaUlang(Submission|BugAttempt $model): array
    {
        $status = $model instanceof Submission ? $model->status : $model->grading_status;

        if ($status !== Submission::STATUS_MENUNGGU_VERIFIKASI) {
            return ['status' => self::ERR_STATUS];
        }

        $kolom = $model instanceof Submission ? 'status' : 'grading_status';

        $model->update([
            $kolom => Submission::STATUS_MENUNGGU,
            'grading_source' => null,
            'verified_at' => null,
            'verified_by' => null,
            'grading_nonce' => bin2hex(random_bytes(32)),
            'nonce_expires_at' => now()->addMinutes(
                (int) config('teater.pyodide.nonce_ttl_minutes', 15),
            ),
        ]);

        return ['status' => 'ok'];
    }

    /**
     * Nilai ulang SATU submission/bug di server memakai nilai mentah yang
     * dikirim guru dari perangkat guru (bukan dari perangkat siswa).
     *
     * Server tetap yang memutuskan lulus/gagal: expected_value hanya dibaca di
     * sini, tidak pernah dikirim balik ke browser.
     *
     * @param  array<string, mixed>  $laporan  {hasil: [{id, tipe, nilai}], galat?}
     * @return array{hasil: array<int, array{id: int, lulus: bool, aktual: string, harapan: string}>, lulus: int, total: int, galat: mixed}
     */
    public function periksaSubmission(Submission $submission, array $laporan): array
    {
        $kasus = TestCase::query()
            ->where('mission_id', $submission->mission_id)
            ->orderBy('test_case_id')
            ->get();

        return $this->periksa($kasus, 'test_case_id', $laporan);
    }

    /**
     * @param  array<string, mixed>  $laporan
     * @return array{hasil: array<int, array{id: int, lulus: bool, aktual: string, harapan: string}>, lulus: int, total: int, galat: mixed}
     */
    public function periksaBugAttempt(BugAttempt $attempt, array $laporan): array
    {
        $kasus = BugTestCase::query()
            ->where('bug_id', $attempt->bug_id)
            ->orderBy('id')
            ->get();

        return $this->periksa($kasus, 'id', $laporan);
    }

    /**
     * Inti pemeriksaan: bandingkan nilai mentah guru dengan expected_value.
     *
     * expected_value HANYA dikembalikan di sini karena endpoint-nya khusus guru
     * (dipakai sebagai bukti saat memverifikasi), tidak pernah ke siswa.
     *
     * @param  iterable<int, TestCase|BugTestCase>  $kasus
     * @param  array<string, mixed>  $laporan
     * @return array{hasil: array<int, array{id: int, lulus: bool, aktual: string, harapan: string}>, lulus: int, total: int, galat: mixed}
     */
    private function periksa(iterable $kasus, string $kolomId, array $laporan): array
    {
        $dilaporkan = [];

        foreach ((array) ($laporan['hasil'] ?? []) as $satu) {
            if (is_array($satu) && isset($satu['id'])) {
                $dilaporkan[(int) $satu['id']] = $satu;
            }
        }

        $hasil = [];
        $lulus = 0;

        foreach ($kasus as $k) {
            $id = (int) $k->{$kolomId};
            $lap = $dilaporkan[$id] ?? null;

            $cocok = $lap !== null && $this->banding->cocok(
                $lap,
                (string) $k->expected_value,
                $k->tolerance,
            );

            if ($cocok) {
                $lulus++;
            }

            $hasil[] = [
                'id' => $id,
                'lulus' => $cocok,
                'aktual' => $lap === null
                    ? '(tidak dilaporkan)'
                    : $this->banding->teksAktual($lap),
                'harapan' => mb_substr((string) $k->expected_value, 0, 100),
            ];
        }

        return [
            'hasil' => $hasil,
            'lulus' => $lulus,
            'total' => count($hasil),
            'galat' => $laporan['galat'] ?? null,
        ];
    }

    /**
     * Selisih antara hasil perangkat dan hasil penilaian ulang guru.
     *
     * Bentuknya sama dengan mismatch_detail pada model, sehingga UI bisa
     * memakai satu komponen untuk keduanya.
     *
     * @param  array<string, mixed>  $pemeriksaan
     * @return array<string, mixed>|null
     */
    private function selisihPemeriksaan(
        Submission|BugAttempt $model,
        array $pemeriksaan,
        string $kolomKasus,
        string $kolomInduk,
    ): ?array {
        $perangkat = $model->results()
            ->get()
            ->keyBy(fn ($r) => (int) $r->{$kolomKasus});

        $selisih = [];

        foreach ((array) ($pemeriksaan['hasil'] ?? []) as $satu) {
            if (! is_array($satu) || ! isset($satu['id'])) {
                continue;
            }

            $id = (int) $satu['id'];
            $sebelum = $perangkat->get($id);

            if ($sebelum === null) {
                continue;
            }

            $bedaStatus = (bool) $sebelum->is_passed !== (bool) ($satu['lulus'] ?? false);

            if (! $bedaStatus) {
                continue;
            }

            $selisih[] = [
                'test_case_id' => $id,
                'sebelum' => [
                    'is_passed' => (bool) $sebelum->is_passed,
                    'actual_value' => mb_substr((string) ($sebelum->actual_value ?? ''), 0, 100),
                ],
                'sesudah' => [
                    'is_passed' => (bool) ($satu['lulus'] ?? false),
                    'actual_value' => mb_substr((string) ($satu['aktual'] ?? ''), 0, 100),
                ],
            ];
        }

        return $selisih === [] ? null : [
            'sumber' => 'perangkat-guru',
            'jumlah' => count($selisih),
            'kasus' => $selisih,
            'dibandingkan_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * Apakah SEMUA kasus uji pada hasil tersimpan lulus?
     *
     * Dipakai saat verifikasi: nilai sudah dihitung server sebelumnya, jadi
     * guru cukup menyetujui, tidak menghitung ulang.
     */
    private function semuaLulusDariHasil(Submission|BugAttempt $model): bool
    {
        $hasil = $model->results()->get();

        if ($hasil->isEmpty()) {
            return false;
        }

        foreach ($hasil as $satu) {
            if (! $satu->is_passed) {
                return false;
            }
        }

        return true;
    }
}
