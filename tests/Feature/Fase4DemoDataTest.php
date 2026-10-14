<?php

use App\Models\BugCard;
use App\Models\ConceptCard;
use App\Models\GradingSetting;
use App\Models\Level;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use App\Services\ConceptUsageAnalyzer;
use App\Services\TeamProgress;
use App\Services\TeamProgressResolver;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * DemoDataSeeder harus mandiri (mengisi materi minimal) dan
 * teater:hapus-demo harus menghapus kelas demo TANPA menyentuh materi global.
 */
beforeEach(function () {
    $this->seed(RolesSeeder::class);

    User::factory()->create(['role' => 'guru']);
});

test('seeder demo mengisi materi minimal saat tabel materi kosong', function () {
    expect(Level::count())->toBe(0)
        ->and(Mission::count())->toBe(0)
        ->and(TestCase::count())->toBe(0)
        ->and(BugCard::count())->toBe(0);

    $this->seed(DemoDataSeeder::class);

    expect(Level::count())->toBe(4)
        ->and(Mission::count())->toBe(4)
        ->and(TestCase::count())->toBeGreaterThan(0)
        ->and(BugCard::count())->toBe(8)
        ->and(ConceptCard::count())->toBe(5);
});

test('seeder demo membuat kelas, siswa, kelompok, dan data permainan', function () {
    $this->seed(DemoDataSeeder::class);

    $namaKelas = config('teater.demo_class_name');

    expect(SchoolClass::query()->where('class_name', $namaKelas)->exists())->toBeTrue()
        ->and(Student::count())->toBe(24)
        ->and(Team::count())->toBe(6)
        ->and(Submission::count())->toBeGreaterThan(0)
        ->and(ScoreEntry::count())->toBeGreaterThan(0)
        ->and(ManualScore::count())->toBeGreaterThan(0);
});

test('seeder demo idempoten saat dijalankan dua kali', function () {
    $this->seed(DemoDataSeeder::class);

    $siswa = Student::count();
    $kelompok = Team::count();
    $submission = Submission::count();
    $poin = ScoreEntry::count();

    $this->seed(DemoDataSeeder::class);

    expect(Student::count())->toBe($siswa)
        ->and(Team::count())->toBe($kelompok)
        ->and(Submission::count())->toBe($submission)
        ->and(ScoreEntry::count())->toBe($poin);
});

test('seeder demo membuat kelompok macet dan kelompok belum mulai', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();

    $alpha = Team::query()->where('class_id', $kelas->class_id)->where('team_name', 'Kelompok Alpha')->firstOrFail();
    $foxtrot = Team::query()->where('class_id', $kelas->class_id)->where('team_name', 'Kelompok Foxtrot')->firstOrFail();

    // Alpha sengaja tidak punya aktivitas apa pun.
    expect($alpha->submissions()->count())->toBe(0)
        ->and($alpha->missionPlans()->count())->toBe(0);

    // Foxtrot punya 5 submission gagal beruntun (syarat "macet").
    expect($foxtrot->submissions()->count())->toBe(5)
        ->and($foxtrot->submissions()->where('all_passed', true)->count())->toBe(0);
});

// ---------- Langkah 0: waktu seeder harus relatif terhadap now() ----------

test('seeder demo memakai waktu relatif sehingga tidak semua kelompok macet', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $teams = Team::query()->where('class_id', $kelas->class_id)->orderBy('team_name')->get();

    $progres = app(TeamProgressResolver::class)
        ->resolveKelas($teams, (int) Level::query()->max('level_number'));

    // Tepat satu kelompok macet (Foxtrot) dan satu belum mulai (Alpha).
    $macet = $progres->filter(fn (TeamProgress $p) => $p->macet);
    $belumMulai = $progres->filter(fn (TeamProgress $p) => ! $p->sudahMulai());

    expect($macet)->toHaveCount(1)
        ->and($belumMulai)->toHaveCount(1);

    // Kelompok yang macet adalah Foxtrot, dan alasannya gagal beruntun.
    $namaMacet = $teams->firstWhere('team_id', $macet->keys()->first())?->team_name;
    expect($namaMacet)->toBe('Kelompok Foxtrot')
        ->and($macet->first()?->alasanMacet)->toBe('5 kali gagal beruntun');
});

test('seeder demo menyisakan kelompok yang baru aktif beberapa menit lalu', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();

    $teamIds = Team::query()->where('class_id', $kelas->class_id)->pluck('team_id')->all();

    // Minimal satu submission berumur <= 5 menit (terlihat "baru saja").
    $submissionBaru = Submission::query()
        ->whereIn('team_id', $teamIds)
        ->where('submitted_at', '>=', Carbon::now()->subMinutes(5))
        ->count();

    expect($submissionBaru)->toBeGreaterThan(0);

    // Dan minimal satu rencana berumur <= 5 menit.
    $rencanaBaru = MissionPlan::query()
        ->whereIn('team_id', $teamIds)
        ->where('planned_at', '>=', Carbon::now()->subMinutes(5))
        ->count();

    expect($rencanaBaru)->toBeGreaterThan(0);
});

