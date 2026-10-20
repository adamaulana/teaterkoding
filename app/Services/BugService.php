<?php

namespace App\Services;

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Rilis Kartu Bug dari guru ke kelompok.
 *
 * Satu kartu bug yang sama TIDAK BOLEH dirilis dua kali ke kelompok yang sama
 * (dijaga di sini + diverifikasi test).
 */
final class BugService
{
    /**
     * Rilis kartu bug ke kelompok.
     *
     * @return array{status: string, attempt?: BugAttempt}
     */
    public function rilis(Team $team, BugCard $kartu, User $guru): array
    {
        $ada = BugAttempt::query()
            ->where('team_id', $team->team_id)
            ->where('bug_id', $kartu->bug_id)
            ->exists();

        if ($ada) {
            return ['status' => 'sudah_dirilis'];
        }

        $attempt = BugAttempt::create([
            'bug_id' => $kartu->bug_id,
            'team_id' => $team->team_id,
            'released_by' => $guru->id,
            'released_at' => now(),
            'is_solved' => false,
            'grading_status' => BugAttempt::STATUS_BELUM,
            'hints_used' => 0,
            'attempted_at' => now(),
        ]);

        return ['status' => 'ok', 'attempt' => $attempt];
    }

    /**
     * Kartu bug pada level tertentu yang BELUM dirilis ke kelompok ini.
     *
     * @return Collection<int, BugCard>
     */
    public function kartuBelumDirilis(Team $team, int $levelId): Collection
    {
        $sudahDirilis = BugAttempt::query()
            ->where('team_id', $team->team_id)
            ->pluck('bug_id');

        return BugCard::query()
            ->where('level_id', $levelId)
            ->whereNotIn('bug_id', $sudahDirilis)
            ->orderBy('bug_title')
            ->get();
    }
}
