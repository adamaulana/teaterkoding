<?php

use App\Models\Level;
use App\Models\ManualScore;
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

/**
 * Rekap Nilai Sumatif: pengisian nilai manual guru dan ekspor CSV.
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

    $this->siswa = collect();

    // Nama pertama sengaja diawali "=" untuk menguji proteksi CSV injection.
    foreach (['=Adi Santoso', 'Bella Safira'] as $nama) {
        $s = Student::create([
            'class_id' => $this->kelas->class_id,
            'full_name' => $nama,
            'student_no' => null,
        ]);
        TeamMember::create(['team_id' => $this->team->team_id, 'student_id' => $s->student_id]);
        $this->siswa->push($s);
    }

    $levelSumatif = Level::query()->where('is_summative', true)->firstOrFail();

    $this->misi = Mission::create([
        'level_id' => $levelSumatif->level_id,
        'unlock_code' => 'SUM-1',
        'client_brief' => 'Brief.',
    ]);

    TestCase::create([
        'mission_id' => $this->misi->mission_id,
        'label' => 'Total',
        'check_target' => 'hitung_total(1, 5000)',
        'expected_value' => '5000',
        'tolerance' => 0,
    ]);

    $this->actingAs($this->guru);
});

test('rekap nilai menampilkan siswa kelas', function () {
    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->assertSee('=Adi Santoso')
        ->assertSee('Bella Safira');
});

test('menyimpan nilai manual refleksi dan observasi', function () {
    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $this->siswa[0]->student_id)
        ->set('suntingRefleksi', '85')
        ->set('suntingObservasi', '90')
        ->set('suntingCatatan', 'Baik sekali')
        ->call('simpanNilai')
        ->assertHasNoErrors();

    $tersimpan = ManualScore::query()
        ->where('student_id', $this->siswa[0]->student_id)
        ->where('mission_id', $this->misi->mission_id)
        ->firstOrFail();

    expect($tersimpan->reflection_score)->toBe(85)
        ->and($tersimpan->observation_score)->toBe(90)
        ->and($tersimpan->notes)->toBe('Baik sekali')
        ->and((int) $tersimpan->graded_by)->toBe($this->guru->id);
});

test('menyimpan ulang memperbarui nilai manual yang sama', function () {
    $komponen = Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ]);

    $komponen->call('bukaSunting', $this->siswa[0]->student_id)
        ->set('suntingRefleksi', '70')
        ->call('simpanNilai');

    $komponen->call('bukaSunting', $this->siswa[0]->student_id)
        ->set('suntingRefleksi', '95')
        ->call('simpanNilai');

    expect(ManualScore::query()->where('student_id', $this->siswa[0]->student_id)->count())->toBe(1)
        ->and(ManualScore::query()->where('student_id', $this->siswa[0]->student_id)->firstOrFail()->reflection_score)->toBe(95);
});

test('nilai manual di luar rentang 0-100 ditolak', function (string $kolom, string $nilai) {
    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $this->siswa[0]->student_id)
        ->set($kolom, $nilai)
        ->call('simpanNilai')
        ->assertHasErrors($kolom);

    expect(ManualScore::query()->count())->toBe(0);
})->with([
    ['suntingRefleksi', '101'],
    ['suntingRefleksi', '-1'],
    ['suntingObservasi', '101'],
    ['suntingObservasi', '-1'],
]);

test('nilai manual boleh dikosongkan kembali', function () {
    ManualScore::create([
        'student_id' => $this->siswa[0]->student_id,
        'mission_id' => $this->misi->mission_id,
        'reflection_score' => 80,
        'observation_score' => 80,
    ]);

    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $this->siswa[0]->student_id)
        ->set('suntingRefleksi', '')
        ->set('suntingObservasi', '')
        ->call('simpanNilai')
        ->assertHasNoErrors();

    $tersimpan = ManualScore::query()->where('student_id', $this->siswa[0]->student_id)->firstOrFail();

    expect($tersimpan->reflection_score)->toBeNull()
        ->and($tersimpan->observation_score)->toBeNull();
});

test('nilai nol diterima sebagai nilai yang sah', function () {
    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $this->siswa[0]->student_id)
        ->set('suntingRefleksi', '0')
        ->set('suntingObservasi', '0')
        ->call('simpanNilai')
        ->assertHasNoErrors();

    $tersimpan = ManualScore::query()->where('student_id', $this->siswa[0]->student_id)->firstOrFail();

    expect($tersimpan->reflection_score)->toBe(0)
        ->and($tersimpan->observation_score)->toBe(0);
});

test('menolak menyimpan nilai untuk siswa kelas lain', function () {
    $kelasLain = SchoolClass::create(['class_name' => 'X RPL 2', 'school_year' => '2026/2027']);
    $siswaLain = Student::create(['class_id' => $kelasLain->class_id, 'full_name' => 'Orang Lain', 'student_no' => null]);

    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $siswaLain->student_id)
        ->set('suntingRefleksi', '90')
        ->call('simpanNilai')
        ->assertForbidden();

    expect(ManualScore::query()->count())->toBe(0);
});

test('bukaSunting memuat nilai manual yang sudah ada', function () {
    ManualScore::create([
        'student_id' => $this->siswa[0]->student_id,
        'mission_id' => $this->misi->mission_id,
        'reflection_score' => 77,
        'observation_score' => 88,
        'notes' => 'Catatan lama',
    ]);

    Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ])
        ->call('bukaSunting', $this->siswa[0]->student_id)
        ->assertSet('suntingRefleksi', '77')
        ->assertSet('suntingObservasi', '88')
        ->assertSet('suntingCatatan', 'Catatan lama');
});
