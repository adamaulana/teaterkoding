<?php

namespace App\Services;

use App\Actions\BandingkanNilai;
use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Menerima LAPORAN NILAI MENTAH dari browser siswa dan memutuskan hasilnya.
 *
 * === PRINSIP KESELAMATAN ===
 * Browser tidak pernah dipercaya soal lulus/gagal. Laporan hanya berisi
 * pasangan (id kasus uji, tipe nilai, nilai mentah). Server:
 *   1. memvalidasi nonce sekali pakai + masa berlaku,
 *   2. mengambil expected_value & tolerance dari DB (tidak pernah dikirim ke
 *      browser),
 *   3. menghitung lulus/gagal sendiri lewat App\Actions\BandingkanNilai,
 *   4. menyimpan hasil per kasus, lalu memberi poin HANYA bila berstatus
 *      'dinilai' (sumatif: 'menunggu_verifikasi' → poin menunggu guru).
 *
 * === NONCE SEKALI PAKAI ===
 * Setelah laporan diterima, nonce dihapus. Laporan kedua dengan nonce yang
 * sama ditolak, sehingga siswa tidak bisa "menembak" nilai berkali-kali.
 */
final class PenilaianKlien
{
    public const ERR_TIDAK_DITEMUKAN = 'tidak_ditemukan';

    public const ERR_NONCE = 'nonce';

    public const ERR_KEDALUWARSA = 'kedaluwarsa';

    public const ERR_STATUS = 'status';

    public const ERR_LAPORAN = 'laporan';

    public function __construct(
        private readonly BandingkanNilai $banding = new BandingkanNilai,
    ) {}

    /**
     * Proses laporan untuk submission misi.
     *
     * @param  array<string, mixed>  $laporan
     * @return array{status: string, submission?: Submission, lulus?: int, total?: int}
     */
    public function submission(Submission $submission, array $laporan): array
    {
        $periksa = $this->periksaLaporan($submission, $laporan);

        if ($periksa !== null) {
            return ['status' => $periksa];
        }

        $kasus = TestCase::query()
            ->where('mission_id', $submission->mission_id)
            ->get()
            ->keyBy('test_case_id');

        $nilai = $this->nilaiKasus($kasus, $laporan);

        if ($nilai === null) {
            return ['status' => self::ERR_LAPORAN];
        }

        $sumatif = $this->wajibVerifikasiMisi((int) $submission->mission_id);

        $submission = DB::transaction(function () use ($submission, $nilai, $laporan, $sumatif): Submission {
            // Selisih terhadap klaim perangkat sebelumnya (bila ada). Guru perlu
            // tahu bila perangkat siswa melaporkan hasil berbeda dari server.
            $selisih = $this->bandingkanKlaimLama($submission, $nilai, 'test_case_id', 'submission_id');

            $this->simpanHasil($submission, $nilai, 'test_case_id', 'submission_id');

            $lulus = $this->semuaLulus($nilai);
            $adaGalat = filled($laporan['galat'] ?? null);

            $status = match (true) {
                $adaGalat => Submission::STATUS_GALAT,
                $sumatif => Submission::STATUS_MENUNGGU_VERIFIKASI,
                default => Submission::STATUS_DINILAI,
            };

            $submission->update([
                'status' => $status,
                // Klaim browser TIDAK langsung dianggap lulus pada sumatif.
                'all_passed' => $lulus && $status === Submission::STATUS_DINILAI,
                'grading_source' => Submission::SUMBER_KLIEN,
                'client_report' => $this->ringkasLaporan($laporan),
                'client_runtime_ms' => max(0, (int) ($laporan['durasi_ms'] ?? 0)),
                'pyodide_version' => mb_substr((string) ($laporan['versi_pyodide'] ?? ''), 0, 20) ?: null,
                'graded_at' => now(),
                'error_message' => $adaGalat ? $this->pesanGalat($laporan) : null,
                'has_mismatch' => $selisih !== null,
                'mismatch_detail' => $selisih,
                // Nonce hangus setelah dipakai.
                'grading_nonce' => null,
                'nonce_expires_at' => null,
            ]);

            return $submission->fresh();
        });

        // Poin hanya diberikan bila status sudah final ('dinilai').
        if ($submission->status === Submission::STATUS_DINILAI) {
            $this->beriPoin($submission);
        }

        return [
            'status' => 'ok',
            'submission' => $submission,
            'lulus' => count(array_filter($nilai, fn (array $n) => $n['is_passed'])),
            'total' => count($nilai),
        ];
    }

