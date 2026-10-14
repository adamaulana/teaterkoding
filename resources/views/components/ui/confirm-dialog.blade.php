@props([
    'action',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin melanjutkan aksi ini?',
    'property' => null,
    'confirmLabel' => 'Ya, lanjutkan',
    'event' => 'konfirmasi-buka',
])

{{--
    Dialog konfirmasi (mis. untuk hapus data).

    Pemicu dari halaman Livewire:
        $this->pesanHapus = 'Yakin hapus "X RPL 1"?';
        $dispatch('konfirmasi-buka');

    Bila satu halaman memakai lebih dari satu dialog, beri `event` unik
    (mis. event="konfirmasi-kasus-buka") agar tidak saling membuka.

    Setelah tombol utama diklik, method $action dipanggil lewat $wire.call().
--}}
<div
    x-data="{ open: false }"
    x-on:{{ $event }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="alertdialog"
    aria-modal="true"
>
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" x-on:click="open = false"></div>

    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div
            x-transition:enter="transition duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-full max-w-md rounded-xl border border-line bg-white p-6 shadow-xl"
        >
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-rose-50 text-rose-600">
                    <x-ui.icon.alert-triangle />
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-slate-800">{{ $title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500">
                        @if (filled($property))
                            <span x-text="$wire.get('{{ $property }}')"></span>
                        @else
                            {{ $message }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-ui.button variant="outline" x-on:click="open = false">Batal</x-ui.button>
                <x-ui.button variant="danger" x-on:click="open = false; $wire.call('{{ $action }}')">
                    {{ $confirmLabel }}
                </x-ui.button>
            </div>
        </div>
    </div>
</div>
