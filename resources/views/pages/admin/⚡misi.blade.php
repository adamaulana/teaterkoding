<?php

use App\Actions\BagiFungsiOtomatis;
use App\Actions\DuplikatMisi;
use App\Actions\GenerateKodeUnlock;
use App\Actions\ParserBarisKasusUji;
use App\Actions\ValidasiKasusUji;
use App\Concerns\InteractsWithTable;
use App\Livewire\Forms\KasusUjiForm;
use App\Livewire\Forms\MisiForm;
use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.admin'), Title('Misi & Kasus Uji')]
class extends Component
{
    use InteractsWithTable;

    public MisiForm $form;

    public KasusUjiForm $kasusForm;

    /** Misi yang sedang dibuka pada halaman detail (null = tampilan daftar). */
    #[Url(as: 'misi', nullable: true)]
    public ?int $misiId = null;

    #[Url(as: 'level', nullable: true)]
    public ?int $filterLevelId = null;

    public string $activeTab = 'ringkasan';

    // ---- Konfirmasi hapus misi ----
    public ?int $hapusMissionId = null;

    public string $pesanKonfirmasi = '';

    // ---- Duplikat misi ----
    public ?int $duplikatSumberId = null;

    public string $kodeDuplikat = '';

    /** @var array<int, string> */
    public array $pratinjauKasusUji = [];

    // ---- Hapus kasus uji ----
    public ?int $hapusKasusId = null;

    public string $pesanKonfirmasiKasus = '';

    // ---- Tambah banyak sekaligus ----
    public string $teksMassal = '';

    public bool $pratinjauMassal = false;

    /** @var array<int, array<string, mixed>> */
    public array $massalValid = [];

    /** @var array<int, array<string, mixed>> */
    public array $massalInvalid = [];

    // ---- Kepemilikan fungsi ----
    public ?int $fungsiKelasId = null;

    /** @var array<int, array<string, int|null>> [teamId][functionName] => studentId */
    public array $pemilik = [];

    /** @var array<int, array<string, int>> [teamId][functionName] => studentId */
    public array $pratinjauBagi = [];

    private const SORTABLE = ['level_id', 'unlock_code', 'test_cases_count', 'mission_id'];

    public function mount(): void
    {
        if ($this->misiId !== null) {
            $this->misiAktif();
        }
    }

    /**
     * Misi yang sedang dibuka pada halaman detail.
     */
    private function misiAktif(): Mission
    {
        return Mission::query()
            ->with('level')
            ->withCount('testCases')
            ->findOrFail($this->misiId);
    }

    #[Computed]
    public function misi(): ?Mission
    {
        if ($this->misiId === null) {
            return null;
        }

        return $this->misiAktif();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'level_id';

        return Mission::query()
            ->with('level')
            ->withCount('testCases')
            ->when($this->filterLevelId !== null, fn ($q) => $q->where('level_id', $this->filterLevelId))
            ->when(filled($this->search), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('unlock_code', 'like', "%{$this->search}%")
                ->orWhere('client_brief', 'like', "%{$this->search}%")))
            ->orderBy($sortBy, $this->sortBy ? $this->sortDir : 'asc')
            ->paginate($this->perPage, ['*'], 'page', $this->page)
            ->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function opsiLevel(): array
    {
        return Level::query()
            ->orderBy('level_number')
            ->pluck('level_name', 'level_id')
            ->all();
    }

    /**
     * Kasus uji misi aktif, diurutkan berdasarkan id.
     *
     * @return \Illuminate\Support\Collection<int, TestCase>
     */
    #[Computed]
    public function kasusUji()
    {
        if ($this->misiId === null) {
            return collect();
        }

        return TestCase::query()
            ->where('mission_id', $this->misiId)
            ->withCount('submissionResults')
            ->orderBy('test_case_id')
            ->get();
    }

