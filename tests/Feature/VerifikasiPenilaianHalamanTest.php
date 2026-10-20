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
use App\Services\AntreanVerifikasi;
use App\Services\SumativeScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Konteks kelas milik guru + 1 kelompok + level sumatif.
 *
 * @return array{0: User, 1: SchoolClass, 2: Team, 3: Mission, 4: Student, 5: Level}
 */
function konteksHalamanVerifikasi(bool $sumatif = true): array
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
        'unlock_code' => 'SUM-1',
        'client_brief' => 'brief',
        'starter_code' => 'def hitung(): pass',
    ]);

    ClassLevelAccess::create(['class_id' => $kelas->class_id, 'level_id' => $level->level_id, 'is_open' => true]);

    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'access_code' => 'ABCD2345']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    return [$guru, $kelas, $team, $misi, $siswa, $level];
}

/**
 * Submission berstatus menunggu_verifikasi + hasil per kasus.
 *
 * @param  array<int, bool>  $lulusPerKasus
 */
function submissionAntrean(
    Team $team,
    Mission $misi,
    Student $siswa,
    array $lulusPerKasus,
    bool $selisih = false,
): Submission {
    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'def hitung(): return 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU_VERIFIKASI,
        'grading_source' => Submission::SUMBER_KLIEN,
        'attempt_no' => 1,
        'graded_at' => now(),
        'has_mismatch' => $selisih,
        'mismatch_detail' => $selisih ? ['jumlah' => 1, 'sumber' => 'perangkat-guru'] : null,
    ]);

    foreach ($lulusPerKasus as $testCaseId => $lulus) {
        $submission->results()->create([
            'test_case_id' => $testCaseId,
            'is_passed' => $lulus,
            'actual_value' => $lulus ? '1' : '0',
        ]);
    }

    return $submission->fresh();
}

// ============================================================
// AntreanVerifikasi (service bersama)
// ============================================================

test('service antrean menghitung tiga angka sesuai batas kelas', function () {
    [$guru, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    // 2 menunggu verifikasi (1 dengan selisih).
    submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);
    submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => false], selisih: true);

    // 1 menunggu penilaian perangkat.
    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'x = 1',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 3,
        'grading_nonce' => str_repeat('a', 64),
        'nonce_expires_at' => now()->addMinutes(15),
    ]);

    $angka = app(AntreanVerifikasi::class)->untuk($guru);

    expect($angka['menunggu_verifikasi'])->toBe(2)
        ->and($angka['menunggu_perangkat'])->toBe(1)
        ->and($angka['selisih'])->toBe(1);

    // Guru lain tidak melihat kelas ini sama sekali.
    $guruLain = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    expect(app(AntreanVerifikasi::class)->untuk($guruLain))
        ->toBe(['menunggu_verifikasi' => 0, 'menunggu_perangkat' => 0, 'selisih' => 0]);
});

test('halaman menampilkan ringkasan, filter misi, dan tabel antrean', function () {
    [$guru, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    Livewire::actingAs($guru)
        ->test('pages::admin.verifikasi-penilaian')
        ->assertSee('Verifikasi Penilaian')
        ->assertSee('Menunggu Verifikasi')
        ->assertSee('Menunggu Penilaian Perangkat')
        ->assertSee('Selisih Terdeteksi')
        ->assertSee('Alpha')
        ->assertSee('Belum')
        ->assertSee($misi->unlock_code);
});

test('filter misi menyaring antrean dan tombol massal memakai seluruh antrean', function () {
    [$guru, , $team, $misi, $siswa, $level] = konteksHalamanVerifikasi();

    $misiLain = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'SUM-2',
        'client_brief' => 'brief',
        'starter_code' => 'def lain(): pass',
    ]);

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'a',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $kasusLain = TestCase::create([
        'mission_id' => $misiLain->mission_id,
        'label' => 'b',
        'check_target' => 'y',
        'expected_value' => '2',
    ]);

    submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);
    submissionAntrean($team, $misiLain, $siswa, [(int) $kasusLain->test_case_id => true]);

    $komponen = Livewire::actingAs($guru)->test('pages::admin.verifikasi-penilaian');

    // Tanpa filter: 2 item (termasuk seluruh antrean untuk tombol massal).
    expect($komponen->instance()->antreanMassal)->toHaveCount(2);

    $komponen->set('misiId', $misi->mission_id);

    expect($komponen->instance()->antreanMassal)->toHaveCount(1)
        ->and($komponen->instance()->rows->total())->toBe(1);
});

