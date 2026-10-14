{{--
    Tab Kasus Uji: tabel kasus uji + CRUD + tambah banyak sekaligus.
    Kasus uji = bank jawaban rahasia (jangan pernah dikirim ke sisi siswa).
--}}
@php
    $validator = new \App\Actions\ValidasiKasusUji;
    $adaSubmission = \App\Models\Submission::query()->where('mission_id', $misi->mission_id)->exists();
@endphp

@if ($adaSubmission)
    <x-ui.alert variant="peringatan" class="mb-4">
        Hasil submission lama merujuk kasus uji ini. Mengubah atau menghapus kasus uji dapat membuat hasil penilaian lama tidak lagi relevan.
    </x-ui.alert>
@endif

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <p class="text-sm text-slate-500">{{ $this->kasusUji->count() }} kasus uji, diurutkan berdasarkan ID.</p>

    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="outline" wire:click="bukaMassal">
            <x-ui.icon.clipboard-check class="size-4" />
            Tambah Banyak Sekaligus
        </x-ui.button>
        <x-ui.button variant="primary" wire:click="bukaTambahKasus">
            <x-ui.icon.plus class="size-4" />
            Tambah Kasus Uji
        </x-ui.button>
    </div>
</div>

@if ($this->kasusUji->isEmpty())
    <x-ui.card :padding="false">
        <x-ui.empty-state
            icon="target"
            title="Belum ada kasus uji"
            message="Tambahkan kasus uji untuk menentukan target pengecekan dan nilai harapan misi ini."
        />
    </x-ui.card>
@else
    <x-ui.card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="bg-page/60">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Label</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Input Awal</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Target</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Diharapkan</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Tol.</th>
                        <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line bg-white">
                    @foreach ($this->kasusUji as $kasus)
                        <tr wire:key="kasus-{{ $kasus->test_case_id }}" class="transition hover:bg-page/70">
                            <td class="px-4 py-3 align-middle font-medium text-slate-800">{{ $kasus->label }}</td>
                            <td class="px-4 py-3 align-middle">
                                @if (filled($kasus->setup_code))
                                    <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ $kasus->setup_code }}</code>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <code class="rounded bg-sky-50 px-2 py-0.5 font-mono text-xs text-sky-700">{{ $kasus->check_target }}</code>
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <code class="rounded bg-primary-50 px-2 py-0.5 font-mono text-xs text-primary-700">{{ $kasus->expected_value }}</code>
                            </td>
                            <td class="px-4 py-3 align-middle text-slate-500">{{ rtrim(rtrim((string) $kasus->tolerance, '0'), '.') ?: '0' }}</td>
                            <td class="px-4 py-3 align-middle">
                                @if ($validator->jenis($kasus->check_target) === \App\Actions\ValidasiKasusUji::JENIS_FUNGSI)
                                    <x-ui.badge variant="info">Fungsi</x-ui.badge>
                                @else
                                    <x-ui.badge variant="netral">Variabel</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-middle text-end">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button" wire:click="bukaUbahKasus({{ $kasus->test_case_id }})" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" title="Ubah">
                                        <x-ui.icon.pencil class="size-4" />
                                    </button>
                                    <button type="button" wire:click="mintaHapusKasus({{ $kasus->test_case_id }})" class="rounded-lg p-1.5 text-rose-400 transition hover:bg-rose-50 hover:text-rose-600" title="Hapus">
                                        <x-ui.icon.trash class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endif