    /**
     * Daftar fungsi yang DITURUNKAN dari kasus uji misi aktif.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function fungsiMisi(): array
    {
        $targets = $this->kasusUji->pluck('check_target')->all();

        return (new ValidasiKasusUji)->daftarFungsi($targets);
    }

    /**
     * Kelas yang boleh dipilih untuk kepemilikan fungsi.
     *
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
     * Kelompok pada kelas terpilih beserta anggotanya.
     *
     * @return \Illuminate\Support\Collection<int, Team>
     */
    #[Computed]
    public function kelompokFungsi()
    {
        if ($this->fungsiKelasId === null) {
            return collect();
        }

        return Team::query()
            ->where('class_id', $this->fungsiKelasId)
            ->with(['teamMembers.student'])
            ->orderBy('team_id')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedFilterLevelId(): void
    {
        $this->page = 1;
        unset($this->rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function columns(): array
    {
        return [
            [
                'key' => 'level_id',
                'label' => 'Level',
                'sortable' => true,
                'format' => fn (Mission $mission) => Blade::render(
                    '<x-ui.badge variant="netral">{{ $n }}</x-ui.badge>',
                    ['n' => $mission->level?->level_name ?? '—'],
                ),
            ],
            [
                'key' => 'unlock_code',
                'label' => 'Kode Unlock',
                'sortable' => true,
                'format' => fn (Mission $mission) => Blade::render(
                    '<code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-semibold tracking-wide text-slate-700">{{ $k }}</code>',
                    ['k' => $mission->unlock_code],
                ),
            ],
            [
                'key' => 'client_brief',
                'label' => 'Ringkasan Brief',
                'format' => fn (Mission $mission) => e(\Illuminate\Support\Str::limit($mission->client_brief, 80)),
            ],
            [
                'key' => 'test_cases_count',
                'label' => 'Kasus Uji',
                'sortable' => true,
                'format' => fn (Mission $mission) => Blade::render(
                    '<x-ui.badge variant="info">{{ $n }} kasus</x-ui.badge>',
                    ['n' => (int) $mission->test_cases_count],
                ),
            ],
            [
                'key' => 'aksi',
                'label' => 'Aksi',
                'class' => 'w-24 text-end',
                'format' => fn (Mission $mission) => Blade::render(<<<'BLADE'
                    <x-ui.dropdown align="end">
                        <x-slot:trigger>
                            <button type="button" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-primary-50 hover:text-primary-700" aria-label="Aksi">
                                <x-ui.icon.dots-vertical />
                            </button>
                        </x-slot:trigger>

                        <button type="button" wire:click="bukaDetail({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.clipboard-list class="size-4 text-slate-400" />
                            Detail
                        </button>
                        <button type="button" wire:click="bukaUbah({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.pencil class="size-4 text-slate-400" />
                            Ubah
                        </button>
                        <button type="button" wire:click="bukaDuplikat({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-slate-600 transition hover:bg-primary-50 hover:text-primary-700">
                            <x-ui.icon.cards class="size-4 text-slate-400" />
                            Duplikat
                        </button>

                        <div class="my-1 border-t border-line"></div>

                        <button type="button" wire:click="mintaHapus({{ $id }})" class="flex w-full items-center gap-2.5 px-4 py-2 text-start text-sm text-rose-600 transition hover:bg-rose-50">
                            <x-ui.icon.trash class="size-4 text-rose-500" />
                            Hapus
                        </button>
                    </x-ui.dropdown>
                    BLADE, ['id' => $mission->mission_id]),
            ],
        ];
    }

    // ---------- Detail ----------

    public function bukaDetail(int $misiId): void
    {
        $this->misiId = $misiId;
        $this->activeTab = 'ringkasan';
        $this->fungsiKelasId = null;
        $this->pemilik = [];
        $this->pratinjauBagi = [];
        $this->resetValidation();

        unset($this->misi, $this->kasusUji, $this->fungsiMisi);
    }

    public function kembaliKeDaftar(): void
    {
        $this->misiId = null;
        $this->activeTab = 'ringkasan';
        $this->pratinjauMassal = false;
        $this->teksMassal = '';
        $this->massalValid = [];
        $this->massalInvalid = [];
        $this->resetValidation();

        unset($this->misi, $this->kasusUji, $this->fungsiMisi);
    }

    // ---------- CRUD misi ----------

    public function bukaTambah(): void
    {
        $this->authorize('create', Mission::class);

        $this->form->resetForm();
        $this->form->level_id = $this->filterLevelId ?? array_key_first($this->opsiLevel);
        $this->resetValidation();

        $this->dispatch('modal-misi-open');
    }

    public function bukaUbah(int $misiId): void
    {
        $misi = Mission::findOrFail($misiId);

        $this->authorize('update', $misi);

        $this->form->setMisi($misi);
        $this->resetValidation();

        $this->dispatch('modal-misi-open');
    }

    public function buatKodeOtomatis(): void
    {
        $levelId = $this->form->level_id;

        if ($levelId === null) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih level terlebih dahulu.');

            return;
        }

        $level = Level::find($levelId);

        if ($level === null) {
            return;
        }

        $generator = new GenerateKodeUnlock;

        $this->form->unlock_code = $generator->buat(
            $level->level_number,
            $generator->kodeTerpakai($this->form->missionId),
        );
    }

    public function simpanMisi(): void
    {
        $this->form->unlock_code = $this->form->normalisasi($this->form->unlock_code);

        $data = $this->form->validate();

        if ($this->form->missionId === null) {
            $this->authorize('create', Mission::class);

            Mission::create([
                'level_id' => $data['level_id'],
                'unlock_code' => $data['unlock_code'],
                'client_brief' => $data['client_brief'],
            ]);

            $pesan = 'Misi berhasil ditambahkan.';
        } else {
            $misi = Mission::findOrFail($this->form->missionId);

            $this->authorize('update', $misi);

            $misi->fill([
                'level_id' => $data['level_id'],
                'unlock_code' => $data['unlock_code'],
                'client_brief' => $data['client_brief'],
            ])->save();

            $pesan = 'Misi berhasil diperbarui.';
        }

        $this->form->resetForm();
        $this->resetValidation();
        unset($this->rows, $this->misi, $this->kasusUji, $this->fungsiMisi);

        $this->dispatch('modal-misi-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    // ---------- Duplikat misi ----------

    public function bukaDuplikat(int $misiId): void
    {
        $misi = Mission::query()->with('level')->findOrFail($misiId);

        $this->authorize('duplicate', $misi);

        $generator = new GenerateKodeUnlock;

        $this->duplikatSumberId = $misi->mission_id;
        $this->kodeDuplikat = $generator->buat(
            (int) ($misi->level?->level_number ?? 1),
            $generator->kodeTerpakai(),
        );
        $this->resetValidation();

        $this->dispatch('modal-duplikat-open');
    }

    public function simpanDuplikat(): void
    {
        if ($this->duplikatSumberId === null) {
            return;
        }

        $sumber = Mission::findOrFail($this->duplikatSumberId);

        $this->authorize('duplicate', $sumber);

        $generator = new GenerateKodeUnlock;
        $kode = $generator->normalisasi($this->kodeDuplikat);

        $this->validate([
            'kodeDuplikat' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/', 'unique:missions,unlock_code'],
        ], [
            'kodeDuplikat.required' => 'Kode unlock baru wajib diisi.',
            'kodeDuplikat.max' => 'Kode unlock maksimal 30 karakter.',
            'kodeDuplikat.regex' => 'Kode unlock hanya boleh berisi huruf besar, angka, dan tanda hubung.',
            'kodeDuplikat.unique' => 'Kode unlock ini sudah dipakai misi lain.',
        ]);

        $hasil = DB::transaction(fn () => (new DuplikatMisi)($sumber, $kode));

        $this->duplikatSumberId = null;
        $this->kodeDuplikat = '';
        unset($this->rows);

        $this->dispatch('modal-duplikat-close');
        $this->dispatch('toast', type: 'sukses', message: "Misi diduplikat dengan kode {$kode} beserta {$hasil['jumlah_kasus_uji']} kasus uji.");
    }

    // ---------- Hapus misi ----------

    public function mintaHapus(int $misiId): void
    {
        $misi = Mission::query()
            ->withCount(['submissions', 'missionPlans', 'functionOwnerships'])
            ->findOrFail($misiId);

        $this->authorize('delete', $misi);

        $alasan = [];

        if ((int) $misi->submissions_count > 0) {
            $alasan[] = $misi->submissions_count.' submission';
        }

        if ((int) $misi->mission_plans_count > 0) {
            $alasan[] = $misi->mission_plans_count.' rencana misi';
        }

        if ((int) $misi->function_ownerships_count > 0) {
            $alasan[] = $misi->function_ownerships_count.' kepemilikan fungsi';
        }

        if ($alasan !== []) {
            $this->dispatch('toast', type: 'bahaya', message: "Misi {$misi->unlock_code} masih memiliki ".implode(', ', $alasan).' — hapus data tersebut dulu sebelum menghapus misi.');

            return;
        }

        $this->hapusMissionId = $misi->mission_id;
        $this->pesanKonfirmasi = "Hapus misi {$misi->unlock_code} beserta kasus ujinya?";

        $this->dispatch('konfirmasi-buka');
    }

    public function hapusMisi(): void
    {
        if ($this->hapusMissionId === null) {
            return;
        }

        $misi = Mission::query()
            ->withCount(['submissions', 'missionPlans', 'functionOwnerships'])
            ->findOrFail($this->hapusMissionId);

        $this->authorize('delete', $misi);

        if ((int) $misi->submissions_count > 0 || (int) $misi->mission_plans_count > 0 || (int) $misi->function_ownerships_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Misi masih memiliki data terkait.');

            return;
        }

        $missionId = $misi->mission_id;

        DB::transaction(function () use ($misi) {
            TestCase::query()->where('mission_id', $misi->mission_id)->delete();
            $misi->delete();
        });

        $this->hapusMissionId = null;

        if ($this->misiId === $missionId) {
            $this->misiId = null;
        }

        $this->page = 1;
        unset($this->rows, $this->misi, $this->kasusUji, $this->fungsiMisi);

        $this->dispatch('toast', type: 'sukses', message: 'Misi berhasil dihapus.');
    }

    // ---------- CRUD kasus uji ----------

    public function bukaTambahKasus(): void
    {
        $this->authorize('create', TestCase::class);

        $this->kasusForm->resetForm();
        $this->kasusForm->mission_id = $this->misiId;
        $this->resetValidation();

        $this->dispatch('modal-kasus-open');
    }

    public function bukaUbahKasus(int $kasusId): void
    {
        $kasus = TestCase::findOrFail($kasusId);

        $this->authorize('update', $kasus);

        $this->kasusForm->setKasusUji($kasus);
        $this->resetValidation();

        $this->dispatch('modal-kasus-open');
    }

    public function simpanKasus(): void
    {
        $data = $this->kasusForm->validate();

        $validator = new ValidasiKasusUji;

        if (($pesan = $validator->periksa($data)) !== null) {
            $this->addError('kasusForm.check_target', $pesan);

            return;
        }

        $missionId = (int) $data['mission_id'];
        $setup = ($data['setup_code'] ?? '') === '' ? null : $data['setup_code'];

        $duplikat = TestCase::query()
            ->where('mission_id', $missionId)
            ->where('setup_code', $setup)
            ->where('check_target', $data['check_target'])
            ->where('expected_value', $data['expected_value'])
            ->when($this->kasusForm->testCaseId !== null, fn ($q) => $q->where('test_case_id', '!=', $this->kasusForm->testCaseId))
            ->exists();

        if ($duplikat) {
            $this->addError('kasusForm.check_target', 'Kasus uji ini sudah ada (kombinasi input awal, target, dan nilai harapan sama).');

            return;
        }

        if ($this->kasusForm->testCaseId === null) {
            $this->authorize('create', TestCase::class);

            TestCase::create([
                'mission_id' => $missionId,
                'label' => $data['label'],
                'setup_code' => $setup,
                'check_target' => $data['check_target'],
                'expected_value' => $data['expected_value'],
                'tolerance' => ($data['tolerance'] ?? '') === '' ? 0 : $data['tolerance'],
            ]);

            $pesan = 'Kasus uji berhasil ditambahkan.';
        } else {
            $kasus = TestCase::findOrFail($this->kasusForm->testCaseId);

            $this->authorize('update', $kasus);

            $kasus->fill([
                'label' => $data['label'],
                'setup_code' => $setup,
                'check_target' => $data['check_target'],
                'expected_value' => $data['expected_value'],
                'tolerance' => ($data['tolerance'] ?? '') === '' ? 0 : $data['tolerance'],
            ])->save();

            $pesan = 'Kasus uji berhasil diperbarui.';
        }

        $this->kasusForm->resetForm();
        $this->resetValidation();
        unset($this->kasusUji, $this->fungsiMisi, $this->rows);

        $this->dispatch('modal-kasus-close');
        $this->dispatch('toast', type: 'sukses', message: $pesan);
    }

    public function mintaHapusKasus(int $kasusId): void
    {
        $kasus = TestCase::query()->withCount('submissionResults')->findOrFail($kasusId);

        $this->authorize('delete', $kasus);

        if ((int) $kasus->submission_results_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: "Kasus uji \"{$kasus->label}\" sudah punya hasil submission — ubah saja, tidak bisa dihapus.");

            return;
        }

        $this->hapusKasusId = $kasus->test_case_id;
        $this->pesanKonfirmasiKasus = "Hapus kasus uji \"{$kasus->label}\"?";

        $this->dispatch('konfirmasi-kasus-buka');
    }

    public function hapusKasus(): void
    {
        if ($this->hapusKasusId === null) {
            return;
        }

        $kasus = TestCase::query()->withCount('submissionResults')->findOrFail($this->hapusKasusId);

        $this->authorize('delete', $kasus);

        if ((int) $kasus->submission_results_count > 0) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kasus uji sudah punya hasil submission — ubah saja, tidak bisa dihapus.');

            return;
        }

        $kasus->delete();

        $this->hapusKasusId = null;
        unset($this->kasusUji, $this->fungsiMisi, $this->rows);

        $this->dispatch('toast', type: 'sukses', message: 'Kasus uji berhasil dihapus.');
    }

    // ---------- Tambah banyak sekaligus ----------

    public function bukaMassal(): void
    {
        $this->authorize('create', TestCase::class);

        $this->teksMassal = '';
        $this->massalValid = [];
        $this->massalInvalid = [];
        $this->pratinjauMassal = false;
        $this->resetValidation();

        $this->dispatch('modal-massal-open');
    }

    public function pratinjauMassal(): void
    {
        $this->validate([
            'teksMassal' => ['required', 'string'],
        ], [
            'teksMassal.required' => 'Tempelkan minimal satu baris kasus uji.',
        ]);

        $hasil = (new ParserBarisKasusUji)->parse($this->teksMassal);

        $this->massalValid = $hasil['valid'];
        $this->massalInvalid = $hasil['invalid'];
        $this->pratinjauMassal = true;
    }

    public function simpanMassal(): void
    {
        $this->authorize('create', TestCase::class);

        if ($this->massalValid === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Tidak ada baris valid untuk disimpan.');

            return;
        }

        $missionId = $this->misiId;
        $valid = $this->massalValid;

        DB::transaction(function () use ($missionId, $valid) {
            foreach ($valid as $baris) {
                TestCase::create([
                    'mission_id' => $missionId,
                    'label' => $baris['label'],
                    'setup_code' => $baris['setup_code'],
                    'check_target' => $baris['check_target'],
                    'expected_value' => $baris['expected_value'],
                    'tolerance' => $baris['tolerance'] ?? 0,
                ]);
            }
        });

        $jumlah = count($valid);
        $bermasalah = count($this->massalInvalid);

        $this->teksMassal = '';
        $this->massalValid = [];
        $this->massalInvalid = [];
        $this->pratinjauMassal = false;
        unset($this->kasusUji, $this->fungsiMisi, $this->rows);

        $this->dispatch('modal-massal-close');
        $this->dispatch('toast', type: 'sukses', message: "{$jumlah} kasus uji ditambahkan".($bermasalah > 0 ? ", {$bermasalah} baris dilewati." : '.'));
    }

    public function kembaliKeMassal(): void
    {
        $this->pratinjauMassal = false;
        $this->massalValid = [];
        $this->massalInvalid = [];
        $this->resetValidation();
    }

    // ---------- Kepemilikan fungsi ----------

    public function updatedFungsiKelasId(): void
    {
        $this->pemilik = [];
        $this->pratinjauBagi = [];
        $this->resetValidation();

        unset($this->kelompokFungsi);

        if ($this->fungsiKelasId === null) {
            return;
        }

        // Otorisasi kelas SELALU dijalankan sebelum pengecekan lain, agar guru
        // tidak bisa memuat kelas milik guru lain (403).
        $kelas = SchoolClass::findOrFail($this->fungsiKelasId);

        abort_unless(auth()->user()->can('view', $kelas), 403);

        if ($this->misiId === null) {
            return;
        }

        $teamIds = Team::query()->where('class_id', $this->fungsiKelasId)->pluck('team_id')->all();

        $tersimpan = FunctionOwnership::query()
            ->where('mission_id', $this->misiId)
            ->whereIn('team_id', $teamIds)
            ->get();

        foreach ($tersimpan as $baris) {
            $this->pemilik[$baris->team_id][$baris->function_name] = $baris->student_id;
        }
    }

    public function bagiOtomatis(): void
    {
        if ($this->fungsiKelasId === null) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih kelas terlebih dahulu.');

            return;
        }

        $fungsi = $this->fungsiMisi;

        if ($fungsi === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Belum ada fungsi yang bisa dibagi. Tambahkan kasus uji fungsi terlebih dahulu.');

            return;
        }

        $action = new BagiFungsiOtomatis;
        $this->pratinjauBagi = [];

        foreach ($this->kelompokFungsi as $team) {
            $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();

            $this->pratinjauBagi[$team->team_id] = $action->petaPemilik($fungsi, $anggota);
        }

        $this->dispatch('toast', type: 'info', message: 'Pratinjau pembagian siap. Klik "Terapkan" lalu "Simpan Semua".');
    }

    public function terapkanBagiOtomatis(): void
    {
        if ($this->pratinjauBagi === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Belum ada pratinjau pembagian.');

            return;
        }

        foreach ($this->pratinjauBagi as $teamId => $peta) {
            foreach ($peta as $studentId => $daftarFungsi) {
                foreach ($daftarFungsi as $namaFungsi) {
                    $this->pemilik[$teamId][$namaFungsi] = (int) $studentId;
                }
            }
        }

        $this->pratinjauBagi = [];

        $this->dispatch('toast', type: 'info', message: 'Pembagian diterapkan ke form. Klik "Simpan Semua" untuk menyimpan.');
    }

    /**
     * Status kelengkapan kepemilikan satu kelompok.
     */
    public function statusFungsiKelompok(int $teamId): string
    {
        $team = $this->kelompokFungsi->firstWhere('team_id', $teamId);

        if ($team === null || $this->misiId === null) {
            return 'kosong';
        }

        return (new BagiFungsiOtomatis)->statusKelompok($team, $this->fungsiMisi, $this->misiId);
    }

    public function simpanKepemilikan(): void
    {
        if ($this->fungsiKelasId === null || $this->misiId === null) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih kelas terlebih dahulu.');

            return;
        }

        $misi = $this->misiAktif();
        $fungsi = $this->fungsiMisi;

        if ($fungsi === []) {
            $this->dispatch('toast', type: 'peringatan', message: 'Belum ada fungsi pada misi ini.');

            return;
        }

        $errors = [];

        foreach ($this->kelompokFungsi as $team) {
            $anggota = $team->teamMembers->pluck('student_id')->map(fn ($id) => (int) $id)->all();
            $petaTeam = $this->pemilik[$team->team_id] ?? [];

            foreach ($fungsi as $namaFungsi) {
                $pemilikId = $petaTeam[$namaFungsi] ?? null;

                if ($pemilikId === null) {
                    $errors[] = "Fungsi {$namaFungsi} pada \"{$team->team_name}\" belum punya pemilik.";

                    continue;
                }

                if (! in_array((int) $pemilikId, $anggota, true)) {
                    $errors[] = "Pemilik fungsi {$namaFungsi} harus anggota kelompok \"{$team->team_name}\".";
                }
            }
        }

        if ($errors !== []) {
            $this->dispatch('toast', type: 'bahaya', message: implode(' ', array_slice($errors, 0, 3)));

            return;
        }

        $teamIds = $this->kelompokFungsi->pluck('team_id')->all();

        $adaSubmission = \App\Models\Submission::query()
            ->where('mission_id', $misi->mission_id)
            ->whereIn('team_id', $teamIds)
            ->exists();

        if ($adaSubmission) {
            $this->dispatch('toast', type: 'bahaya', message: 'Kepemilikan fungsi tidak bisa diubah karena sudah ada kelompok yang mengirim submission untuk misi ini.');

            return;
        }

        $pemilik = $this->pemilik;
        $missionId = $misi->mission_id;
        $kelompok = $this->kelompokFungsi;

        DB::transaction(function () use ($pemilik, $missionId, $kelompok, $fungsi) {
            foreach ($kelompok as $team) {
                foreach ($fungsi as $namaFungsi) {
                    $pemilikId = $pemilik[$team->team_id][$namaFungsi] ?? null;

                    if ($pemilikId === null) {
                        continue;
                    }

                    FunctionOwnership::updateOrCreate(
                        [
                            'mission_id' => $missionId,
                            'team_id' => $team->team_id,
                            'function_name' => $namaFungsi,
                        ],
                        ['student_id' => (int) $pemilikId],
                    );
                }
            }
        });

        unset($this->kelompokFungsi);

        $this->dispatch('toast', type: 'sukses', message: 'Kepemilikan fungsi berhasil disimpan.');
    }
}; ?>