test('verifikasi per baris memberi poin dan menghilangkan baris dari antrean', function () {
    [$guru, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    Livewire::actingAs($guru)
        ->test('pages::admin.verifikasi-penilaian')
        ->call('verifikasiSatu', 'misi', $submission->submission_id)
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(Submission::STATUS_DINILAI)
        ->and($submission->fresh()->grading_source)->toBe(Submission::SUMBER_TERVERIFIKASI)
        ->and(ScoreEntry::query()->where('source_type', 'mission_result')->exists())->toBeTrue();

    // Antrean kosong setelah verifikasi.
    app(AntreanVerifikasi::class)->lupakan();

    expect(app(AntreanVerifikasi::class)->untuk($guru)['menunggu_verifikasi'])->toBe(0);
});

test('verifikasi massal memproses submission dan kartu bug sekaligus', function () {
    [$guru, , $team, $misi, $siswa, $level] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Saldo Berupa Teks',
        'symptom_text' => 'TypeError',
        'buggy_code' => 'saldo = "2500000"',
        'hint_text' => 'Kutip.',
        'fix_explanation' => 'Hapus kutip.',
        'points' => 10,
    ]);

    $kasusBug = BugTestCase::create([
        'bug_id' => $kartu->bug_id,
        'label' => 'Bunga',
        'check_target' => 'bunga',
        'expected_value' => '12500.0',
        'tolerance' => 0.01,
    ]);

    $attempt = BugAttempt::create([
        'bug_id' => $kartu->bug_id,
        'team_id' => $team->team_id,
        'released_at' => now(),
        'fixed_code' => 'saldo = 2500000',
        'grading_status' => BugAttempt::STATUS_MENUNGGU_VERIFIKASI,
        'grading_source' => BugAttempt::SUMBER_KLIEN,
        'attempted_at' => now(),
    ]);

    $attempt->results()->create([
        'bug_test_case_id' => $kasusBug->id,
        'is_passed' => true,
        'actual_value' => '12500.0',
    ]);

    $komponen = Livewire::actingAs($guru)->test('pages::admin.verifikasi-penilaian');

    expect($komponen->instance()->antreanMassal)->toHaveCount(2);

    $komponen->call('verifikasiSatu', 'misi', $submission->submission_id);
    $komponen->call('verifikasiSatu', 'bug', $attempt->attempt_id);

    expect($submission->fresh()->status)->toBe(Submission::STATUS_DINILAI)
        ->and($attempt->fresh()->grading_status)->toBe(BugAttempt::STATUS_DINILAI)
        ->and($attempt->fresh()->is_solved)->toBeTrue()
        ->and(ScoreEntry::query()->where('source_type', 'bug_bonus')->sum('points'))->toBe(10);
});

test('verifikasi item yang sudah diverifikasi dilaporkan dilewati, bukan galat', function () {
    [$guru, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    $komponen = Livewire::actingAs($guru)->test('pages::admin.verifikasi-penilaian');

    $komponen->call('verifikasiSatu', 'misi', $submission->submission_id);
    $komponen->call('verifikasiSatu', 'misi', $submission->submission_id);

    // Poin tidak bertambah dua kali (idempoten).
    expect(ScoreEntry::query()->where('source_type', 'mission_result')->count())->toBe(1)
        ->and($submission->fresh()->status)->toBe(Submission::STATUS_DINILAI);
});

test('guru lain tidak boleh memverifikasi item kelas orang lain', function () {
    [, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    $submission = submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    $guruLain = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    $this->actingAs($guruLain)
        ->getJson(route('admin.verifikasi-penilaian.paket', [
            'jenis' => 'misi',
            'id' => $submission->submission_id,
        ]))
        ->assertForbidden();
});

// ============================================================
// Rekap sumatif mengabaikan hasil yang belum diverifikasi
// ============================================================

test('rekap sumatif tidak menghitung submission menunggu verifikasi', function () {
    [, , $team, $misi, $siswa] = konteksHalamanVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'x',
        'check_target' => 'x',
        'expected_value' => '1',
    ]);

    submissionAntrean($team, $misi, $siswa, [(int) $kasus->test_case_id => true]);

    $bobot = ['w_individual' => 50, 'w_team' => 20, 'w_bug_plan' => 10, 'w_reflection' => 10, 'w_observation' => 10];
    $kalkulator = new SumativeScoreCalculator;

    // Belum diverifikasi → komponen kelompok belum punya data.
    $hasil = $kalkulator->hitungKelas(collect([$siswa]), $misi, $bobot);

    expect($hasil->get($siswa->student_id)->komponenKelompok)->toBeNull();

    // Setelah diverifikasi (status dinilai) baru terhitung.
    Submission::query()->where('status', Submission::STATUS_MENUNGGU_VERIFIKASI)
        ->update(['status' => Submission::STATUS_DINILAI, 'all_passed' => true]);

    $hasil2 = $kalkulator->hitungKelas(collect([$siswa]), $misi, $bobot);

    expect($hasil2->get($siswa->student_id)->komponenKelompok)->toBe(100.0);
});
