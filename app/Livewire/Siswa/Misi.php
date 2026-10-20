<?php

namespace App\Livewire\Siswa;

use App\Models\CodeDraft;
use App\Models\ConceptCard;
use App\Models\FunctionOwnership;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\MissionPlanConcept;
use App\Models\Reflection;
use App\Models\Role;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Services\CodeDraftService;
use App\Services\LevelAccessService;
use App\Services\RoleService;
use App\Services\SubmissionComposer;
use App\Services\SubmissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.siswa')]
#[Title('Misi')]
class Misi extends Component
{
    public Mission $misi;

    public string $langkah = 'kartu';

    public string $stepsNote = '';

    /** @var array<int, int> concept_id yang dipilih */
    public array $dipilih = [];

    // ---------- Editor ----------
    public string $kode = '';

    public int $versi = 1;

    public ?int $draftId = null;

    public string $simpanInfo = '';

    public bool $konflik = false;

    // ---------- Catatan ----------
    public string $body = '';

    public string $type = '';

    public function mount(Mission $mission): void
    {
        $this->misi = $mission->load('level');

        abort_unless($this->bolehAkses(), 404);

        $this->muatRencana();
        $this->muatDrafUtama();
        $this->muatLangkahAwal();
    }

    /**
     * Halaman misi hanya bisa dibuka oleh kelompok sendiri + level terbuka.
     */
    private function bolehAkses(): bool
    {
        $siswa = $this->siswa();

        if ($siswa === null || $siswa->team() === null) {
            return false;
        }

        return app(LevelAccessService::class)->canWork($siswa->team(), $this->misi);
    }

    private function muatRencana(): void
    {
        $rencana = $this->rencana();

        if ($rencana === null) {
            return;
        }

        $this->dipilih = $rencana->planConcepts()->pluck('concept_id')->map(fn ($id) => (int) $id)->all();
        $this->stepsNote = (string) ($rencana->steps_note ?? '');
    }

    private function muatDrafUtama(): void
    {
        $draf = $this->drafUtama();

        if ($draf !== null) {
            $this->kode = (string) $draf->code_text;
            $this->versi = (int) $draf->version;
            $this->draftId = (int) $draf->id;
        } else {
            $this->kode = (string) $this->misi->starter_code;
            $this->versi = 1;
            $this->draftId = null;
        }
    }

    private function muatLangkahAwal(): void
    {
        if ($this->misi->level->is_summative) {
            $this->langkah = 'kartu';

            return;
        }

        if ($this->adaSubmission()) {
            $this->langkah = 'hasil';

            return;
        }

        if ($this->rencana()?->terkunci()) {
            $this->langkah = 'koding';

            return;
        }

        $this->langkah = 'kartu';
    }

    #[Computed]
    public function siswa(): Student
    {
        return Auth::guard('siswa')->user();
    }

    #[Computed]
    public function team(): Team
    {
        return $this->siswa->team();
    }

    #[Computed]
    public function rencana(): ?MissionPlan
    {
        return MissionPlan::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->first();
    }

    /**
     * Lima kartu konsep beserta status terpilih.
     *
     * @return Collection<int, ConceptCard>
     */
    #[Computed]
    public function kartuKonsep(): Collection
    {
        return ConceptCard::query()->orderBy('concept_id')->get();
    }

    /**
     * Draf program utama (function_name kosong).
     */
    #[Computed]
    public function drafUtama(): ?CodeDraft
    {
        return CodeDraft::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->where('function_name', CodeDraft::PROGRAM_UTAMA)
            ->first();
    }

    #[Computed]
    public function rencanaTerkunci(): bool
    {
        return $this->rencana()?->terkunci() ?? false;
    }

    #[Computed]
    public function adaSubmission(): bool
    {
        return Submission::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->exists();
    }

    #[Computed]
    public function submissionTerakhir(): ?Submission
    {
        return Submission::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id')
            ->first();
    }

    #[Computed]
    public function isSumatif(): bool
    {
        return (bool) $this->misi->level->is_summative;
    }

    /**
     * Draf per fungsi untuk level sumatif (urut penanda starter_code).
     *
     * @return Collection<int, array{function_name: string, draft: ?CodeDraft, owner: ?Student}>
     */
    #[Computed]
    public function drafFungsi(): Collection
    {
        $urutan = app(SubmissionComposer::class)->urutanFungsi((string) $this->misi->starter_code);

        $draf = CodeDraft::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->where('function_name', '!=', CodeDraft::PROGRAM_UTAMA)
            ->get()
            ->keyBy('function_name');

        $pemilik = FunctionOwnership::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->get()
            ->keyBy('function_name');

        return $urutan->map(function (string $nama) use ($draf, $pemilik) {
            $milik = $pemilik->get($nama);

            return [
                'function_name' => $nama,
                'draft' => $draf->get($nama),
                'owner' => $milik?->student,
            ];
        })->values();
    }

