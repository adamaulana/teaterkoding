<?php

use App\Actions\BandingkanNilai;
use App\Jobs\GradeBugAttemptJob;
use App\Livewire\Siswa\Misi;
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
use App\Services\BugAttemptService;
use App\Services\PaketPenilaian;
use App\Services\PenilaianKlien;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Konteks minimal: 1 kelas, 1 kelompok, 1 level, 1 misi, 1 siswa.
 *
 * @return array{0: SchoolClass, 1: Team, 2: Level, 3: Mission, 4: Student}
 */
function konteksPenilaian(bool $sumatif = false): array
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
        'starter_code' => 'saldo = 0',
    ]);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    return [$kelas, $team, $level, $misi, $siswa];
}

/**
 * Submission berstatus 'menunggu' + nonce (seperti hasil submit pyodide).
 */
function submissionMenunggu(Team $team, Mission $misi, Student $siswa, string $kode = 'x = 1'): Submission
{
    return Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => $kode,
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
        'grading_nonce' => str_repeat('a', 64),
        'nonce_expires_at' => now()->addMinutes(15),
    ]);
}

// ============================================================
// BandingkanNilai (murni)
// ============================================================

test('pembanding menerima angka dalam toleransi dan menolak di luarnya', function () {
    $b = new BandingkanNilai;

    expect($b->cocok(['tipe' => 'int', 'nilai' => 4780850], '4780850', 1))->toBeTrue()
        ->and($b->cocok(['tipe' => 'int', 'nilai' => 4780851], '4780850', 1))->toBeTrue()
        ->and($b->cocok(['tipe' => 'int', 'nilai' => 4780852], '4780850', 1))->toBeFalse()
        ->and($b->cocok(['tipe' => 'float', 'nilai' => 12500.0], '12500.0', 0.01))->toBeTrue();
});

test('pembanding membandingkan teks, bool, dan None secara ketat', function () {
    $b = new BandingkanNilai;

    expect($b->cocok(['tipe' => 'str', 'nilai' => 'Gold'], '"Gold"', 0))->toBeTrue()
        ->and($b->cocok(['tipe' => 'str', 'nilai' => 'gold'], '"Gold"', 0))->toBeFalse()
        ->and($b->cocok(['tipe' => 'bool', 'nilai' => true], 'True', 0))->toBeTrue()
        // bool tidak boleh lolos untuk expected angka 1.
        ->and($b->cocok(['tipe' => 'bool', 'nilai' => true], '1', 0))->toBeFalse()
        ->and($b->cocok(['tipe' => 'none', 'nilai' => null], 'None', 0))->toBeTrue()
        ->and($b->cocok(['tipe' => 'none', 'nilai' => null], '0', 0))->toBeFalse();
});

test('tipe lain dan belum_dibuat selalu gagal', function () {
    $b = new BandingkanNilai;

    expect($b->cocok(['tipe' => 'lain', 'nilai' => '[1, 2]'], '1', 0))->toBeFalse()
        ->and($b->cocok(['tipe' => 'belum_dibuat', 'nilai' => null], '1', 0))->toBeFalse()
        ->and($b->teksAktual(['tipe' => 'belum_dibuat', 'nilai' => null]))->toBe('(variabel belum dibuat)');
});

// ============================================================
// PaketPenilaian â€” tidak boleh membocorkan jawaban
// ============================================================

test('paket penilaian tidak memuat expected_value atau tolerance', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga dari saldo',
        'check_target' => 'bunga',
        'expected_value' => 'NILAI_RAHASIA_123',
        'tolerance' => 0.01,
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $paket = app(PaketPenilaian::class)->untukSubmission($submission);

    expect($paket)->toBeArray();

    $json = (string) json_encode($paket);

    expect($json)->not->toContain('NILAI_RAHASIA_123')
        ->and($json)->not->toContain('expected_value')
        ->and($json)->not->toContain('tolerance')
        // Label kasus uji guru juga tidak perlu bocor ke browser.
        ->and($json)->not->toContain('Bunga dari saldo');

    expect($paket['kelompok'][0]['target'][0])->toBe([
        'id' => TestCase::query()->value('test_case_id'),
        'check_target' => 'bunga',
    ]);
});

