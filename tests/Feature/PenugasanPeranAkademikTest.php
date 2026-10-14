<?php

use App\Actions\BagiPeranAwal;
use App\Actions\RotasiPeran;
use App\Models\Level;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(LevelsSeeder::class);
});

/**
 * Siapkan kelas + kelompok berisi siswa, beserta peta role_id.
 *
 * @return array{0: User, 1: SchoolClass, 2: Team, 3: array<int, Student>, 4: array<string, int>, 5: Level}
 */
function siapkanPeran(int $jumlahAnggota = 4): array
{
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    $siswa = [];
    for ($i = 1; $i <= $jumlahAnggota; $i++) {
        $s = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siswa '.$i, 'student_no' => (string) (100 + $i)]);
        TeamMember::create(['team_id' => $team->team_id, 'student_id' => $s->student_id]);
        $siswa[] = $s;
    }

    $roleIds = Role::query()->pluck('role_id', 'role_name')->all();
    $level = Level::query()->where('level_number', 1)->firstOrFail();

    return [$guru, $kelas, $team, $siswa, $roleIds, $level];
}

// ---------- Algoritma murni ----------

test('rotasi peran menggeser melingkar sesuai urutan anggota', function () {
    $urutan = [10, 20, 30];
    $peranSebelumnya = [10 => 1, 20 => 2, 30 => 3];

    $hasil = (new RotasiPeran)->geser($urutan, $peranSebelumnya);

    expect($hasil[10])->toBe(3)
        ->and($hasil[20])->toBe(1)
        ->and($hasil[30])->toBe(2);
});

test('rotasi peran pada daftar kosong menghasilkan array kosong', function () {
    expect((new RotasiPeran)->geser([], []))->toBe([]);
});

test('bagi peran awal mengisi Coder Tester Analyst wajib satu orang', function () {
    $roleIds = ['Coder' => 1, 'Tester' => 2, 'Analyst' => 3, 'Reviewer' => 4, 'Bug Hunter' => 5];

    $hasil = (new BagiPeranAwal)->bagi([1, 2, 3, 4, 5], $roleIds, acak: false);

    expect($hasil)->toHaveCount(5);

    $nilai = array_values($hasil);
    expect($nilai)->toContain(1, 2, 3)
        ->and(count(array_unique($nilai)))->toBe(5);
});

test('bagi peran awal tidak memberi peran opsional bila anggota kurang', function () {
    $roleIds = ['Coder' => 1, 'Tester' => 2, 'Analyst' => 3, 'Reviewer' => 4, 'Bug Hunter' => 5];

    $hasil = (new BagiPeranAwal)->bagi([1, 2, 3], $roleIds, acak: false);

    expect($hasil)->toHaveCount(3)
        ->and(array_values($hasil))->toBe([1, 2, 3]);
});

// ---------- Aturan penugasan pada halaman ----------

test('Coder Tester Analyst wajib tepat satu orang per kelompok', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id)
        ->set("peran.{$team->team_id}.{$siswa[0]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[1]->student_id}", $roleIds['Tester'])
        ->call('simpanKelompok', $team->team_id);

    expect(RoleAssignment::query()->where('team_id', $team->team_id)->count())->toBe(0);
});

test('penugasan lengkap tersimpan ke basis data', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id)
        ->set("peran.{$team->team_id}.{$siswa[0]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[1]->student_id}", $roleIds['Tester'])
        ->set("peran.{$team->team_id}.{$siswa[2]->student_id}", $roleIds['Analyst'])
        ->set("peran.{$team->team_id}.{$siswa[3]->student_id}", $roleIds['Reviewer'])
        ->call('simpanKelompok', $team->team_id);

    expect(RoleAssignment::query()->where('team_id', $team->team_id)->where('level_id', $level->level_id)->count())->toBe(4);
});

test('satu peran tidak boleh dipegang dua siswa', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id)
        ->set("peran.{$team->team_id}.{$siswa[0]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[1]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[2]->student_id}", $roleIds['Analyst'])
        ->call('simpanKelompok', $team->team_id);

    expect(RoleAssignment::query()->where('team_id', $team->team_id)->count())->toBe(0);
});

test('status kelompok lengkap dihitung benar', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id);

    expect($komponen->instance()->statusKelompok($team->team_id))->toBe('kosong');

    $komponen
        ->set("peran.{$team->team_id}.{$siswa[0]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[1]->student_id}", $roleIds['Tester'])
        ->set("peran.{$team->team_id}.{$siswa[2]->student_id}", $roleIds['Analyst']);

    expect($komponen->instance()->statusKelompok($team->team_id))->toBe('lengkap');
});

test('rotasi hanya tersedia mulai level 2', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id);

    expect($komponen->instance()->bolehRotasi())->toBeFalse();

    $level2 = Level::query()->where('level_number', 2)->firstOrFail();
    $komponen->set('levelId', $level2->level_id);

    expect($komponen->instance()->bolehRotasi())->toBeTrue();
});

test('simpan semua menyimpan peran seluruh kelompok', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    $this->actingAs($guru);

    Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level->level_id)
        ->set("peran.{$team->team_id}.{$siswa[0]->student_id}", $roleIds['Coder'])
        ->set("peran.{$team->team_id}.{$siswa[1]->student_id}", $roleIds['Tester'])
        ->set("peran.{$team->team_id}.{$siswa[2]->student_id}", $roleIds['Analyst'])
        ->call('simpanSemua');

    expect(RoleAssignment::query()->where('team_id', $team->team_id)->count())->toBe(3);
});

test('rotasi dari level sebelumnya menggeser peran ke level tujuan', function () {
    [$guru, $kelas, $team, $siswa, $roleIds, $level] = siapkanPeran(4);

    RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $siswa[0]->student_id, 'level_id' => $level->level_id, 'role_id' => $roleIds['Coder']]);
    RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $siswa[1]->student_id, 'level_id' => $level->level_id, 'role_id' => $roleIds['Tester']]);
    RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $siswa[2]->student_id, 'level_id' => $level->level_id, 'role_id' => $roleIds['Analyst']]);
    RoleAssignment::create(['team_id' => $team->team_id, 'student_id' => $siswa[3]->student_id, 'level_id' => $level->level_id, 'role_id' => $roleIds['Reviewer']]);

    $level2 = Level::query()->where('level_number', 2)->firstOrFail();

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.penugasan-peran')
        ->set('kelasId', $kelas->class_id)
        ->set('levelId', $level2->level_id)
        ->call('siapkanRotasi');

    $pratinjau = $komponen->get('rotasiPratinjau');
    expect($pratinjau)->toHaveKey($team->team_id)
        ->and($pratinjau[$team->team_id])->toHaveCount(4);

    $komponen->call('terapkanRotasi')->call('simpanSemua');

    expect(RoleAssignment::query()->where('team_id', $team->team_id)->where('level_id', $level2->level_id)->count())->toBe(4);
});
