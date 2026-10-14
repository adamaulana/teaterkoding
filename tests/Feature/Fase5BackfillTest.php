<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ConceptCard;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Langkah 1 Fase 5: BACKFILL data lama.
 *
 * RefreshDatabase menjalankan migration dari nol, jadi tidak ada baris "lama"
 * yang bisa diuji langsung. Karena itu tiap test di sini membuat baris dalam
 * kondisi seperti sebelum migration, lalu MENJALANKAN ULANG potongan
 * backfill-nya untuk memastikan hasilnya benar.
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->guru = User::factory()->create(['role' => 'guru']);

    $this->kelas = SchoolClass::create([
        'class_name' => 'X RPL 1',
        'school_year' => '2026/2027',
        'user_id' => $this->guru->id,
    ]);

    $this->team = Team::create(['class_id' => $this->kelas->class_id, 'team_name' => 'Kelompok 1']);

    $this->siswa = Student::create([
        'class_id' => $this->kelas->class_id,
        'full_name' => 'Adi Nugroho',
        'student_no' => 'D001',
    ]);

    TeamMember::create(['team_id' => $this->team->team_id, 'student_id' => $this->siswa->student_id]);

    $this->level = Level::query()->firstOrFail();

    $this->misi = Mission::create([
        'level_id' => $this->level->level_id,
        'unlock_code' => 'KOP-L1-TEST',
        'client_brief' => 'Brief.',
    ]);
});

test('backfill menandai submission lama sebagai dinilai', function () {
    Submission::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'submitted_by' => $this->siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now()->subHour(),
        'all_passed' => true,
        'status' => Submission::STATUS_DINILAI,
    ]);

    // Kembalikan ke kondisi sebelum migration.
    DB::table('submissions')->update(['status' => 'menunggu', 'graded_at' => null]);

    // Jalankan ulang potongan backfill persis seperti di migration.
    DB::table('submissions')->update([
        'status' => 'dinilai',
        'graded_at' => DB::raw('submitted_at'),
    ]);

    $submission = Submission::query()->firstOrFail();

    expect($submission->status)->toBe(Submission::STATUS_DINILAI)
        ->and($submission->graded_at)->not->toBeNull()
        ->and($submission->graded_at?->equalTo($submission->submitted_at))->toBeTrue();
});

test('backfill memberi attempt_no berurutan per tim dan misi', function () {
    $misiLain = Mission::create([
        'level_id' => $this->level->level_id,
        'unlock_code' => 'KOP-L2-TEST',
        'client_brief' => 'Brief.',
    ]);

    // Tiga submission pada misi yang sama, waktu dibuat berurutan.
    foreach ([now()->subMinutes(30), now()->subMinutes(20), now()->subMinutes(10)] as $kapan) {
        Submission::create([
            'team_id' => $this->team->team_id,
            'mission_id' => $this->misi->mission_id,
            'submitted_by' => $this->siswa->student_id,
            'code_text' => 'print(1)',
            'submitted_at' => $kapan,
            'all_passed' => false,
        ]);
    }

    // Satu submission pada misi BERBEDA → penomoran mulai dari 1 lagi.
    Submission::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $misiLain->mission_id,
        'submitted_by' => $this->siswa->student_id,
        'code_text' => 'print(2)',
        'submitted_at' => now(),
        'all_passed' => false,
    ]);

    DB::table('submissions')->update(['attempt_no' => 1]);

    // Jalankan ulang potongan backfill persis seperti di migration.
    $semua = DB::table('submissions')
        ->select('submission_id', 'team_id', 'mission_id')
        ->orderBy('team_id')
        ->orderBy('mission_id')
        ->orderBy('submitted_at')
        ->orderBy('submission_id')
        ->get();

    $nomor = [];

    foreach ($semua as $baris) {
        $kunci = $baris->team_id.'|'.$baris->mission_id;
        $nomor[$kunci] = ($nomor[$kunci] ?? 0) + 1;

        DB::table('submissions')
            ->where('submission_id', $baris->submission_id)
            ->update(['attempt_no' => $nomor[$kunci]]);
    }

    $misiPertama = Submission::query()
        ->where('mission_id', $this->misi->mission_id)
        ->orderBy('submitted_at')
        ->pluck('attempt_no')
        ->all();

    $misiKedua = Submission::query()
        ->where('mission_id', $misiLain->mission_id)
        ->pluck('attempt_no')
        ->all();

    expect($misiPertama)->toBe([1, 2, 3])
        ->and($misiKedua)->toBe([1]);
});

