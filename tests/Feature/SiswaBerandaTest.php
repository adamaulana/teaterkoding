<?php

use App\Livewire\Siswa\Beranda;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('beranda menampilkan pesan bila siswa belum masuk kelompok', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'access_code' => 'ABCD2345']);

    $this->actingAs($siswa, 'siswa');

    $this->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('Kamu belum masuk kelompok');
});

test('beranda menampilkan nama kelompok dan anggota', function () {
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok Alpha']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $this->actingAs($siswa, 'siswa');

    $this->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('Kelompok Alpha')
        ->assertSee('Budi Santoso');
});

// ============================================================
// Gambar peran (public/images) di beranda siswa
// ============================================================

/**
 * Konteks minimal: kelas, kelompok, satu siswa, level, dan (opsional) peran.
 *
 * @return array{0: SchoolClass, 1: Team, 2: Level, 3: Student}
 */
function konteksPeran(?string $namaPeran = null, bool $levelAktif = true): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok Alpha']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'access_code' => 'ABCD2345']);

    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    if ($namaPeran !== null) {
        $role = Role::firstOrCreate(['role_name' => $namaPeran]);

        RoleAssignment::create([
            'team_id' => $team->team_id,
            'student_id' => $siswa->student_id,
            'level_id' => $level->level_id,
            'role_id' => $role->role_id,
        ]);
    }

    if ($levelAktif) {
        // Level dianggap aktif bila guru membuka akses kelas + ada misi.
        Mission::create([
            'level_id' => $level->level_id,
            'unlock_code' => 'KOP-L1-BUDI',
            'client_brief' => 'brief',
            'starter_code' => 'print(1)',
        ]);

        ClassLevelAccess::create([
            'class_id' => $kelas->class_id,
            'level_id' => $level->level_id,
            'is_open' => true,
        ]);
    }

    return [$kelas, $team, $level, $siswa];
}

test('peran Coder menampilkan gambar Coder.png', function () {
    [, , , $siswa] = konteksPeran('Coder');

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Coder.png', false)
        ->assertSee('Peranmu saat ini')
        ->assertSee('Menulis dan mengirim kode program utama.');
});

test('peran Bug Hunter memakai berkas Bug_Hunter.png (spasi jadi garis bawah)', function () {
    [, , , $siswa] = konteksPeran('Bug Hunter');

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Bug_Hunter.png', false)
        ->assertSee('Bug Hunter');
});

test('anggota tanpa peran menampilkan fallback tanpa gambar peran', function () {
    [, , , $siswa] = konteksPeran(null);

    $html = $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('Belum ada peran')
        ->getContent();

    // Tidak boleh menampilkan gambar peran apa pun bila peran belum ditetapkan.
    foreach (['Coder.png', 'Tester.png', 'Analyst.png', 'Reviewer.png', 'Bug_Hunter.png'] as $berkas) {
        expect($html)->not->toContain('images/'.$berkas);
    }
});

test('peran tetap tampil walau belum ada level aktif', function () {
    [, , , $siswa] = konteksPeran('Analyst', levelAktif: false);

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Analyst.png', false)
        ->assertSee('Analyst');
});

test('semua berkas gambar peran yang dipetakan benar-benar ada', function () {
    foreach (['Coder.png', 'Tester.png', 'Analyst.png', 'Reviewer.png', 'Bug_Hunter.png'] as $satu) {
        expect(public_path('images/'.$satu))->toBeFile();
    }
});

// ============================================================
// Regresi: peran hanya ditugaskan di Level 1, tetapi level aktif sudah L4
// ============================================================

/**
 * Konteks dengan peran di level tertentu, plus status level lain.
 *
 * Meniru kondisi nyata: kelompok ditugaskan peran di Level 1 (BagiPeranAwal),
 * lalu naik ke Level 4 karena Level 1-3 sudah lulus.
 *
 * @return array{0: SchoolClass, 1: Team, 2: Student, 3: array<int, Level>}
 */
function konteksPeranLintasLevel(string $namaPeran, int $levelPeran, bool $lulusLevelPeran = false): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok Echo']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Tika Purnama', 'access_code' => 'ABCD2345']);

    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $levels = [];

    foreach ([1, 2, 3, 4] as $nomor) {
        $levels[$nomor] = Level::create([
            'level_number' => $nomor,
            'level_name' => 'Level '.$nomor,
            'is_summative' => $nomor === 4,
        ]);

        Mission::create([
            'level_id' => $levels[$nomor]->level_id,
            'unlock_code' => 'KOP-L'.$nomor,
            'client_brief' => 'brief',
            'starter_code' => 'print(1)',
        ]);

        ClassLevelAccess::create([
            'class_id' => $kelas->class_id,
            'level_id' => $levels[$nomor]->level_id,
            'is_open' => true,
        ]);
    }

    $role = Role::firstOrCreate(['role_name' => $namaPeran]);

    RoleAssignment::create([
        'team_id' => $team->team_id,
        'student_id' => $siswa->student_id,
        'level_id' => $levels[$levelPeran]->level_id,
        'role_id' => $role->role_id,
    ]);

    // Level 1-3 lulus → level aktif menjadi Level 4.
    if ($lulusLevelPeran) {
        foreach ([1, 2, 3] as $nomor) {
            Submission::create([
                'team_id' => $team->team_id,
                'mission_id' => Mission::query()->where('level_id', $levels[$nomor]->level_id)->value('mission_id'),
                'submitted_by' => $siswa->student_id,
                'code_text' => 'print(1)',
                'submitted_at' => now(),
                'all_passed' => true,
                'status' => Submission::STATUS_DINILAI,
                'attempt_no' => 1,
            ]);
        }
    }

    return [$kelas, $team, $siswa, $levels];
}