test('paket mengelompokkan kasus uji berdasarkan setup_code', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    foreach ([
        ['Saldo 12 juta', 'saldo = 12000000', 'status'],
        ['Pinjaman 12 juta', 'saldo = 12000000', 'pinjaman'],
        ['Saldo 10 juta', 'saldo = 10000000', 'status'],
        ['Saldo 0', 'saldo = 0', 'status'],
        ['Tanpa setup', null, 'total'],
    ] as [$label, $setup, $target]) {
        TestCase::create([
            'mission_id' => $misi->mission_id,
            'label' => $label,
            'setup_code' => $setup,
            'check_target' => $target,
            'expected_value' => '1',
        ]);
    }

    $paket = app(PaketPenilaian::class)->untukSubmission(submissionMenunggu($team, $misi, $siswa));

    // 3 setup_code berbeda + 1 tanpa setup = 4 kelompok eksekusi terpisah.
    expect($paket['kelompok'])->toHaveCount(4)
        ->and($paket['kelompok'][0]['setup_code'])->toBe('saldo = 12000000')
        ->and($paket['kelompok'][0]['target'])->toHaveCount(2)
        ->and($paket['kelompok'][3]['setup_code'])->toBe('');
});

test('paket menyegarkan nonce yang sudah kedaluwarsa', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionMenunggu($team, $misi, $siswa);
    $submission->forceFill(['nonce_expires_at' => now()->subMinute()])->save();

    $lama = (string) $submission->grading_nonce;

    $paket = app(PaketPenilaian::class)->untukSubmission($submission->fresh());

    expect($paket['nonce'])->not->toBe($lama)
        ->and($submission->fresh()->nonce_expires_at->isFuture())->toBeTrue();
});

// ============================================================
// PenilaianKlien â€” server tetap yang memutuskan
// ============================================================

/**
 * Bangun laporan mentah dari browser untuk nonce tertentu.
 *
 * @param  array<int, array{id: int, tipe: string, nilai: mixed}>  $hasil
 * @return array<string, mixed>
 */
function laporanKlien(string $nonce, array $hasil, ?array $galat = null, string $keluaran = 'halo'): array
{
    return [
        'nonce' => $nonce,
        'hasil' => $hasil,
        'keluaran' => $keluaran,
        'galat' => $galat,
        'durasi_ms' => 120,
        'versi_pyodide' => '314.0.7',
    ];
}

test('laporan dengan nilai benar menghasilkan status dinilai dan poin', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'bunga',
        'expected_value' => '12500.0',
        'tolerance' => 0.01,
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $hasil = app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $kasus->test_case_id, 'tipe' => 'float', 'nilai' => 12500.0]],
    ));

    expect($hasil['status'])->toBe('ok')
        ->and($hasil['lulus'])->toBe(1)
        ->and($hasil['total'])->toBe(1);

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_DINILAI)
        ->and($segar->all_passed)->toBeTrue()
        ->and($segar->grading_source)->toBe(Submission::SUMBER_KLIEN)
        ->and($segar->grading_nonce)->toBeNull()
        ->and($segar->results()->where('is_passed', true)->count())->toBe(1)
        ->and($segar->client_report['keluaran'])->toBe('halo');

    expect(ScoreEntry::query()->where('team_id', $team->team_id)->where('source_type', 'mission_result')->exists())->toBeTrue();
});

test('nilai salah tetap gagal walau browser mengaku lulus', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'bunga',
        'expected_value' => '12500.0',
        'tolerance' => 0,
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    // Laporan memuat klaim "lulus" â€” harus DIABAIKAN server.
    $laporan = laporanKlien((string) $submission->grading_nonce, [
        ['id' => (int) $kasus->test_case_id, 'tipe' => 'float', 'nilai' => 999.0, 'is_passed' => true],
    ]);

    $hasil = app(PenilaianKlien::class)->submission($submission, $laporan);

    expect($hasil['status'])->toBe('ok')
        ->and($hasil['lulus'])->toBe(0)
        ->and($submission->fresh()->status)->toBe(Submission::STATUS_DINILAI)
        ->and($submission->fresh()->all_passed)->toBeFalse()
        ->and($submission->fresh()->results()->where('is_passed', true)->count())->toBe(0);
});

