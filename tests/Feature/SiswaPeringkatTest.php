<?php

use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function konteksPeringkat(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $teamA = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $teamB = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Bravo']);

    $siswaA = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siswa A', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $teamA->team_id, 'student_id' => $siswaA->student_id]);

    $siswaB = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siswa B', 'access_code' => 'EFGH2345']);
    TeamMember::create(['team_id' => $teamB->team_id, 'student_id' => $siswaB->student_id]);

    ScoreEntry::create(['team_id' => $teamA->team_id, 'source_type' => 'mission_result', 'points' => 80]);
    ScoreEntry::create(['team_id' => $teamB->team_id, 'source_type' => 'mission_result', 'points' => 50]);

    return [$kelas, $teamA, $teamB, $siswaA, $siswaB];
}

test('peringkat menampilkan kelompok dan menyorot kelompok sendiri', function () {
    [, $teamA, , $siswaA] = konteksPeringkat();

    $this->actingAs($siswaA, 'siswa')
        ->get(route('siswa.peringkat'))
        ->assertOk()
        ->assertSee('Alpha')
        ->assertSee('Bravo')
        ->assertSee('kelompokmu');
});

test('peringkat disembunyikan saat level sumatif terbuka', function () {
    [$kelas, , , $siswaA] = konteksPeringkat();

    $level = Level::create(['level_number' => 4, 'level_name' => 'Level 4', 'is_summative' => true]);
    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $this->actingAs($siswaA, 'siswa')
        ->get(route('siswa.peringkat'))
        ->assertOk()
        ->assertSee('disembunyikan sementara');
});

test('peringkat disembunyikan bila config show_leaderboard mati', function () {
    [, , , $siswaA] = konteksPeringkat();

    config(['teater.show_leaderboard' => false]);

    $this->actingAs($siswaA, 'siswa')
        ->get(route('siswa.peringkat'))
        ->assertOk()
        ->assertSee('Peringkat disembunyikan');
});
