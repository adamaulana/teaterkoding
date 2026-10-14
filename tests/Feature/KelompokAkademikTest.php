<?php

use App\Actions\AcakKelompok;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Buat kelas + kelompok + sejumlah siswa siap pakai.
 *
 * @return array{0: User, 1: SchoolClass, 2: Team, 3: array<int, Student>}
 */
function siapkanAkademik(int $jumlahSiswa = 0): array
{
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);

    $siswa = [];
    for ($i = 1; $i <= $jumlahSiswa; $i++) {
        $siswa[] = Student::create([
            'class_id' => $kelas->class_id,
            'full_name' => 'Siswa '.$i,
            'student_no' => (string) (1000 + $i),
        ]);
    }

    return [$guru, $kelas, $team, $siswa];
}

// ---------- Algoritma AcakKelompok (murni) ----------

test('acak kelompok 22 siswa target 5 menghasilkan 5,5,4,4,4', function () {
    $ids = range(1, 22);

    $hasil = (new AcakKelompok)->susun($ids, 5, 1, acak: false);

    expect($hasil)->toHaveCount(5);

    $ukuran = array_map(fn (array $g): int => count($g['student_ids']), $hasil);
    expect($ukuran)->toBe([5, 5, 4, 4, 4]);

    $semua = array_merge(...array_map(fn (array $g): array => $g['student_ids'], $hasil));
    sort($semua);
    expect($semua)->toBe($ids);
});

test('acak kelompok 13 siswa target 4 menghasilkan 4,3,3,3 tanpa grup 1-2 orang', function () {
    $ids = range(1, 13);

    $hasil = (new AcakKelompok)->susun($ids, 4, 1, acak: false);

    $ukuran = array_map(fn (array $g): int => count($g['student_ids']), $hasil);

    expect($ukuran)->toBe([4, 3, 3, 3]);

    foreach ($ukuran as $jumlah) {
        expect($jumlah)->toBeGreaterThanOrEqual(3);
    }
});

test('nama kelompok melanjutkan nomor terakhir', function () {
    $action = new AcakKelompok;

    $hasil = $action->susun(range(1, 5), 4, $action->deteksiNomorAwal(['Kelompok 1', 'Kelompok 2', 'Kelompok 7']));

    expect($hasil[0]['name'])->toBe('Kelompok 8');
});

test('daftar siswa kosong menghasilkan tanpa kelompok', function () {
    expect((new AcakKelompok)->susun([], 4))->toBe([]);
});

// ---------- Aturan kelompok pada halaman ----------

test('kelompok maksimal 5 anggota', function () {
    [$guru, $kelas, $team, $siswa] = siapkanAkademik(6);

    foreach (array_slice($siswa, 0, 5) as $s) {
        TeamMember::create(['team_id' => $team->team_id, 'student_id' => $s->student_id]);
    }

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaKelolaAnggota', $team->team_id)
        ->set('anggotaTerpilih', [$siswa[5]->student_id])
        ->call('tambahAnggota');

    expect($team->teamMembers()->count())->toBe(5);
});

test('satu siswa hanya boleh punya satu kelompok per kelas', function () {
    [$guru, $kelas, $team, $siswa] = siapkanAkademik(1);

    $team2 = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 2']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa[0]->student_id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaKelolaAnggota', $team2->team_id)
        ->set('anggotaTerpilih', [$siswa[0]->student_id])
        ->call('tambahAnggota');

    expect($team2->teamMembers()->count())->toBe(0);
});

test('siswa dari kelas lain tidak bisa ditambahkan ke kelompok', function () {
    [$guru, $kelas, $team] = siapkanAkademik();

    $kelasLain = SchoolClass::create(['class_name' => 'X RPL 2', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $siswaLain = Student::create(['class_id' => $kelasLain->class_id, 'full_name' => 'Orang Luar', 'student_no' => null]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaKelolaAnggota', $team->team_id)
        ->set('anggotaTerpilih', [$siswaLain->student_id])
        ->call('tambahAnggota');

    expect($team->teamMembers()->count())->toBe(0);
});

/**
 * Buat submission pada sebuah kelompok (butuh level + misi).
 */
function buatSubmission(Team $team, Student $siswa): void
{
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);

    $mission = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'KODE-'.uniqid(),
        'client_brief' => 'Brief klien.',
    ]);

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $mission->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
    ]);
}

test('hapus kelompok ditolak saat sudah punya submission', function () {
    [$guru, $kelas, $team, $siswa] = siapkanAkademik(1);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa[0]->student_id]);
    buatSubmission($team, $siswa[0]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('mintaHapus', $team->team_id)
        ->call('hapusKelompok');

    expect(Team::query()->where('team_id', $team->team_id)->exists())->toBeTrue();
});

test('hapus kelompok berhasil saat kosong dan menghapus anggotanya', function () {
    [$guru, $kelas, $team, $siswa] = siapkanAkademik(1);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa[0]->student_id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('mintaHapus', $team->team_id)
        ->call('hapusKelompok');

    expect(Team::query()->where('team_id', $team->team_id)->exists())->toBeFalse()
        ->and(TeamMember::query()->where('team_id', $team->team_id)->count())->toBe(0);
});

test('acak otomatis mode reset menyimpan kelompok dan seluruh anggotanya', function () {
    [$guru, $kelas] = siapkanAkademik(9);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaAcak')
        ->set('acakTarget', 4)
        ->set('acakMode', 'reset')
        ->set('acakKonfirmasi', $kelas->class_name)
        ->call('acakUlang')
        ->call('simpanAcak');

    $teams = Team::query()->where('class_id', $kelas->class_id)->get();

    expect($teams)->toHaveCount(3)
        ->and($teams->sum(fn (Team $t): int => $t->teamMembers()->count()))->toBe(9);
});

test('mode reset ditolak bila sudah ada submission', function () {
    [$guru, $kelas, $team, $siswa] = siapkanAkademik(1);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa[0]->student_id]);
    buatSubmission($team, $siswa[0]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaAcak')
        ->set('acakMode', 'reset')
        ->set('acakKonfirmasi', $kelas->class_name)
        ->call('acakUlang')
        ->call('simpanAcak');

    // Kelompok lama masih ada; tidak ada kelompok baru dibuat.
    expect(Team::query()->where('class_id', $kelas->class_id)->count())->toBe(1);
});

test('mode reset ditolak bila nama kelas salah ketik', function () {
    [$guru, $kelas] = siapkanAkademik(4);

    $this->actingAs($guru);

    Livewire::test('pages::admin.kelompok')
        ->set('kelasId', $kelas->class_id)
        ->call('bukaAcak')
        ->set('acakMode', 'reset')
        ->set('acakKonfirmasi', 'nama salah')
        ->call('acakUlang')
        ->call('simpanAcak')
        ->assertHasErrors(['acakKonfirmasi']);

    // Tidak ada perubahan: tetap hanya kelompok bawaan dari setup.
    expect(Team::query()->where('class_id', $kelas->class_id)->count())->toBe(1);
});
