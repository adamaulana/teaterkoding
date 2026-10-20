<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Regresi "Sedang dinilai... macet tanpa error".
 *
 * Dulu cabang status 'menunggu' (runner non-pyodide) memakai wire:poll.2s
 * tanpa batas dan hanya menampilkan teks pasif, sehingga siswa tidak tahu
 * penyebabnya dan server dibanjiri poll selamanya.
 */
uses(RefreshDatabase::class);

function konteksPenantian(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'KOP-L1',
        'client_brief' => 'brief',
        'starter_code' => 'print(1)',
    ]);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'kasus',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    return [$kelas, $team, $misi, $siswa];
}

test('penantian baru memakai poll cepat', function () {
    config(['teater.runner' => 'fake']);

    [, $team, $misi, $siswa] = konteksPenantian();

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now()->subSeconds(5),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertSee('Sedang dinilai...')
        ->assertSee('wire:poll.2s', false);
});

test('penantian lebih dari 60 detik berhenti polling dan menampilkan penyebab', function () {
    config([
        'teater.runner' => 'fake',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 90,
    ]);

    [, $team, $misi, $siswa] = konteksPenantian();

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now()->subMinutes(10),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    // Job basi + runner fake → diagnosa menyebut queue worker.
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{"displayName":"App\\\\Jobs\\\\GradeSubmissionJob"}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->subMinutes(10)->getTimestamp(),
    ]);

    $html = $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertSee('Penilaian belum selesai')
        ->assertSee('Penyebab')
        ->assertSee('queue:work')
        ->assertSee('Periksa Status')
        ->assertDontSee('wire:poll.2s', false)
        ->getContent();

    expect($html)->not->toContain('penilai otomatis sedang sibuk atau belum aktif');
});

test('halaman bug juga menampilkan penyebab setelah menunggu lama', function () {
    config([
        'teater.runner' => 'fake',
        'queue.default' => 'sync',
    ]);

    [$kelas, $team, , $siswa] = konteksPenantian();

    $kartu = BugCard::create([
        'level_id' => 1,
        'bug_title' => 'Bug Lambat',
        'symptom_text' => 'Gejala.',
        'buggy_code' => 'print(0)',
        'fix_explanation' => 'penjelasan',
        'points' => 10,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'is_solved' => false,
        'grading_status' => BugAttempt::STATUS_MENUNGGU,
        'hints_used' => 0,
        'attempted_at' => now()->subMinutes(10),
    ]);

    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.bug', $attempt->attempt_id))
        ->assertOk()
        ->assertSee('Penilaian belum selesai')
        ->assertSee('Periksa Status')
        ->assertDontSee('wire:poll.2s', false);
});
