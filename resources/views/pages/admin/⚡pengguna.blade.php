<?php

use App\Actions\CreateUserByAdmin;
use App\Actions\ResetPasswordByAdmin;
use App\Actions\ToggleUserActive;
use App\Actions\UpdateUserRole;
use App\Concerns\InteractsWithTable;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Pengguna')]
class extends Component
{
    use InteractsWithTable;

    // Form tambah pengguna
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = 'guru';

    // Pengguna terpilih untuk aksi
    public ?int $userId = null;

    public string $roleBaru = 'guru';

    public string $passwordBaru = '';

    public string $password_baru_confirmation = '';

    /**
     * Baris tabel (pencarian + urutan + paginasi ditangani InteractsWithTable).
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return User::query()
            ->when(filled($this->search), fn ($query) => $query
                ->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy($this->sortBy ?: 'created_at', $this->sortBy ? $this->sortDir : 'desc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
    }

    /**
     * Definisi kolom tabel (format sel berupa closure — bukan query).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Nama',
                'sortable' => true,
                'format' => fn (User $user) => Blade::render(<<<'BLADE'
                    <div class="flex items-center gap-3">
                        <x-ui.avatar :name="$name" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800">{{ $name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $email }}</p>
                        </div>
                    </div>
                    BLADE, ['name' => $user->name, 'email' => $user->email]),
            ],
            [
                'key' => 'role',
                'label' => 'Peran',
                'sortable' => true,
                'format' => fn (User $user) => Blade::render(
                    '<x-ui.badge :variant="$variant">{{ $label }}</x-ui.badge>',
                    ['variant' => $user->isAdmin() ? 'info' : 'sukses', 'label' => $user->isAdmin() ? 'Admin' : 'Guru'],
                ),
            ],
            [
                'key' => 'is_active',
                'label' => 'Status',
                'sortable' => true,
                'format' => fn (User $user) => Blade::render(
                    '<x-ui.badge :variant="$variant">{{ $label }}</x-ui.badge>',
                    [
                        'variant' => $user->is_active ? 'sukses' : 'bahaya',
                        'label' => $user->is_active ? 'Aktif' : 'Nonaktif',
                    ],
                ),
            ],
            [
                'key' => 'created_at',
                'label' => 'Terdaftar',
                'sortable' => true,
                'format' => fn (User $user) => $user->created_at?->format('d M Y') ?? '-',
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (User $user) => Blade::render(<<<'BLADE'
                    <x-ui.dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" aria-label="Aksi">
                                <x-ui.icon.dots-vertical />
                            </button>
                        </x-slot:trigger>

                        @unless ($isSelf)
                            <button type="button" wire:click="bukaUbahPeran({{ $id }})" class="block w-full px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                                Ubah Peran
                            </button>
                        @endunless

                        <button type="button" wire:click="bukaResetPassword({{ $id }})" class="block w-full px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            Reset Password
                        </button>

                        @unless ($isSelf)
                            <button type="button" wire:click="toggleAktif({{ $id }})" class="block w-full px-4 py-2 text-start text-sm transition hover:bg-primary-50 {{ $aktif ? 'text-rose-600 hover:text-rose-700' : 'text-primary-700' }}">
                                {{ $aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        @endunless
                    </x-ui.dropdown>
                    BLADE, ['id' => $user->id, 'isSelf' => $user->id === Auth::id(), 'aktif' => (bool) $user->is_active]),
            ],
        ];
    }

    /**
     * Simpan pengguna baru (dibuat oleh admin, langsung terverifikasi).
     */
    public function simpanPengguna(): void
    {
        $action = app(CreateUserByAdmin::class);

        $validated = $this->validate($action->rules());

        $action($validated);

        $this->reset(['name', 'email', 'password', 'password_confirmation', 'role']);
        $this->resetValidation();

        $this->dispatch('modal-tambah-close');
        $this->dispatch('toast', type: 'sukses', message: 'Pengguna baru berhasil ditambahkan.');
    }

    public function bukaUbahPeran(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->userId = $user->id;
        $this->roleBaru = $user->role;
        $this->resetValidation();

        $this->dispatch('modal-peran-open');
    }

