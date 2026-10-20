<?php

namespace App\Livewire\Siswa;

use App\Models\Mission;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Services\LevelAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa')]
#[Title('Riwayat Misi')]
class RiwayatMisi extends Component
{
    public Mission $misi;

    public function mount(Mission $mission): void
    {
        $this->misi = $mission->load('level');

        abort_unless($this->bolehAkses(), 404);
    }

    private function bolehAkses(): bool
    {
        $siswa = $this->siswa();

        if ($siswa === null || $siswa->team() === null) {
            return false;
        }

        return app(LevelAccessService::class)->canWork($siswa->team(), $this->misi);
    }

    #[Computed]
    public function siswa(): Student
    {
        return Auth::guard('siswa')->user();
    }

    #[Computed]
    public function team(): Team
    {
        return $this->siswa->team();
    }

    /**
     * Riwayat submission kelompok sendiri (nomor, waktu, pengirim, hasil).
     *
     * @return Collection<int, Submission>
     */
    #[Computed]
    public function riwayat(): Collection
    {
        return Submission::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->with('submittedBy')
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->get();
    }

    public function render()
    {
        return view('livewire.siswa.riwayat-misi');
    }
}
