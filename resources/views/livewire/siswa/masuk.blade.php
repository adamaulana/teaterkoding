<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        {{-- Logo & nama aplikasi --}}
        <div class="mb-8 flex flex-col items-center text-center">
            <span class="grid size-16 place-items-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-600/30">
                <x-ui.icon.presentation class="size-8" />
            </span>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-800">Teater Koding</h1>
            <p class="mt-1 text-sm text-slate-500">Masuk dengan kode akses dari gurumu</p>
        </div>

        <div class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-8">
            <form wire:submit="masuk" class="space-y-5">
                <div>
                    <label for="kode" class="mb-2 block text-sm font-semibold text-slate-700">Kode Akses</label>
                    <input
                        id="kode"
                        type="text"
                        inputmode="text"
                        autocomplete="off"
                        spellcheck="false"
                        wire:model.live="kode"
                        placeholder="XXXX-XXXX"
                        autofocus
                        style="text-transform: uppercase; letter-spacing: 0.15em;"
                        class="block w-full rounded-xl border border-line bg-white px-4 py-3 text-center text-lg font-semibold tracking-widest text-slate-800 shadow-sm transition placeholder:text-slate-300 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                    >
                    @if ($pesan !== '')
                        <p class="mt-2 text-sm text-rose-600">{{ $pesan }}</p>
                    @endif
                </div>

                <x-ui.button variant="primary" type="submit" class="w-full !py-3 text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="masuk">Masuk</span>
                    <span wire:loading wire:target="masuk">Memeriksa...</span>
                </x-ui.button>
            </form>

            <p class="mt-5 text-center text-xs text-slate-400">
                Kode akses terdiri dari 8 karakter, contoh {{ app(\App\Services\AccessCodeService::class)->display('ABCD2345') }}.
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            Kasir, Coder, &amp; penampil teater koding koperasi.
        </p>
    </div>
</div>