test('peran Level 1 tetap tampil walau level aktif sudah Level 4', function () {
    // Kasus nyata: peran hanya di Level 1, Level 1-3 sudah lulus.
    [, , $siswa] = konteksPeranLintasLevel('Reviewer', levelPeran: 1, lulusLevelPeran: true);

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Reviewer.png', false)
        ->assertSee('Reviewer')
        ->assertDontSee('Belum ada peran');
});

test('peran pada level aktif diutamakan bila ada', function () {
    // Peran ada di Level 1 DAN Level 4; level aktif = Level 4.
    [$kelas, $team, $siswa, $levels] = konteksPeranLintasLevel('Reviewer', levelPeran: 1, lulusLevelPeran: true);

    $roleAnalyst = Role::firstOrCreate(['role_name' => 'Analyst']);

    RoleAssignment::create([
        'team_id' => $team->team_id,
        'student_id' => $siswa->student_id,
        'level_id' => $levels[4]->level_id,
        'role_id' => $roleAnalyst->role_id,
    ]);

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Analyst.png', false)
        ->assertSee('Analyst');
});

test('peran level 2 tampil saat level aktif adalah level 2', function () {
    [, , $siswa] = konteksPeranLintasLevel('Tester', levelPeran: 2);

    $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('images/Tester.png', false)
        ->assertSee('Tester');
});

test('tanpa penugasan sama sekali tetap menampilkan Belum ada peran', function () {
    [, , $siswa] = konteksPeranLintasLevel('Coder', levelPeran: 1, lulusLevelPeran: true);

    // Hapus penugasan untuk skenario ini.
    RoleAssignment::query()->delete();

    $html = $this->actingAs($siswa, 'siswa')
        ->get('/siswa/beranda')
        ->assertOk()
        ->assertSee('Belum ada peran')
        ->getContent();

    expect($html)->not->toContain('images/Coder.png');
});

// ============================================================
// Buka misi dengan kode unlock (regresi "Kode misi tidak ditemukan")
// ============================================================

/**
 * Konteks: kelas + kelompok + siswa + satu misi Level 1 yang TERBUKA.
 *
 * @return array{0: SchoolClass, 1: Team, 2: Student, 3: Mission}
 */
function konteksBukaMisi(string $kodeUnlock = 'KOP-L1-BUDI'): array
{
    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok Echo']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi Santoso', 'access_code' => 'ABCD2345']);

    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => $kodeUnlock,
        'client_brief' => 'brief',
        'starter_code' => 'print(1)',
    ]);

    ClassLevelAccess::create([
        'class_id' => $kelas->class_id,
        'level_id' => $level->level_id,
        'is_open' => true,
    ]);

    return [$kelas, $team, $siswa, $misi];
}

test('kode misi bertanda hubung membuka misi yang benar', function () {
    // Kasus nyata: guru menyimpan "KOP-L1-BUDI", siswa mengetik hal yang sama.
    [, , $siswa, $misi] = konteksBukaMisi('KOP-L1-BUDI');

    Livewire::actingAs($siswa, 'siswa')
        ->test(Beranda::class)
        ->set('kodeMisi', 'KOP-L1-BUDI')
        ->call('bukaMisi')
        ->assertRedirect(route('siswa.misi', $misi->mission_id));
});

test('kode misi huruf kecil tetap membuka misi', function () {
    [, , $siswa, $misi] = konteksBukaMisi('KOP-L1-BUDI');

    Livewire::actingAs($siswa, 'siswa')
        ->test(Beranda::class)
        ->set('kodeMisi', 'kop-l1-budi')
        ->call('bukaMisi')
        ->assertRedirect(route('siswa.misi', $misi->mission_id));
});

test('kode misi tanpa tanda hubung tetap membuka misi (toleransi)', function () {
    [, , $siswa, $misi] = konteksBukaMisi('KOP-L1-BUDI');

    Livewire::actingAs($siswa, 'siswa')
        ->test(Beranda::class)
        ->set('kodeMisi', 'KOPL1BUDI')
        ->call('bukaMisi')
        ->assertRedirect(route('siswa.misi', $misi->mission_id));
});

test('kode misi dengan spasi di tepi tetap membuka misi', function () {
    [, , $siswa, $misi] = konteksBukaMisi('KOP-L1-BUDI');

    Livewire::actingAs($siswa, 'siswa')
        ->test(Beranda::class)
        ->set('kodeMisi', '  kop-l1-budi  ')
        ->call('bukaMisi')
        ->assertRedirect(route('siswa.misi', $misi->mission_id));
});

test('kode misi yang tidak ada menampilkan pesan tidak ditemukan', function () {
    [, , $siswa] = konteksBukaMisi('KOP-L1-BUDI');

    Livewire::actingAs($siswa, 'siswa')
        ->test(Beranda::class)
        ->set('kodeMisi', 'TIDAK-ADA-9')
        ->call('bukaMisi')
        ->assertNoRedirect()
        ->assertDispatched('toast', type: 'bahaya', message: 'Kode misi tidak ditemukan.');
});
