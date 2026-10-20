<?php

use App\Livewire\Siswa\KartuBug;
use App\Livewire\Siswa\Misi;
use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\MissionPlan;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use Database\Seeders\ConceptCardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Regresi: langkah Koding sempat kosong sama sekali ketika CodeMirror gagal
 * dimuat ("Unrecognized extension value in extension set"). Siswa tidak boleh
 * melihat langkah Koding tanpa tempat mengetik.
 *
 * Perbaikan JS-nya diuji di `npm run uji:editor`. Test di sini memastikan
 * MARKUP selalu membawa textarea cadangan + pesan yang diminta, sehingga
 * fallback tetap ada walau modul JS gagal termuat sekalipun.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ConceptCardsSeeder::class);
});

const PESAN_CADANGAN = 'Editor lanjutan gagal dimuat, kamu tetap bisa mengetik di kolom ini.';

function konteksKoding(bool $sumatif = false): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $level = Level::create([
        'level_number' => $sumatif ? 4 : 1,
        'level_name' => $sumatif ? 'Level 4' : 'Level 1',
        'is_summative' => $sumatif,
    ]);
    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'KOP-L1',
        'client_brief' => 'brief',
        'starter_code' => 'print(1)',
    ]);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $roleCoder = Role::firstOrCreate(['role_name' => 'Coder']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Coder Satu', 'access_code' => 'ABCD2345']);

    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);
    RoleAssignment::create([
        'team_id' => $team->team_id,
        'student_id' => $siswa->student_id,
        'level_id' => $level->level_id,
        'role_id' => $roleCoder->role_id,
    ]);

    MissionPlan::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'planned_at' => now(),
        'locked_at' => now(),
        'unlocked_by' => $siswa->student_id,
    ]);

    return [$kelas, $team, $level, $misi, $siswa];
}

test('langkah Koding menyediakan textarea cadangan bila editor gagal dimuat', function () {
    [, , , $misi, $siswa] = konteksKoding();

    $html = Livewire::actingAs($siswa, 'siswa')
        ->test(Misi::class, ['mission' => $misi])
        ->set('langkah', 'koding')
        ->html();

    expect($html)->toContain(PESAN_CADANGAN)
        ->and($html)->toContain('data-editor-cadangan')
        ->and($html)->toContain('x-ref="cadanganArea"')
        ->and($html)->toContain('onCadanganInput($event)')
        ->and($html)->not->toContain('x-init="init($wire)"');
});

test('textarea cadangan memuat draf tersimpan agar siswa tidak kehilangan kode', function () {
    [, , , $misi, $siswa] = konteksKoding();

    $kode = 'saldo = 100'."\n".'print(saldo)';

    $komponen = Livewire::actingAs($siswa, 'siswa')
        ->test(Misi::class, ['mission' => $misi])
        ->set('langkah', 'koding')
        ->set('kode', $kode)
        ->call('simpanDraf');

    expect($komponen->html())->toContain('print(saldo)');
});

test('kartu bug menyediakan textarea cadangan bila editor gagal dimuat', function () {
    [$kelas, $team, $level] = konteksKoding();

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug Cadangan',
        'symptom_text' => 'Gejala.',
        'buggy_code' => 'print(0)',
        'fix_explanation' => 'penjelasan',
        'points' => 10,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'is_solved' => false,
        'grading_status' => BugAttempt::STATUS_BELUM,
        'hints_used' => 0,
        'attempted_at' => now(),
    ]);

    $html = Livewire::actingAs($siswa = Student::query()->where('class_id', $kelas->class_id)->firstOrFail(), 'siswa')
        ->test(KartuBug::class, ['attempt' => $attempt])
        ->html();

    expect($html)->toContain(PESAN_CADANGAN)
        ->and($html)->toContain('data-editor-cadangan')
        ->and($html)->toContain('onCadanganInput($event)');
});

test('kedua editor memakai autosave simpanDraf yang sama', function () {
    [, , , $misi, $siswa] = konteksKoding();

    // @script Livewire disuntikkan ke respons halaman penuh, bukan ke html()
    // komponen, jadi pemeriksaan skrip dilakukan lewat GET.
    $html = $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('simpanDraf')
        ->and($html)->toContain('jadwalkanSimpan')
        ->and($html)->toContain('onCadanganInput')
        ->and($html)->toContain('pakaiCadangan');
});
