<x-layouts::auth-guest title="Verifikasi Email">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Verifikasi alamat email
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Silakan klik tautan verifikasi yang kami kirim ke alamat email Anda sebelum melanjutkan ke panel.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="mt-4">
                <x-ui.alert variant="sukses">
                    Tautan verifikasi baru telah dikirim ke alamat email Anda.
                </x-ui.alert>
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf

            <x-ui.button variant="primary" type="submit" class="w-full">
                Kirim Ulang Tautan Verifikasi
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
