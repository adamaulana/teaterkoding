{{--
    Tab Kepemilikan Fungsi (hanya untuk level sumatif).
    Fungsi DITURUNKAN otomatis dari kasus uji (nama sebelum tanda kurung).
    Data ini terikat kelas (lewat team_id), jadi mengikuti pembatasan kelas guru.
--}}
@php
    $fungsi = $this->fungsiMisi;
@endphp

@if ($fungsi === [])
    <x-ui.card :padding="false">
        <x-ui.empty-state
            icon="math-function"
            title="Belum ada fungsi yang bisa dibagi"
            message="Kepemilikan fungsi diturunkan dari kasus uji bertipe fungsi. Tambahkan kasus uji dengan target seperti hitung_total(100) pada tab Kasus Uji."
        >
            <x-slot:actions>
                <x-ui.button variant="primary" wire:click="$set('activeTab', 'kasus')">
                    <x-ui.icon.target class="size-4" />
                    Buka Tab Kasus Uji
                </x-ui.button>
            </x-slot:actions>
        </x-ui.empty-state>
    </x-ui.card>
@else
    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.select
                label="Kelas"
                name="fungsiKelasId"
                :options="$this->opsiKelas"
                placeholder="— Pilih kelas —"
                wire:model.live="fungsiKelasId"
                help="Hanya kelas milik Anda (admin: semua kelas)."
            />
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
            <span class="text-sm text-slate-500">Fungsi terdeteksi:</span>
            @foreach ($fungsi as $satu)
                <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">{{ $satu }}</code>
            @endforeach
        </div>
    </x-ui.card>

    @if ($fungsiKelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="users-group"
                title="Pilih kelas terlebih dahulu"
                message="Kepemilikan fungsi ditetapkan per kelompok, sehingga perlu memilih kelas."
            />
        </x-ui.card>
    @elseif ($this->kelompokFungsi->isEmpty())
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="users-group"
                title="Belum ada kelompok di kelas ini"
                message="Bentuk kelompok pada halaman Kelompok terlebih dahulu."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" :href="route('admin.kelompok', ['kelas' => $fungsiKelasId])" wire:navigate>
                        <x-ui.icon.plus class="size-4" />
                        Buka Halaman Kelompok
                    </x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div class="mb-4 flex flex-wrap justify-end gap-2">
            <x-ui.button variant="outline" wire:click="bagiOtomatis">
                <x-ui.icon.arrows-shuffle class="size-4" />
                Bagi Otomatis
            </x-ui.button>
            <x-ui.button variant="primary" wire:click="simpanKepemilikan">
                <x-ui.icon.check class="size-4" />
                Simpan Semua
            </x-ui.button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->kelompokFungsi as $team)
                @php
                    $status = $this->statusFungsiKelompok($team->team_id);
                    $badge = $status === 'lengkap' ? ['sukses', 'Lengkap'] : ['peringatan', 'Belum lengkap'];
                @endphp

                <x-ui.card wire:key="fungsi-{{ $team->team_id }}">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate font-semibold text-slate-800">{{ $team->team_name }}</p>
                        <x-ui.badge :variant="$badge[0]">{{ $badge[1] }}</x-ui.badge>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($fungsi as $namaFungsi)
                            <div class="flex items-center gap-3">
                                <code class="min-w-0 flex-1 truncate rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700">{{ $namaFungsi }}</code>

                                <select
                                    wire:model="pemilik.{{ $team->team_id }}.{{ $namaFungsi }}"
                                    class="rounded-lg border-line py-1.5 text-sm text-slate-700 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                                >
                                    <option value="">— Belum diatur —</option>
                                    @foreach ($team->teamMembers as $member)
                                        <option value="{{ $member->student_id }}">{{ $member->student?->full_name ?? '—' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
@endif