    public function simpanPeran(): void
    {
        $user = User::findOrFail($this->userId);

        app(UpdateUserRole::class)($user, ['role' => $this->roleBaru]);

        $this->dispatch('modal-peran-close');
        $this->dispatch('toast', type: 'sukses', message: 'Peran pengguna berhasil diperbarui.');
    }

    public function bukaResetPassword(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->userId = $user->id;
        $this->passwordBaru = '';
        $this->password_baru_confirmation = '';
        $this->resetValidation();

        $this->dispatch('modal-password-open');
    }

    public function simpanPasswordBaru(): void
    {
        $this->validate([
            'passwordBaru' => ['required', 'string', Password::default(), 'confirmed:password_baru_confirmation'],
        ]);

        $user = User::findOrFail($this->userId);

        app(ResetPasswordByAdmin::class)($user, ['password' => $this->passwordBaru]);

        $this->dispatch('modal-password-close');
        $this->dispatch('toast', type: 'sukses', message: 'Password pengguna berhasil direset.');
    }

    /**
     * Aktifkan / nonaktifkan akun. Akun nonaktif tidak bisa login.
     */
    public function toggleAktif(int $userId): void
    {
        $user = User::findOrFail($userId);

        $aktif = app(ToggleUserActive::class)($user);

        unset($this->rows);

        $this->dispatch(
            'toast',
            type: 'sukses',
            message: $aktif
                ? "Akun {$user->name} diaktifkan kembali."
                : "Akun {$user->name} dinonaktifkan dan tidak bisa login.",
        );
    }
}; ?>

<div>
    <x-ui.page-header title="Pengguna" description="Kelola akun guru dan admin yang mengakses panel.">
        <x-slot:actions>
            <x-ui.button variant="primary" x-on:click="$dispatch('modal-tambah-open')">
                <x-ui.icon.plus class="size-4" />
                Tambah Pengguna
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.data-table
        :rows="$this->rows"
        :columns="$this->columns"
        searchable
        search-placeholder="Cari nama atau email..."
        :sort-property="$sortBy"
        :sort-direction="$sortDir"
        empty-title="Belum ada pengguna"
        empty-message="Tambahkan akun guru atau admin untuk mulai memakai panel."
    />

    {{-- Modal tambah pengguna --}}
    <x-ui.modal name="modal-tambah" title="Tambah Pengguna Baru">
        <form wire:submit="simpanPengguna" class="space-y-4">
            <x-ui.input label="Nama Lengkap" name="name" wire:model="name" autocomplete="name" required autofocus />
            <x-ui.input label="Alamat Email" name="email" type="email" wire:model="email" autocomplete="email" required />
            <x-ui.input
                label="Kata Sandi"
                name="password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                help="Minimal 8 karakter. Di produksi, wajib mengandung huruf besar/kecil, angka, dan simbol."
                required
            />
            <x-ui.input
                label="Konfirmasi Kata Sandi"
                name="password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />
            <x-ui.select
                label="Peran"
                name="role"
                :options="['guru' => 'Guru', 'admin' => 'Admin']"
                wire:model="role"
                help="Guru hanya melihat kelas miliknya; admin melihat semua."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-tambah-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Pengguna</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal ubah peran --}}
    <x-ui.modal name="modal-peran" title="Ubah Peran Pengguna">
        <form wire:submit="simpanPeran" class="space-y-4">
            <x-ui.select
                label="Peran"
                name="roleBaru"
                :options="['guru' => 'Guru', 'admin' => 'Admin']"
                wire:model="roleBaru"
                help="Guru hanya melihat kelas miliknya; admin melihat semua."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-peran-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Peran</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal reset password --}}
    <x-ui.modal name="modal-password" title="Reset Password Pengguna">
        <form wire:submit="simpanPasswordBaru" class="space-y-4">
            <x-ui.input
                label="Password Baru"
                name="passwordBaru"
                type="password"
                wire:model="passwordBaru"
                autocomplete="new-password"
                help="Minimal 8 karakter. Di produksi, wajib mengandung huruf besar/kecil, angka, dan simbol."
                required
            />
            <x-ui.input
                label="Konfirmasi Password Baru"
                name="password_baru_confirmation"
                type="password"
                wire:model="password_baru_confirmation"
                autocomplete="new-password"
                required
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-password-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Password</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
