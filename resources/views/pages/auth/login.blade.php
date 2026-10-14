<x-layouts::auth-guest title="Masuk">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Selamat datang kembali
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Masuk menggunakan akun guru atau admin Anda.
        </p>

        @if (session('status'))
            <div class="mt-4">
                <x-ui.alert variant="info">{{ session('status') }}</x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
            @csrf

            <x-ui.input
                label="Alamat Email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                placeholder="nama@sekolah.sch.id"
                autofocus
                required
            />

            <x-ui.input
                label="Kata Sandi"
                name="password"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                required
            />

            <div class="flex items-center justify-between gap-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        @checked(old('remember'))
                        class="size-4 rounded border-line text-primary-600 focus:ring-primary-500/40"
                    >
                    Ingat saya
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-700 transition hover:text-primary-800">
                        Lupa kata sandi?
                    </a>
                @endif
            </div>

            <x-ui.button variant="primary" type="submit" class="w-full">
                Masuk
            </x-ui.button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-400">
            Akun dibuat oleh admin sekolah. Hubungi admin jika Anda belum memiliki akun.
        </p>
    </div>
</x-layouts::auth-guest>
