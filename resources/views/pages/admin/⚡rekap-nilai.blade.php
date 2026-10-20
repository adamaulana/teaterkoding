<?php

use App\Models\GradingSetting;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SumativeScore;
use App\Services\SumativeScoreCalculator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap Nilai Sumatif — nilai akhir per siswa pada misi level sumatif.
 *
 * Guru dapat mengisi/mengubah nilai manual (refleksi & observasi) lewat modal;
 * komponen lain dihitung service SumativeScoreCalculator.
 *
 * Baris ditandai "Sementara" selama ada komponen manual yang masih kosong.
 */
new #[Layout('layouts.admin'), Title('Rekap Nilai Sumatif')]
class extends Component
{
    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    #[Url(as: 'misi', nullable: true)]
    public ?int $misiId = null;

    public ?int $suntingStudentId = null;

    public ?string $suntingRefleksi = null;

    public ?string $suntingObservasi = null;

    public string $suntingCatatan = '';

    public function mount(): void
    {
        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo(auth()->user())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->kelasTerpilih();

        if ($this->misiId === null) {
            $this->misiId = $this->daftarMisi->first()?->mission_id;
        }

        if ($this->misiId !== null) {
            $this->pastikanMisiSumatif();
        }
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

    /**
     * Misi yang dipilih wajib berada pada level sumatif.
     */
    private function pastikanMisiSumatif(): Mission
    {
        $misi = Mission::query()->with('level')->findOrFail($this->misiId);

        abort_unless((bool) $misi->level?->is_summative, 404);

        return $misi;
    }

    public function updatedKelasId(): void
    {
        $this->kelasTerpilih();
        $this->misiId = null;
        $this->suntingStudentId = null;

        unset($this->daftarMisi, $this->misiTerpilih, $this->siswa, $this->nilai, $this->bobot, $this->ringkasan);
    }

    public function updatedMisiId(): void
    {
        if ($this->misiId !== null) {
            $this->pastikanMisiSumatif();
        }

        $this->suntingStudentId = null;

        unset($this->misiTerpilih, $this->nilai, $this->ringkasan);
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

    /**
     * @return Collection<int, Mission>
     */
    #[Computed]
    public function daftarMisi(): Collection
    {
        return Mission::query()
            ->whereHas('level', fn ($q) => $q->where('is_summative', true))
            ->with('level')
            ->orderBy('mission_id')
            ->get();
    }

    #[Computed]
    public function misiTerpilih(): ?Mission
    {
        if ($this->misiId === null) {
            return null;
        }

        return Mission::query()->with('level')->find($this->misiId);
    }

    /**
     * Bobot efektif kelas (baris tersimpan atau default config).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function bobot(): array
    {
        if ($this->kelasId === null) {
            return GradingSetting::bobotDefault();
        }

        $kelas = SchoolClass::find($this->kelasId);

        return $kelas !== null ? $kelas->bobotEfektif() : GradingSetting::bobotDefault();
    }

    /**
     * @return Collection<int, Student>
     */
    #[Computed]
    public function siswa(): Collection
    {
        if ($this->kelasId === null) {
            return collect();
        }

        return Student::query()
            ->where('class_id', $this->kelasId)
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Nilai sumatif seluruh siswa pada misi terpilih.
     *
     * @return Collection<int, SumativeScore>  kunci = student_id
     */
    #[Computed]
    public function nilai(): Collection
    {
        $misi = $this->misiTerpilih;

        if ($misi === null || $this->siswa->isEmpty()) {
            return collect();
        }

        return app(SumativeScoreCalculator::class)->hitungKelas($this->siswa, $misi, $this->bobot);
    }

    /**
     * @return array<string, int|float>
     */
    #[Computed]
    public function ringkasan(): array
    {
        $nilai = $this->nilai;

        return [
            'jumlah' => $nilai->count(),
            'rata' => $nilai->isEmpty() ? 0.0 : round((float) $nilai->avg('nilaiAkhir'), 1),
            'tertinggi' => $nilai->isEmpty() ? 0.0 : round((float) $nilai->max('nilaiAkhir'), 1),
            'sementara' => $nilai->filter(fn (SumativeScore $s) => $s->sementara)->count(),
        ];
    }

    /**
     * Buka modal pengisian nilai manual untuk satu siswa.
     */
    public function bukaSunting(int $studentId): void
    {
        $this->kelasTerpilih();

        $manual = ManualScore::query()
            ->where('mission_id', $this->misiId)
            ->where('student_id', $studentId)
            ->first();

        $this->suntingStudentId = $studentId;
        $this->suntingRefleksi = $manual?->reflection_score !== null ? (string) $manual->reflection_score : '';
        $this->suntingObservasi = $manual?->observation_score !== null ? (string) $manual->observation_score : '';
        $this->suntingCatatan = (string) ($manual?->notes ?? '');
        $this->resetValidation();

        unset($this->catatanSiswa);

        $this->dispatch('modal-nilai-open');
    }

    /**
     * Catatan & refleksi siswa terpilih pada misi sumatif ini (untuk rubrik guru).
     *
     * @return Collection<int, \App\Models\Reflection>
     */
    #[Computed]
    public function catatanSiswa(): Collection
    {
        if ($this->suntingStudentId === null || $this->misiId === null) {
            return collect();
        }

        return \App\Models\Reflection::query()
            ->where('student_id', $this->suntingStudentId)
            ->where('mission_id', $this->misiId)
            ->orderBy('type')
            ->get();
    }

    /**
     * Simpan nilai manual (refleksi & observasi) untuk siswa terpilih.
     */
    public function simpanNilai(): void
    {
        $this->kelasTerpilih();
        $this->pastikanMisiSumatif();

        $this->validate([
            'suntingRefleksi' => ['nullable', 'integer', 'min:0', 'max:100'],
            'suntingObservasi' => ['nullable', 'integer', 'min:0', 'max:100'],
            'suntingCatatan' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'suntingRefleksi' => 'nilai refleksi',
            'suntingObservasi' => 'nilai observasi',
            'suntingCatatan' => 'catatan',
        ]);

        $studentId = $this->suntingStudentId;

        abort_if($studentId === null, 404);

        // Siswa harus benar-benar berada di kelas yang sedang dibuka.
        $adaSiswa = Student::query()
            ->where('student_id', $studentId)
            ->where('class_id', $this->kelasId)
            ->exists();

        abort_unless($adaSiswa, 403);

        ManualScore::updateOrCreate(
            [
                'student_id' => $studentId,
                'mission_id' => $this->misiId,
            ],
            [
                'reflection_score' => $this->suntingRefleksi === '' ? null : (int) $this->suntingRefleksi,
                'observation_score' => $this->suntingObservasi === '' ? null : (int) $this->suntingObservasi,
                'notes' => $this->suntingCatatan === '' ? null : $this->suntingCatatan,
                'graded_by' => auth()->id(),
            ],
        );

        $this->suntingStudentId = null;

        unset($this->nilai, $this->ringkasan);

        $this->dispatch('modal-nilai-close');
        $this->dispatch('toast', type: 'sukses', message: 'Nilai manual berhasil disimpan.');
    }

    /**
     * Netralkan sel CSV: beri awalan kutip bila dimulai karakter berbahaya.
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
     * Ekspor rekap nilai ke CSV (BOM UTF-8 + proteksi injeksi).
     */
    public function ekspor(): StreamedResponse
    {
        $this->kelasTerpilih();

        if ($this->misiId !== null) {
            $this->pastikanMisiSumatif();
        }

        $siswa = $this->siswa;
        $nilai = $this->nilai;
        $label = config('teater.komponen_labels', []);
        $namaKelas = $this->kelasId !== null ? ($this->opsiKelas[$this->kelasId] ?? 'kelas') : 'kelas';
        $namaBerkas = 'rekap-nilai-'.str()->slug($namaKelas).'.csv';

        return response()->streamDownload(function () use ($siswa, $nilai, $label): void {
            $keluaran = fopen('php://output', 'w');

            if ($keluaran === false) {
                return;
            }

            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, array_merge(
                ['Nama', 'Nomor'],
                array_values($label),
                ['Nilai Akhir', 'Status'],
            ));

            foreach ($siswa as $s) {
                $hasil = $nilai->get($s->student_id);

                if ($hasil === null) {
                    continue;
                }

                fputcsv($keluaran, array_merge(
                    [$this->amanCsv($s->full_name), $this->amanCsv($s->student_no ?? '')],
                    array_map(
                        fn (?float $komponen) => $komponen === null ? '' : (string) round($komponen, 1),
                        array_values($hasil->komponen()),
                    ),
                    [
                        $hasil->adaData() ? (string) round($hasil->nilaiAkhir, 1) : '',
                        $hasil->statusLabel(),
                    ],
                ));
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

<div>
    <x-ui.page-header
        title="Rekap Nilai Sumatif"
        description="Nilai akhir per siswa pada misi level sumatif, lengkap dengan rincian tiap komponen."
    >
        <x-slot:actions>
            <x-ui.button variant="outline" wire:click="ekspor" @disabled($this->siswa->isEmpty())>
                <x-ui.icon.download class="size-4" />
                Ekspor CSV
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select
                label="Kelas"
                name="kelasId"
                wire:model.live="kelasId"
                :options="$this->opsiKelas"
                placeholder="Pilih kelas"
            />
            <x-ui.select
                label="Misi Sumatif"
                name="misiId"
                wire:model.live="misiId"
                :options="$this->daftarMisi->pluck('level.level_name', 'mission_id')->all()"
                placeholder="Pilih misi sumatif"
            />
        </div>
    </x-ui.card>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk menyusun rekap nilai."
            />
        </x-ui.card>
    @elseif ($this->daftarMisi->isEmpty())
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="target"
                variant="info"
                title="Belum ada misi sumatif"
                message="Tandai salah satu level sebagai sumatif dan tambahkan misi padanya."
            >
                <x-slot:actions>
                    <x-ui.button variant="primary" href="{{ route('admin.level') }}" wire:navigate>Kelola Level</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.alert variant="info" class="mb-4">
            Bobot kelas saat ini —
            @foreach ($this->bobot as $kunci => $nilai)
                <strong>{{ \App\Models\GradingSetting::labelPerBobot()[$kunci] ?? $kunci }}: {{ $nilai }}%</strong>{{ ! $loop->last ? ' · ' : '' }}
            @endforeach
            <span class="mt-1 block text-xs">
                Ubah bobot di halaman Pengaturan Penilaian. Komponen refleksi &amp; observasi diisi manual lewat
                tombol <em>Isi Nilai</em>; baris berstatus "Sementara" masih menunggu komponen manual.
            </span>
        </x-ui.alert>


        <x-ui.card :padding="false">
            <div class="border-b border-line px-4 py-3">
                <p class="text-sm font-semibold text-slate-800">
                    {{ $this->misiTerpilih?->level?->level_name ?? 'Misi Sumatif' }} —
                    {{ $this->siswa->count() }} siswa
                </p>
            </div>

            @if ($this->siswa->isEmpty())
                <x-ui.empty-state
                    icon="users"
                    title="Belum ada siswa"
                    message="Tambahkan siswa ke kelas ini untuk menghitung nilai sumatif."
                />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-page/60">
                            <tr>
                                <th class="px-4 py-3 text-start text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                                @foreach (config('teater.komponen_labels', []) as $label)
                                    <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</th>
                                @endforeach
                                <th class="px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai Akhir</th>
                                <th class="w-36 px-4 py-3 text-end text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-white">
                            @foreach ($this->siswa as $s)
                                @php $hasil = $this->nilai->get($s->student_id); @endphp
                                @if ($hasil !== null)
                                    <tr wire:key="nilai-{{ $s->student_id }}" class="transition hover:bg-page/70">
                                        <td class="px-4 py-3 align-middle">
                                            <p class="font-medium text-slate-800">{{ $s->full_name }}</p>
                                            <p class="text-xs text-slate-400">{{ $s->student_no ?? '—' }}</p>
                                        </td>

                                        @foreach ($hasil->komponen() as $komponen)
                                            <td @class([
                                                'px-4 py-3 text-end align-middle tabular-nums',
                                                \App\Services\SumativeScore::warna($komponen),
                                            ])>{{ $komponen === null ? '—' : round($komponen, 1) }}</td>
                                        @endforeach

                                        {{-- Tanpa data sama sekali, nilai akhir belum bermakna → "—" netral. --}}
                                        @if ($hasil->adaData())
                                            <td @class([
                                                'px-4 py-3 text-end align-middle text-base font-semibold tabular-nums',
                                                \App\Services\SumativeScore::warna($hasil->nilaiAkhir),
                                            ])>
                                                {{ round($hasil->nilaiAkhir, 1) }}
                                            </td>
                                        @else
                                            <td class="px-4 py-3 text-end align-middle text-base font-semibold tabular-nums text-slate-400">—</td>
                                        @endif

                                        <td class="px-4 py-3 text-end align-middle">
                                            @if ($hasil->statusLabel() === 'Sementara')
                                                <x-ui.badge variant="peringatan" class="mb-1.5">Sementara</x-ui.badge>
                                            @elseif ($hasil->statusLabel() === 'Belum ada data')
                                                <x-ui.badge variant="netral" class="mb-1.5">Belum ada data</x-ui.badge>
                                            @endif
                                            <x-ui.button variant="outline" size="sm" wire:click="bukaSunting({{ $s->student_id }})">
                                                <x-ui.icon.pencil class="size-3.5" />
                                                Isi Nilai
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif

    {{-- Modal isi nilai manual --}}
    <x-ui.modal name="modal-nilai" title="Isi Nilai Manual">
        <form wire:submit="simpanNilai" class="space-y-4">
            <x-ui.alert variant="info">
                Komponen refleksi/rubrik dan observasi diisi guru. Keduanya 0–100; biarkan kosong
                bila belum dinilai — baris akan tetap berstatus "Sementara".
            </x-ui.alert>

            {{-- Catatan & refleksi siswa, untuk membantu guru mengisi rubrik --}}
            @if ($this->catatanSiswa->isNotEmpty())
                <div class="rounded-lg border border-line bg-page/50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Catatan & Refleksi Siswa</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($this->catatanSiswa as $catatan)
                            <li class="rounded-lg border border-line bg-white p-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-medium text-slate-700">
                                        {{ \App\Models\Reflection::jenis()[$catatan->type]['label'] ?? $catatan->type }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">{{ $catatan->updated_at?->format('d/m H:i') }}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-line text-xs text-slate-600">{{ $catatan->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-ui.input
                label="Nilai Refleksi/Rubrik (0–100)"
                name="suntingRefleksi"
                type="number"
                min="0"
                max="100"
                wire:model="suntingRefleksi"
                placeholder="mis. 85"
            />

            <x-ui.input
                label="Nilai Observasi (0–100)"
                name="suntingObservasi"
                type="number"
                min="0"
                max="100"
                wire:model="suntingObservasi"
                placeholder="mis. 90"
            />

            <x-ui.textarea
                label="Catatan (opsional)"
                name="suntingCatatan"
                wire:model="suntingCatatan"
                rows="3"
                placeholder="Catatan singkat untuk siswa ini..."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-nilai-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Nilai</x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>
