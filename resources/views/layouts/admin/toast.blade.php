{{--
    Notifikasi toast.

    Dari halaman Livewire:  $dispatch('toast', type: 'sukses', message: 'Berhasil disimpan.')
    Dari redirect biasa:    session()->flash('flash_toast', ['type' => 'bahaya', 'message' => '...']);

    Tipe: sukses | peringatan | bahaya | info  (hilang otomatis ~4,5 detik)
--}}
<div
    x-data="{
        toasts: [],
        tambah(payload) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: payload.type || 'sukses', message: payload.message || '' });
            setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== id); }, 4500);
        },
    }"
    x-init="
        @if (session('flash_toast'))
            tambah({{ json_encode(session('flash_toast')) }});
        @endif
    "
    x-on:toast.window="tambah($event.detail)"
    x-cloak
    class="pointer-events-none fixed right-4 top-4 z-[60] flex w-full max-w-sm flex-col gap-2"
    role="status"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border border-line bg-white p-4 shadow-lg shadow-slate-900/5"
        >
            <span class="mt-0.5 shrink-0">
                <span x-show="toast.type === 'sukses'"><x-ui.icon.circle-check class="text-primary-600" /></span>
                <span x-show="toast.type === 'peringatan'"><x-ui.icon.alert-triangle class="text-amber-500" /></span>
                <span x-show="toast.type === 'bahaya'"><x-ui.icon.circle-x class="text-rose-600" /></span>
                <span x-show="toast.type === 'info' || ! toast.type"><x-ui.icon.info-circle class="text-sky-500" /></span>
            </span>
            <p class="flex-1 text-sm text-slate-700" x-text="toast.message"></p>
        </div>
    </template>
</div>
