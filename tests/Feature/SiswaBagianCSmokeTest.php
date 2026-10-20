<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function guruSmoke(): User
{
    return User::factory()->create(['role' => 'guru', 'is_active' => true]);
}

test('halaman admin kartu bug dapat dirender', function () {
    $this->actingAs(guruSmoke())->get('/admin/kartu-bug')->assertOk()->assertSee('Kartu Bug');
});

test('halaman admin pantau live dapat dirender', function () {
    $this->actingAs(guruSmoke())->get('/admin/pantau-live')->assertOk();
});

test('halaman admin rekap nilai dapat dirender', function () {
    $this->actingAs(guruSmoke())->get('/admin/rekap-nilai')->assertOk();
});

test('halaman admin riwayat submission dapat dirender', function () {
    $this->actingAs(guruSmoke())->get('/admin/riwayat-submission')->assertOk();
});

test('halaman admin dashboard dapat dirender', function () {
    $this->actingAs(guruSmoke())->get('/admin/dashboard')->assertOk();
});

test('halaman siswa bug dan peringkat dapat dirender', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siswa', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug Smoke',
        'symptom_text' => 'Gejala smoke.',
        'buggy_code' => 'print(0)',
        'fix_explanation' => 'kunci',
        'points' => 10,
    ]);
    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'is_solved' => false,
        'grading_status' => BugAttempt::STATUS_BELUM,
        'hints_used' => 0,
        'attempted_at' => now(),
    ]);

    $this->actingAs($siswa, 'siswa')->get(route('siswa.bug', $attempt->attempt_id))->assertOk()->assertSee('Bug Smoke');
    $this->actingAs($siswa, 'siswa')->get(route('siswa.peringkat'))->assertOk();
});
