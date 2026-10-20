<?php

use App\Concerns\InteractsWithTable;
use App\Models\BugAttempt;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use App\Services\AntreanVerifikasi;
use App\Services\VerifikasiHasil;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verifikasi Penilaian — antrean hasil penilaian di perangkat siswa (Fase 6).
 *
 * Halaman ini HANYA menampilkan data berstatus 'menunggu_verifikasi', yaitu
 * hasil yang sudah dihitung server dari laporan browser tetapi belum diberi
 * poin. Guru dapat:
 *   - memeriksa rincian kasus uji yang dilaporkan perangkat siswa,
 *   - menjalankan ulang kode di perangkat guru (bukti independen),
 *   - memverifikasi satu per satu, atau menjalankan seluruh antrean sekaligus,
 *   - meminta siswa menilai ulang.
 *
 * Catatan keamanan: halaman ini menampilkan expected_value kepada guru, jadi
 * akses dibatasi policy kelas (guru pemilik kelas atau admin). Paket yang
 * dikirim ke browser guru TETAP tidak memuat expected_value/tolerance.
 */
new #[Layout('layouts.admin'), Title('Verifikasi Penilaian')]
class extends Component
{
    // State tabel (page, perPage) mengikuti komponen lain di repo: x-ui.data-table
    // memakai properti $page/$perPage, sedangkan Livewire 4 WithPagination
    // memakai $paginators — jadi trait repo yang dipakai di sini.
    use InteractsWithTable;

    #[Url(as: 'kelas', nullable: true)]
    public ?int $kelasId = null;

    /** Filter misi (null = semua misi). */
    #[Url(as: 'misi', nullable: true)]
    public ?int $misiId = null;

    /** Jenis data yang dipilih: 'misi' atau 'bug'. */
    #[Url(as: 'jenis', nullable: true)]
    public string $jenis = 'misi';

    /** Id submission / attempt yang sedang diperiksa. */
    #[Url(as: 'periksa', nullable: true)]
    public ?int $terpilihId = null;

    /** Bukti pemeriksaan guru (hasil eksekusi ulang di perangkat guru). */
    public ?array $pemeriksaan = null;

    public int $perPage = 10;

    public function mount(): void
    {
        $this->pengguna();

        if ($this->kelasId === null) {
            $this->kelasId = SchoolClass::query()
                ->visibleTo($this->pengguna())
                ->orderBy('class_name')
                ->value('class_id');
        }

        $this->pastikanKelas();
    }