    /**
     * Peran siswa saat ini (null bila tidak ditugaskan).
     */
    #[Computed]
    public function peranSaya(): ?string
    {
        return app(RoleService::class)->peran($this->team, $this->siswa, (int) $this->misi->level_id);
    }

    #[Computed]
    public function penegakanAktif(): bool
    {
        return app(RoleService::class)->penegakanAktif($this->team, (int) $this->misi->level_id);
    }

    #[Computed]
    public function bolehEditKode(): bool
    {
        return app(RoleService::class)->boleh($this->team, $this->siswa, (int) $this->misi->level_id, [RoleService::PERAN_CODER]);
    }

    #[Computed]
    public function bolehKunciRencana(): bool
    {
        return app(RoleService::class)->boleh($this->team, $this->siswa, (int) $this->misi->level_id, [RoleService::PERAN_ANALYST]);
    }

    #[Computed]
    public function bolehSubmit(): bool
    {
        return app(RoleService::class)->boleh($this->team, $this->siswa, (int) $this->misi->level_id, [RoleService::PERAN_CODER]);
    }

    #[Computed]
    public function bolehTinjau(): bool
    {
        return app(RoleService::class)->boleh($this->team, $this->siswa, (int) $this->misi->level_id, [RoleService::PERAN_REVIEWER]);
    }

    /**
     * Refleksi per jenis untuk siswa saat ini (key = type).
     *
     * @return Collection<int, Reflection>
     */
    #[Computed]
    public function refleksiSaya(): Collection
    {
        return Reflection::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->where('student_id', $this->siswa->student_id)
            ->get()
            ->keyBy('type');
    }

    public function toggleKonsep(int $conceptId): void
    {
        if ($this->rencanaTerkunci) {
            return;
        }

        $this->dipilih = in_array($conceptId, $this->dipilih, true)
            ? array_values(array_diff($this->dipilih, [$conceptId]))
            : [...$this->dipilih, $conceptId];
    }

    public function simpanStepsNote(): void
    {
        if ($this->rencanaTerkunci) {
            return;
        }

        $this->validate(['stepsNote' => ['nullable', 'string', 'max:1000']]);

        $this->rencana()->update(['steps_note' => $this->stepsNote]);

        $this->dispatch('toast', type: 'sukses', message: 'Urutan langkah disimpan.');
    }

    public function kunciRencana(): void
    {
        if ($this->rencanaTerkunci) {
            return;
        }

        if (! $this->bolehKunciRencana) {
            $this->dispatch('toast', type: 'peringatan', message: 'Hanya Analyst yang boleh mengunci rencana.');

            return;
        }

        if (count($this->dipilih) === 0) {
            $this->dispatch('toast', type: 'peringatan', message: 'Pilih minimal satu kartu konsep.');

            return;
        }

        $rencana = $this->rencana();

        if ($rencana === null) {
            $rencana = MissionPlan::create([
                'team_id' => $this->team->team_id,
                'mission_id' => $this->misi->mission_id,
                'planned_at' => now(),
                'unlocked_by' => $this->siswa->student_id,
            ]);
        }

        $rencana->update(['locked_at' => now(), 'steps_note' => $this->stepsNote]);

        $rencana->planConcepts()->delete();
        foreach ($this->dipilih as $conceptId) {
            MissionPlanConcept::create(['plan_id' => $rencana->plan_id, 'concept_id' => $conceptId]);
        }

        unset($this->rencana, $this->rencanaTerkunci);

        $this->langkah = 'koding';
        $this->dispatch('toast', type: 'sukses', message: 'Rencana dikunci. Selamat mengoding!');
    }

    /**
     * Autosave draf program utama (dipanggil dari CodeMirror via JS).
     */
    public function simpanDraf(): void
    {
        if (! $this->bolehEditKode) {
            return;
        }

        $servis = app(CodeDraftService::class);

        // Inisialisasi bila belum ada draf.
        $draf = $this->drafUtama();

        if ($draf === null) {
            $draf = CodeDraft::create([
                'team_id' => $this->team->team_id,
                'mission_id' => $this->misi->mission_id,
                'function_name' => CodeDraft::PROGRAM_UTAMA,
                'code_text' => $this->kode,
                'version' => 1,
                'updated_by' => $this->siswa->student_id,
            ]);

            $this->draftId = (int) $draf->id;
            $this->versi = (int) $draf->version;
            $this->simpanInfo = 'Tersimpan '.now()->format('H:i:s');
            unset($this->drafUtama);

            return;
        }

        $hasil = $servis->simpan($draf, $this->kode, $this->siswa, $this->versi);

        if ($hasil['status'] === CodeDraftService::KONFLIK) {
            $this->konflik = true;

            return;
        }

        $this->versi = (int) $hasil['draft']->version;
        $this->draftId = (int) $hasil['draft']->id;
        $this->konflik = false;
        $this->simpanInfo = 'Tersimpan '.now()->format('H:i:s');
        unset($this->drafUtama);
    }