<div>
    @if ($this->misi === null)
        {{-- ============ TAMPILAN DAFTAR ============ --}}
        <x-ui.page-header title="Misi & Kasus Uji" description="Kelola misi per level beserta bank kasus uji yang menjadi kunci penilaian.">
            <x-slot:actions>
                <x-ui.button variant="primary" wire:click="bukaTambah">
                    <x-ui.icon.plus class="size-4" />
                    Tambah Misi
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.alert variant="info" class="mb-4">
            Perubahan materi berlaku untuk semua kelas dan semua guru.
            Kasus uji adalah <strong>bank jawaban rahasia</strong> — jangan dibagikan ke siswa.
        </x-ui.alert>

        <x-ui.card class="mb-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-ui.select
                    label="Filter Level"
                    name="filterLevelId"
                    :options="$this->opsiLevel"
                    placeholder="Semua level"
                    wire:model.live="filterLevelId"
                />
            </div>
        </x-ui.card>

        <x-ui.data-table
            :rows="$this->rows"
            :columns="$this->columns"
            searchable
            search-placeholder="Cari kode unlock atau isi brief..."
            :sort-property="$sortBy"
            :sort-direction="$sortDir"
            empty-title="Belum ada misi"
            empty-message="Tambahkan misi untuk level, lalu susun kasus ujinya."
        />
    @else
        {{-- ============ TAMPILAN DETAIL ============ --}}
        @php $misiAktif = $this->misi; @endphp

        <x-ui.page-header :title="'Misi '.$misiAktif->unlock_code" :description="$misiAktif->level?->level_name ?? ''">
            <x-slot:actions>
                <x-ui.button variant="outline" wire:click="kembaliKeDaftar">
                    <x-ui.icon.chevron-left class="size-4" />
                    Kembali ke Daftar
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="bukaUbah({{ $misiAktif->mission_id }})">
                    <x-ui.icon.pencil class="size-4" />
                    Ubah Misi
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @php
            $tabs = [
                ['key' => 'ringkasan', 'label' => 'Ringkasan'],
                ['key' => 'kasus', 'label' => 'Kasus Uji', 'count' => $this->kasusUji->count()],
            ];

            if ($misiAktif->level?->is_summative) {
                $tabs[] = ['key' => 'fungsi', 'label' => 'Kepemilikan Fungsi', 'count' => count($this->fungsiMisi)];
            }
        @endphp

        <x-ui.tabs :tabs="$tabs" :active="$activeTab" property="activeTab" />

        <div class="mt-6">
            @if ($activeTab === 'ringkasan')
                @include('pages.admin.partials.misi-ringkasan', ['misi' => $misiAktif])
            @elseif ($activeTab === 'kasus')
                @include('pages.admin.partials.misi-kasus-uji', ['misi' => $misiAktif])
            @elseif ($activeTab === 'fungsi')
                @include('pages.admin.partials.misi-kepemilikan-fungsi', ['misi' => $misiAktif])
            @endif
        </div>
    @endif

    {{-- Modal tambah/ubah misi --}}
    <x-ui.modal name="modal-misi" title="Misi">
        <form wire:submit="simpanMisi" class="space-y-4">
            <x-ui.select
                label="Level"
                name="form.level_id"
                :options="$this->opsiLevel"
                wire:model.live="form.level_id"
                required
            />

            <div class="flex items-end gap-2">
                <x-ui.input
                    label="Kode Unlock"
                    name="form.unlock_code"
                    wire:model.blur="form.unlock_code"
                    placeholder="KOP-L1-BUDI"
                    help="Huruf besar, angka, tanda hubung. Maks 30 karakter."
                    class="flex-1"
                    required
                />
                <x-ui.button variant="outline" type="button" wire:click="buatKodeOtomatis" class="shrink-0">
                    <x-ui.icon.refresh class="size-4" />
                    Buat Kode Otomatis
                </x-ui.button>
            </div>

            <x-ui.textarea
                label="Brief Klien"
                name="form.client_brief"
                rows="8"
                wire:model="form.client_brief"
                placeholder="Tuliskan brief/permintaan klien. Baris baru didukung."
                required
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-misi-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Misi</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal duplikat misi --}}
    <x-ui.modal name="modal-duplikat" title="Duplikat Misi">
        <form wire:submit="simpanDuplikat" class="space-y-4">
            <x-ui.alert variant="info">
                Seluruh kasus uji akan disalin. Kepemilikan fungsi <strong>tidak</strong> ikut disalin.
            </x-ui.alert>

            <x-ui.input
                label="Kode Unlock Baru"
                name="kodeDuplikat"
                wire:model.blur="kodeDuplikat"
                help="Huruf besar, angka, dan tanda hubung. Harus unik."
                required
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-duplikat-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Duplikat Sekarang</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusMisi"
        title="Hapus Misi"
        property="pesanKonfirmasi"
        confirm-label="Ya, hapus"
    />

    {{-- Modal tambah/ubah kasus uji --}}
    <x-ui.modal name="modal-kasus" title="Kasus Uji">
        <form wire:submit="simpanKasus" class="space-y-4">
            <x-ui.input label="Label" name="kasusForm.label" wire:model="kasusForm.label" placeholder="Contoh: Status pinjaman lunas" required autofocus />

            <x-ui.input
                label="Input Awal (opsional)"
                name="kasusForm.setup_code"
                wire:model="kasusForm.setup_code"
                placeholder="saldo = 10_000_000"
                help="Hanya assignment sederhana, satu per baris. Contoh: saldo = 10_000_000"
            />

            <x-ui.input
                label="Target Pengecekan"
                name="kasusForm.check_target"
                wire:model="kasusForm.check_target"
                placeholder="status  atau  hitung_total(100)"
                help="Nama variabel (mis. status) atau satu pemanggilan fungsi (mis. hitung_total(100))."
                required
            />

            <x-ui.input
                label="Nilai Diharapkan"
                name="kasusForm.expected_value"
                wire:model="kasusForm.expected_value"
                placeholder="1500  atau  3.5  atau  &quot;lunas&quot;  atau  True"
                help='Literal skalar saja: bilangan (1500, 3.5), string dalam kutip ("lunas"), True, False, atau None. Tidak boleh list atau ekspresi.'
                required
            />

            <x-ui.input
                label="Toleransi (opsional)"
                name="kasusForm.tolerance"
                type="number"
                step="0.01"
                wire:model="kasusForm.tolerance"
                placeholder="0"
                help="Hanya untuk nilai numerik. Contoh 1 untuk rentang ±1."
            />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="outline" x-on:click="$dispatch('modal-kasus-close')">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit">Simpan Kasus Uji</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal tambah banyak sekaligus --}}
    <x-ui.modal name="modal-massal" title="Tambah Banyak Sekaligus">
        @if (! $pratinjauMassal)
            <form wire:submit="pratinjauMassal" class="space-y-4">
                <x-ui.alert variant="info">
                    Satu baris satu kasus uji, dipisah tanda pipa:<br>
                    <code class="font-mono text-xs">label | input_awal | target | nilai_diharapkan | toleransi</code><br>
                    Kolom input awal dan toleransi boleh dikosongkan.
                </x-ui.alert>

                <x-ui.textarea
                    label="Baris Kasus Uji"
                    name="teksMassal"
                    rows="10"
                    wire:model="teksMassal"
                    placeholder="Status lunas | saldo = 10_000_000 | status | &quot;lunas&quot; | "
                    required
                />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" x-on:click="$dispatch('modal-massal-close')">Batal</x-ui.button>
                    <x-ui.button variant="primary" type="submit">Pratinjau</x-ui.button>
                </div>
            </form>
        @else
            <div class="space-y-4">
                <x-ui.alert :variant="count($massalInvalid) > 0 ? 'peringatan' : 'sukses'">
                    <strong>{{ count($massalValid) }}</strong> baris valid,
                    <strong>{{ count($massalInvalid) }}</strong> baris bermasalah.
                </x-ui.alert>

                @if (count($massalInvalid) > 0)
                    <div class="max-h-64 overflow-y-auto rounded-lg border border-line">
                        <table class="min-w-full divide-y divide-line text-xs">
                            <thead class="bg-page/60">
                                <tr>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Baris</th>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Isi</th>
                                    <th class="px-3 py-2 text-start font-semibold text-slate-500">Alasan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line bg-white">
                                @foreach ($massalInvalid as $baris)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-500">{{ $baris['line'] }}</td>
                                        <td class="px-3 py-2 font-mono text-slate-700">{{ $baris['raw'] }}</td>
                                        <td class="px-3 py-2 text-rose-600">{{ $baris['reason'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <x-ui.button variant="outline" wire:click="kembaliKeMassal">Kembali</x-ui.button>
                    <x-ui.button variant="primary" wire:click="simpanMassal" @disabled(count($massalValid) === 0)>
                        Simpan {{ count($massalValid) }} Baris Valid
                    </x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.modal>

    <x-ui.confirm-dialog
        action="hapusKasus"
        title="Hapus Kasus Uji"
        property="pesanKonfirmasiKasus"
        confirm-label="Ya, hapus"
        event="konfirmasi-kasus-buka"
    />
</div>
