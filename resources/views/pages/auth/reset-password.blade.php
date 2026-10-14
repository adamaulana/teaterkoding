<x-layouts::auth-guest title="Atur Ulang Kata Sandi">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Atur ulang kata sandi
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Masukkan kata sandi baru untuk akun Anda.
        </p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

            <x-ui.input
                label="Alamat Email"
                name="email"
                type="email"
                value="{{ old('email', $email ?? '') }}"
                autocomplete="email"
                autofocus
                required
            />

            <x-ui.input
                label="Kata Sandi Baru"
                name="password"
                type="password"
                autocomplete="new-password"
                required
            />

            <x-ui.input
                label="Konfirmasi Kata Sandi"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            />

            <x-ui.button variant="primary" type="submit" class="w-full">
                Simpan Kata Sandi Baru
            </x-ui.button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-medium text-primary-700 transition hover:text-primary-800">
                Kembali ke halaman masuk
            </a>
        </p>
    </div>
</x-layouts::auth-guest>
