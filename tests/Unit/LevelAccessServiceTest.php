<?php

use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Services\LevelAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buatKonteksAkses(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok Alpha']);
    $level1 = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $level2 = Level::create(['level_number' => 2, 'level_name' => 'Level 2', 'is_summative' => false]);
    $misi1 = Mission::create(['level_id' => $level1->level_id, 'unlock_code' => 'KOP-L1', 'client_brief' => 'brief 1']);
    $misi2 = Mission::create(['level_id' => $level2->level_id, 'unlock_code' => 'KOP-L2', 'client_brief' => 'brief 2']);

    return [$kelas, $siswa, $team, $level1, $level2, $misi1, $misi2];
}

test('tanpa baris class_level_access berarti level tertutup', function () {
    [, , $team, , , $misi1] = buatKonteksAkses();

    $servis = app(LevelAccessService::class);

    expect($servis->canUnlock($team, $misi1))->toBeFalse();
    expect($servis->canWork($team, $misi1))->toBeFalse();
});

test('level terbuka di dalam jendela waktu bisa dibuka', function () {
    [$kelas, , $team, , , $misi1] = buatKonteksAkses();

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $misi1->level_id, 'is_open' => true]);

    expect(app(LevelAccessService::class)->canUnlock($team, $misi1))->toBeTrue();
});

test('level terbuka tapi melewati waktu tutup tidak bisa dibuka', function () {
    [$kelas, , $team, , , $misi1] = buatKonteksAkses();

    ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => $misi1->level_id,
        'is_open' => true,
        'closes_at' => now()->subMinute(),
    ]);

    expect(app(LevelAccessService::class)->canUnlock($team, $misi1))->toBeFalse();
});

test('level terbuka tapi belum tiba waktu buka tidak bisa dibuka', function () {
    [$kelas, , $team, , , $misi1] = buatKonteksAkses();

    ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => $misi1->level_id,
        'is_open' => true,
        'opens_at' => now()->addMinute(),
    ]);

    expect(app(LevelAccessService::class)->canUnlock($team, $misi1))->toBeFalse();
});

test('level 2 tidak bisa dibuka sebelum level 1 lulus (berurutan)', function () {
    [$kelas, , $team, , , , $misi2] = buatKonteksAkses();

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $misi2->level_id, 'is_open' => true]);

    expect(app(LevelAccessService::class)->canUnlock($team, $misi2))->toBeFalse();
});

test('level 2 bisa dibuka setelah level 1 lulus', function () {
    [$kelas, $siswa, $team, $level1, , $misi1, $misi2] = buatKonteksAkses();

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level1->level_id, 'is_open' => true]);
    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $misi2->level_id, 'is_open' => true]);

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi1->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now(),
        'all_passed' => true,
        'status' => 'dinilai',
        'attempt_no' => 1,
    ]);

    expect(app(LevelAccessService::class)->canUnlock($team, $misi2))->toBeTrue();
});