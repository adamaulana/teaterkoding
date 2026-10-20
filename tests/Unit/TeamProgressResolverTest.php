<?php

use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\TeamProgress;
use App\Services\TeamProgressResolver;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Progres kelompok: status, level lulus, dan deteksi "macet".
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->resolver = new TeamProgressResolver;

    $this->kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);

    // Empat misi, satu per level.
    $this->misi = Level::query()->orderBy('level_number')->get()->mapWithKeys(function (Level $level) {
        $misi = Mission::create([
            'level_id' => $level->level_id,
            'unlock_code' => 'KODE-'.$level->level_number,
            'client_brief' => 'Brief.',
        ]);

        return [$level->level_number => $misi];
    });
});

/**
 * Buat kelompok baru pada kelas uji.
 */
function kelompok(string $nama): Team
{
    return Team::create(['class_id' => test()->kelas->class_id, 'team_name' => $nama]);
}

/**
 * Buat satu submission untuk kelompok pada misi tertentu.
 */
function submissionPada(Team $team, Mission $misi, bool $lulus, ?Carbon $waktu = null): Submission
{
    $siswa = Student::create([
        'class_id' => $team->class_id,
        'full_name' => 'Siswa '.$team->team_name.' '.uniqid(),
        'student_no' => null,
    ]);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    return Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => $waktu ?? Carbon::now(),
        'all_passed' => $lulus,
        // Submission pada test ini mewakili yang sudah selesai dinilai, sesuai
        // semantik baru: hanya submission 'dinilai' yang dihitung progres.
        'status' => Submission::STATUS_DINILAI,
        'attempt_no' => 1,
    ]);
}

test('kelompok tanpa rencana dan submission berstatus belum mulai', function () {
    $team = kelompok('Alpha');

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->status)->toBe(TeamProgress::STATUS_BELUM_MULAI)
        ->and($progres->sudahMulai())->toBeFalse()
        ->and($progres->levelLulus)->toBe(0)
        ->and($progres->levelAktif)->toBeNull()
        ->and($progres->macet)->toBeFalse();
});

test('kelompok dengan rencana tetapi tanpa submission berstatus merencanakan', function () {
    $team = kelompok('Bravo');

    MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $this->misi[1]->mission_id,
        'planned_at' => Carbon::now(),
    ]);

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->status)->toBe(TeamProgress::STATUS_MERENCANAKAN)
        ->and($progres->levelAktif)->toBe(1)
        ->and($progres->levelLulus)->toBe(0);
});

test('submission gagal pada level pertama berstatus mengerjakan', function () {
    $team = kelompok('Charlie');

    submissionPada($team, $this->misi[1], false);

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->status)->toBe(TeamProgress::STATUS_MENGERJAKAN)
        ->and($progres->levelLulus)->toBe(0)
        ->and($progres->jumlahSubmission)->toBe(1);
});

test('submission lulus menaikkan level lulus dan status lulus', function () {
    $team = kelompok('Delta');

    submissionPada($team, $this->misi[1], true);

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->status)->toBe(TeamProgress::STATUS_LULUS)
        ->and($progres->levelLulus)->toBe(1)
        ->and($progres->kemajuan())->toBe(1);
});

test('lulus semua level berstatus selesai', function () {
    $team = kelompok('Selesai');

    foreach ($this->misi as $misi) {
        submissionPada($team, $misi, true);
    }

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->status)->toBe(TeamProgress::STATUS_SELESAI)
        ->and($progres->levelLulus)->toBe(4)
        ->and($progres->kemajuan())->toBe(4)
        ->and($progres->macet)->toBeFalse();
});

test('lima submission gagal beruntun ditandai macet', function () {
    $team = kelompok('Foxtrot');

    for ($i = 0; $i < 5; $i++) {
        submissionPada($team, $this->misi[1], false, Carbon::now());
    }

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->macet)->toBeTrue()
        ->and($progres->alasanMacet)->toBe('5 kali gagal beruntun');
});

test('empat submission gagal belum dianggap macet', function () {
    $team = kelompok('Empat');

    for ($i = 0; $i < 4; $i++) {
        submissionPada($team, $this->misi[1], false, Carbon::now());
    }

    expect($this->resolver->resolve($team, 4)->macet)->toBeFalse();
});

test('gagal beruntun yang terputus oleh keberhasilan tidak dianggap macet', function () {
    $team = kelompok('Terputus');

    submissionPada($team, $this->misi[1], true, Carbon::now()->subMinutes(30));

    for ($i = 0; $i < 3; $i++) {
        submissionPada($team, $this->misi[1], false, Carbon::now()->subMinutes(10 - $i));
    }

    expect($this->resolver->resolve($team, 4)->macet)->toBeFalse();
});

test('tidak ada aktivitas selama ambang waktu ditandai macet', function () {
    config(['teater.stuck.no_submission_minutes' => 10, 'teater.stuck.consecutive_failures' => 99]);

    $team = kelompok('Sepi');

    submissionPada($team, $this->misi[1], false, Carbon::now()->subMinutes(25));

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->macet)->toBeTrue()
        ->and($progres->alasanMacet)->toBe('10 menit tanpa aktivitas');
});

test('aktivitas baru-baru ini tidak dianggap macet', function () {
    config(['teater.stuck.no_submission_minutes' => 10]);

    $team = kelompok('Rajin');

    submissionPada($team, $this->misi[1], false, Carbon::now()->subMinutes(2));

    expect($this->resolver->resolve($team, 4)->macet)->toBeFalse();
});

test('kelompok selesai tidak pernah ditandai macet meski lama sepi', function () {
    $team = kelompok('Lulus Semua');

    foreach ($this->misi as $misi) {
        submissionPada($team, $misi, true, Carbon::now()->subHours(5));
    }

    expect($this->resolver->resolve($team, 4)->macet)->toBeFalse();
});

test('resolveKelas menghitung semua kelompok sekaligus', function () {
    $satu = kelompok('Satu');
    $dua = kelompok('Dua');

    submissionPada($satu, $this->misi[1], true);

    MissionPlan::create([
        'team_id' => $dua->team_id,
        'mission_id' => $this->misi[2]->mission_id,
        'planned_at' => Carbon::now(),
    ]);

    $hasil = $this->resolver->resolveKelas(collect([$satu, $dua]), 4);

    expect($hasil)->toHaveCount(2)
        ->and($hasil->get($satu->team_id)?->status)->toBe(TeamProgress::STATUS_LULUS)
        ->and($hasil->get($dua->team_id)?->status)->toBe(TeamProgress::STATUS_MERENCANAKAN);
});

test('resolveKelas dengan kumpulan kelompok kosong mengembalikan koleksi kosong', function () {
    expect($this->resolver->resolveKelas(collect(), 4))->toBeEmpty();
});

test('label dan varian status sesuai', function () {
    $team = kelompok('Label');

    $progres = $this->resolver->resolve($team, 4);

    expect($progres->label())->toBe('Belum mulai')
        ->and($progres->variant())->toBe('netral');

    submissionPada($team, $this->misi[1], true);

    $lulus = $this->resolver->resolve($team->fresh(), 4);

    expect($lulus->label())->toBe('Lulus Level 1')
        ->and($lulus->variant())->toBe('info');
});
