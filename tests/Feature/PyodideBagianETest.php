<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\ClassLevelAccess;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Models\User;
use App\Services\PenilaianKlien;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Uji tambahan Bagian E poin 1: skema payload, batas ukuran, kepemilikan,
 * dan kebocoran expected_value lewat respons mentah & state Livewire.
 *
 * @return array{0: User, 1: SchoolClass, 2: Team, 3: Mission, 4: Student, 5: Level}
 */
function konteksE(bool $sumatif = true): array
{
    $guru = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    $kelas = SchoolClass::create([
        'class_name' => 'X RPL',
        'school_year' => '2025/2026',
        'user_id' => $guru->id,
    ]);

    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);

    $level = Level::create([
        'level_number' => $sumatif ? 4 : 1,
        'level_name' => $sumatif ? 'Level 4' : 'Level 1',
        'is_summative' => $sumatif,
    ]);

    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'KOP-E1',
        'client_brief' => 'brief',
        'starter_code' => 'x = 0',
    ]);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    return [$guru, $kelas, $team, $misi, $siswa, $level];
}

// ============================================================
// Skema & ukuran payload
// ============================================================

/** Submission 'menunggu' dengan nonce valid (hasil submit runner pyodide). */
function submissionE(Team $team, Mission $misi, Student $siswa): Submission
{
    return Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
        'grading_nonce' => str_repeat('a', 64),
        'nonce_expires_at' => now()->addMinutes(15),
    ]);
}

test('payload dengan skema salah ditolak validasi', function () {
    [, , $team, $misi, $siswa] = konteksE();

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionE($team, $misi, $siswa);

    $dasar = [
        'jenis' => 'misi',
        'id' => $submission->submission_id,
        'nonce' => str_repeat('a', 64),
        'hasil' => [['id' => 1, 'tipe' => 'int', 'nilai' => 1]],
    ];

    $this->actingAs($siswa, 'siswa');

    // Jenis tidak dikenal.
    $this->postJson(route('siswa.penilaian.lapor'), [...$dasar, 'jenis' => 'lainnya'])->assertStatus(422);

    // Nonce bukan 64 karakter.
    $this->postJson(route('siswa.penilaian.lapor'), [...$dasar, 'nonce' => 'pendek'])->assertStatus(422);

    // Tipe hasil tidak dikenal.
    $this->postJson(route('siswa.penilaian.lapor'), [
        ...$dasar,
        'hasil' => [['id' => 1, 'tipe' => 'dict', 'nilai' => 1]],
    ])->assertStatus(422);

    // hasil kosong.
    $this->postJson(route('siswa.penilaian.lapor'), [...$dasar, 'hasil' => []])->assertStatus(422);

    // durasi_ms di luar batas.
    $this->postJson(route('siswa.penilaian.lapor'), [...$dasar, 'durasi_ms' => 999999999])->assertStatus(422);
});

test('payload terlalu besar ditolak (hasil > 200 kasus)', function () {
    [, , $team, $misi, $siswa] = konteksE();

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionE($team, $misi, $siswa);

    $hasil = [];

    for ($i = 1; $i <= 201; $i++) {
        $hasil[] = ['id' => $i, 'tipe' => 'int', 'nilai' => 1];
    }

    $this->actingAs($siswa, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
            'nonce' => str_repeat('a', 64),
            'hasil' => $hasil,
        ])
        ->assertStatus(422);
});

test('nilai non-skalar dari klien dibersihkan, bukan disimpan', function () {
    [, , $team, $misi, $siswa] = konteksE();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionE($team, $misi, $siswa);

    // Klien mengirim array sebagai "nilai" — harus dibersihkan menjadi null
    // sehingga tidak lolos dan tidak tersimpan sebagai JSON di kolom teks.
    $this->actingAs($siswa, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
            'nonce' => str_repeat('a', 64),
            'hasil' => [[
                'id' => (int) $kasus->test_case_id,
                'tipe' => 'int',
                'nilai' => ['palsu' => true],
            ]],
        ])
        ->assertOk();

    $hasil = $submission->fresh()->results()->first();

    expect($hasil->is_passed)->toBeFalse();
});

// ============================================================
// Klaim lulus dari klien diabaikan (formatif & sumatif)
// ============================================================

