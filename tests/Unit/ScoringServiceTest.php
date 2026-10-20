<?php

use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\MissionPlanConcept;
use App\Models\ConceptCard;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TestCase;
use App\Services\ScoringService;
use Database\Seeders\ConceptCardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function siapkanMisiSkor(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => 4, 'level_name' => 'Level 4', 'is_summative' => true]);
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L4', 'client_brief' => 'brief']);

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'a', 'check_target' => 'hitung_bunga(1)', 'expected_value' => '2']);
    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'b', 'check_target' => 'hitung_bunga(2)', 'expected_value' => '3']);
    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'c', 'check_target' => 'cek_status(1)', 'expected_value' => 'x']);
    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'd', 'check_target' => 'cek_status(2)', 'expected_value' => 'y']);

    return [$kelas, $siswa, $team, $level, $misi];
}

function buatSubmissionDinilai(array $ctx, int $lulus, int $attemptNo = 1): Submission
{
    [, $siswa, $team, , $misi] = $ctx;

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now(),
        'all_passed' => $lulus === 4,
        'status' => 'dinilai',
        'attempt_no' => $attemptNo,
    ]);

    $kasus = TestCase::query()->where('mission_id', $misi->mission_id)->orderBy('test_case_id')->get();

    foreach ($kasus as $index => $k) {
        SubmissionResult::create([
            'submission_id' => $submission->submission_id,
            'test_case_id' => $k->test_case_id,
            'is_passed' => $index < $lulus,
            'actual_value' => 'x',
        ]);
    }

    return $submission;
}

test('mission_result memberi delta persen terhadap skor terbaik sebelumnya', function () {
    $ctx = siapkanMisiSkor();
    $scoring = app(ScoringService::class);

    $scoring->missionResult(buatSubmissionDinilai($ctx, 2)); // 50%

    expect((int) ScoreEntry::query()->where('source_type', 'mission_result')->sum('points'))->toBe(50);

    $scoring->missionResult(buatSubmissionDinilai($ctx, 4, 2)); // 100%

    expect((int) ScoreEntry::query()->where('source_type', 'mission_result')->sum('points'))->toBe(100);
});

test('mission_result idempoten bila dipanggil ulang', function () {
    $ctx = siapkanMisiSkor();
    $scoring = app(ScoringService::class);

    $s = buatSubmissionDinilai($ctx, 4);
    $scoring->missionResult($s);
    $scoring->missionResult($s);

    expect(ScoreEntry::query()->where('source_type', 'mission_result')->count())->toBe(1);
});

test('first_try_bonus hanya untuk attempt_no 1 dan semua lulus', function () {
    $ctx = siapkanMisiSkor();
    $scoring = app(ScoringService::class);

    $scoring->firstTryBonus(buatSubmissionDinilai($ctx, 4, 1));

    expect(ScoreEntry::query()->where('source_type', 'first_try_bonus')->count())->toBe(1);

    $scoring->firstTryBonus(buatSubmissionDinilai($ctx, 4, 2));

    expect(ScoreEntry::query()->where('source_type', 'first_try_bonus')->count())->toBe(1);
});

test('mission_result_individu memberi delta per siswa pemilik fungsi', function () {
    [$kelas, $siswa, $team, , $misi] = siapkanMisiSkor();
    $scoring = app(ScoringService::class);

    $siswa2 = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siti']);

    FunctionOwnership::create(['mission_id' => $misi->mission_id, 'function_name' => 'hitung_bunga', 'team_id' => $team->team_id, 'student_id' => $siswa->student_id]);
    FunctionOwnership::create(['mission_id' => $misi->mission_id, 'function_name' => 'cek_status', 'team_id' => $team->team_id, 'student_id' => $siswa2->student_id]);

    $submission = buatSubmissionDinilai([$kelas, $siswa, $team, null, $misi], 4);

    $submission->parts()->create(['function_name' => 'hitung_bunga', 'student_id' => $siswa->student_id, 'code_text' => 'x']);
    $submission->parts()->create(['function_name' => 'cek_status', 'student_id' => $siswa2->student_id, 'code_text' => 'x']);

    $scoring->missionResultIndividu($submission);

    $entry = ScoreEntry::query()->where('source_type', 'mission_result')->whereNotNull('student_id')->get();

    expect($entry)->toHaveCount(2);

    foreach ($entry as $e) {
        expect($e->points)->toBe(100);
    }
});

test('plan_bonus diberikan bila semua lulus, rencana terkunci sebelum submit, dan sesuai', function () {
    $this->seed(ConceptCardsSeeder::class);

    [$kelas, $siswa, $team, , $misi] = siapkanMisiSkor();

    $konsep = ConceptCard::query()->where('concept_name', 'Variabel')->firstOrFail();

    $rencana = MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now()->subMinutes(5),
        'locked_at' => now()->subMinutes(4),
    ]);

    MissionPlanConcept::create(['plan_id' => $rencana->plan_id, 'concept_id' => $konsep->concept_id]);

    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'saldo = 0',
        'submitted_at' => now(),
        'all_passed' => true,
        'status' => 'dinilai',
        'attempt_no' => 1,
    ]);

    foreach (TestCase::query()->where('mission_id', $misi->mission_id)->get() as $k) {
        SubmissionResult::create(['submission_id' => $submission->submission_id, 'test_case_id' => $k->test_case_id, 'is_passed' => true, 'actual_value' => 'x']);
    }

    app(ScoringService::class)->planBonus($submission);

    expect(ScoreEntry::query()->where('source_type', 'plan_bonus')->count())->toBe(1);
});

test('plan_bonus tidak diberikan bila rencana belum terkunci', function () {
    $this->seed(ConceptCardsSeeder::class);

    [$kelas, $siswa, $team, , $misi] = siapkanMisiSkor();

    MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
    ]);

    $submission = buatSubmissionDinilai([$kelas, $siswa, $team, null, $misi], 4);
    $submission->update(['code_text' => 'saldo = 0']);

    app(ScoringService::class)->planBonus($submission);

    expect(ScoreEntry::query()->where('source_type', 'plan_bonus')->count())->toBe(0);
});
