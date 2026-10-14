<x-layouts::auth-guest title="Lupa Kata Sandi">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Lupa kata sandi?
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Masukkan alamat email Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
        </p>

        @if (session('status'))
            <div class="mt-4">
                <x-ui.alert variant="sukses">{{ __('passwords.sent') }}</x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
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

            <x-ui.button variant="primary" type="submit" class="w-full">
                Kirim Tautan Reset
            </x-ui.button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="font-medium text-primary-700 transition hover:text-primary-800">
                Kembali ke halaman masuk
            </a>
        </p>
    </div>
</x-layouts::auth-guest>
