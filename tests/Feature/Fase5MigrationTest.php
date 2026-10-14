<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ClassLevelAccess;
use App\Models\CodeDraft;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Langkah 1 Fase 5: struktur tabel/kolom baru dan perilaku default-nya.
 *
 * Backfill (attempt_no, status, locked_at) diuji di Fase5BackfillTest karena
 * hanya muncul saat ada data lama.
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);
});

/**
 * Siapkan kelas + kelompok + satu siswa, lalu kembalikan [team, misi].
 *
 * @return array{0: Team, 1: Mission}
 */
function siapkanTimMisi(): array
{
    $guru = User::factory()->create(['role' => 'guru']);

    $kelas = SchoolClass::create([
        'class_name' => 'X RPL 1',
        'school_year' => '2026/2027',
        'user_id' => $guru->id,
    ]);

    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    $siswa = Student::create([
        'class_id' => $kelas->class_id,
        'full_name' => 'Adi Nugroho',
        'student_no' => 'D001',
    ]);

    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $level = Level::query()->firstOrFail();

    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'KOP-L1-TEST',
        'client_brief' => 'Brief.',
    ]);

    return [$team, $misi];
}

// ---------- Struktur tabel & kolom baru ----------

test('kolom baru pada students ada', function () {
    expect(Schema::hasColumns('students', ['access_code', 'access_code_generated_at', 'last_login_at']))
        ->toBeTrue();
});

test('tabel class_level_access ada dengan kolom lengkap', function () {
    expect(Schema::hasTable('class_level_access'))->toBeTrue()
        ->and(Schema::hasColumns('class_level_access', [
            'id', 'class_id', 'level_id', 'is_open', 'opens_at', 'closes_at',
        ]))->toBeTrue();
});

test('kolom baru pada mission_plans ada', function () {
    expect(Schema::hasColumns('mission_plans', ['locked_at', 'steps_note', 'unlocked_by']))
        ->toBeTrue();
});

test('kolom baru pada missions, submissions, dan score_entries ada', function () {
    expect(Schema::hasColumn('missions', 'starter_code'))->toBeTrue()
        ->and(Schema::hasColumns('submissions', [
            'status', 'attempt_no', 'graded_at', 'error_message', 'runtime_ms',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('score_entries', 'award_key'))->toBeTrue();
});

test('tabel baru Fase 5 ada', function () {
    foreach (['code_drafts', 'submission_parts', 'reflections', 'bug_test_cases', 'bug_attempt_results'] as $tabel) {
        expect(Schema::hasTable($tabel))->toBeTrue("Tabel {$tabel} belum ada.");
    }
});

test('kolom baru pada bug_cards dan bug_attempts ada', function () {
    expect(Schema::hasColumn('bug_cards', 'symptom_text'))->toBeTrue()
        ->and(Schema::hasColumns('bug_attempts', [
            'released_by', 'released_at', 'solved_at', 'grading_status', 'error_message',
        ]))->toBeTrue();
});

// ---------- Default nilai pada baris baru ----------

test('submission baru berstatus menunggu dan belum dinilai', function () {
    [$team, $misi] = siapkanTimMisi();

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => Student::query()->where('class_id', $team->class_id)->value('student_id'),
        'code_text' => 'print(1)',
        'submitted_at' => now(),
        'all_passed' => false,
    ])->refresh();

    expect($submission->status)->toBe(Submission::STATUS_MENUNGGU)
        ->and($submission->attempt_no)->toBe(1)
        ->and($submission->sudahDinilai())->toBeFalse()
        ->and($submission->labelStatus())->toBe('Menunggu penilaian')
        ->and($submission->variantStatus())->toBe('info');
});

test('submission dinilai dikenali sebagai sudah dinilai', function () {
    [$team, $misi] = siapkanTimMisi();

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => Student::query()->where('class_id', $team->class_id)->value('student_id'),
        'code_text' => 'print(1)',
        'submitted_at' => now(),
        'all_passed' => true,
        'status' => Submission::STATUS_DINILAI,
    ])->refresh();

    expect($submission->sudahDinilai())->toBeTrue()
        ->and($submission->labelStatus())->toBe('Sudah dinilai');
});

