<?php

use App\Models\ClassLevelAccess;
use App\Models\CodeDraft;
use App\Models\Level;
use App\Models\Mission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Services\CodeDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function konteksEditor(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1', 'client_brief' => 'brief', 'starter_code' => 'print(1)']);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $coder = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Coder', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $coder->student_id]);
    RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $coder->student_id, 'level_id' => $level->level_id, 'role_id' => Role::firstOrCreate(['role_name' => 'Coder'])->role_id]);

    // Kasus uji dengan expected_value RAHASIA.
    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'rahasia', 'check_target' => 'x', 'expected_value' => 'NILAI_RAHASIA_123']);

    return [$team, $misi, $coder];
}

test('respons halaman misi tidak memuat expected_value kasus uji', function () {
    [$team, $misi, $coder] = konteksEditor();

    $this->actingAs($coder, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertDontSee('NILAI_RAHASIA_123');
});

test('draf optimis: simpan dengan versi lama ditolak', function () {
    [$team, $misi, $coder] = konteksEditor();

    $draf = CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => CodeDraft::PROGRAM_UTAMA,
        'code_text' => 'v1',
        'version' => 2,
        'updated_by' => $coder->student_id,
    ]);

    // Klien masih mengira versi 1 → konflik.
    $hasil = app(CodeDraftService::class)->simpan($draf, 'v3', $coder, 1);

    expect($hasil['status'])->toBe(CodeDraftService::KONFLIK);
});

test('draf optimis: simpan dengan versi terkini berhasil', function () {
    [$team, $misi, $coder] = konteksEditor();

    $draf = CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => CodeDraft::PROGRAM_UTAMA,
        'code_text' => 'v1',
        'version' => 1,
        'updated_by' => $coder->student_id,
    ]);

    $hasil = app(CodeDraftService::class)->simpan($draf, 'v2', $coder, 1);

    expect($hasil['status'])->toBe('ok')
        ->and($hasil['draft']->version)->toBe(2)
        ->and($hasil['draft']->code_text)->toBe('v2');
});