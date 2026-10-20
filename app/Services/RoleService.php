<?php

namespace App\Services;

use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\Team;

/**
 * Aturan hak peran siswa di sisi siswa.
 *
 * Peran aktif hanya ditegakkan bila config('teater.enforce_roles') aktif DAN
 * kelompok punya penugasan peran pada level terkait. Tanpa keduanya, semua
 * anggota boleh semua aksi (dengan catatan kecil di UI).
 */
final class RoleService
{
    public const PERAN_CODER = 'Coder';

    public const PERAN_TESTER = 'Tester';

    public const PERAN_ANALYST = 'Analyst';

    public const PERAN_REVIEWER = 'Reviewer';

    public const PERAN_BUG_HUNTER = 'Bug Hunter';

    /**
     * Peran siswa pada satu level (null bila tidak ditugaskan).
     */
    public function peran(Team $team, Student $siswa, int $levelId): ?string
    {
        return RoleAssignment::query()
            ->where('team_id', $team->team_id)
            ->where('student_id', $siswa->student_id)
            ->where('level_id', $levelId)
            ->with('role')
            ->first()?->role?->role_name;
    }

    /**
     * Apakah penegakan peran aktif untuk kelompok ini pada level ini.
     */
    public function penegakanAktif(Team $team, int $levelId): bool
    {
        if (! config('teater.enforce_roles', true)) {
            return false;
        }

        return RoleAssignment::query()
            ->where('team_id', $team->team_id)
            ->where('level_id', $levelId)
            ->exists();
    }

    /**
     * Apakah siswa boleh melakukan aksi tertentu pada level ini.
     *
     * Bila penegakan tidak aktif → semua boleh. Bila aktif → hanya siswa
     * dengan salah satu peran pada $peranBoleh yang boleh.
     *
     * @param  array<int, string>  $peranBoleh
     */
    public function boleh(Team $team, Student $siswa, int $levelId, array $peranBoleh): bool
    {
        if (! $this->penegakanAktif($team, $levelId)) {
            return true;
        }

        $peran = $this->peran($team, $siswa, $levelId);

        return $peran !== null && in_array($peran, $peranBoleh, true);
    }
}