test('nonce yang salah, kedaluwarsa, atau dipakai ulang ditolak', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);
    $hasilKasus = [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 1]];

    // 1. Nonce salah.
    expect(app(PenilaianKlien::class)->submission($submission, laporanKlien(str_repeat('b', 64), $hasilKasus))['status'])
        ->toBe(PenilaianKlien::ERR_NONCE);

    // 2. Kedaluwarsa.
    $submission->forceFill(['nonce_expires_at' => now()->subMinute()])->save();

    expect(app(PenilaianKlien::class)->submission(
        $submission->fresh(),
        laporanKlien((string) $submission->grading_nonce, $hasilKasus),
    )['status'])->toBe(PenilaianKlien::ERR_KEDALUWARSA);

    // 3. Dipakai ulang setelah berhasil.
    $submission->forceFill(['nonce_expires_at' => now()->addMinutes(15)])->save();
    $nonce = (string) $submission->grading_nonce;

    expect(app(PenilaianKlien::class)->submission($submission->fresh(), laporanKlien($nonce, $hasilKasus))['status'])
        ->toBe('ok');

    expect(app(PenilaianKlien::class)->submission(
        $submission->fresh(),
        laporanKlien($nonce, $hasilKasus),
    )['status'])->toBe(PenilaianKlien::ERR_STATUS);
});

test('level sumatif menunggu verifikasi guru dan belum memberi poin', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian(sumatif: true);

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'f',
        'check_target' => 'f()',
        'expected_value' => '1',
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $hasil = app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 1]],
    ));

    expect($hasil['status'])->toBe('ok');

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_MENUNGGU_VERIFIKASI)
        ->and($segar->all_passed)->toBeFalse()
        ->and(ScoreEntry::query()->where('team_id', $team->team_id)->exists())->toBeFalse();
});

test('kasus uji yang tidak dilaporkan dianggap gagal', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $a = TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'a', 'check_target' => 'a', 'expected_value' => '1']);
    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'b', 'check_target' => 'b', 'expected_value' => '2']);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $hasil = app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $a->test_case_id, 'tipe' => 'int', 'nilai' => 1]],
    ));

    expect($hasil['lulus'])->toBe(1)
        ->and($hasil['total'])->toBe(2)
        ->and($submission->fresh()->all_passed)->toBeFalse()
        ->and($submission->fresh()->results()->where('actual_value', '(tidak dilaporkan)')->count())->toBe(1);
});

test('galat python pada laporan menghasilkan status galat', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionMenunggu($team, $misi, $siswa);

    app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $kasus->test_case_id, 'tipe' => 'belum_dibuat', 'nilai' => null]],
        ['jenis' => 'ZeroDivisionError', 'pesan' => 'division by zero', 'baris' => 2],
    ));

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_GALAT)
        ->and($segar->error_message)->toContain('ZeroDivisionError')
        ->and($segar->error_message)->toContain('baris 2');
});

// ============================================================
// Endpoint HTTP
// ============================================================

test('endpoint paket menolak kelompok lain dan tidak membocorkan jawaban', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'kasus',
        'check_target' => 'x',
        'expected_value' => 'RAHASIA_XYZ',
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    // Anggota kelompok sendiri: boleh, dan jawaban tidak ikut.
    $this->actingAs($siswa, 'siswa')
        ->getJson(route('siswa.penilaian.paket-misi', $submission->submission_id))
        ->assertOk()
        ->assertJsonPath('jenis', 'misi')
        ->assertJsonPath('id', $submission->submission_id)
        ->assertJsonMissing(['expected_value' => 'RAHASIA_XYZ'])
        ->assertDontSee('RAHASIA_XYZ');

    // Siswa kelompok lain: 404.
    $kelas2 = SchoolClass::create(['class_name' => 'Y RPL', 'school_year' => '2025/2026']);
    $lain = Student::create(['class_id' => $kelas2->class_id, 'full_name' => 'Lain', 'access_code' => 'MNOP2345']);
    $team2 = Team::create(['class_id' => $kelas2->class_id, 'team_name' => 'Beta']);
    TeamMember::create(['team_id' => $team2->team_id, 'student_id' => $lain->student_id]);

    $this->actingAs($lain, 'siswa')
        ->getJson(route('siswa.penilaian.paket-misi', $submission->submission_id))
        ->assertNotFound();
});