    public function muatVersiTerbaru(): void
    {
        $draf = $this->drafUtama();

        if ($draf !== null) {
            $this->kode = (string) $draf->code_text;
            $this->versi = (int) $draf->version;
        }

        $this->konflik = false;
    }

    public function timpaVersiku(): void
    {
        $draf = $this->drafUtama();

        if ($draf === null) {
            return;
        }

        $draf->update([
            'code_text' => $this->kode,
            'version' => (int) $draf->version + 1,
            'updated_by' => $this->siswa->student_id,
        ]);

        $this->versi = (int) $draf->version;
        $this->konflik = false;
        $this->simpanInfo = 'Tersimpan '.now()->format('H:i:s');
        unset($this->drafUtama);
    }

    /**
     * Tandai draf sudah ditinjau (Reviewer).
     */
    public function tandaiDitinjau(): void
    {
        if (! $this->bolehTinjau) {
            $this->dispatch('toast', type: 'peringatan', message: 'Hanya Reviewer yang boleh menandai sudah ditinjau.');

            return;
        }

        $draf = $this->drafUtama();

        if ($draf !== null) {
            app(CodeDraftService::class)->tandaiDitinjau($draf, $this->siswa);
            $this->dispatch('toast', type: 'sukses', message: 'Versi saat ini ditandai sudah ditinjau.');
            unset($this->drafUtama);
        }
    }

    /**
     * Submit tim (L1-3: kode tunggal; L4: gabungan fungsi).
     */
    public function submit(): void
    {
        if ($this->isSumatif) {
            $this->submitSumatif();

            return;
        }

        $hasil = app(SubmissionService::class)->submit(
            $this->team,
            $this->misi,
            $this->siswa,
            $this->kode,
            [],
        );

        $this->tanggapiSubmit($hasil);
    }

    private function submitSumatif(): void
    {
        $composer = app(SubmissionComposer::class);
        $urutan = $composer->urutanFungsi((string) $this->misi->starter_code);

        $draf = CodeDraft::query()
            ->where('team_id', $this->team->team_id)
            ->where('mission_id', $this->misi->mission_id)
            ->get();

        $susun = $composer->susun($urutan, $draf);

        $hasil = app(SubmissionService::class)->submit(
            $this->team,
            $this->misi,
            $this->siswa,
            $susun['code'],
            $susun['parts']->all(),
        );

        $this->tanggapiSubmit($hasil);
    }

    /**
     * @param  array{status: string, submission?: Submission, tunggu?: int, pesan?: string}  $hasil
     */
    private function tanggapiSubmit(array $hasil): void
    {
        match ($hasil['status']) {
            'ok' => $this->langkah = 'hasil',
            SubmissionService::ERR_HAK_PERAN => $this->dispatch('toast', type: 'peringatan', message: 'Hanya Coder yang boleh mengirim kode.'),
            SubmissionService::ERR_UKURAN => $this->dispatch('toast', type: 'bahaya', message: 'Kode terlalu besar (maks 20000 byte).'),
            SubmissionService::ERR_NUL => $this->dispatch('toast', type: 'bahaya', message: 'Kode mengandung karakter tidak valid.'),
            SubmissionService::ERR_JEDA => $this->dispatch('toast', type: 'peringatan', message: 'Tunggu '.($hasil['tunggu'] ?? 0).' detik sebelum submit lagi.'),
            SubmissionService::ERR_TANPA_KASUS => $this->dispatch('toast', type: 'peringatan', message: 'Misi ini belum punya kasus uji.'),
            SubmissionService::ERR_BUKAN_LEVEL => $this->dispatch('toast', type: 'peringatan', message: 'Misi tidak bisa dikerjakan sekarang.'),
            default => $this->dispatch('toast', type: 'bahaya', message: 'Terjadi kesalahan saat mengirim.'),
        };
    }

    /**
     * Simpan/update catatan & refleksi.
     */
    public function simpanCatatan(string $type): void
    {
        $this->type = $type;
        $this->loadCatatan();

        $this->validate(['body' => ['required', 'string', 'min:20', 'max:1000']]);

        Reflection::updateOrCreate(
            [
                'team_id' => $this->team->team_id,
                'mission_id' => $this->misi->mission_id,
                'student_id' => $this->siswa->student_id,
                'type' => $type,
            ],
            [
                'role_id' => $this->peranSaya() !== null
                    ? Role::query()->where('role_name', $this->peranSaya())->value('role_id')
                    : null,
                'body' => $this->body,
            ],
        );

        unset($this->refleksiSaya);

        $this->dispatch('toast', type: 'sukses', message: 'Catatan disimpan.');
    }

    public function muatCatatanKe(string $type): void
    {
        $this->type = $type;
        $this->loadCatatan();
    }

    private function loadCatatan(): void
    {
        $ada = $this->refleksiSaya->get($this->type);

        $this->body = $ada?->body ?? '';
    }

    public function render()
    {
        return view('livewire.siswa.misi');
    }
}
