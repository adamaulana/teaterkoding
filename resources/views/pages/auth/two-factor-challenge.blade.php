<x-layouts::auth-guest title="Tantangan Dua Faktor">
    <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-800">
            Dua faktor
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Masukkan kode dari aplikasi autentikator Anda, atau gunakan kode pemulihan.
        </p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-6 space-y-5">
            @csrf

            <x-ui.input
                label="Kode Autentikator"
                name="code"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="one-time-code"
                placeholder="Contoh: 123456"
                autofocus
            />

            <div class="flex items-center gap-3">
                <span class="h-px flex-1 bg-line"></span>
                <span class="text-xs uppercase tracking-wider text-slate-400">atau</span>
                <span class="h-px flex-1 bg-line"></span>
            </div>

            <x-ui.input
                label="Kode Pemulihan"
                name="recovery_code"
                placeholder="Masukkan kode pemulihan"
            />

            <x-ui.button variant="primary" type="submit" class="w-full">
                Verifikasi
            </x-ui.button>
        </form>
    </div>
</x-layouts::auth-guest>
