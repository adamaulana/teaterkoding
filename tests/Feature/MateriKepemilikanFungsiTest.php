<?php

use App\Actions\BagiFungsiOtomatis;
use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
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
});

/**
 * Siapkan misi pada level sumatif + kelas + kelompok berisi siswa.
 *
 * @return array{0: User, 1: Mission, 2: SchoolClass, 3: Team, 4: array<int, Student>}
 */
function siapkanFungsi(int $jumlahAnggota = 4, int $jumlahFungsi = 3): array
{
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->where('is_summative', true)->firstOrFail();

    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'SUM-'.uniqid(), 'client_brief' => 'Brief.']);

    for ($i = 1; $i <= $jumlahFungsi; $i++) {
        TestCase::create([
            'mission_id' => $misi->mission_id,
            'label' => "Fungsi {$i}",
            'check_target' => "fungsi_{$i}(100)",
            'expected_value' => '1000',
            'tolerance' => 0,
        ]);
    }

    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    $siswa = [];
    for ($i = 1; $i <= $jumlahAnggota; $i++) {
        $s = Student::create(['class_id' => $kelas->class_id, 'full_name' => "Siswa {$i}", 'student_no' => (string) (100 + $i)]);
        TeamMember::create(['team_id' => $team->team_id, 'student_id' => $s->student_id]);
        $siswa[] = $s;
    }

    return [$guru, $misi, $kelas, $team, $siswa];
}

// ---------- Algoritma bagi otomatis (murni) ----------

test('bagi otomatis membagi fungsi merata', function () {
    $peta = (new BagiFungsiOtomatis)->petaPemilik(['f1', 'f2', 'f3', 'f4'], [10, 20], acak: false);

    expect($peta[10])->toBe(['f1', 'f2'])
        ->and($peta[20])->toBe(['f3', 'f4']);
});

test('bagi otomatis menaruh sisa pada anggota terakhir bila fungsi lebih banyak', function () {
    // 5 fungsi, 2 anggota → dibagi merata 2 dan 3 (sisa ke anggota terakhir).
    $peta = (new BagiFungsiOtomatis)->petaPemilik(['f1', 'f2', 'f3', 'f4', 'f5'], [10, 20], acak: false);

    expect($peta[10])->toBe(['f1', 'f2'])
        ->and($peta[20])->toBe(['f3', 'f4', 'f5']);
});

test('bagi otomatis membuat anggota terakhir memegang dua fungsi', function () {
    // 3 fungsi, 2 anggota → 1 dan 2 (sesuai spesifikasi).
    $peta = (new BagiFungsiOtomatis)->petaPemilik(['f1', 'f2', 'f3'], [10, 20], acak: false);

    expect($peta[10])->toBe(['f1'])
        ->and($peta[20])->toBe(['f2', 'f3']);
});

test('bagi otomatis tanpa anggota atau fungsi menghasilkan array kosong', function () {
    $action = new BagiFungsiOtomatis;

    expect($action->petaPemilik([], [10]))->toBe([])
        ->and($action->petaPemilik(['f1'], []))->toBe([]);
});

// ---------- Penyimpanan kepemilikan ----------

test('kepemilikan fungsi tersimpan dan status lengkap', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(3, 3);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id);

    expect($komponen->instance()->statusFungsiKelompok($team->team_id))->toBe('belum_lengkap');

    $komponen
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswa[0]->student_id)
        ->set("pemilik.{$team->team_id}.fungsi_2", $siswa[1]->student_id)
        ->set("pemilik.{$team->team_id}.fungsi_3", $siswa[2]->student_id)
        ->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->where('mission_id', $misi->mission_id)->count())->toBe(3)
        ->and($komponen->instance()->statusFungsiKelompok($team->team_id))->toBe('lengkap');
});

test('pemilik harus anggota kelompok tersebut', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(2, 1);

    $siswaLain = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Orang Luar', 'student_no' => '999']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id)
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswaLain->student_id)
        ->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->count())->toBe(0);
});

test('fungsi tanpa pemilik ditolak saat menyimpan', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(2, 2);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id)
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswa[0]->student_id)
        ->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->count())->toBe(0);
});

test('kepemilikan ditolak bila kelompok sudah punya submission', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(1, 1);

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa[0]->student_id,
        'code_text' => 'print(1)',
    ]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id)
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswa[0]->student_id)
        ->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->count())->toBe(0);
});

test('bagi otomatis mengisi pratinjau lalu bisa diterapkan dan disimpan', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(4, 3);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id)
        ->call('bagiOtomatis');

    $pratinjau = $komponen->get('pratinjauBagi');
    expect($pratinjau)->toHaveKey($team->team_id);

    $komponen->call('terapkanBagiOtomatis')->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->where('mission_id', $misi->mission_id)->count())->toBe(3);
});

test('kepemilikan unik per fungsi per kelompok', function () {
    [$guru, $misi, $kelas, $team, $siswa] = siapkanFungsi(2, 1);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->set('activeTab', 'fungsi')
        ->set('fungsiKelasId', $kelas->class_id)
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswa[0]->student_id)
        ->call('simpanKepemilikan');

    $komponen
        ->set("pemilik.{$team->team_id}.fungsi_1", $siswa[1]->student_id)
        ->call('simpanKepemilikan');

    expect(FunctionOwnership::query()->where('mission_id', $misi->mission_id)->count())->toBe(1)
        ->and((int) FunctionOwnership::query()->firstOrFail()->student_id)->toBe($siswa[1]->student_id);
});
