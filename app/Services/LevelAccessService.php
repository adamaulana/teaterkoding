<?php

namespace App\Services;

use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\Submission;
use App\Models\Team;

/**
 * Aturan buka/kerja satu misi untuk satu kelompok (sisi siswa).
 *
 * - Level harus TERBUKA di class_level_access (is_open = true).
 * - Harus berada dalam jendela opens_at/closes_at bila diisi.
 * - TANPA baris class_level_access berarti TERTUTUP (default aman).
 * - Bila require_sequential_levels aktif, level sebelumnya harus sudah lulus
 *   (ada submission all_passed berstatus 'dinilai' pada salah satu misi level tsb).
 */
final class LevelAccessService
{
    /**
     * Boleh membuka misi (memasukkan kode unlock untuk pertama kali).
     */
    public function canUnlock(Team $team, Mission $mission): bool
    {
        return $this->terbuka($team, $mission) && $this->urutanTerpenuhi($team, $mission);
    }

    /**
     * Boleh mengerjakan misi (lanjut/edit/submit).
     */
    public function canWork(Team $team, Mission $mission): bool
    {
        return $this->terbuka($team, $mission) && $this->urutanTerpenuhi($team, $mission);
    }

    /**
     * Ambil baris pengaturan akses level (null bila belum dibuka guru).
     */
    public function akses(Team $team, Mission $mission): ?ClassLevelAccess
    {
        return ClassLevelAccess::query()
            ->where('class_id', $team->class_id)
            ->where('level_id', $mission->level_id)
            ->first();
    }

    /**
     * Level terbuka & berada dalam jendela waktunya.
     */
    private function terbuka(Team $team, Mission $mission): bool
    {
        $akses = $this->akses($team, $mission);

        return $akses !== null && $akses->bolehDibuka();
    }

    /**
     * Bila require_sequential_levels aktif, pastikan level sebelumnya lulus.
     *
     * Level 1 (atau level yang tidak punya pendahulu) selalu lolos.
     */
    private function urutanTerpenuhi(Team $team, Mission $mission): bool
    {
        if (! config('teater.require_sequential_levels', true)) {
            return true;
        }

        $level = Level::query()->find($mission->level_id);

        if ($level === null || (int) $level->level_number <= 1) {
            return true;
        }

        $sebelumnya = Level::query()
            ->where('level_number', (int) $level->level_number - 1)
            ->first();

        if ($sebelumnya === null) {
            return true;
        }

        $missionIds = Mission::query()
            ->where('level_id', $sebelumnya->level_id)
            ->pluck('mission_id');

        return Submission::query()
            ->where('team_id', $team->team_id)
            ->whereIn('mission_id', $missionIds)
            ->where('all_passed', true)
            ->where('status', Submission::STATUS_DINILAI)
            ->exists();
    }
}