    /**
     * Pengguna panel (guru/admin).
     *
     * Guard `siswa` memakai sesi terpisah, tetapi `auth()->user()` mengikuti
     * guard default. Pemeriksaan tipe eksplisit mencegah TypeError (500) bila
     * ada sesi siswa yang mencoba membuka halaman ini.
     */
    private function pengguna(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function pastikanKelas(): void
    {
        if ($this->kelasId === null) {
            return;
        }

        $kelas = SchoolClass::findOrFail($this->kelasId);

        abort_unless($this->pengguna()->can('view', $kelas), 403);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiKelas(): array
    {
        return SchoolClass::query()
            ->visibleTo($this->pengguna())
            ->orderBy('class_name')
            ->pluck('class_name', 'class_id')
            ->all();
    }

    /**
     * Misi sumatif yang punya antrean (untuk filter misi).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function opsiMisi(): array
    {
        if ($this->kelasId === null) {
            return [];
        }

        $missionIds = Submission::query()
            ->whereIn('team_id', $this->teamIds())
            ->where('status', Submission::STATUS_MENUNGGU_VERIFIKASI)
            ->distinct()
            ->pluck('mission_id')
            ->all();

        if ($missionIds === []) {
            return [];
        }

        return Mission::query()
            ->whereIn('mission_id', $missionIds)
            ->with('level')
            ->orderBy('mission_id')
            ->get()
            ->mapWithKeys(fn (Mission $m) => [
                (int) $m->mission_id => trim(($m->level?->level_name ?? 'Level').' · '.$m->unlock_code),
            ])
            ->all();
    }

    /**
     * Ringkasan antrean (dihitung service bersama, dipakai juga sidebar).
     *
     * @return array{menunggu_verifikasi: int, menunggu_perangkat: int, selisih: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        return app(AntreanVerifikasi::class)->untuk($this->pengguna());
    }

    /**
     * Id kelompok pada kelas terpilih.
     *
     * @return array<int, int>
     */
    private function teamIds(): array
    {
        if ($this->kelasId === null) {
            return [];
        }

        return Team::query()
            ->where('class_id', $this->kelasId)
            ->pluck('team_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Baris tabel: submission misi / percobaan bug yang menunggu verifikasi.
     *
     * Query (bukan Collection) supaya paginasi & filter tetap di database.
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $teamIds = $this->teamIds();

        if ($teamIds === []) {
            return new LengthAwarePaginator([], 0, max(1, $this->perPage));
        }

        if ($this->jenis === 'bug') {
            return $this->rowsBug($teamIds);
        }

        return Submission::query()
            ->whereIn('team_id', $teamIds)
            ->where('status', Submission::STATUS_MENUNGGU_VERIFIKASI)
            ->when($this->misiId !== null, fn ($q) => $q->where('mission_id', $this->misiId))
            ->with(['team', 'mission.level', 'submittedBy'])
            ->withCount([
                'results',
                'results as hasil_lulus_count' => fn ($q) => $q->where('is_passed', true),
            ])
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /**
     * @param  array<int, int>  $teamIds
     */
    private function rowsBug(array $teamIds): LengthAwarePaginator
    {
        $levelId = $this->misiId !== null
            ? Mission::query()->where('mission_id', $this->misiId)->value('level_id')
            : null;

        return BugAttempt::query()
            ->whereIn('team_id', $teamIds)
            ->where('grading_status', BugAttempt::STATUS_MENUNGGU_VERIFIKASI)
            ->when($levelId !== null, fn ($q) => $q->whereHas(
                'bugCard',
                fn ($b) => $b->where('level_id', $levelId),
            ))
            ->with(['team', 'bugCard.level'])
            ->withCount([
                'results',
                'results as hasil_lulus_count' => fn ($q) => $q->where('is_passed', true),
            ])
            ->orderByDesc('attempted_at')
            ->orderByDesc('attempt_id')
            ->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    /**
     * Seluruh id antrean (semua halaman) untuk tombol verifikasi massal.
     *
     * Sengaja id saja dan bukan hasil paginasi, supaya tombol "Verifikasi yang
     * Menunggu" memproses SELURUH antrean, bukan hanya halaman yang terlihat.
     *
     * @return array<int, array{jenis: string, id: int}>
     */
    #[Computed]
    public function antreanMassal(): array
    {
        $teamIds = $this->teamIds();

        if ($teamIds === []) {
            return [];
        }

        $submission = Submission::query()
            ->whereIn('team_id', $teamIds)
            ->where('status', Submission::STATUS_MENUNGGU_VERIFIKASI)
            ->when($this->misiId !== null, fn ($q) => $q->where('mission_id', $this->misiId))
            ->orderBy('submitted_at')
            ->pluck('submission_id')
            ->map(fn ($id) => ['jenis' => 'misi', 'id' => (int) $id])
            ->all();

        $bug = BugAttempt::query()
            ->whereIn('team_id', $teamIds)
            ->where('grading_status', BugAttempt::STATUS_MENUNGGU_VERIFIKASI)
            ->orderBy('attempted_at')
            ->pluck('attempt_id')
            ->map(fn ($id) => ['jenis' => 'bug', 'id' => (int) $id])
            ->all();

        return array_merge($submission, $bug);
    }

    /**
     * Definisi kolom tabel (format sel berupa closure — bukan query).
     *
     * CATATAN: sel format dirender sebagai HTML mentah oleh x-ui.data-table,
     * jadi SETIAP nilai dinamis di sini WAJIB melewati e() (escape). Tag
     * komponen Blade sengaja tidak dipakai di dalam string PHP karena compiler
     * Blade ikut mengompilasi tag tersebut dan merusak sintaks berkas ini.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'waktu',
                'label' => 'Waktu',
                'class' => 'whitespace-nowrap',
                'format' => fn ($row) => (($row instanceof BugAttempt ? $row->attempted_at : $row->submitted_at)
                    ?? now())->format('d M Y H:i'),
            ],
            [
                'key' => 'kelompok',
                'label' => 'Kelompok',
                'format' => fn ($row) => '<span class="font-medium text-slate-800">'
                    .e($row->team?->team_name ?? '-').'</span>',
            ],
            [
                'key' => 'misi',
                'label' => 'Misi',
                'format' => fn ($row) => e($row instanceof BugAttempt
                    ? ($row->bugCard?->bug_title ?? 'Kartu Bug')
                    : ($row->mission?->level?->level_name ?? '-')),
            ],
            [
                'key' => 'pengirim',
                'label' => 'Pengirim',
                'format' => fn ($row) => e($row instanceof BugAttempt
                    ? '—'
                    : ($row->submittedBy?->full_name ?? '-')),
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'format' => fn ($row) => $this->badge($row->variantStatus(), $row->labelStatus()),
            ],
            [
                'key' => 'sementara',
                'label' => 'Hasil Perangkat',
                'format' => fn ($row) => '<span class="text-slate-700">'
                    .(int) ($row->hasil_lulus_count ?? 0).' / '.(int) ($row->results_count ?? 0)
                    .' lulus</span>',
            ],
            [
                'key' => 'verifikasi',
                'label' => 'Hasil Verifikasi',
                'format' => fn ($row) => $row->grading_source === 'terverifikasi'
                    ? $this->badge('sukses', 'Resmi')
                    : '<span class="text-slate-400">Belum</span>',
            ],
            [
                'key' => 'selisih',
                'label' => 'Selisih',
                'class' => 'whitespace-nowrap',
                'format' => function ($row) {
                    if (! $row->has_mismatch) {
                        return '<span class="text-slate-300">—</span>';
                    }

                    return $this->badge(
                        'peringatan',
                        (int) data_get($row->mismatch_detail, 'jumlah', 0).' kasus',
                    );
                },
            ],
            [
                'key' => 'aksi',
                'label' => '',
                'class' => 'w-48 text-end',
                'format' => fn ($row) => $this->aksiBaris($row),
            ],
        ];
    }

    /**
     * Badge HTML dengan nilai yang sudah di-escape.
     *
     * Warnanya mengikuti resources/views/components/ui/badge.blade.php supaya
     * tampilan tabel identik dengan badge di tempat lain.
     */
    private function badge(string $variant, string $label): string
    {
        $kelas = match ($variant) {
            'sukses' => 'bg-primary-50 text-primary-700 ring-primary-200',
            'peringatan' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'bahaya' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };

        return '<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '
            .$kelas.'">'.e($label).'</span>';
    }

    /**
     * Tombol aksi per baris (Periksa / Verifikasi).
     */
    private function aksiBaris(Submission|BugAttempt $row): string
    {
        $jenis = $this->jenisRow($row);
        $id = (int) $row->getKey();

        return '<div class="flex items-center justify-end gap-1.5">'
            .'<button type="button" wire:click="buka(\''.$jenis.'\', '.$id.')"'
            .' class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-page">Periksa</button>'
            .'<button type="button" wire:click="verifikasiSatu(\''.$jenis.'\', '.$id.')"'
            .' class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-medium text-white shadow-sm shadow-primary-600/20 transition hover:bg-primary-700">Verifikasi</button>'
            .'</div>';
    }

    /**
     * Jenis baris untuk aksi ('misi' | 'bug').
     */
    private function jenisRow(Submission|BugAttempt $row): string
    {
        return $row instanceof BugAttempt ? 'bug' : 'misi';
    }

    /**
     * Data yang sedang diperiksa beserta rincian hasil per kasus.
     */
    #[Computed]
    public function terpilih(): Submission|BugAttempt|null
    {
        if ($this->terpilihId === null) {
            return null;
        }

        if ($this->jenis === 'bug') {
            $attempt = BugAttempt::query()
                ->with(['team.schoolClass', 'bugCard', 'results.testCase'])
                ->find($this->terpilihId);

            abort_if($attempt === null, 404);
            abort_unless($this->pengguna()->can('view', $attempt->team?->schoolClass), 403);

            return $attempt;
        }

        $submission = Submission::query()
            ->with(['team.schoolClass', 'mission.level', 'submittedBy', 'results.testCase'])
            ->find($this->terpilihId);

        abort_if($submission === null, 404);
        abort_unless($this->pengguna()->can('view', $submission->team?->schoolClass), 403);

        return $submission;
    }

    public function updatedKelasId(): void
    {
        $this->misiId = null;
        $this->page = 1;
        $this->bersihkanPilihan();
        $this->lupakan();
    }

    public function updatedMisiId(): void
    {
        $this->page = 1;
        $this->bersihkanPilihan();
        $this->lupakan();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Ganti tab jenis hasil (Misi / Kartu Bug).
     */
    public function gantiJenis(string $jenis): void
    {
        $this->jenis = $jenis === 'bug' ? 'bug' : 'misi';
        $this->page = 1;
        $this->bersihkanPilihan();
        $this->lupakan();
    }

    /**
     * Buka satu data untuk diperiksa.
     */
    public function buka(string $jenis, int $id): void
    {
        $this->jenis = $jenis === 'bug' ? 'bug' : 'misi';
        $this->terpilihId = $id;
        $this->pemeriksaan = null;

        // Membaca computed memicu pemeriksaan policy (403 bila bukan kelasnya).
        $this->terpilih;
    }

    public function tutup(): void
    {
        $this->terpilihId = null;
        $this->pemeriksaan = null;
    }

    /**
     * Simpan bukti pemeriksaan dari perangkat guru (dikirim oleh Alpine).
     *
     * Rincian (termasuk nilai harapan) disimpan di state Livewire dan dirender
     * ulang oleh server, bukan disimpan di state Alpine — supaya tidak hilang
     * ketika Livewire me-morph ulang panel setelah permintaan berikutnya.
     *
     * @param  array{hasil?: array<int, array{id: int, lulus: bool, aktual: string, harapan: string}>, galat?: mixed}  $data
     */
    public function simpanPemeriksaan(array $data): void
    {
        if ($this->terpilih === null) {
            return;
        }

        $hasil = array_map(fn (array $satu) => [
            'id' => (int) $satu['id'],
            'lulus' => (bool) ($satu['lulus'] ?? false),
            'aktual' => mb_substr((string) ($satu['aktual'] ?? ''), 0, 100),
            'harapan' => mb_substr((string) ($satu['harapan'] ?? ''), 0, 100),
        ], $data['hasil'] ?? []);

        $this->pemeriksaan = [
            'hasil' => $hasil,
            'galat' => $data['galat'] ?? null,
            'pada' => now()->format('H:i:s'),
        ];

        $lulus = count(array_filter($hasil, fn (array $s) => $s['lulus']));

        $this->dispatch(
            'toast',
            type: 'info',
            message: "Pemeriksaan selesai: {$lulus} dari ".count($hasil).' kasus lulus di perangkat guru.',
        );
    }

    /**
     * Verifikasi satu item dari panel yang sedang dibuka (idempoten).
     */
    public function verifikasi(): void
    {
        $data = $this->terpilih;

        if ($data === null) {
            return;
        }

        $hasil = $this->jalankanVerifikasi($data, $this->pemeriksaan);

        if ($hasil['status'] !== 'ok') {
            $this->dispatch('toast', type: 'peringatan', message: 'Data ini sudah tidak menunggu verifikasi.');

            return;
        }

        $this->dispatch(
            'toast',
            type: $hasil['selisih'] ? 'peringatan' : 'sukses',
            message: $hasil['selisih']
                ? 'Terverifikasi, tetapi hasil perangkat berbeda dari pemeriksaan guru.'
                : 'Hasil terverifikasi dan poin sudah diberikan.',
        );

        $this->bersihkanPilihan();
        $this->lupakan();
    }

    /**
     * Verifikasi satu baris dari tabel (tanpa membuka panel).
     *
     * Dipakai tombol "Verifikasi" per baris DAN oleh verifikasi massal
     * (satu kode jalan, supaya aturan verifikasi tidak bercabang).
     *
     * @param  array<string, mixed>|null  $pemeriksaan
     * @return array{status: string, selisih: bool}
     */
    public function verifikasiSatu(string $jenis, int $id, ?array $pemeriksaan = null): array
    {
        $data = $jenis === 'bug'
            ? BugAttempt::query()->with('team.schoolClass')->find($id)
            : Submission::query()->with('team.schoolClass')->find($id);

        if ($data === null) {
            return ['status' => 'tidak_ditemukan', 'selisih' => false];
        }

        abort_unless($this->pengguna()->can('view', $data->team?->schoolClass), 403);

        $hasil = $this->jalankanVerifikasi($data, $pemeriksaan);

        if ($hasil['status'] !== 'ok') {
            return ['status' => 'dilewati', 'selisih' => false];
        }

        $this->lupakan();

        if ($this->terpilihId !== null && (int) $this->terpilihId === $id) {
            $this->bersihkanPilihan();
        }

        return ['status' => 'ok', 'selisih' => $hasil['selisih']];
    }

    /**
     * Jalankan VerifikasiHasil untuk model apa pun.
     *
     * @param  array<string, mixed>|null  $pemeriksaan
     * @return array{status: string, selisih: bool}
     */
    private function jalankanVerifikasi(Submission|BugAttempt $data, ?array $pemeriksaan): array
    {
        $servis = app(VerifikasiHasil::class);
        $guru = $this->pengguna();

        $hasil = $data instanceof Submission
            ? $servis->submission($data, $guru, $pemeriksaan)
            : $servis->bugAttempt($data, $guru, $pemeriksaan);

        return [
            'status' => $hasil['status'],
            'selisih' => ($hasil['selisih'] ?? null) !== null,
        ];
    }

    /**
     * Minta siswa menilai ulang (nonce baru, status kembali menunggu).
     */
    public function mintaUlang(): void
    {
        $data = $this->terpilih;

        if ($data === null) {
            return;
        }

        $hasil = app(VerifikasiHasil::class)->mintaUlang($data);

        if ($hasil['status'] !== 'ok') {
            $this->dispatch('toast', type: 'peringatan', message: 'Data ini sudah tidak menunggu verifikasi.');

            return;
        }

        $this->dispatch('toast', type: 'info', message: 'Siswa diminta menilai ulang di perangkatnya.');

        $this->bersihkanPilihan();
        $this->lupakan();
    }

    private function bersihkanPilihan(): void
    {
        $this->terpilihId = null;
        $this->pemeriksaan = null;

        unset($this->terpilih);
    }

    /**
     * Buang cache computed + cache service agar angka langsung segar.
     */
    private function lupakan(): void
    {
        app(AntreanVerifikasi::class)->lupakan();

        unset(
            $this->rows,
            $this->columns,
            $this->antreanMassal,
            $this->opsiMisi,
            $this->ringkasan,
        );
    }

    public function render()
    {
        return view('pages.admin.⚡verifikasi-penilaian');
    }
};
?>

<div
    data-penilai-indikator
    data-penilai-aset="{{ rtrim((string) config('teater.pyodide.asset_path', '/vendor/pyodide'), '/').'/'.config('teater.pyodide.version', '314.0.7').'/' }}"
    data-penilai-versi="{{ config('teater.pyodide.version', '314.0.7') }}"
    data-penilai-status="idle"
    title="Penilai siap dipakai"
>
    <x-ui.page-header
        title="Verifikasi Penilaian"
        description="Periksa hasil penilaian yang dijalankan di perangkat siswa sebelum poin diberikan (level sumatif)."
    >
        <x-slot:actions>
            <span
                data-penilai-label
                class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500"
            >
                <span data-penilai-titik class="size-2 rounded-full bg-slate-300"></span>
                <span>Penilai siap dipakai</span>
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Ringkasan antrean --}}
    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card
            label="Menunggu Verifikasi"
            :value="$this->ringkasan['menunggu_verifikasi']"
            icon="shield-check"
            hint="Hasil perangkat sudah masuk, poin menunggu guru"
        />
        <x-ui.stat-card
            label="Menunggu Penilaian Perangkat"
            :value="$this->ringkasan['menunggu_perangkat']"
            icon="braces"
            hint="Siswa sudah submit, penilaian di browser belum selesai"
        />
        <x-ui.stat-card
            label="Selisih Terdeteksi"
            :value="$this->ringkasan['selisih']"
            icon="alert-triangle"
            hint="Hasil perangkat berbeda dari pemeriksaan/verifikasi"
        />
    </div>

    {{-- Filter --}}
    <x-ui.card class="mb-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select
                label="Kelas"
                name="kelasId"
                wire:model.live="kelasId"
                :options="$this->opsiKelas"
                placeholder="Pilih kelas"
            />

            <x-ui.select
                label="Misi"
                name="misiId"
                wire:model.live="misiId"
                :options="$this->opsiMisi"
                placeholder="Semua misi"
            />

            <div>
                <span class="mb-1 block text-sm font-medium text-slate-700">Jenis hasil</span>
                <div class="flex gap-2">
                    <x-ui.button
                        size="sm"
                        variant="{{ $jenis === 'misi' ? 'primary' : 'outline' }}"
                        wire:click="gantiJenis('misi')"
                    >
                        Misi
                    </x-ui.button>
                    <x-ui.button
                        size="sm"
                        variant="{{ $jenis === 'bug' ? 'primary' : 'outline' }}"
                        wire:click="gantiJenis('bug')"
                    >
                        Kartu Bug
                    </x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>

    @if ($kelasId === null)
        <x-ui.card :padding="false">
            <x-ui.empty-state
                icon="school"
                variant="info"
                title="Belum ada kelas"
                message="Buat kelas terlebih dahulu untuk melihat antrean verifikasi."
            />
        </x-ui.card>
    @else
        @include('pages.admin.partials.verifikasi-massal')

        <x-ui.data-table
            :rows="$this->rows"
            :columns="$this->columns"
            empty-title="Tidak ada yang menunggu verifikasi"
            empty-message="Hasil penilaian dari perangkat siswa akan muncul di sini setelah siswa menyelesaikan penilaian."
        />
    @endif

    @include('pages.admin.partials.verifikasi-panel')
</div>