test('klaim lulus palsu diabaikan pada formatif dan sumatif', function (bool $sumatif) {
    [, , $team, $misi, $siswa] = konteksE($sumatif);

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionE($team, $misi, $siswa);

    app(PenilaianKlien::class)->submission($submission, [
        'nonce' => (string) $submission->grading_nonce,
        'hasil' => [[
            'id' => (int) $kasus->test_case_id,
            'tipe' => 'int',
            'nilai' => 999,
            // Klaim klien — harus diabaikan sepenuhnya.
            'is_passed' => true,
            'lulus' => true,
        ]],
        'keluaran' => '',
        'galat' => null,
        'durasi_ms' => 10,
        'versi_pyodide' => '314.0.7',
    ]);

    $segar = $submission->fresh();

    // Nilai 999 ≠ 1 → gagal, dan poin tidak diberikan.
    expect($segar->results()->where('is_passed', true)->count())->toBe(0)
        ->and($segar->all_passed)->toBeFalse()
        ->and(ScoreEntry::query()->count())->toBe(0);

    if ($sumatif) {
        expect($segar->status)->toBe(Submission::STATUS_MENUNGGU_VERIFIKASI);
    } else {
        expect($segar->status)->toBe(Submission::STATUS_DINILAI);
    }
})->with([
    'formatif' => [false],
    'sumatif' => [true],
]);

// ============================================================
// Paket: tidak membocorkan expected_value / tolerance
// ============================================================

test('paket siswa & halaman misi tidak memuat expected_value atau tolerance', function () {
    [, , $team, $misi, $siswa] = konteksE();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Label Rahasia',
        'check_target' => 'x',
        'expected_value' => 'NILAI_RAHASIA_E',
        'tolerance' => 0.25,
    ]);

    $submission = submissionE($team, $misi, $siswa);

    $respons = $this->actingAs($siswa, 'siswa')
        ->getJson(route('siswa.penilaian.paket-misi', $submission->submission_id));

    $respons->assertOk();

    // Periksa isi respons MENTAH, bukan hanya JSON terstruktur.
    $mentah = (string) $respons->getContent();

    expect($mentah)->not->toContain('NILAI_RAHASIA_E')
        ->and($mentah)->not->toContain('expected_value')
        ->and($mentah)->not->toContain('tolerance')
        ->and($mentah)->not->toContain('Label Rahasia');

    // Halaman misi (state Livewire) juga tidak boleh memuatnya.
    $halaman = (string) $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->getContent();

    expect($halaman)->not->toContain('NILAI_RAHASIA_E')
        ->and($halaman)->not->toContain('expected_value');
});

test('siswa tidak bisa mengambil paket atau melapor untuk kelompok lain', function () {
    [, , $team, $misi, $siswa] = konteksE();

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionE($team, $misi, $siswa);

    $kelas2 = SchoolClass::create(['class_name' => 'Y RPL', 'school_year' => '2025/2026']);
    $lain = Student::create(['class_id' => $kelas2->class_id, 'full_name' => 'Lain', 'access_code' => 'MNOP2345']);
    $team2 = Team::create(['class_id' => $kelas2->class_id, 'team_name' => 'Beta']);
    TeamMember::create(['team_id' => $team2->team_id, 'student_id' => $lain->student_id]);

    $this->actingAs($lain, 'siswa')
        ->getJson(route('siswa.penilaian.paket-misi', $submission->submission_id))
        ->assertNotFound();

    $this->actingAs($lain, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
            'nonce' => str_repeat('a', 64),
            'hasil' => [['id' => 1, 'tipe' => 'int', 'nilai' => 1]],
        ])
        ->assertNotFound();
});

test('paket bug tidak memuat expected_value dan menolak kelompok lain', function () {
    [, , $team, , $siswa, $level] = konteksE();

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Bug E',
        'symptom_text' => 'gejala',
        'buggy_code' => 'x = "1"',
        'hint_text' => 'petunjuk',
        'fix_explanation' => 'perbaikan',
        'points' => 10,
    ]);

    BugTestCase::create([
        'bug_id' => $kartu->bug_id,
        'label' => 'Label Bug Rahasia',
        'check_target' => 'x',
        'expected_value' => 'NILAI_BUG_RAHASIA',
        'tolerance' => 0.5,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'fixed_code' => 'x = 1',
        'grading_status' => BugAttempt::STATUS_MENUNGGU,
        'attempted_at' => now(),
        'grading_nonce' => str_repeat('b', 64),
        'nonce_expires_at' => now()->addMinutes(15),
    ]);

    $respons = $this->actingAs($siswa, 'siswa')
        ->getJson(route('siswa.penilaian.paket-bug', $attempt->attempt_id));

    $respons->assertOk();

    expect((string) $respons->getContent())
        ->not->toContain('NILAI_BUG_RAHASIA')
        ->not->toContain('Label Bug Rahasia');
});