    /**
     * Proses laporan untuk percobaan Kartu Bug.
     *
     * @param  array<string, mixed>  $laporan
     * @return array{status: string, attempt?: BugAttempt, lulus?: int, total?: int}
     */
    public function bugAttempt(BugAttempt $attempt, array $laporan): array
    {
        $periksa = $this->periksaLaporan($attempt, $laporan);

        if ($periksa !== null) {
            return ['status' => $periksa];
        }

        $kasus = BugTestCase::query()
            ->where('bug_id', $attempt->bug_id)
            ->get()
            ->keyBy('id');

        $nilai = $this->nilaiKasus($kasus, $laporan);

        if ($nilai === null) {
            return ['status' => self::ERR_LAPORAN];
        }

        $sumatif = $this->wajibVerifikasiBug($attempt);

        $attempt = DB::transaction(function () use ($attempt, $nilai, $laporan, $sumatif): BugAttempt {
            $selisih = $this->bandingkanKlaimLama($attempt, $nilai, 'bug_test_case_id', 'attempt_id');

            $this->simpanHasil($attempt, $nilai, 'bug_test_case_id', 'attempt_id');

            $lulus = $this->semuaLulus($nilai);
            $adaGalat = filled($laporan['galat'] ?? null);

            $status = match (true) {
                $adaGalat => BugAttempt::STATUS_GALAT,
                $sumatif => BugAttempt::STATUS_MENUNGGU_VERIFIKASI,
                default => BugAttempt::STATUS_DINILAI,
            };

            $final = $status === BugAttempt::STATUS_DINILAI;

            $attempt->update([
                'grading_status' => $status,
                'is_solved' => $lulus && $final,
                'solved_at' => $lulus && $final ? now() : null,
                'grading_source' => BugAttempt::SUMBER_KLIEN,
                'client_report' => $this->ringkasLaporan($laporan),
                'error_message' => $adaGalat ? $this->pesanGalat($laporan) : null,
                'has_mismatch' => $selisih !== null,
                'mismatch_detail' => $selisih,
                'grading_nonce' => null,
                'nonce_expires_at' => null,
            ]);

            return $attempt->fresh();
        });

        if ($attempt->is_solved) {
            app(ScoringService::class)->bugBonus($attempt);
        }

        return [
            'status' => 'ok',
            'attempt' => $attempt,
            'lulus' => count(array_filter($nilai, fn (array $n) => $n['is_passed'])),
            'total' => count($nilai),
        ];
    }

    /**
     * Validasi nonce, masa berlaku, dan status model.
     *
     * @param  array<string, mixed>  $laporan
     */
    private function periksaLaporan(Submission|BugAttempt $model, array $laporan): ?string
    {
        $status = $model instanceof Submission ? $model->status : $model->grading_status;

        if (! app(PaketPenilaian::class)->bolehDinilai($status)) {
            return self::ERR_STATUS;
        }

        $nonce = (string) ($laporan['nonce'] ?? '');

        if ($nonce === '' || ! hash_equals((string) $model->grading_nonce, $nonce)) {
            return self::ERR_NONCE;
        }

        if ($model->nonce_expires_at === null || $model->nonce_expires_at->isPast()) {
            return self::ERR_KEDALUWARSA;
        }

        if (! is_array($laporan['hasil'] ?? null) || $laporan['hasil'] === []) {
            return self::ERR_LAPORAN;
        }

        return null;
    }

    /**
     * Hitung lulus/gagal SETIAP kasus uji dari nilai mentah laporan.
     *
     * Kasus uji yang tidak dilaporkan dianggap GAGAL (bukan dilewati), agar
     * laporan yang dipotong tidak menghasilkan nilai lebih tinggi.
     *
     * @param  iterable<int, TestCase|BugTestCase>  $kasus
     * @param  array<string, mixed>  $laporan
     * @return array<int, array{is_passed: bool, actual_value: string}>|null
     */
    private function nilaiKasus(iterable $kasus, array $laporan): ?array
    {
        $dilaporkan = [];

        foreach ((array) $laporan['hasil'] as $satu) {
            if (! is_array($satu) || ! isset($satu['id'])) {
                return null;
            }

            $dilaporkan[(int) $satu['id']] = $satu;
        }

        $hasil = [];

        foreach ($kasus as $id => $k) {
            $laporanKasus = $dilaporkan[(int) $id] ?? null;

            if ($laporanKasus === null) {
                $hasil[(int) $id] = [
                    'is_passed' => false,
                    'actual_value' => '(tidak dilaporkan)',
                ];

                continue;
            }

            $hasil[(int) $id] = [
                'is_passed' => $this->banding->cocok(
                    $laporanKasus,
                    (string) $k->expected_value,
                    $k->tolerance,
                ),
                'actual_value' => $this->banding->teksAktual($laporanKasus),
            ];
        }

        return $hasil === [] ? null : $hasil;
    }

    /**
     * Simpan hasil per kasus (updateOrCreate agar aman bila diulang).
     *
     * $kolomKasus adalah nama kolom di TABEL HASIL (submission_results
     * .test_case_id / bug_attempt_results.bug_test_case_id), sedangkan
     * $kolomInduk adalah kolom relasi induk. Keduanya sengaja dipisah karena
     * id kasus bug berasal dari kolom `id` tabel bug_test_cases.
     *
     * @param  array<int, array{is_passed: bool, actual_value: string}>  $nilai
     */
    private function simpanHasil(
        Submission|BugAttempt $model,
        array $nilai,
        string $kolomKasus,
        string $kolomInduk,
    ): void {
        $relasi = $model->results();

        foreach ($nilai as $kasusId => $satu) {
            $relasi->updateOrCreate(
                [$kolomInduk => $model->getKey(), $kolomKasus => $kasusId],
                ['is_passed' => $satu['is_passed'], 'actual_value' => $satu['actual_value']],
            );
        }
    }

