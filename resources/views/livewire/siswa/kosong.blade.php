<div class="space-y-4">
    <x-ui.card>
        <x-ui.empty-state icon="info-circle" title="Segera hadir" message="Bagian ini akan dirampungkan pada tahap berikutnya.">
            <x-slot:actions>
                <a href="{{ route('siswa.beranda') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-primary-600/20 transition hover:bg-primary-700">
                    <x-ui.icon.home class="size-4" />
                    Kembali ke Beranda
                </a>
            </x-slot:actions>
        </x-ui.empty-state>
    </x-ui.card>
</div>