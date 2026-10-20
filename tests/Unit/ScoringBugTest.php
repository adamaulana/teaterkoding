<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Team;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function siapkanBugSkor(): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug contoh',
        'buggy_code' => 'print(1)',
        'fix_explanation' => 'kunci',
        'points' => 15,
    ]);

    return [$kelas, $team, $level, $kartu];
}

function buatAttempt(array $ctx, bool $solved, int $hints = 0): BugAttempt
{
    [, $team, , $kartu] = $ctx;

    return BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'is_solved' => $solved,
        'solved_at' => $solved ? now() : null,
        'grading_status' => $solved ? BugAttempt::STATUS_DINILAI : BugAttempt::STATUS_BELUM,
        'hints_used' => $hints,
        'attempted_at' => now(),
    ]);
}

test('bug_bonus memberi poin sebesar points kartu saat lulus', function () {
    $ctx = siapkanBugSkor();
    $attempt = buatAttempt($ctx, true);

    app(ScoringService::class)->bugBonus($attempt);

    expect((int) ScoreEntry::query()->where('source_type', 'bug_bonus')->sum('points'))->toBe(15);
});

test('bug_bonus tidak memberi poin bila belum lulus', function () {
    $ctx = siapkanBugSkor();
    $attempt = buatAttempt($ctx, false);

    app(ScoringService::class)->bugBonus($attempt);

    expect(ScoreEntry::query()->where('source_type', 'bug_bonus')->count())->toBe(0);
});

test('bug_bonus idempoten', function () {
    $ctx = siapkanBugSkor();
    $attempt = buatAttempt($ctx, true);

    $scoring = app(ScoringService::class);
    $scoring->bugBonus($attempt);
    $scoring->bugBonus($attempt);

    expect(ScoreEntry::query()->where('source_type', 'bug_bonus')->count())->toBe(1);
});

test('hint_penalty memberi poin negatif sebesar config', function () {
    $ctx = siapkanBugSkor();
    $attempt = buatAttempt($ctx, false, 1);

    app(ScoringService::class)->hintPenalty($attempt);

    expect((int) ScoreEntry::query()->where('source_type', 'hint_penalty')->sum('points'))
        ->toBe(-1 * (int) config('teater.hint_penalty', 5));
});

test('hint_penalty tidak diberikan bila petunjuk tidak dipakai', function () {
    $ctx = siapkanBugSkor();
    $attempt = buatAttempt($ctx, false, 0);

    app(ScoringService::class)->hintPenalty($attempt);

    expect(ScoreEntry::query()->where('source_type', 'hint_penalty')->count())->toBe(0);
});