test('endpoint lapor menolak laporan tanpa nonce yang sah', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => '1']);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $this->actingAs($siswa, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
            'nonce' => str_repeat('f', 64),
            'hasil' => [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 1]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('status', PenilaianKlien::ERR_NONCE);

    // Nonce benar: diterima dan status berubah.
    $this->actingAs($siswa, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
            'nonce' => (string) $submission->grading_nonce,
            'hasil' => [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 1]],
            'keluaran' => 'halo',
            'durasi_ms' => 90,
            'versi_pyodide' => '314.0.7',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('lulus', 1)
        ->assertJsonPath('status_penilaian', Submission::STATUS_DINILAI);
});

test('endpoint lapor menolak jenis hasil yang tidak dikenal', function () {
    [, , , , $siswa] = konteksPenilaian();

    $this->actingAs($siswa, 'siswa')
        ->postJson(route('siswa.penilaian.lapor'), [
            'jenis' => 'misi',
            'id' => 1,
            'nonce' => str_repeat('a', 64),
            'hasil' => [['id' => 1, 'tipe' => 'objek-ajaib', 'nilai' => 1]],
        ])
        ->assertStatus(422);
});

// ============================================================
// UI siswa
// ============================================================

test('halaman hasil menampilkan penilai di perangkat saat status menunggu', function () {
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'x', 'check_target' => 'x', 'expected_value' => 'RAHASIA_UI']);

    $submission = submissionMenunggu($team, $misi, $siswa);

    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertSee('Menunggu penilaian di perangkat')
        ->assertSee('penilaiPyodide', false)
        ->assertDontSee('RAHASIA_UI');
});

test('hasil dinilai menampilkan keluaran program dan nilai aktual', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian();

    $kasus = TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'Total', 'check_target' => 'total', 'expected_value' => '10']);

    $submission = submissionMenunggu($team, $misi, $siswa, 'print("halo dunia")\ntotal = 9');

    app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 9]],
        null,
        "halo dunia\n",
    ));

    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertSee('0 dari 1 lulus')
        ->assertSee('Keluaran program')
        ->assertSee('halo dunia')
        // Nilai siswa yang salah tampil sebagai actual_value, bukan expected.
        ->assertSee('>9<', false)
        ->assertDontSee('>10<', false);
});

test('hasil sumatif menampilkan label menunggu verifikasi, bukan lulus', function () {
    [, $team, , $misi, $siswa] = konteksPenilaian(sumatif: true);

    $kasus = TestCase::create(['mission_id' => $misi->mission_id, 'label' => 'f', 'check_target' => 'f()', 'expected_value' => '1']);

    $submission = submissionMenunggu($team, $misi, $siswa);

    app(PenilaianKlien::class)->submission($submission, laporanKlien(
        (string) $submission->grading_nonce,
        [['id' => (int) $kasus->test_case_id, 'tipe' => 'int', 'nilai' => 1]],
    ));

    // Misi sumatif selalu dibuka di langkah 'kartu', jadi langkah Hasil
    // dipilih eksplisit seperti yang dilakukan siswa lewat stepper.
    Livewire::actingAs($siswa, 'siswa')
        ->test(Misi::class, ['mission' => $misi])
        ->set('langkah', 'hasil')
        ->assertSee('Menunggu verifikasi')
        ->assertSee('Poin belum diberikan')
        ->assertDontSee('Lulus Semua');
});

// ============================================================
// Kartu Bug
// ============================================================

/**
 * Siapkan percobaan bug yang sudah dirilis guru.
 *
 * @return array{0: BugAttempt, 1: Student, 2: BugTestCase}
 */
function percobaanBug(): array
{
    [, $team, $level, , $siswa] = konteksPenilaian();

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Saldo Berupa Teks',
        'symptom_text' => 'TypeError',
        'buggy_code' => 'saldo = "2500000"',
        'hint_text' => 'Perhatikan kutip.',
        'fix_explanation' => 'RAHASIA_PENJELASAN',
        'points' => 10,
    ]);

    $kasus = BugTestCase::create([
        'bug_id' => $kartu->bug_id,
        'label' => 'Bunga dari saldo',
        'check_target' => 'bunga',
        'expected_value' => '12500.0',
        'tolerance' => 0.01,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'fixed_code' => 'saldo = 2500000',
        'grading_status' => BugAttempt::STATUS_MENUNGGU,
        'attempted_at' => now()->subMinute(),
        'grading_nonce' => str_repeat('c', 64),
        'nonce_expires_at' => now()->addMinutes(15),
    ]);

    return [$attempt, $siswa, $kasus];
}

