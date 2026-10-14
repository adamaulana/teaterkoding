<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Profil Saya')]
class extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfil(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('toast', type: 'sukses', message: 'Profil berhasil diperbarui.');
    }
}; ?>

<div>
    <x-ui.page-header
        title="Profil Saya"
        description="Perbarui nama dan alamat email yang digunakan untuk masuk."
    />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form wire:submit="updateProfil" class="space-y-5">
                <x-ui.input label="Nama Lengkap" name="name" wire:model="name" autocomplete="name" required />
                <x-ui.input label="Alamat Email" name="email" type="email" wire:model="email" autocomplete="email" required />

                <div class="flex justify-end">
                    <x-ui.button variant="primary" type="submit">
                        Simpan Perubahan
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <p class="text-sm font-semibold text-slate-800">Informasi Akun</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Peran</dt>
                    <dd>
                        <x-ui.badge :variant="Auth::user()->isAdmin() ? 'info' : 'sukses'">
                            {{ Auth::user()->isAdmin() ? 'Admin' : 'Guru' }}
                        </x-ui.badge>
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Email terverifikasi</dt>
                    <dd class="font-medium {{ Auth::user()->hasVerifiedEmail() ? 'text-primary-700' : 'text-amber-600' }}">
                        {{ Auth::user()->hasVerifiedEmail() ? 'Sudah' : 'Belum' }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Bergabung</dt>
                    <dd>{{ Auth::user()->created_at?->format('d M Y') ?? '-' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</div>
