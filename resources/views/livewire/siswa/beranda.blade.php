<div class="space-y-6">
    @if (! $this->punyaKelompok)
        <x-ui.card>
            <x-ui.empty-state icon="users" variant="peringatan" title="Kamu belum masuk kelompok" message="Kamu belum masuk kelompok, hubungi gurumu agar ditambahkan ke kelompok sebelum bermain." />
        </x-ui.card>
    @else
        {{-- Header siswa & kelompok --}}
        <x-ui.card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <x-ui.avatar :name="$this->siswa->full_name" size="lg" />
                    <div>
                        <p class="text-lg font-semibold text-slate-800">{{ $this->siswa->full_name }}</p>
                        <p class="text-sm text-slate-500">{{ $this->team->team_name }} · {{ $this->kelasNama }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <div class="text-end">
                        <p class="text-[11px] uppercase tracking-wide text-slate-400">Skor Kelompok</p>
                        <p class="text-2xl font-bold text-primary-700">{{ $this->skorKelompok }}</p>
                    </div>
                    @if ($this->peringkat !== null)
                        <div class="text-end">
                            <p class="text-[11px] uppercase tracking-wide text-slate-400">Peringkat</p>
                            <p class="text-2xl font-bold text-slate-800">
                                #{{ $this->peringkat['posisi'] }}
                                <span class="text-sm font-normal text-slate-400">/ {{ $this->peringkat['total_kelompok'] }}</span>
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-5 border-t border-line pt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Anggota Kelompok</p>
                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($this->anggota as $anggota)
                        <x-ui.kartu-peran
                            :peran="$anggota->peran_aktif"
                            :nama="$anggota->full_name"
                            :milik-saya="$anggota->student_id === $this->siswa->student_id"
                        />
                    @endforeach
                </div>
            </div>
        </x-ui.card>

        {{-- Sorotan peran siswa sendiri --}}
        <x-ui.kartu-peran
            :peran="$this->peranSaya"
            :nama="$this->siswa->full_name"
            varian="utama"
            :milik-saya="true"
        />

        {{-- Kartu input Kode Misi --}}
        <x-ui.card>
            <form wire:submit="bukaMisi" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="kodeMisi" class="mb-1.5 block text-sm font-semibold text-slate-700">Kode Misi</label>
                    <input
                        id="kodeMisi"
                        type="text"
                        wire:model.live="kodeMisi"
                        placeholder="Masukkan kode dari Kartu Misi, contoh KOP-L1-BUDI"
                        autocomplete="off"
                        spellcheck="false"
                        style="text-transform: uppercase;"
                        class="block w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                    >
                </div>
                <x-ui.button variant="primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="bukaMisi">Buka Misi</span>
                    <span wire:loading wire:target="bukaMisi">Membuka...</span>
                </x-ui.button>
            </form>
        </x-ui.card>

        {{-- Empat kartu level --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($this->kartuLevel as $kartu)
                @php
                    $status = $kartu['status'];
                    $ikon = match ($status) {
                        'lulus' => 'circle-check',
                        'berjalan' => 'code',
                        'siap' => 'key',
                        default => 'lock',
                    };
                    $warnaIkon = match ($status) {
                        'lulus' => 'bg-primary-100 text-primary-700',
                        'berjalan' => 'bg-amber-50 text-amber-600',
                        'siap' => 'bg-primary-50 text-primary-600',
                        default => 'bg-slate-100 text-slate-400',
                    };
                @endphp
                <x-ui.card class="{{ $status === 'siap' ? 'ring-2 ring-primary-300' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ $kartu['level']->level_name }}{{ $kartu['level']->is_summative ? ' · Sumatif' : '' }}
                            </p>
                            <p class="mt-3 text-sm text-slate-600">{{ $kartu['keterangan'] }}</p>
                        </div>
                        <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $warnaIkon }}">
                            <x-dynamic-component :component="'ui.icon.'.$ikon" />
                        </span>
                    </div>

                    <div class="mt-4">
                        @if ($status === 'berjalan' && $kartu['misi'])
                            <a href="{{ route('siswa.misi', $kartu['misi']->mission_id) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:text-primary-800">
                                Lanjutkan
                                <x-ui.icon.chevron-right class="size-4" />
                            </a>
                        @elseif ($status === 'lulus')
                            <x-ui.badge variant="sukses">Lulus</x-ui.badge>
                        @elseif ($status === 'siap')
                            <x-ui.badge variant="sukses">Siap dibuka</x-ui.badge>
                        @else
                            <x-ui.badge variant="netral">Terkunci</x-ui.badge>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        {{-- Kartu Bug aktif --}}
        @if ($this->kartuBug->isNotEmpty())
            <div>
                <p class="mb-3 text-base font-semibold text-slate-800">Kartu Bug Aktif</p>
                <ul class="space-y-3">
                    @foreach ($this->kartuBug as $bug)
                        <li>
                            <a href="{{ route('siswa.bug', $bug->attempt_id) }}" wire:navigate class="block rounded-xl border border-line bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow-md">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800">{{ $bug->bugCard?->bug_title }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $bug->bugCard?->symptom_text }}</p>
                                    </div>
                                    <x-ui.badge variant="peringatan" class="shrink-0">+{{ $bug->bugCard?->points }} poin</x-ui.badge>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>