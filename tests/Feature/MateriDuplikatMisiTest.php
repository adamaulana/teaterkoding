<?php

use App\Actions\DuplikatMisi;
use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
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

test('duplikat misi menyalin seluruh kasus uji', function () {
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $sumber = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-ASAL', 'client_brief' => 'Brief asal.']);

    TestCase::create(['mission_id' => $sumber->mission_id, 'label' => 'A', 'setup_code' => 'saldo = 100', 'check_target' => 'status', 'expected_value' => '"lunas"', 'tolerance' => 0]);
    TestCase::create(['mission_id' => $sumber->mission_id, 'label' => 'B', 'check_target' => 'hitung_total(100)', 'expected_value' => '1000', 'tolerance' => 1]);

    $hasil = (new DuplikatMisi)($sumber, 'KOP-L1-SALIN');

    expect($hasil['jumlah_kasus_uji'])->toBe(2)
        ->and($hasil['mission']->unlock_code)->toBe('KOP-L1-SALIN')
        ->and($hasil['mission']->client_brief)->toBe('Brief asal.')
        ->and(TestCase::query()->where('mission_id', $hasil['mission']->mission_id)->count())->toBe(2);

    // setup_code ikut tersalin.
    $tersalin = TestCase::query()->where('mission_id', $hasil['mission']->mission_id)->orderBy('test_case_id')->first();
    expect($tersalin->setup_code)->toBe('saldo = 100');
});

test('duplikat misi tidak menyalin kepemilikan fungsi', function () {
    $level = Level::query()->where('is_summative', true)->firstOrFail();
    $sumber = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L4-ASAL', 'client_brief' => 'Brief.']);

    TestCase::create(['mission_id' => $sumber->mission_id, 'label' => 'A', 'check_target' => 'hitung_total(100)', 'expected_value' => '1000', 'tolerance' => 0]);

    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $this->guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => '1']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    FunctionOwnership::create([
        'mission_id' => $sumber->mission_id,
        'team_id' => $team->team_id,
        'function_name' => 'hitung_total',
        'student_id' => $siswa->student_id,
    ]);

    $hasil = (new DuplikatMisi)($sumber, 'KOP-L4-SALIN');

    expect(FunctionOwnership::query()->where('mission_id', $sumber->mission_id)->count())->toBe(1)
        ->and(FunctionOwnership::query()->where('mission_id', $hasil['mission']->mission_id)->count())->toBe(0);
});

test('duplikat misi lewat halaman memakai kode baru dan menolak kode duplikat', function () {
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $sumber = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-ASAL', 'client_brief' => 'Brief.']);
    TestCase::create(['mission_id' => $sumber->mission_id, 'label' => 'A', 'check_target' => 'status', 'expected_value' => '"lunas"', 'tolerance' => 0]);

    $this->actingAs($this->guru);

    // Kode sudah dipakai → ditolak.
    Livewire::test('pages::admin.misi')
        ->call('bukaDuplikat', $sumber->mission_id)
        ->set('kodeDuplikat', 'KOP-L1-ASAL')
        ->call('simpanDuplikat')
        ->assertHasErrors(['kodeDuplikat']);

    expect(Mission::query()->count())->toBe(1);

    // Kode baru → berhasil, kasus uji tersalin.
    Livewire::test('pages::admin.misi')
        ->call('bukaDuplikat', $sumber->mission_id)
        ->set('kodeDuplikat', 'KOP-L1-BARU')
        ->call('simpanDuplikat')
        ->assertHasNoErrors();

    $baru = Mission::query()->where('unlock_code', 'KOP-L1-BARU')->firstOrFail();

    expect(Mission::query()->count())->toBe(2)
        ->and(TestCase::query()->where('mission_id', $baru->mission_id)->count())->toBe(1);
});

test('form duplikat menyiapkan kode otomatis yang belum terpakai', function () {
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $sumber = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-ASAL', 'client_brief' => 'Brief.']);

    $this->actingAs($this->guru);

    $komponen = Livewire::test('pages::admin.misi')->call('bukaDuplikat', $sumber->mission_id);

    $kode = $komponen->get('kodeDuplikat');

    expect($kode)->not->toBe('')
        ->and(Mission::query()->where('unlock_code', $kode)->exists())->toBeFalse();
});
