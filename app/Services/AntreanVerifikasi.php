<?php

namespace App\Services;

use App\Models\BugAttempt;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;

/**
 * Hitungan antrean verifikasi untuk satu pengguna panel (Fase 6 — Bagian D).
 *
 * Dipakai TIGA tempat sekaligus: halaman Verifikasi Penilaian, lencana di
 * sidebar, dan kartu ringkasan di Dashboard. Karena itu semua rumusnya ada di
 * sini — tidak ada hitungan di Blade maupun di komponen Livewire.
 *
 * === TIGA ANGKA ===
 *   menunggu_verifikasi  hasil perangkat sudah masuk, poin menunggu guru
 *   menunggu_perangkat   siswa sudah submit tetapi belum menilai di perangkat
 *                        (status 'menunggu', nonce masih berlaku)
 *   selisih              hasil perangkat berbeda dari pemeriksaan/verifikasi
 *                        sebelumnya (has_mismatch)
 *
 * === BATASAN KELAS ===
 * Admin melihat semua kelas; guru hanya kelas miliknya. Batasannya memakai
 * scope SchoolClass::visibleTo() yang sama dengan halaman lain, sehingga
 * angka di sidebar tidak pernah membocorkan kelas guru lain.
 *
 * Hasil di-cache per pengguna selama satu request: sidebar, dashboard, dan
 * halaman memanggil service ini berkali-kali tanpa menambah query.
 */
final class AntreanVerifikasi
{
    /**
     * Cache per pengguna untuk SATU request.
     *
     * Disimpan di instance (bukan static) dan service didaftarkan sebagai
     * `scoped` di AppServiceProvider. Cache static akan bocor antar-request
     * pada proses long-running (queue/Octane) DAN antar-test, sehingga angka
     * antrean bisa basi setelah verifikasi.
     *
     * @var array<int, array{menunggu_verifikasi: int, menunggu_perangkat: int, selisih: int}>
     */
    private array $cache = [];

    /**
     * Ringkasan antrean untuk pengguna ini.
     *
     * @return array{menunggu_verifikasi: int, menunggu_perangkat: int, selisih: int}
     */
    public function untuk(User $user): array
    {
        $kunci = (int) $user->id;

        if (isset($this->cache[$kunci])) {
            return $this->cache[$kunci];
        }

        $classIds = SchoolClass::query()->visibleTo($user)->pluck('class_id')->all();

        if ($classIds === []) {
            return $this->cache[$kunci] = [
                'menunggu_verifikasi' => 0,
                'menunggu_perangkat' => 0,
                'selisih' => 0,
            ];
        }

        $teamIds = Team::query()->whereIn('class_id', $classIds)->pluck('team_id')->all();

        return $this->cache[$kunci] = $teamIds === []
            ? ['menunggu_verifikasi' => 0, 'menunggu_perangkat' => 0, 'selisih' => 0]
            : $this->hitung($teamIds);
    }

    /**
     * Jumlah item yang menunggu verifikasi (submission + percobaan bug).
     */
    public function jumlahMenungguVerifikasi(User $user): int
    {
        return $this->untuk($user)['menunggu_verifikasi'];
    }

    /**
     * Lupakan cache (dipakai test dan setelah aksi verifikasi).
     */
    public function lupakan(): void
    {
        $this->cache = [];
    }

    /**
     * Query agregat per kelompok id.
     *
     * @param  array<int, int>  $teamIds
     * @return array{menunggu_verifikasi: int, menunggu_perangkat: int, selisih: int}
     */
    private function hitung(array $teamIds): array
    {
        $submission = Submission::query()
            ->whereIn('team_id', $teamIds)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as menunggu_verifikasi,'
                .' COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as menunggu_perangkat,'
                .' COALESCE(SUM(CASE WHEN has_mismatch = 1 THEN 1 ELSE 0 END), 0) as selisih',
                [Submission::STATUS_MENUNGGU_VERIFIKASI, Submission::STATUS_MENUNGGU],
            )
            ->first();

        $bug = BugAttempt::query()
            ->whereIn('team_id', $teamIds)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN grading_status = ? THEN 1 ELSE 0 END), 0) as menunggu_verifikasi,'
                .' COALESCE(SUM(CASE WHEN grading_status = ? THEN 1 ELSE 0 END), 0) as menunggu_perangkat,'
                .' COALESCE(SUM(CASE WHEN has_mismatch = 1 THEN 1 ELSE 0 END), 0) as selisih',
                [BugAttempt::STATUS_MENUNGGU_VERIFIKASI, BugAttempt::STATUS_MENUNGGU],
            )
            ->first();

        return [
            'menunggu_verifikasi' => (int) ($submission?->menunggu_verifikasi ?? 0)
                + (int) ($bug?->menunggu_verifikasi ?? 0),
            'menunggu_perangkat' => (int) ($submission?->menunggu_perangkat ?? 0)
                + (int) ($bug?->menunggu_perangkat ?? 0),
            'selisih' => (int) ($submission?->selisih ?? 0)
                + (int) ($bug?->selisih ?? 0),
        ];
    }
}
