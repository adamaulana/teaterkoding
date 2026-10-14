<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Laravel\Fortify\Actions\UpdateUserPassword;

new #[Layout('layouts.admin'), Title('Ganti Password')]
class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        app(UpdateUserPassword::class)(Auth::user(), $validated);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->resetErrorBag();

        $this->dispatch('toast', type: 'sukses', message: 'Password berhasil diubah.');
    }
}; ?>

<div>
    <x-ui.page-header
        title="Ganti Password"
        description="Gunakan kata sandi yang kuat dan tidak dipakai di layanan lain."
    />

    <x-ui.card class="max-w-xl">
        <form wire:submit="updatePassword" class="space-y-5">
            <x-ui.input
                label="Password Saat Ini"
                name="current_password"
                type="password"
                wire:model="current_password"
                autocomplete="current-password"
                required
            />

            <x-ui.input
                label="Password Baru"
                name="password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                help="Minimal 8 karakter. Di produksi, wajib mengandung huruf besar/kecil, angka, dan simbol."
                required
            />

            <x-ui.input
                label="Konfirmasi Password Baru"
                name="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />

            <div class="flex justify-end">
                <x-ui.button variant="primary" type="submit">
                    Ubah Password
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
