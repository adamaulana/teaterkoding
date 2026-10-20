<?php

use App\Contracts\GradingRunner;
use App\Jobs\GradeSubmissionJob;
use App\Livewire\Siswa\Misi;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Services\ScoringService;
use App\Services\SubmissionService;
use Database\Seeders\ConceptCardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ConceptCardsSeeder::class);

    // Test di berkas ini menguji jalur QUEUE (FakeGradingRunner + job), jadi
    // runner dikunci eksplisit. Jangan bergantung pada .env: nilai default
    // proyek kini 'pyodide' (penilaian di browser) yang tidak memakai job.
    config(['teater.runner' => 'fake']);
});

function buatKonteksMisi(bool $sumatif = false): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => $sumatif ? 4 : 1, 'level_name' => $sumatif ? 'Level 4' : 'Level 1', 'is_summative' => $sumatif]);
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1', 'client_brief' => 'brief', 'starter_code' => $sumatif ? "# === FUNGSI: f ===\ndef f():\n    pass" : 'print(1)']);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $roleCoder = Role::firstOrCreate(['role_name' => 'Coder']);
    $roleAnalyst = Role::firstOrCreate(['role_name' => 'Analyst']);
    $roleReviewer = Role::firstOrCreate(['role_name' => 'Reviewer']);

    $coder = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Coder Satu', 'access_code' => 'ABCD2345']);
    $analyst = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Analyst Satu', 'access_code' => 'EFGH2345']);
    $reviewer = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Reviewer Satu', 'access_code' => 'IJKL2345']);

    foreach ([[$coder, $roleCoder], [$analyst, $roleAnalyst], [$reviewer, $roleReviewer]] as [$s, $r]) {
        TeamMember::create(['team_id' => $team->team_id, 'student_id' => $s->student_id]);
        RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $s->student_id, 'level_id' => $level->level_id, 'role_id' => $r->role_id]);
    }

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'kasus', 'check_target' => 'x', 'expected_value' => '1']);

    return [$kelas, $team, $level, $misi, $coder, $analyst, $reviewer];
}

function siapkanRencana(Mission $misi, Team $team, Student $analyst): void
{
    MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
        'locked_at' => now(),
        'unlocked_by' => $analyst->student_id,
    ]);
}

test('riwayat misi hanya menampilkan submission kelompok sendiri', function () {
    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    // Submission kelompok Alpha.
    app(SubmissionService::class)->submit($team, $misi, $coder, 'print("alpha-rahasia")', []);

    // Kelompok Beta (kelas berbeda).
    $kelas2 = SchoolClass::create(['class_name' => 'Y RPL', 'school_year' => '2025/2026']);
    $team2 = Team::create(['class_id' => $kelas2->class_id, 'team_name' => 'Beta']);
    $lain = Student::create(['class_id' => $kelas2->class_id, 'full_name' => 'Orang Lain', 'access_code' => 'MNOP2345']);
    TeamMember::create(['team_id' => $team2->team_id, 'student_id' => $lain->student_id]);
    ClassLevelAccess::create(['class_id' => $kelas2->class_id, 'level_id' => $misi->level_id, 'is_open' => true]);

    $this->actingAs($lain, 'siswa')
        ->get(route('siswa.misi.riwayat', $misi->mission_id))
        ->assertOk()
        ->assertDontSee('alpha-rahasia');
});

test('kunci rencana hanya untuk Analyst', function () {
    [, $team, , $misi, $coder] = buatKonteksMisi();

    // Coder mencoba kunci rencana → tidak boleh.
    Livewire::actingAs($coder, 'siswa')
        ->test(Misi::class, ['mission' => $misi])
        ->set('dipilih', [1])
        ->call('kunciRencana')
        ->assertNotSet('langkah', 'koding');

    expect(MissionPlan::query()->where('team_id', $team->team_id)->whereNotNull('locked_at')->exists())->toBeFalse();
});

test('kunci rencana berhasil oleh Analyst', function () {
    [, $team, , $misi, , $analyst] = buatKonteksMisi();

    Livewire::actingAs($analyst, 'siswa')
        ->test(Misi::class, ['mission' => $misi])
        ->set('dipilih', [1])
        ->call('kunciRencana')
        ->assertSet('langkah', 'koding');

    expect(MissionPlan::query()->where('team_id', $team->team_id)->whereNotNull('locked_at')->exists())->toBeTrue();
});

test('pipeline submit memvalidasi jeda antar submit', function () {
    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    $servis = app(SubmissionService::class);

    $pertama = $servis->submit($team, $misi, $coder, 'print(1)', []);
    expect($pertama['status'])->toBe('ok');

    $kedua = $servis->submit($team, $misi, $coder, 'print(2)', []);
    expect($kedua['status'])->toBe(SubmissionService::ERR_JEDA);
});

test('pipeline submit menolak byte NUL', function () {
    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    $hasil = app(SubmissionService::class)->submit($team, $misi, $coder, "print(1)\0", []);

    expect($hasil['status'])->toBe(SubmissionService::ERR_NUL);
});

test('pipeline submit menolak kode melebihi max_code_bytes', function () {
    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    $kode = str_repeat('a', 20001);

    $hasil = app(SubmissionService::class)->submit($team, $misi, $coder, $kode, []);

    expect($hasil['status'])->toBe(SubmissionService::ERR_UKURAN);
});

test('pipeline submit mendispatch GradeSubmissionJob', function () {
    Queue::fake();

    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    $hasil = app(SubmissionService::class)->submit($team, $misi, $coder, 'print(1)', []);

    expect($hasil['status'])->toBe('ok');

    Queue::assertPushed(GradeSubmissionJob::class);
});

test('GradeSubmissionJob idempoten', function () {
    [$kelas, $team, , $misi, $coder] = buatKonteksMisi();
    siapkanRencana($misi, $team, $coder);

    $hasil = app(SubmissionService::class)->submit($team, $misi, $coder, "# demo:lulus\nprint(1)", []);

    $job = new GradeSubmissionJob($hasil['submission']->submission_id);
    $job->handle(app(GradingRunner::class), app(ScoringService::class));

    $sub = $hasil['submission']->fresh();
    expect($sub->status)->toBe('dinilai');

    $jumlahHasil = $sub->results()->count();

    // Jalankan ulang → tidak membuat hasil ganda.
    $job->handle(app(GradingRunner::class), app(ScoringService::class));

    expect($sub->fresh()->results()->count())->toBe($jumlahHasil);
});
