<?php

namespace App\Http\Controllers;

use App\Models\BugAttempt;
use App\Models\Submission;
use App\Models\User;
use App\Services\PaketPenilaian;
use App\Services\VerifikasiHasil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint penilaian ulang oleh GURU (Fase 6 — Bagian C).
 *
 * Alur verifikasi sumatif:
 *   1. Guru membuka paket → menjalankan kode siswa di perangkat GURU.
 *   2. Guru mengirim nilai mentah hasil eksekusinya ke endpoint periksa.
 *   3. Server membandingkan dengan expected_value dan mengembalikan rincian
 *      (termasuk expected_value — HANYA untuk guru, tidak pernah ke siswa).
 *   4. Guru menekan Verifikasi di halaman; poin baru diberikan saat itu.
 *
 * Semua rute memakai middleware `auth` + `verified` dan dibatasi policy
 * kelas (lihat VerifikasiController::pastikanBoleh).
 */
class VerifikasiPenilaianController extends Controller
{
    public function __construct(
        private readonly PaketPenilaian $paket,
        private readonly VerifikasiHasil $verifikasi,
    ) {}

    /**
     * Paket penilaian untuk dijalankan ulang di perangkat guru.
     */
    public function paket(Request $request, string $jenis, int $id): JsonResponse
    {
        $model = $this->model($jenis, $id);

        $this->pastikanBoleh($request, $model);

        $paket = $this->paket->untukVerifikasi($model);

        if ($paket === null) {
            return response()->json([
                'status' => 'tidak_tersedia',
                'pesan' => 'Data ini tidak punya kasus uji untuk diperiksa.',
            ], 409);
        }

        return response()->json($paket);
    }

    /**
     * Periksa nilai mentah hasil eksekusi guru.
     *
     * Request: { hasil: [{id, tipe, nilai}], galat?: {...} }
     * Response: { hasil: [{id, lulus, aktual, harapan}], lulus, total, galat }
     */
    public function periksa(Request $request, string $jenis, int $id): JsonResponse
    {
        $model = $this->model($jenis, $id);

        $this->pastikanBoleh($request, $model);

        $data = Validator::make($request->all(), [
            'hasil' => ['required', 'array', 'min:1', 'max:200'],
            'hasil.*.id' => ['required', 'integer', 'min:1'],
            'hasil.*.tipe' => ['required', 'string', 'in:int,float,str,bool,none,lain,belum_dibuat'],
            'hasil.*.nilai' => ['nullable'],
            'galat' => ['nullable', 'array'],
        ])->validate();

        $laporan = [
            'hasil' => $data['hasil'],
            'galat' => $data['galat'] ?? null,
        ];

        $hasil = $model instanceof Submission
            ? $this->verifikasi->periksaSubmission($model, $laporan)
            : $this->verifikasi->periksaBugAttempt($model, $laporan);

        return response()->json($hasil);
    }

    /**
     * Ambil model dari segmen rute (misi|bug).
     */
    private function model(string $jenis, int $id): Submission|BugAttempt
    {
        $model = $jenis === 'bug'
            ? BugAttempt::query()->find($id)
            : Submission::query()->find($id);

        abort_if($model === null, 404);

        return $model;
    }

    /**
     * Hanya admin, atau guru pemilik kelas data ini.
     *
     * Pemeriksaan tipe eksplisit penting: guard `siswa` juga memakai sesi yang
     * sama, dan scopeVisibleTo() hanya menerima User. Tanpa ini, siswa yang
     * membuka URL admin akan mendapat 500, bukan 403.
     */
    private function pastikanBoleh(Request $request, Submission|BugAttempt $model): void
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $kelas = $model->team?->schoolClass;

        abort_if($kelas === null, 404);

        abort_unless($user->can('view', $kelas), 403);
    }
}