test('seeder demo membuat kelompok Foxtrot benar-benar macet karena lama sepi', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $foxtrot = Team::query()->where('class_id', $kelas->class_id)->where('team_name', 'Kelompok Foxtrot')->firstOrFail();

    // Seluruh submission Foxtrot lebih tua dari ambang macet (10 menit).
    $ambang = (int) config('teater.stuck.no_submission_minutes', 10);

    $terbaru = $foxtrot->submissions()->max('submitted_at');

    expect(Carbon::parse((string) $terbaru)->diffInMinutes(Carbon::now()))->toBeGreaterThanOrEqual($ambang);
});

test('seeder demo membuat kondisi konsep dipilih tapi tidak dipakai', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $delta = Team::query()->where('class_id', $kelas->class_id)->where('team_name', 'Kelompok Delta')->firstOrFail();

    // Rencana pada level 2 memilih "Loop"...
    $rencana = $delta->missionPlans()
        ->whereHas('mission.level', fn ($q) => $q->where('level_number', 2))
        ->with('concepts')
        ->firstOrFail();

    expect($rencana->concepts->pluck('concept_name'))->toContain('Loop');

    // ...tetapi kode submission level 2 tidak memakai perulangan.
    $kode = (string) $delta->submissions()
        ->where('mission_id', $rencana->mission_id)
        ->orderByDesc('submitted_at')
        ->value('code_text');

    expect($kode)->not->toContain('for ')
        ->and($kode)->not->toContain('while ')
        ->and($kode)->toContain('if ');
});

test('seeder demo membuat kondisi konsep dipakai tapi tidak direncanakan', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $echo = Team::query()->where('class_id', $kelas->class_id)->where('team_name', 'Kelompok Echo')->firstOrFail();

    // Rencana level 4 tidak memilih "Function"...
    $rencana = $echo->missionPlans()
        ->whereHas('mission.level', fn ($q) => $q->where('level_number', 4))
        ->with('concepts')
        ->firstOrFail();

    expect($rencana->concepts->pluck('concept_name'))->not->toContain('Function');

    // ...tetapi kode level 4 memakai def.
    $kode = (string) $echo->submissions()
        ->where('mission_id', $rencana->mission_id)
        ->orderByDesc('submitted_at')
        ->value('code_text');

    expect($kode)->toContain('def ');

    $analisis = app(ConceptUsageAnalyzer::class)->analisisRencana($rencana, $kode);

    expect($analisis['dipakai_tak_direncanakan'])->toContain('Function');
});

test('perintah hapus demo menghapus kelas demo dan seluruh turunannya', function () {
    $this->seed(DemoDataSeeder::class);

    $this->artisan('teater:hapus-demo --force')->assertSuccessful();

    $namaKelas = config('teater.demo_class_name');

    expect(SchoolClass::query()->where('class_name', $namaKelas)->exists())->toBeFalse()
        ->and(Student::count())->toBe(0)
        ->and(Team::count())->toBe(0)
        ->and(Submission::count())->toBe(0)
        ->and(ScoreEntry::count())->toBe(0)
        ->and(ManualScore::count())->toBe(0)
        ->and(GradingSetting::count())->toBe(0);
});

test('perintah hapus demo TIDAK menghapus materi global', function () {
    $this->seed(DemoDataSeeder::class);

    $level = Level::count();
    $misi = Mission::count();
    $kasus = TestCase::count();
    $bug = BugCard::count();
    $konsep = ConceptCard::count();

    $this->artisan('teater:hapus-demo --force')->assertSuccessful();

    expect(Level::count())->toBe($level)
        ->and(Mission::count())->toBe($misi)
        ->and(TestCase::count())->toBe($kasus)
        ->and(BugCard::count())->toBe($bug)
        ->and(ConceptCard::count())->toBe($konsep);
});

test('perintah hapus demo tidak mengganggu kelas nyata', function () {
    $this->seed(DemoDataSeeder::class);

    $guru = User::query()->where('role', 'guru')->firstOrFail();

    $kelasNyata = SchoolClass::create([
        'class_name' => 'X RPL Nyata',
        'school_year' => '2026/2027',
        'user_id' => $guru->id,
    ]);

    $siswaNyata = Student::create([
        'class_id' => $kelasNyata->class_id,
        'full_name' => 'Siswa Nyata',
        'student_no' => 'N001',
    ]);

    $this->artisan('teater:hapus-demo --force')->assertSuccessful();

    expect(SchoolClass::query()->where('class_id', $kelasNyata->class_id)->exists())->toBeTrue()
        ->and(Student::query()->where('student_id', $siswaNyata->student_id)->exists())->toBeTrue();
});

test('perintah hapus demo melaporkan bila kelas demo tidak ada', function () {
    $this->artisan('teater:hapus-demo --force')
        ->expectsOutputToContain('Tidak ditemukan kelas demo')
        ->assertSuccessful();
});