    /**
     * @param  array<int, array{is_passed: bool, actual_value: string}>  $nilai
     */
    private function semuaLulus(array $nilai): bool
    {
        if ($nilai === []) {
            return false;
        }

        foreach ($nilai as $satu) {
            if (! $satu['is_passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bandingkan hasil baru dengan hasil per kasus yang SUDAH tersimpan.
     *
     * Dipakai untuk mengisi has_mismatch/mismatch_detail: bila perangkat siswa
     * pernah melaporkan nilai berbeda untuk kasus yang sama, guru perlu tahu.
     * Null berarti tidak ada selisih (atau belum ada hasil lama).
     *
     * @param  array<int, array{is_passed: bool, actual_value: string}>  $baru
     * @return array<string, mixed>|null
     */
    private function bandingkanKlaimLama(
        Submission|BugAttempt $model,
        array $baru,
        string $kolomKasus,
        string $kolomInduk,
    ): ?array {
        $lama = $model->results()
            ->get()
            ->keyBy(fn ($r) => (int) $r->{$kolomKasus});

        if ($lama->isEmpty()) {
            return null;
        }

        $selisih = [];

        foreach ($baru as $kasusId => $satu) {
            $sebelum = $lama->get((int) $kasusId);

            if ($sebelum === null) {
                continue;
            }

            $bedaNilai = (string) ($sebelum->actual_value ?? '') !== $satu['actual_value'];
            $bedaStatus = (bool) $sebelum->is_passed !== $satu['is_passed'];

            if (! $bedaNilai && ! $bedaStatus) {
                continue;
            }

            $selisih[] = [
                'test_case_id' => (int) $kasusId,
                'sebelum' => [
                    'is_passed' => (bool) $sebelum->is_passed,
                    'actual_value' => mb_substr((string) ($sebelum->actual_value ?? ''), 0, 100),
                ],
                'sesudah' => [
                    'is_passed' => $satu['is_passed'],
                    'actual_value' => mb_substr($satu['actual_value'], 0, 100),
                ],
            ];
        }

        return $selisih === [] ? null : [
            'jumlah' => count($selisih),
            'kasus' => $selisih,
            'dibandingkan_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * Level sumatif wajib diverifikasi guru (bila diaktifkan di config).
     */
    private function wajibVerifikasiMisi(int $missionId): bool
    {
        if (! config('teater.pyodide.require_verification_for_summative', true)) {
            return false;
        }

        return Mission::query()
            ->whereKey($missionId)
            ->whereHas('level', fn ($q) => $q->where('is_summative', true))
            ->exists();
    }

    /**
     * Kartu bug pada level sumatif juga menunggu verifikasi guru.
     */
    private function wajibVerifikasiBug(BugAttempt $attempt): bool
    {
        if (! config('teater.pyodide.require_verification_for_summative', true)) {
            return false;
        }

        return BugCard::query()
            ->whereKey($attempt->bug_id)
            ->whereHas('level', fn ($q) => $q->where('is_summative', true))
            ->exists();
    }

    /**
     * Poin untuk submission yang sudah final.
     */
    private function beriPoin(Submission $submission): void
    {
        $scoring = app(ScoringService::class);

        $scoring->missionResult($submission);
        $scoring->missionResultIndividu($submission);
        $scoring->firstTryBonus($submission);
        $scoring->planBonus($submission);
    }

    /**
     * Ringkasan laporan mentah untuk disimpan (bukan seluruh stdout).
     *
     * @param  array<string, mixed>  $laporan
     * @return array<string, mixed>
     */
    private function ringkasLaporan(array $laporan): array
    {
        return [
            'keluaran' => mb_substr((string) ($laporan['keluaran'] ?? ''), 0, 2000),
            'galat' => $laporan['galat'] ?? null,
            'durasi_ms' => max(0, (int) ($laporan['durasi_ms'] ?? 0)),
            'versi_pyodide' => mb_substr((string) ($laporan['versi_pyodide'] ?? ''), 0, 20),
            'jumlah_hasil' => count((array) ($laporan['hasil'] ?? [])),
            'diterima_pada' => now()->toIso8601String(),
        ];
    }

    /**
     * Pesan galat ringkas untuk kolom error_message.
     *
     * @param  array<string, mixed>  $laporan
     */
    private function pesanGalat(array $laporan): ?string
    {
        $galat = $laporan['galat'] ?? null;

        if (! is_array($galat)) {
            return null;
        }

        $jenis = mb_substr((string) ($galat['jenis'] ?? 'Galat'), 0, 60);
        $pesan = mb_substr((string) ($galat['pesan'] ?? ''), 0, 400);
        $baris = $galat['baris'] ?? null;

        $teks = $jenis.($baris !== null ? " (baris {$baris})" : '').': '.$pesan;

        return mb_substr(trim($teks), 0, 1500);
    }
}
