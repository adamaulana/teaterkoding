<x-layouts::auth-guest title="Konfirmasi Kata Sandi">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Konfirmasi kata sandi
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Ini adalah area aman. Masukkan kata sandi Anda untuk melanjutkan.
        </p>

        <form method="POST" action="{{ route('password.confirm.store') }}" class="mt-6 space-y-5">
            @csrf

            <x-ui.input
                label="Kata Sandi"
                name="password"
                type="password"
                autocomplete="current-password"
                autofocus
                required
            />

            <x-ui.button variant="primary" type="submit" class="w-full">
                Konfirmasi
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf

            <button type="submit" class="text-sm font-medium text-slate-500 transition hover:text-slate-700">
                Keluar
            </button>
        </form>
    </div>
</x-layouts::auth-guest>
