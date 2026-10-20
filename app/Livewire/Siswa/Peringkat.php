<?php

namespace App\Livewire\Siswa;

use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa')]
#[Title('Peringkat')]
class Peringkat extends Component
{
    #[Computed]
    public function siswa(): Student
    {
        return Auth::guard('siswa')->user();
    }

    #[Computed]
    public function team(): ?Team
    {
        return $this->siswa->team();
    }

    #[Computed]
    public function ditampilkan(): bool
    {
        return (bool) config('teater.show_leaderboard', true) && $this->team !== null;
    }

    /**
     * Apakah ada level sumatif yang sedang terbuka (peringkat disembunyikan).
     */
    #[Computed]
    public function sumatifTerbuka(): bool
    {
        if ($this->team === null) {
            return false;
        }

        $levelSumatif = Level::query()->where('is_summative', true)->pluck('level_id');

        if ($levelSumatif->isEmpty()) {
            return false;
        }

        return ClassLevelAccess::query()
            ->where('class_id', $this->team->class_id)
            ->whereIn('level_id', $levelSumatif)
            ->get()
            ->contains(fn ($a) => $a->bolehDibuka());
    }

    /**
     * Peringkat kelompok di kelas sendiri (SUM skor kelompok).
     *
     * @return Collection<int, array{team_id: int, team_name: string, skor: int, peringkat: int, sendiri: bool}>
     */
    #[Computed]
    public function papan(): Collection
    {
        if ($this->team === null) {
            return collect();
        }

        $teamIds = Team::query()->where('class_id', $this->team->class_id)->pluck('team_id');

        $skor = ScoreEntry::query()
            ->whereNull('student_id')
            ->whereIn('team_id', $teamIds)
            ->selectRaw('team_id, SUM(points) as total')
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        $nama = Team::query()->whereIn('team_id', $teamIds)->pluck('team_name', 'team_id');

        $baris = $teamIds
            ->map(fn ($id) => [
                'team_id' => (int) $id,
                'team_name' => (string) $nama->get($id, 'Kelompok'),
                'skor' => (int) $skor->get($id, 0),
                'sendiri' => (int) $id === (int) $this->team->team_id,
            ])
            ->sortByDesc('skor')
            ->values();

        $peringkat = 0;
        $sebelumnya = null;
        $hasil = collect();

        foreach ($baris as $index => $item) {
            if ($item['skor'] !== $sebelumnya) {
                $peringkat = $index + 1;
                $sebelumnya = $item['skor'];
            }

            $hasil->push([...$item, 'peringkat' => $peringkat]);
        }

        return $hasil;
    }

    public function render()
    {
        return view('livewire.siswa.peringkat');
    }
}
