<?php

namespace App\Livewire\Siswa;

use App\Models\Student;
use App\Services\AccessCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa-guest')]
#[Title('Masuk')]
class Masuk extends Component
{
    public string $kode = '';

    public string $pesan = '';

    public function mount(): void
    {
        if (Auth::guard('siswa')->check()) {
            $this->redirect(route('siswa.beranda'));
        }
    }

    public function masuk(): void
    {
        $servis = app(AccessCodeService::class);
        $kode = $servis->normalisasi($this->kode);

        $batas = (int) config('teater.login_throttle', 10);
        $kunciIp = 'siswa-login:ip:'.request()->ip();
        $kunciKode = 'siswa-login:kode:'.$kode;

        if (RateLimiter::tooManyAttempts($kunciIp, $batas) || RateLimiter::tooManyAttempts($kunciKode, $batas)) {
            $this->gagalGenerik();

            return;
        }

        if ($kode === '') {
            RateLimiter::hit($kunciIp, 60);
            $this->gagalGenerik();

            return;
        }

        $siswa = Student::query()->where('access_code', $kode)->first();

        if ($siswa === null) {
            RateLimiter::hit($kunciIp, 60);
            RateLimiter::hit($kunciKode, 60);
            $this->gagalGenerik();

            return;
        }

        RateLimiter::clear($kunciIp);
        RateLimiter::clear($kunciKode);

        Auth::guard('siswa')->login($siswa);

        $siswa->forceFill(['last_login_at' => now()])->save();

        session()->regenerate();

        $this->redirect(route('siswa.beranda'), navigate: true);
    }

    /**
     * Pesan gagal SELALU generik — tidak membocorkan keberadaan kode.
     */
    private function gagalGenerik(): void
    {
        $this->pesan = 'Kode tidak ditemukan';
        $this->reset('kode');
    }

    public function render()
    {
        return view('livewire.siswa.masuk');
    }
}
