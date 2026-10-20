<?php

namespace App\Http\Controllers;

use App\Models\BugAttempt;
use App\Models\Student;
use App\Models\Submission;
use App\Services\PaketPenilaian;
use App\Services\PenilaianKlien;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint penilaian di browser (Fase 6 — Pyodide).
 *
 * Tiga aturan yang dijaga di sini:
 *   1. Hanya siswa pemilik kelompok yang boleh meminta paket / mengirim laporan.
 *   2. Paket TIDAK memuat expected_value atau tolerance.
 *   3. Laporan hanya diterima bila nonce cocok & belum kedaluwarsa; server
 *      tetap yang memutuskan lulus/gagal.
 */
class PenilaianController extends Controller
{
    public function __construct(
        private readonly PaketPenilaian $paket,
        private readonly PenilaianKlien $penilaian,
    ) {}

    /**
     * Paket penilaian untuk submission misi.
     */
    public function paketSubmission(Request $request, Submission $submission): JsonResponse
    {
        abort_unless($this->milikSaya($request, $submission->team_id), 404);

        $paket = $this->paket->untukSubmission($submission);

        if ($paket === null) {
            return $this->tanpaCache(response()->json([
                'status' => 'tidak_tersedia',
                'pesan' => 'Submission ini tidak bisa dinilai di perangkat.',
            ], 409));
        }

        return $this->tanpaCache(response()->json($paket));
    }

    /**
     * Paket penilaian untuk percobaan Kartu Bug.
     */
    public function paketBug(Request $request, BugAttempt $attempt): JsonResponse
    {
        abort_unless($this->milikSaya($request, $attempt->team_id), 404);
        abort_if($attempt->released_at === null, 404);

        $paket = $this->paket->untukBugAttempt($attempt);

        if ($paket === null) {
            return $this->tanpaCache(response()->json([
                'status' => 'tidak_tersedia',
                'pesan' => 'Percobaan ini tidak bisa dinilai di perangkat.',
            ], 409));
        }

        return $this->tanpaCache(response()->json($paket));
    }

    /**
     * Paket berisi kode siswa + nonce sekali pakai: JANGAN di-cache browser,
     * proxy sekolah, atau service worker. Nonce yang di-cache akan ditolak
     * server ("tidak cocok") sehingga penilaian tampak gagal tanpa sebab.
     */
    private function tanpaCache(JsonResponse $respons): JsonResponse
    {
        return $respons->header('Cache-Control', 'no-store, max-age=0, must-revalidate');
    }

    /**
     * Terima laporan nilai mentah dari browser siswa.
     *
     * Bentuk laporan (semua nilai MENTAH, tanpa klaim lulus/gagal):
     * {
     *   "jenis": "misi"|"bug", "id": 12, "nonce": "…",
     *   "hasil": [{"id": 3, "tipe": "int", "nilai": 4780850}, …],
     *   "keluaran": "…", "galat": null, "durasi_ms": 812,
     *   "versi_pyodide": "314.0.7"
     * }
     */
    public function lapor(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'jenis' => ['required', 'in:misi,bug'],
            'id' => ['required', 'integer', 'min:1'],
            'nonce' => ['required', 'string', 'size:64'],
            'hasil' => ['required', 'array', 'min:1', 'max:200'],
            'hasil.*.id' => ['required', 'integer', 'min:1'],
            'hasil.*.tipe' => ['required', 'string', 'in:int,float,str,bool,none,lain,belum_dibuat'],
            'hasil.*.nilai' => ['nullable'],
            'keluaran' => ['nullable', 'string', 'max:4000'],
            'galat' => ['nullable', 'array'],
            'galat.jenis' => ['nullable', 'string', 'max:60'],
            'galat.pesan' => ['nullable', 'string', 'max:600'],
            'galat.baris' => ['nullable', 'integer', 'min:0'],
            'durasi_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'versi_pyodide' => ['nullable', 'string', 'max:20'],
        ])->validate();

        $laporan = [
            'nonce' => (string) $data['nonce'],
            'hasil' => $this->bersihkanHasil($data['hasil']),
            'keluaran' => (string) ($data['keluaran'] ?? ''),
            'galat' => $data['galat'] ?? null,
            'durasi_ms' => (int) ($data['durasi_ms'] ?? 0),
            'versi_pyodide' => (string) ($data['versi_pyodide'] ?? ''),
        ];

        if ($data['jenis'] === 'misi') {
            $submission = Submission::query()->find($data['id']);

            abort_if($submission === null, 404);
            abort_unless($this->milikSaya($request, $submission->team_id), 404);

            return $this->tanggapi($this->penilaian->submission($submission, $laporan), 'submission');
        }

        $attempt = BugAttempt::query()->find($data['id']);

        abort_if($attempt === null, 404);
        abort_unless($this->milikSaya($request, $attempt->team_id), 404);

        return $this->tanggapi($this->penilaian->bugAttempt($attempt, $laporan), 'attempt');
    }

    /**
     * Rapikan nilai mentah agar hanya skalar yang masuk DB.
     *
     * @param  array<int, array<string, mixed>>  $hasil
     * @return array<int, array{id: int, tipe: string, nilai: mixed}>
     */
    private function bersihkanHasil(array $hasil): array
    {
        $bersih = [];

        foreach ($hasil as $satu) {
            $nilai = $satu['nilai'] ?? null;

            // Hanya skalar; objek/array dari browser tidak pernah dipercaya.
            if (is_array($nilai) || is_object($nilai)) {
                $nilai = null;
            }

            if (is_string($nilai)) {
                $nilai = mb_substr($nilai, 0, 200);
            }

            $bersih[] = [
                'id' => (int) $satu['id'],
                'tipe' => (string) $satu['tipe'],
                'nilai' => $nilai,
            ];
        }

        return $bersih;
    }

    /**
     * @param  array<string, mixed>  $hasil
     */
    private function tanggapi(array $hasil, string $kunci): JsonResponse
    {
        if ($hasil['status'] !== 'ok') {
            $pesan = match ($hasil['status']) {
                PenilaianKlien::ERR_NONCE => 'Kode penilaian tidak cocok. Muat ulang halaman lalu coba lagi.',
                PenilaianKlien::ERR_KEDALUWARSA => 'Sesi penilaian kedaluwarsa. Tekan "Nilai Ulang".',
                PenilaianKlien::ERR_STATUS => 'Hasil sudah tersimpan sebelumnya.',
                default => 'Laporan tidak bisa diproses.',
            };

            return response()->json(['status' => $hasil['status'], 'pesan' => $pesan], 422);
        }

        $model = $hasil[$kunci] ?? null;

        return response()->json([
            'status' => 'ok',
            'lulus' => $hasil['lulus'] ?? 0,
            'total' => $hasil['total'] ?? 0,
            'status_penilaian' => $model instanceof Submission
                ? $model->status
                : ($model instanceof BugAttempt ? $model->grading_status : null),
        ]);
    }

    private function milikSaya(Request $request, mixed $teamId): bool
    {
        /** @var Student|null $siswa */
        $siswa = Auth::guard('siswa')->user();

        if ($siswa === null) {
            return false;
        }

        $team = $siswa->team();

        return $team !== null && (int) $team->team_id === (int) $teamId;
    }
}
