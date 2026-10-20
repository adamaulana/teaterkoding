<?php

use App\Contracts\GradingRunner;
use App\Jobs\GradeSubmissionJob;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Services\ScoringService;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function konteksPyodide(bool $sumatif = false): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create([
        'level_number' => $sumatif ? 4 : 1,
        'level_name' => $sumatif ? 'Level 4' : 'Level 1',
        'is_summative' => $sumatif,
    ]);
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1', 'client_brief' => 'brief', 'starter_code' => 'print(1)']);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'kasus', 'check_target' => 'x', 'expected_value' => '1']);

    return [$kelas, $team, $level, $misi, $siswa];
}

test('kolom provenance ada di submissions dan bug_attempts', function () {
    $kolomSub = ['grading_source', 'grading_nonce', 'nonce_expires_at', 'client_report', 'client_runtime_ms', 'pyodide_version', 'verified_at', 'verified_by', 'has_mismatch', 'mismatch_detail'];
    $kolomBug = ['grading_source', 'grading_nonce', 'nonce_expires_at', 'client_report', 'verified_at', 'verified_by', 'has_mismatch', 'mismatch_detail'];

    expect(Schema::hasColumns('submissions', $kolomSub))->toBeTrue()
        ->and(Schema::hasColumns('bug_attempts', $kolomBug))->toBeTrue();
});

test('backfill memberi grading_source server pada baris lama berstatus dinilai', function () {
    [, $team, , $misi, $siswa] = konteksPyodide();

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => true,
        'status' => Submission::STATUS_DINILAI,
        'attempt_no' => 1,
    ]);

    // Simulasikan baris lama (sebelum backfill).
    $submission->forceFill(['grading_source' => null])->save();

    DB::table('submissions')->where('status', 'dinilai')->update(['grading_source' => 'server']);

    expect($submission->fresh()->grading_source)->toBe('server');
});

test('model meng-cast client_report dan mismatch_detail sebagai array', function () {
    [, $team, , $misi, $siswa] = konteksPyodide();

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
        'client_report' => ['hasil' => [['id' => 1, 'tipe' => 'int', 'nilai' => 1]]],
        'mismatch_detail' => ['beda' => [1]],
    ]);

    $fresh = $submission->fresh();

    expect($fresh->client_report)->toBeArray()
        ->and($fresh->mismatch_detail)->toBeArray()
        ->and($fresh->has_mismatch)->toBeFalse();
});

test('label dan varian status menunggu_verifikasi bersifat netral', function () {
    [, $team, , $misi, $siswa] = konteksPyodide(true);

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU_VERIFIKASI,
        'attempt_no' => 1,
    ]);

    expect($submission->labelStatus())->toBe('Menunggu verifikasi')
        ->and($submission->variantStatus())->toBe('netral');
});

test('config pyodide tersedia dengan nilai default yang benar', function () {
    expect(config('teater.pyodide.version'))->toBe('314.0.7')
        ->and(config('teater.pyodide.setup_timeout_ms'))->toBe(3000)
        ->and(config('teater.pyodide.total_timeout_ms'))->toBe(15000)
        ->and(config('teater.pyodide.verify_timeout_multiplier'))->toBe(2)
        ->and(config('teater.pyodide.max_output_chars'))->toBe(2000)
        ->and(config('teater.pyodide.nonce_ttl_minutes'))->toBe(15)
        ->and(config('teater.pyodide.require_verification_for_summative'))->toBeTrue()
        ->and(config('teater.pyodide.allowed_modules'))->toContain('math');
});

test('runner pyodide membuat submission dengan nonce dan TANPA dispatch job', function () {
    Queue::fake();
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPyodide();

    $hasil = app(SubmissionService::class)->submit($team, $misi, $siswa, 'x = 1', []);

    expect($hasil['status'])->toBe('ok');

    $submission = $hasil['submission']->fresh();

    expect($submission->status)->toBe(Submission::STATUS_MENUNGGU)
        ->and($submission->grading_nonce)->not->toBeNull()
        ->and(strlen((string) $submission->grading_nonce))->toBe(64)
        ->and($submission->nonce_expires_at)->not->toBeNull();

    Queue::assertNotPushed(GradeSubmissionJob::class);
});

test('runner fake tetap men-dispatch job seperti sebelumnya', function () {
    Queue::fake();
    config(['teater.runner' => 'fake']);

    [, $team, , $misi, $siswa] = konteksPyodide();

    $hasil = app(SubmissionService::class)->submit($team, $misi, $siswa, 'x = 1', []);

    expect($hasil['status'])->toBe('ok')
        ->and($hasil['submission']->grading_nonce)->toBeNull();

    Queue::assertPushed(GradeSubmissionJob::class);
});

test('job penilaian tidak menilai apa pun saat runner pyodide', function () {
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPyodide();

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    (new GradeSubmissionJob($submission->submission_id))->handle(
        app(GradingRunner::class),
        app(ScoringService::class),
    );

    // Tetap menunggu: server tidak menjalankan kode siswa.
    expect($submission->fresh()->status)->toBe(Submission::STATUS_MENUNGGU)
        ->and($submission->fresh()->results()->count())->toBe(0);
});
