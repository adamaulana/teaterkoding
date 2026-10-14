<?php

use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\ScoreEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Skor & Peringkat — dua papan peringkat dari ledger score_entries.
 *
 *   Tab Kelompok : total poin per kelompok + rincian per jenis sumber.
 *   Tab Individu : total poin per siswa (baris skor dengan student_id).
 *
 * Ekspor CSV memakai BOM UTF-8 (agar Excel membaca huruf beraksen benar)
 * dan menetralkan sel yang diawali karakter berbahaya (CSV injection).
 */
new #[Layout('layouts.admin'), Title('Skor & Peringkat')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    public string $activeTab = 'kelompok';

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->kelasTerpilih();
    }

    private function kelasTerpilih(): ?SchoolClass
    {
        if ($this->kelasId === null) {
            return null;
        }

        $kelas = SchoolClass::findOrFail($this->kelasId);

        abort_unless(auth()->user()->can('view', $kelas), 403);

        return $kelas;
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();

        unset($this->peringkatKelompok, $this->peringkatIndividu, $this->podium, $this->ringkasan, $this->juara);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiKelas(): array
    {
        return SchoolClass::query()
            ->visibleTo(auth()->user())
            ->orderBy('class_name')
            ->pluck('class_name', 'class_id')
            ->all();
    }

    #[Computed]
    public function totalLevel(): int
    {
        return (int) (Level::query()->max('level_number') ?? 0);
    }

    /**
     * Label tiap jenis sumber skor.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function labelSumber(): array
    {
        return [
            'mission_result' => 'Hasil Misi',
            'plan_bonus' => 'Bonus Rencana',
            'bug_bonus' => 'Bonus Bug',
            'hint_penalty' => 'Penalti Petunjuk',
            'first_try_bonus' => 'Bonus Sekali Coba',
        ];
    }

    /**
     * Peringkat kelompok: total poin + rincian per jenis sumber.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function peringkatKelompok(): Collection
    {
        if ($this->kelasId === null) {
            return collect();
        }

        $teams = Team::query()
            ->where('class_id', $this->kelasId)
            ->orderBy('team_name')
            ->get();

        if ($teams->isEmpty()) {
            return collect();
        }

        $rincian = ScoreEntry::query()
            ->whereIn('team_id', $teams->pluck('team_id')->all())
            ->selectRaw('team_id, source_type, SUM(points) as total')
            ->groupBy('team_id', 'source_type')
            ->get()
            ->groupBy('team_id');

        $labelSumber = $this->labelSumber;

        $hasil = $teams->map(function (Team $team) use ($rincian, $labelSumber) {
            $baris = $rincian->get($team->team_id, collect());

            $perSumber = [];

            foreach (array_keys($labelSumber) as $sumber) {
                $cocok = $baris->firstWhere('source_type', $sumber);
                $perSumber[$sumber] = $cocok !== null ? (int) $cocok->total : 0;
            }

            return [
                'id' => (int) $team->team_id,
                'nama' => (string) $team->team_name,
                'total' => array_sum($perSumber),
                'rincian' => $perSumber,
            ];
        })
            ->sortByDesc('total')
            ->values();

        return $this->beriPeringkat($hasil);
    }

    /**
     * Peringkat individu: poin pribadi + jumlah fungsi yang dipegang.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function peringkatIndividu(): Collection
    {
        if ($this->kelasId === null) {
            return collect();
        }

        $studentIds = Student::query()
            ->where('class_id', $this->kelasId)
            ->pluck('student_id')
            ->all();

        if ($studentIds === []) {
            return collect();
        }

        $poin = ScoreEntry::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, SUM(points) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $fungsi = FunctionOwnership::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COUNT(*) as jumlah')
            ->groupBy('student_id')
            ->pluck('jumlah', 'student_id');

        $hasil = Student::query()
            ->whereIn('student_id', $studentIds)
            ->with('teams')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $siswa) => [
                'id' => (int) $siswa->student_id,
                'nama' => (string) $siswa->full_name,
                'nomor' => (string) ($siswa->student_no ?? '—'),
                'tim' => (string) ($siswa->teams->first()?->team_name ?? '—'),
                'total' => (int) ($poin[$siswa->student_id] ?? 0),
                'fungsi' => (int) ($fungsi[$siswa->student_id] ?? 0),
            ])
            ->sortByDesc('total')
            ->values();

        return $this->beriPeringkat($hasil);
    }

    /**
     * Beri nomor peringkat; skor yang sama mendapat peringkat yang sama.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function beriPeringkat(Collection $data): Collection
    {
        $peringkat = 0;
        $sebelumnya = null;

        return $data->map(function (array $item, int $index) use (&$peringkat, &$sebelumnya) {
            if ($sebelumnya === null || $item['total'] !== $sebelumnya) {
                $peringkat = $index + 1;
                $sebelumnya = $item['total'];
            }

            $item['peringkat'] = $peringkat;

            return $item;
        });
    }

    /**
     * Tiga besar untuk podium (mengikuti tab aktif).
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function podium(): Collection
    {
        return $this->activeTab === 'individu'
            ? $this->peringkatIndividu->take(3)->values()
            : $this->peringkatKelompok->take(3)->values();
    }

    /**
     * @return array<string, int|float>
     */
    #[Computed]
    public function ringkasan(): array
    {
        $data = $this->activeTab === 'individu' ? $this->peringkatIndividu : $this->peringkatKelompok;

        return [
            'jumlah' => $data->count(),
            'total_poin' => (int) $data->sum('total'),
            'tertinggi' => (int) ($data->max('total') ?? 0),
            'rata' => $data->isEmpty() ? 0.0 : round((float) $data->avg('total'), 1),
        ];
    }

    /**
     * Netralkan sel CSV: beri awalan kutip bila dimulai karakter berbahaya.
     *
     * Excel/Sheets memperlakukan sel yang diawali =, +, -, @ sebagai rumus.
     */
    public function amanCsv(mixed $nilai): string
    {
        $teks = (string) $nilai;
        $berbahaya = config('teater.csv_injection_chars', ['=', '+', '-', '@']);

        if ($teks !== '' && in_array($teks[0], $berbahaya, true)) {
            return (string) config('teater.csv_injection_prefix', "'").$teks;
        }

        return $teks;
    }

    /**
     * Ekspor peringkat tab aktif ke CSV (BOM UTF-8 + proteksi injeksi).
     */
    public function ekspor(): StreamedResponse
    {
        $this->kelasTerpilih();

        $individu = $this->activeTab === 'individu';
        $data = $individu ? $this->peringkatIndividu : $this->peringkatKelompok;
        $namaKelas = $this->kelasId !== null ? ($this->opsiKelas[$this->kelasId] ?? 'kelas') : 'kelas';
        $namaBerkas = 'peringkat-'.($individu ? 'individu' : 'kelompok').'-'.str()->slug($namaKelas).'.csv';

        return response()->streamDownload(function () use ($data, $individu): void {
            $keluaran = fopen('php://output', 'w');

            if ($keluaran === false) {
                return;
            }

            // BOM UTF-8 agar Excel membaca huruf beraksen dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            if ($individu) {
                fputcsv($keluaran, ['Peringkat', 'Nama', 'Nomor', 'Kelompok', 'Total Poin', 'Fungsi Dipegang']);

                foreach ($data as $baris) {
                    fputcsv($keluaran, [
                        $baris['peringkat'],
                        $this->amanCsv($baris['nama']),
                        $this->amanCsv($baris['nomor']),
                        $this->amanCsv($baris['tim']),
                        $baris['total'],
                        $baris['fungsi'],
                    ]);
                }
            } else {
                fputcsv($keluaran, array_merge(
                    ['Peringkat', 'Kelompok'],
                    array_values($this->labelSumber),
                    ['Total Poin'],
                ));

                foreach ($data as $baris) {
                    fputcsv($keluaran, array_merge(
                        [$baris['peringkat'], $this->amanCsv($baris['nama'])],
                        array_map(fn (int $nilai) => $nilai, array_values($baris['rincian'])),
                        [$baris['total']],
                    ));
                }
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

<div>
    <x-ui.page-header
        title="Skor & Peringkat"
        description="Papan peringkat kelompok dan individu dari ledger poin permainan."
    >
        <x-slot:actions>
            <x-ui.button variant="outline" wire:click="ekspor">
                <x-ui.icon.download class="size-4" />
                Ekspor CSV
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select
                label="Kelas"
                name="kelasId"
                wire:model.live="kelasId"
                :options="$this->opsiKelas"
                placeholder="Pilih kelas"
            />
            <div class="sm:col-span-2 flex items-end text-xs text-slate-400">
                Poin dihitung dari ledger skor: hasil misi, bonus rencana, bonus bug, penalti
                petunjuk, dan bonus sekali coba.
            </div>
        </div>
    </x-ui.card>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk melihat peringkat."
            />
        </x-ui.card>
    @else
        <x-ui.tabs
            :tabs="[
                ['key' => 'kelompok', 'label' => 'Peringkat Kelompok', 'count' => $this->peringkatKelompok->count()],
                ['key' => 'individu', 'label' => 'Peringkat Individu', 'count' => $this->peringkatIndividu->count()],
            ]"
            :active="$activeTab"
        />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Jumlah Peserta" :value="$this->ringkasan['jumlah']" icon="users" />
            <x-ui.stat-card label="Total Poin" :value="$this->ringkasan['total_poin']" icon="star" />
            <x-ui.stat-card label="Poin Tertinggi" :value="$this->ringkasan['tertinggi']" icon="trophy" />
            <x-ui.stat-card label="Rata-rata Poin" :value="$this->ringkasan['rata']" icon="chart-bar" />
        </div>

        {{-- Podium tiga besar --}}
        @if ($this->podium->isNotEmpty())
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ($this->podium as $baris)
                    <x-ui.card class="relative overflow-hidden">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                    Peringkat {{ $baris['peringkat'] }}
                                </p>
                                <p class="mt-1 truncate text-base font-semibold text-slate-800">{{ $baris['nama'] }}</p>
                                @if ($activeTab === 'individu')
                                    <p class="truncate text-xs text-slate-400">{{ $baris['tim'] }}</p>
                                @endif
                            </div>
                            <span @class([
                                'grid size-11 shrink-0 place-items-center rounded-xl',
                                'bg-amber-100 text-amber-700' => $baris['peringkat'] === 1,
                                'bg-slate-100 text-slate-600' => $baris['peringkat'] === 2,
                                'bg-orange-100 text-orange-700' => $baris['peringkat'] === 3,
                            ])>
                                <x-ui.icon.medal class="size-5" />
                            </span>
                        </div>

                        <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-800">
                            {{ $baris['total'] }}
                            <span class="text-sm font-normal text-slate-400">poin</span>
                        </p>
                    </x-ui.card>
                @endforeach
            </div>
        @endif



        {{-- Tabel peringkat --}}
        <x-ui.card :padding="false" class="mt-6">
            <div class="border-b border-line px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">
                    {{ $activeTab === 'individu' ? 'Peringkat Individu' : 'Peringkat Kelompok' }}
                </p>
            </div>

            @php
                $data = $activeTab === 'individu' ? $this->peringkatIndividu : $this->peringkatKelompok;
            @endphp

            @if ($data->isEmpty())
                <x-ui.empty-state
                    icon="trophy"
                    title="Belum ada poin"
                    message="Peringkat akan tampil setelah kelompok mengirim kode dan mengumpulkan poin."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-page/60">
                            <tr>
                                <th class="w-20 px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Peringkat</th>
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    {{ $activeTab === 'individu' ? 'Nama' : 'Kelompok' }}
                                </th>

                                @if ($activeTab === 'individu')
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Nomor</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Kelompok</th>
                                    <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Fungsi</th>
                                @else
                                    @foreach ($this->labelSumber as $label)
                                        <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</th>
                                    @endforeach
                                @endif

                                <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-white">
                            @foreach ($data as $baris)
                                <tr wire:key="rank-{{ $baris['id'] }}" class="transition hover:bg-page/70">
                                    <td class="px-4 py-3 align-middle">
                                        <span @class([
                                            'inline-grid size-7 place-items-center rounded-full text-xs font-semibold',
                                            'bg-amber-100 text-amber-700' => $baris['peringkat'] === 1,
                                            'bg-slate-100 text-slate-600' => $baris['peringkat'] !== 1,
                                        ])>{{ $baris['peringkat'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-middle font-medium text-slate-800">{{ $baris['nama'] }}</td>

                                    @if ($activeTab === 'individu')
                                        <td class="px-4 py-3 align-middle text-slate-500">{{ $baris['nomor'] }}</td>
                                        <td class="px-4 py-3 align-middle text-slate-500">{{ $baris['tim'] }}</td>
                                        <td class="px-4 py-3 align-middle text-slate-500">{{ $baris['fungsi'] }} fungsi</td>
                                    @else
                                        @foreach ($baris['rincian'] as $nilai)
                                            <td @class([
                                                'px-4 py-3 text-end align-middle tabular-nums',
                                                'text-rose-600' => $nilai < 0,
                                                'text-slate-500' => $nilai >= 0,
                                            ])>{{ $nilai }}</td>
                                        @endforeach
                                    @endif

                                    <td class="px-4 py-3 text-end align-middle text-base font-semibold tabular-nums text-slate-800">
                                        {{ $baris['total'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
