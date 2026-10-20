<?php

namespace App\Livewire\Siswa;

use App\Models\BugAttempt;
use App\Models\BugAttemptResult;
use App\Models\Student;
use App\Models\Team;
use App\Services\BugAttemptService;
use App\Services\RoleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa')]
#[Title('Kartu Bug')]
class KartuBug extends Component
{
    public BugAttempt $attempt;

    public string $kode = '';

    public string $petunjuk = '';

    public bool $petunjukTerbuka = false;

    public function mount(BugAttempt $attempt): void
    {
        $this->attempt = $attempt->load('bugCard');

        // Hanya bug milik kelompok sendiri yang boleh dibuka (URL langsung → 404).
        abort_unless($this->bolehAkses(), 404);

        // Bug harus sudah dirilis guru.
        abort_if($this->attempt->released_at === null, 404);

        $this->kode = (string) ($this->attempt->fixed_code ?? $this->attempt->bugCard?->buggy_code ?? '');
    }

    private function bolehAkses(): bool
    {
        $siswa = $this->siswa();

        if ($siswa === null || $siswa->team() === null) {
            return false;
        }

        return (int) $this->attempt->team_id === (int) $siswa->team()->team_id;
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
     * Bug Hunter yang boleh mengedit & submit (fallback peran sama).
     */
    #[Computed]
    public function bolehKerjakan(): bool
    {
        return app(RoleService::class)->boleh(
            $this->team,
            $this->siswa,
            (int) $this->attempt->bugCard->level_id,
            [RoleService::PERAN_BUG_HUNTER],
        );
    }

    #[Computed]
    public function hasil(): Collection
    {
        return BugAttemptResult::query()
            ->where('attempt_id', $this->attempt->attempt_id)
            ->with('testCase')
            ->get();
    }

    public function simpanDraf(): void
    {
        if (! $this->bolehKerjakan || $this->attempt->is_solved) {
            return;
        }

        $this->attempt->update(['fixed_code' => $this->kode]);

        $this->dispatch('toast', type: 'sukses', message: 'Tersimpan '.now()->format('H:i:s'));
    }

    public function mintaPetunjuk(): void
    {
        if (! $this->bolehKerjakan) {
            $this->dispatch('toast', type: 'peringatan', message: 'Hanya Bug Hunter yang mengerjakan kartu ini.');

            return;
        }

        $hasil = app(BugAttemptService::class)->mintaPetunjuk($this->attempt, $this->siswa);

        if ($hasil['status'] === BugAttemptService::ERR_SUDAH_LULUS) {
            return;
        }

        $this->petunjuk = (string) ($hasil['hint'] ?? '');
        $this->petunjukTerbuka = true;
        unset($this->attempt);

        if ($hasil['status'] === 'ok') {
            $this->dispatch('toast', type: 'peringatan', message: 'Petunjuk dibuka (-'.config('teater.hint_penalty', 5).' poin).');
        }
    }

    public function submit(): void
    {
        if (! $this->bolehKerjakan) {
            $this->dispatch('toast', type: 'peringatan', message: 'Hanya Bug Hunter yang mengerjakan kartu ini.');

            return;
        }

        $hasil = app(BugAttemptService::class)->submit($this->attempt, $this->siswa, $this->kode);

        match ($hasil['status']) {
            'ok' => $this->dispatch('toast', type: 'sukses', message: 'Perbaikan dikirim, sedang dinilai.'),
            BugAttemptService::ERR_UKURAN => $this->dispatch('toast', type: 'bahaya', message: 'Kode terlalu besar.'),
            BugAttemptService::ERR_NUL => $this->dispatch('toast', type: 'bahaya', message: 'Kode mengandung karakter tidak valid.'),
            BugAttemptService::ERR_JEDA => $this->dispatch('toast', type: 'peringatan', message: 'Tunggu '.($hasil['tunggu'] ?? 0).' detik sebelum submit lagi.'),
            BugAttemptService::ERR_TANPA_KASUS => $this->dispatch('toast', type: 'peringatan', message: 'Kartu ini belum punya kasus uji.'),
            default => null,
        };

        unset($this->attempt, $this->hasil);
    }

    public function render()
    {
        return view('livewire.siswa.kartu-bug');
    }
}
