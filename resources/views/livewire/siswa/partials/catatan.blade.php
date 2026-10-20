@php
    $jenis = \App\Models\Reflection::jenis();
@endphp

<x-ui.card>
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Catatan</h2>
        <p class="text-sm text-slate-500">Tulis catatan sesuai peranmu. Bisa diedit sampai level ditutup guru.</p>
    </div>

    <div class="mt-5 space-y-4">
        @foreach ($jenis as $type => $meta)
            @php
                $peran = $meta['peran'];
                // Catatan peran hanya ditampilkan untuk siswa dengan peran tsb (atau bila penegakan mati).
                $relevan = $peran === null || ! $this->penegakanAktif || $this->peranSaya === $peran;
                $ada = $this->refleksiSaya->get($type);
            @endphp

            @if ($relevan)
                <div class="rounded-xl border border-line bg-white p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $meta['label'] }}</p>
                            <p class="text-xs text-slate-400">{{ $meta['pertanyaan'] }}</p>
                        </div>
                        @if ($peran)
                            <x-ui.badge variant="info">{{ $peran }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="netral">Semua anggota</x-ui.badge>
                        @endif
                    </div>

                    <textarea
                        wire:model="body"
                        rows="4"
                        class="mt-3 block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                        placeholder="Tulis catatanmu di sini (20-1000 karakter)..."
                    ></textarea>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <p class="text-xs text-slate-400">{{ $ada ? 'Sudah ada catatan.' : 'Belum ada catatan.' }}</p>
                        <x-ui.button variant="primary" size="sm" wire:click="simpanCatatan('{{ $type }}')">
                            <x-ui.icon.check class="size-4" />
                            Simpan
                        </x-ui.button>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="mt-5 flex justify-between gap-3">
        <x-ui.button variant="outline" wire:click="$set('langkah', 'hasil')">
            <x-ui.icon.chevron-left class="size-4" />
            Hasil
        </x-ui.button>
        <a href="{{ route('siswa.beranda') }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-page">
            <x-ui.icon.home class="size-4" />
            Beranda
        </a>
    </div>
</x-ui.card>