test('submit bug pada runner pyodide membuat nonce dan tidak men-dispatch job', function () {
    Queue::fake();
    config(['teater.runner' => 'pyodide']);

    [$attempt, $siswa] = percobaanBug();

    $hasil = app(BugAttemptService::class)->submit($attempt, $siswa, 'saldo = 2500000');

    expect($hasil['status'])->toBe('ok');

    $segar = $attempt->fresh();

    expect($segar->grading_status)->toBe(BugAttempt::STATUS_MENUNGGU)
        ->and($segar->grading_nonce)->not->toBeNull()
        ->and(strlen((string) $segar->grading_nonce))->toBe(64);

    Queue::assertNotPushed(GradeBugAttemptJob::class);
});

test('laporan bug dengan nilai benar menandai terpecahkan dan memberi poin', function () {
    [$attempt, $siswa, $kasus] = percobaanBug();

    $hasil = app(PenilaianKlien::class)->bugAttempt($attempt, laporanKlien(
        (string) $attempt->grading_nonce,
        [['id' => (int) $kasus->id, 'tipe' => 'float', 'nilai' => 12500.0]],
    ));

    expect($hasil['status'])->toBe('ok');

    $segar = $attempt->fresh();

    expect($segar->grading_status)->toBe(BugAttempt::STATUS_DINILAI)
        ->and($segar->is_solved)->toBeTrue()
        ->and($segar->solved_at)->not->toBeNull()
        ->and($segar->results()->where('is_passed', true)->count())->toBe(1)
        ->and(ScoreEntry::query()->where('source_type', 'bug_bonus')->sum('points'))->toBe(10);
});

test('halaman bug menampilkan penilai perangkat tanpa membocorkan penjelasan perbaikan', function () {
    config(['teater.runner' => 'pyodide']);

    [$attempt, $siswa] = percobaanBug();

    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.bug', $attempt->attempt_id))
        ->assertOk()
        ->assertSee('Menunggu penilaian di perangkat')
        ->assertSee('penilaiPyodide', false)
        ->assertDontSee('RAHASIA_PENJELASAN');
});

// ============================================================
// Pemulihan submission lama (regresi "Sedang dinilai... macet")
// ============================================================

test('submission lama tanpa nonce dipulihkan saat paket diminta', function () {
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'kasus',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    // Submission dari masa runner 'fake': tidak punya nonce sama sekali.
    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now()->subMinutes(10),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    expect($submission->grading_nonce)->toBeNull();

    $paket = app(PaketPenilaian::class)->untukSubmission($submission);

    expect($paket)->not->toBeNull()
        ->and(strlen((string) $paket['nonce']))->toBe(64)
        ->and($submission->fresh()->grading_nonce)->toBe($paket['nonce']);
});

test('halaman hasil menampilkan penilai perangkat untuk submission lama tanpa nonce', function () {
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'kasus',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now()->subMinutes(10),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    // Tanpa nonce, halaman HARUS tetap menawarkan penilaian di perangkat
    // (bukan spinner yang menunggu job queue).
    $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.misi', $misi->mission_id))
        ->assertOk()
        ->assertSee('Menunggu penilaian di perangkat')
        ->assertDontSee('penilai otomatis sedang sibuk atau belum aktif');
});

test('respons paket penilaian tidak boleh di-cache browser atau proxy', function () {
    config(['teater.runner' => 'pyodide']);

    [, $team, , $misi, $siswa] = konteksPenilaian();

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'kasus',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionMenunggu($team, $misi, $siswa);

    // Nonce yang ter-cache akan ditolak server → penilaian tampak gagal
    // tanpa sebab. Karena itu paket wajib no-store (Laravel menambahkan
    // "private" lewat middleware sesi, jadi yang diperiksa bagian pentingnya).
    $respons = $this->actingAs($siswa, 'siswa')
        ->get(route('siswa.penilaian.paket-misi', $submission->submission_id))
        ->assertOk();

    $cache = (string) $respons->headers->get('Cache-Control');

    expect($cache)->toContain('no-store')
        ->and($cache)->toContain('max-age=0')
        ->and($cache)->toContain('must-revalidate');
});
