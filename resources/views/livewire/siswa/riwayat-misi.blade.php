<div class="space-y-6">
    <x-ui.card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800">Riwayat Submission</h1>
                <p class="text-sm text-slate-500">{{ $this->misi->level->level_name }} · {{ $this->team->team_name }}</p>
            </div>
            <a href="{{ route('siswa.misi', $this->misi->mission_id) }}" wire:navigate class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-page">
                <x-ui.icon.chevron-left class="size-4" />
                Kembali ke Misi
            </a>
        </div>
    </x-ui.card>

    <x-ui.card :padding="false">
        @if ($this->riwayat->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-400">Belum ada submission.</p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->riwayat as $sub)
                    <li class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                Percobaan #{{ $sub->attempt_no }}
                            </p>
                            <p class="truncate text-xs text-slate-400">
                                {{ $sub->submitted_at?->format('d/m H:i') }} · {{ $sub->submittedBy?->full_name ?? 'Siswa' }}
                            </p>
                        </div>
                        @if ($sub->status === 'menunggu')
                            <x-ui.badge variant="info">Menunggu penilaian</x-ui.badge>
                        @elseif ($sub->status === 'menunggu_verifikasi')
                            <x-ui.badge variant="netral">Menunggu verifikasi</x-ui.badge>
                        @elseif ($sub->status === 'dinilai')
                            <x-ui.badge :variant="$sub->all_passed ? 'sukses' : 'peringatan'">{{ $sub->all_passed ? 'Lulus' : 'Gagal' }}</x-ui.badge>
                        @elseif ($sub->status === 'galat')
                            <x-ui.badge variant="bahaya">Galat</x-ui.badge>
                        @else
                            <x-ui.badge variant="peringatan">Waktu habis</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>