test('backfill mengisi locked_at dari planned_at untuk rencana berkartu konsep', function () {
    $rencana = MissionPlan::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'planned_at' => now()->subHour(),
    ]);

    // Rencana punya kartu konsep → dianggap sudah final sebelum migration.
    $konsep = ConceptCard::create(['concept_name' => 'Variabel']);

    DB::table('mission_plan_concepts')->insert([
        'plan_id' => $rencana->plan_id,
        'concept_id' => $konsep->concept_id,
    ]);

    DB::table('mission_plans')->update(['locked_at' => null]);

    DB::statement(
        'UPDATE mission_plans SET locked_at = planned_at '
        .'WHERE locked_at IS NULL AND plan_id IN (SELECT DISTINCT plan_id FROM mission_plan_concepts)',
    );

    $hasil = MissionPlan::query()->firstOrFail();

    expect($hasil->locked_at)->not->toBeNull()
        ->and($hasil->locked_at?->equalTo($hasil->planned_at))->toBeTrue()
        ->and($hasil->terkunci())->toBeTrue();
});

test('backfill tidak mengunci rencana tanpa kartu konsep', function () {
    MissionPlan::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'planned_at' => now()->subHour(),
    ]);

    DB::table('mission_plans')->update(['locked_at' => null]);

    DB::statement(
        'UPDATE mission_plans SET locked_at = planned_at '
        .'WHERE locked_at IS NULL AND plan_id IN (SELECT DISTINCT plan_id FROM mission_plan_concepts)',
    );

    // Tanpa kartu konsep tetap NULL → dianggap belum merencanakan.
    expect(MissionPlan::query()->firstOrFail()->terkunci())->toBeFalse();
});

test('backfill mengisi solved_at dari attempted_at untuk bug yang sudah terpecahkan', function () {
    $kartu = BugCard::create([
        'level_id' => $this->level->level_id,
        'bug_title' => 'Contoh',
        'buggy_code' => 'x',
        'fix_explanation' => 'y',
        'points' => 10,
    ]);

    BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $this->team->team_id,
        'is_solved' => true,
        'attempted_at' => now()->subHour(),
    ]);

    DB::table('bug_attempts')->update(['solved_at' => null]);

    DB::table('bug_attempts')
        ->where('is_solved', true)
        ->whereNull('solved_at')
        ->update(['solved_at' => DB::raw('attempted_at')]);

    $attempt = BugAttempt::query()->firstOrFail();

    expect($attempt->solved_at)->not->toBeNull()
        ->and($attempt->solved_at?->equalTo($attempt->attempted_at))->toBeTrue();
});

test('deteksi rencana ganda menemukan duplikat saat unique index dilepas', function () {
    MissionPlan::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'planned_at' => now(),
    ]);

    $deteksi = fn () => DB::table('mission_plans')
        ->select('team_id', 'mission_id', DB::raw('COUNT(*) as jumlah'))
        ->groupBy('team_id', 'mission_id')
        ->havingRaw('COUNT(*) > 1')
        ->get();

    // Satu baris → tidak ada duplikat.
    expect($deteksi())->toBeEmpty();

    // Lepas unique index sementara, lalu sisipkan duplikat lewat DB mentah
    // untuk meniru data kotor sebelum migration.
    Schema::table('mission_plans', function ($table) {
        $table->dropUnique('mission_plans_team_mission_unique');
    });

    DB::table('mission_plans')->insert([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'planned_at' => now(),
    ]);

    $duplikat = $deteksi();

    expect($duplikat)->toHaveCount(1)
        ->and((int) $duplikat->first()->jumlah)->toBe(2);
});
