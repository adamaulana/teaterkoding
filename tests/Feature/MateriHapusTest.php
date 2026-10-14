<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LevelsSeeder::class);
    $this->guru = User::factory()->create(['role' => 'guru']);
});

/**
 * @return array{0: Level, 1: Mission, 2: SchoolClass, 3: Team, 4: Student}
 */
function siapkanMateri(): array
{
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-X', 'client_brief' => 'Brief.']);

    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => '1']);

    return [$level, $misi, $kelas, $team, $siswa];
}

// ---------- Hapus level ----------

test('level tidak bisa dihapus bila masih punya misi', function () {
    [$level] = siapkanMateri();

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.level')
        ->call('mintaHapus', $level->level_id)
        ->call('hapusLevel');

    expect(Level::query()->where('level_id', $level->level_id)->exists())->toBeTrue();
});

test('level tidak bisa dihapus bila masih punya kartu bug', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug A',
        'buggy_code' => 'x = 1',
        'fix_explanation' => 'Perbaiki.',
        'points' => 10,
    ]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.level')
        ->call('mintaHapus', $level->level_id)
        ->call('hapusLevel');

    expect(Level::query()->where('level_id', $level->level_id)->exists())->toBeTrue();
});

test('level bisa dihapus bila kosong', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::create(['level_number' => 99, 'level_name' => 'Level Kosong', 'is_summative' => false]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.level')
        ->call('mintaHapus', $level->level_id)
        ->call('hapusLevel');

    expect(Level::query()->where('level_id', $level->level_id)->exists())->toBeFalse();
});

// ---------- Hapus misi ----------

test('misi tidak bisa dihapus bila sudah punya submission', function () {
    [$level, $misi, $kelas, $team, $siswa] = siapkanMateri();

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
    ]);

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.misi')
        ->call('mintaHapus', $misi->mission_id)
        ->call('hapusMisi');

    expect(Mission::query()->where('mission_id', $misi->mission_id)->exists())->toBeTrue();
});

test('misi tidak bisa dihapus bila sudah punya rencana misi', function () {
    [$level, $misi, $kelas, $team] = siapkanMateri();

    MissionPlan::create(['team_id' => $team->team_id, 'mission_id' => $misi->mission_id]);

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.misi')
        ->call('mintaHapus', $misi->mission_id)
        ->call('hapusMisi');

    expect(Mission::query()->where('mission_id', $misi->mission_id)->exists())->toBeTrue();
});

test('hapus misi ikut menghapus kasus ujinya', function () {
    [$level, $misi] = siapkanMateri();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Kasus',
        'check_target' => 'status',
        'expected_value' => '"lunas"',
        'tolerance' => 0,
    ]);

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.misi')
        ->call('mintaHapus', $misi->mission_id)
        ->call('hapusMisi');

    expect(Mission::query()->where('mission_id', $misi->mission_id)->exists())->toBeFalse()
        ->and(TestCase::query()->where('mission_id', $misi->mission_id)->count())->toBe(0);
});

// ---------- Hapus kasus uji ----------

test('kasus uji tidak bisa dihapus bila sudah punya hasil submission', function () {
    [$level, $misi, $kelas, $team, $siswa] = siapkanMateri();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Kasus',
        'check_target' => 'status',
        'expected_value' => '"lunas"',
        'tolerance' => 0,
    ]);

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
    ]);

    SubmissionResult::create([
        'submission_id' => $submission->submission_id,
        'test_case_id' => $kasus->test_case_id,
        'is_passed' => true,
    ]);

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('mintaHapusKasus', $kasus->test_case_id)
        ->call('hapusKasus');

    expect(TestCase::query()->where('test_case_id', $kasus->test_case_id)->exists())->toBeTrue();
});

test('kasus uji tanpa hasil bisa dihapus', function () {
    [$level, $misi] = siapkanMateri();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Kasus',
        'check_target' => 'status',
        'expected_value' => '"lunas"',
        'tolerance' => 0,
    ]);

    $this->actingAs($this->guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('mintaHapusKasus', $kasus->test_case_id)
        ->call('hapusKasus');

    expect(TestCase::query()->where('test_case_id', $kasus->test_case_id)->exists())->toBeFalse();
});

// ---------- Hapus kartu bug ----------

test('kartu bug tidak bisa dihapus bila sudah punya percobaan', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    $bug = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug A',
        'buggy_code' => 'x = 1',
        'fix_explanation' => 'Perbaiki.',
        'points' => 10,
    ]);

    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    BugAttempt::create(['bug_id' => $bug->bug_id, 'team_id' => $team->team_id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kartu-bug')
        ->call('mintaHapus', $bug->bug_id)
        ->call('hapusBug');

    expect(BugCard::query()->where('bug_id', $bug->bug_id)->exists())->toBeTrue();
});

test('kartu bug tanpa percobaan bisa dihapus', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    $bug = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug B',
        'buggy_code' => 'x = 1',
        'fix_explanation' => 'Perbaiki.',
        'points' => 10,
    ]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kartu-bug')
        ->call('mintaHapus', $bug->bug_id)
        ->call('hapusBug');

    expect(BugCard::query()->where('bug_id', $bug->bug_id)->exists())->toBeFalse();
});
