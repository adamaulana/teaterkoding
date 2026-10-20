<?php

use App\Contracts\GradingRunner;
use App\Jobs\GradeBugAttemptJob;
use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\BugAttemptService;
use App\Services\BugService;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Test di berkas ini menguji jalur QUEUE (FakeGradingRunner + job), jadi runner
 * dikunci eksplisit. Jangan bergantung pada .env: nilai default proyek kini
 * 'pyodide' (penilaian di browser) yang tidak memakai job sama sekali.
 */
beforeEach(function () {
    config(['teater.runner' => 'fake']);
});


function konteksBug(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug Alpha',
        'symptom_text' => 'Output salah.',
        'buggy_code' => 'print(0)',
        'hint_text' => 'Cek lagi.',
        'fix_explanation' => 'KUNCI_RAHASIA_XYZ',
        'points' => 15,
    ]);

    BugTestCase::create(['bug_id' => $kartu->bug_id, 'label' => 'Kasus A', 'check_target' => 'hasil', 'expected_value' => 'NILAI_RAHASIA_ABC']);

    $guru = User::factory()->create(['role' => 'guru', 'is_active' => true]);
    $hunter = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Hunter', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $hunter->student_id]);
    RoleAssignment::create([
        'team_id' => $team->team_id,
        'student_id' => $hunter->student_id,
        'level_id' => $level->level_id,
        'role_id' => Role::firstOrCreate(['role_name' => 'Bug Hunter'])->role_id,
    ]);

    return [$kelas, $team, $level, $kartu, $guru, $hunter];
}

test('satu kartu bug tidak bisa dirilis dua kali ke kelompok yang sama', function () {
    [, $team, , $kartu, $guru] = konteksBug();

    $servis = app(BugService::class);

    expect($servis->rilis($team, $kartu, $guru)['status'])->toBe('ok')
        ->and($servis->rilis($team, $kartu, $guru)['status'])->toBe('sudah_dirilis');

    expect(BugAttempt::query()->where('team_id', $team->team_id)->where('bug_id', $kartu->bug_id)->count())->toBe(1);
});

test('siswa kelompok lain tidak bisa membuka bug kelompok lain', function () {
    [, $team, , $kartu, $guru] = konteksBug();

    app(BugService::class)->rilis($team, $kartu, $guru);
    $attempt = BugAttempt::query()->firstOrFail();

    $kelas2 = SchoolClass::create(['class_name' => 'Y RPL', 'school_year' => '2025/2026']);
    $lain = Student::create(['class_id' => $kelas2->class_id, 'full_name' => 'Lain', 'access_code' => 'MNOP2345']);
    $team2 = Team::create(['class_id' => $kelas2->class_id, 'team_name' => 'Beta']);
    TeamMember::create(['team_id' => $team2->team_id, 'student_id' => $lain->student_id]);

    $this->actingAs($lain, 'siswa')->get(route('siswa.bug', $attempt->attempt_id))->assertNotFound();
});



test('setelah lulus fix_explanation ditampilkan', function () {
    [, $team, , $kartu, $guru, $hunter] = konteksBug();

    app(BugService::class)->rilis($team, $kartu, $guru);
    $attempt = BugAttempt::query()->firstOrFail();

    $attempt->update(['fixed_code' => "# demo:lulus\nprint(1)", 'grading_status' => BugAttempt::STATUS_MENUNGGU]);
    (new GradeBugAttemptJob($attempt->attempt_id))->handle(
        app(GradingRunner::class),
        app(ScoringService::class),
    );

    $attempt->refresh();

    expect($attempt->is_solved)->toBeTrue()
        ->and($attempt->solved_at)->not->toBeNull();

    $this->actingAs($hunter, 'siswa')
        ->get(route('siswa.bug', $attempt->attempt_id))
        ->assertOk()
        ->assertSee('KUNCI_RAHASIA_XYZ');

    expect((int) ScoreEntry::query()->where('source_type', 'bug_bonus')->sum('points'))->toBe(15);
});

test('minta petunjuk menaikkan hints_used dan mencatat penalti', function () {
    [, $team, , $kartu, $guru, $hunter] = konteksBug();

    app(BugService::class)->rilis($team, $kartu, $guru);
    $attempt = BugAttempt::query()->firstOrFail();

    $hasil = app(BugAttemptService::class)->mintaPetunjuk($attempt, $hunter);

    expect($hasil['status'])->toBe('ok')
        ->and($attempt->fresh()->hints_used)->toBe(1);

    expect((int) ScoreEntry::query()->where('source_type', 'hint_penalty')->sum('points'))
        ->toBe(-1 * (int) config('teater.hint_penalty', 5));

    app(BugAttemptService::class)->mintaPetunjuk($attempt->fresh(), $hunter);

    expect(ScoreEntry::query()->where('source_type', 'hint_penalty')->count())->toBe(1);
});

test('submit perbaikan mendispatch GradeBugAttemptJob', function () {
    Queue::fake();

    [, $team, , $kartu, $guru, $hunter] = konteksBug();

    app(BugService::class)->rilis($team, $kartu, $guru);
    $attempt = BugAttempt::query()->firstOrFail();

    // Majukan waktu percobaan agar jeda minimal sudah lewat.
    $attempt->update(['attempted_at' => now()->subMinute()]);

    $hasil = app(BugAttemptService::class)->submit($attempt->fresh(), $hunter, 'print(1)');

    expect($hasil['status'])->toBe('ok');

    Queue::assertPushed(GradeBugAttemptJob::class);
});

test('GradeBugAttemptJob idempoten', function () {
    [, $team, , $kartu, $guru] = konteksBug();

    app(BugService::class)->rilis($team, $kartu, $guru);
    $attempt = BugAttempt::query()->firstOrFail();

    $attempt->update(['fixed_code' => "# demo:lulus\nprint(1)", 'grading_status' => BugAttempt::STATUS_MENUNGGU]);

    $job = new GradeBugAttemptJob($attempt->attempt_id);
    $job->handle(app(GradingRunner::class), app(ScoringService::class));

    $jumlah = $attempt->fresh()->results()->count();

    $job->handle(app(GradingRunner::class), app(ScoringService::class));

    expect($attempt->fresh()->results()->count())->toBe($jumlah)
        ->and(ScoreEntry::query()->where('source_type', 'bug_bonus')->count())->toBe(1);
});
