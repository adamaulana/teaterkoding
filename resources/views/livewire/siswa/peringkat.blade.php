<div class="space-y-6">
    @if (! $this->ditampilkan)
        <x-ui.card>
            <x-ui.empty-state icon="trophy" title="Peringkat disembunyikan" message="Papan peringkat sedang tidak ditampilkan." />
        </x-ui.card>
    @elseif ($this->sumatifTerbuka)
        <x-ui.card>
            <x-ui.empty-state icon="trophy" title="Peringkat disembunyikan sementara" message="Papan peringkat ditutup selama level sumatif masih terbuka." />
        </x-ui.card>
    @else
        <x-ui.card :padding="false">
            <div class="border-b border-line px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">Peringkat Kelompok</p>
                <p class="text-xs text-slate-400">{{ $this->team->team_name }} · total skor kelompok di kelasmu.</p>
            </div>

            <ul class="divide-y divide-line">
                @foreach ($this->papan as $baris)
                    <li @class([
                        'flex items-center gap-4 px-4 py-3',
                        'bg-primary-50' => $baris['sendiri'],
                    ])>
                        <span @class([
                            'grid size-9 shrink-0 place-items-center rounded-full text-sm font-bold',
                            'bg-amber-100 text-amber-700' => $baris['peringkat'] === 1,
                            'bg-slate-100 text-slate-600' => $baris['peringkat'] !== 1,
                        ])>{{ $baris['peringkat'] }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-800">
                                {{ $baris['team_name'] }}
                                @if ($baris['sendiri'])
                                    <span class="text-xs text-primary-600">(kelompokmu)</span>
                                @endif
                            </p>
                        </div>

                        <span class="text-base font-bold tabular-nums text-primary-700">{{ $baris['skor'] }}</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>