test('attempt_no dapat diisi berurutan oleh pipeline', function () {
    [$team, $misi] = siapkanTimMisi();

    $siswaId = (int) Student::query()->where('class_id', $team->class_id)->value('student_id');

    for ($i = 1; $i <= 3; $i++) {
        Submission::create([
            'team_id' => $team->team_id,
            'mission_id' => $misi->mission_id,
            'submitted_by' => $siswaId,
            'code_text' => 'print(1)',
            'submitted_at' => now()->addSeconds($i),
            'all_passed' => false,
            'attempt_no' => $i,
        ]);
    }

    $nomor = Submission::query()
        ->where('team_id', $team->team_id)
        ->where('mission_id', $misi->mission_id)
        ->orderBy('attempt_no')
        ->pluck('attempt_no')
        ->all();

    expect($nomor)->toBe([1, 2, 3]);
});

// ---------- Unique index ----------

test('unique team+mission mencegah rencana misi ganda', function () {
    [$team, $misi] = siapkanTimMisi();

    MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
    ]);

    expect(fn () => MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('award_key unik mencegah poin ganda', function () {
    [$team] = siapkanTimMisi();

    ScoreEntry::create([
        'team_id' => $team->team_id,
        'source_type' => 'plan_bonus',
        'award_key' => 'plan:'.$team->team_id.':1',
        'points' => 10,
    ]);

    expect(fn () => ScoreEntry::create([
        'team_id' => $team->team_id,
        'source_type' => 'plan_bonus',
        'award_key' => 'plan:'.$team->team_id.':1',
        'points' => 10,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

// ---------- Rencana: penguncian ----------

test('rencana baru belum terkunci sampai dikunci analyst', function () {
    [$team, $misi] = siapkanTimMisi();

    $rencana = MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
    ]);

    expect($rencana->terkunci())->toBeFalse();

    $rencana->update(['locked_at' => now()]);

    expect($rencana->fresh()->terkunci())->toBeTrue();
});

// ---------- code_drafts ----------

test('code_drafts memakai string kosong untuk program utama, bukan NULL', function () {
    // Alasan: unique index MySQL mengizinkan banyak NULL, sehingga program
    // utama bisa tergandakan tanpa batas bila memakai NULL.
    [$team, $misi] = siapkanTimMisi();

    $draf = CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => CodeDraft::PROGRAM_UTAMA,
        'code_text' => 'print(1)',
        'version' => 1,
    ]);

    expect($draf->programUtama())->toBeTrue()
        ->and($draf->function_name)->toBe('');
});

test('satu tim hanya boleh punya satu draf per fungsi', function () {
    [$team, $misi] = siapkanTimMisi();

    CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => 'hitung_bunga',
        'code_text' => 'x',
    ]);

    expect(fn () => CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => 'hitung_bunga',
        'code_text' => 'y',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('draf dianggap sudah ditinjau bila versinya tidak lebih baru', function () {
    [$team, $misi] = siapkanTimMisi();

    $draf = CodeDraft::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'function_name' => 'hitung_bunga',
        'code_text' => 'x',
        'version' => 3,
        'reviewed_version' => 2,
    ]);

    expect($draf->sudahDitinjau())->toBeFalse();

    $draf->update(['reviewed_version' => 3]);

    expect($draf->fresh()->sudahDitinjau())->toBeTrue();
});

// ---------- class_level_access ----------

test('kelas tanpa baris class_level_access berarti level tertutup', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);

    expect($kelas->levelAccess()->count())->toBe(0);
});

test('class_level_access menaati jendela waktu', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);

    $terbuka = ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => 1,
        'is_open' => true,
    ]);

    expect($terbuka->bolehDibuka())->toBeTrue();

    $masaDepan = ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => 2,
        'is_open' => true,
        'opens_at' => now()->addDay(),
    ]);

    expect($masaDepan->bolehDibuka())->toBeFalse();

    $sudahLewat = ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => 3,
        'is_open' => true,
        'closes_at' => now()->subMinute(),
    ]);

    expect($sudahLewat->bolehDibuka())->toBeFalse();
});

test('level tertutup walau berada dalam jendela waktu', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);

    $tertutup = ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => 1,
        'is_open' => false,
    ]);

    expect($tertutup->dalamJendelaWaktu())->toBeTrue()
        ->and($tertutup->bolehDibuka())->toBeFalse();
});

// ---------- bug_attempt ----------

test('bug_attempt baru belum dirilis dan belum dinilai', function () {
    [$team] = siapkanTimMisi();

    $kartu = BugCard::create([
        'level_id' => 1,
        'bug_title' => 'Contoh',
        'buggy_code' => 'x',
        'fix_explanation' => 'y',
        'points' => 10,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'attempted_at' => now(),
    ])->refresh();

    expect($attempt->grading_status)->toBe(BugAttempt::STATUS_BELUM)
        ->and($attempt->sudahDirilis())->toBeFalse()
        ->and($attempt->labelStatus())->toBe('Belum dikerjakan');
});
