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
use App\Services\PaketPenilaian;
use App\Services\VerifikasiHasil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Konteks sumatif: kelas milik guru, 1 kelompok, 1 misi sumatif.
 *
 * @return array{0: User, 1: SchoolClass, 2: Team, 3: Mission, 4: Student}
 */
function konteksVerifikasi(bool $sumatif = true): array
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

    return [$guru, $kelas, $team, $misi, $siswa];
}

/**
 * Submission yang sudah menunggu verifikasi guru (hasil laporan perangkat).
 */
function submissionVerifikasi(
    Team $team,
    Mission $misi,
    Student $siswa,
    int $kasusId,
    bool $lulus = true,
    string $aktual = '20000.0',
): Submission {
    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'def hitung(saldo, persen): return saldo * persen / 100',
        'submitted_at' => now(),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU_VERIFIKASI,
        'grading_source' => Submission::SUMBER_KLIEN,
        'client_report' => ['keluaran' => '20000.0', 'durasi_ms' => 120],
        'client_runtime_ms' => 120,
        'pyodide_version' => '314.0.7',
        'attempt_no' => 1,
        'graded_at' => now(),
    ]);

    $submission->results()->create([
        'test_case_id' => $kasusId,
        'is_passed' => $lulus,
        'actual_value' => $aktual,
    ]);

    return $submission->fresh();
}

// ============================================================
// VerifikasiHasil â€” keputusan & poin
// ============================================================

test('verifikasi memberi poin dan mengubah status menjadi dinilai', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'hitung(4000000, 0.5)',
        'expected_value' => '20000.0',
        'tolerance' => 0.01,
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    $hasil = app(VerifikasiHasil::class)->submission($submission, $guru);

    expect($hasil['status'])->toBe('ok');

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_DINILAI)
        ->and($segar->all_passed)->toBeTrue()
        ->and($segar->grading_source)->toBe(Submission::SUMBER_TERVERIFIKASI)
        ->and($segar->verified_by)->toBe($guru->id)
        ->and($segar->verified_at)->not->toBeNull();

    expect(ScoreEntry::query()->where('team_id', $team->team_id)->where('source_type', 'mission_result')->exists())->toBeTrue();
});

test('verifikasi bersifat idempoten dan menolak status lain', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    app(VerifikasiHasil::class)->submission($submission, $guru);

    $poinPertama = (int) ScoreEntry::query()->sum('points');

    // Panggilan kedua: status sudah 'dinilai' â†’ ditolak, poin tidak bertambah.
    expect(app(VerifikasiHasil::class)->submission($submission->fresh(), $guru)['status'])
        ->toBe(VerifikasiHasil::ERR_STATUS);

    expect((int) ScoreEntry::query()->sum('points'))->toBe($poinPertama);
});

test('minta ulang mengembalikan status ke menunggu dengan nonce baru', function () {
    [, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    expect(app(VerifikasiHasil::class)->mintaUlang($submission)['status'])->toBe('ok');

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_MENUNGGU)
        ->and($segar->grading_source)->toBeNull()
        ->and($segar->grading_nonce)->not->toBeNull()
        ->and(strlen((string) $segar->grading_nonce))->toBe(64)
        ->and($segar->nonce_expires_at->isFuture())->toBeTrue();

    // Status 'menunggu' berarti siswa bisa menilai lagi lewat paket.
    expect(app(PaketPenilaian::class)->untukSubmission($segar))->not->toBeNull();
});

test('selisih hasil guru dicatat pada mismatch_detail', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    // Perangkat melaporkan LULUS, tetapi pemeriksaan guru menyatakan GAGAL.
    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id, true, '20000.0');

    $hasil = app(VerifikasiHasil::class)->submission($submission, $guru, [
        'hasil' => [
            ['id' => (int) $kasus->test_case_id, 'lulus' => false, 'aktual' => '15000.0'],
        ],
    ]);

    expect($hasil['status'])->toBe('ok')
        ->and($hasil['selisih'])->not->toBeNull();

    $segar = $submission->fresh();

    expect($segar->has_mismatch)->toBeTrue()
        ->and($segar->mismatch_detail['jumlah'])->toBe(1)
        ->and($segar->mismatch_detail['sumber'])->toBe('perangkat-guru');
});

test('verifikasi kartu bug memberi bonus poin saat lulus', function () {
    [$guru, , $team, $level] = konteksVerifikasi();

    $kartu = BugCard::create([
        'level_id' => $level->level_id,
        'bug_title' => 'Saldo Berupa Teks',
        'symptom_text' => 'TypeError',
        'buggy_code' => 'saldo = "2500000"',
        'hint_text' => 'Kutip.',
        'fix_explanation' => 'Hapus kutip.',
        'points' => 10,
    ]);

    $kasus = BugTestCase::create([
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
        'bug_test_case_id' => $kasus->id,
        'is_passed' => true,
        'actual_value' => '12500.0',
    ]);

    expect(app(VerifikasiHasil::class)->bugAttempt($attempt, $guru)['status'])->toBe('ok');

    $segar = $attempt->fresh();

    expect($segar->grading_status)->toBe(BugAttempt::STATUS_DINILAI)
        ->and($segar->is_solved)->toBeTrue()
        ->and($segar->grading_source)->toBe(BugAttempt::SUMBER_TERVERIFIKASI)
        ->and(ScoreEntry::query()->where('source_type', 'bug_bonus')->sum('points'))->toBe(10);
});

// ============================================================
// Endpoint guru: paket & pemeriksaan nilai mentah
// ============================================================

test('endpoint paket verifikasi hanya untuk guru pemilik kelas', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'hitung(4000000, 0.5)',
        'expected_value' => 'RAHASIA_GURU_20000',
        'tolerance' => 0.01,
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    // Guru pemilik kelas: boleh, dan paket tetap tanpa expected_value.
    $this->actingAs($guru)
        ->getJson(route('admin.verifikasi-penilaian.paket', ['jenis' => 'misi', 'id' => $submission->submission_id]))
        ->assertOk()
        ->assertJsonPath('mode', 'verifikasi')
        ->assertDontSee('RAHASIA_GURU_20000')
        // Batas waktu guru dikali verify_timeout_multiplier (default 2).
        ->assertJsonPath('batas_kelompok_ms', (int) config('teater.pyodide.setup_timeout_ms') * 2);

    // Guru lain (bukan pemilik kelas): 403.
    $guruLain = User::factory()->create(['role' => 'guru', 'is_active' => true]);

    $this->actingAs($guruLain)
        ->getJson(route('admin.verifikasi-penilaian.paket', ['jenis' => 'misi', 'id' => $submission->submission_id]))
        ->assertForbidden();
});

test('endpoint periksa mengembalikan lulus dan nilai harapan untuk guru', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'hitung(4000000, 0.5)',
        'expected_value' => '20000.0',
        'tolerance' => 0.01,
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    $this->actingAs($guru)
        ->postJson(route('admin.verifikasi-penilaian.periksa', ['jenis' => 'misi', 'id' => $submission->submission_id]), [
            'hasil' => [
                ['id' => (int) $kasus->test_case_id, 'tipe' => 'float', 'nilai' => 20000.0],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('lulus', 1)
        ->assertJsonPath('total', 1)
        ->assertJsonPath('hasil.0.lulus', true)
        ->assertJsonPath('hasil.0.harapan', '20000.0');

    // Nilai meleset: server menandai gagal walau tipenya benar.
    $this->actingAs($guru)
        ->postJson(route('admin.verifikasi-penilaian.periksa', ['jenis' => 'misi', 'id' => $submission->submission_id]), [
            'hasil' => [
                ['id' => (int) $kasus->test_case_id, 'tipe' => 'float', 'nilai' => 15000.0],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('lulus', 0)
        ->assertJsonPath('hasil.0.lulus', false);
});

test('halaman verifikasi menampilkan antrean dan tidak bisa diakses siswa', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    $this->actingAs($guru)
        ->get(route('admin.verifikasi-penilaian'))
        ->assertOk()
        ->assertSee('Verifikasi Penilaian')
        ->assertSee('Alpha')
        // Ringkasan antrean & tabel (Fase 6 — Bagian D).
        ->assertSee('Menunggu Verifikasi')
        ->assertSee('Hasil Perangkat')
        ->assertSee('Verifikasi yang Menunggu');
});

test('siswa tidak bisa membuka halaman verifikasi guru', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    // Guard `siswa` memakai sesi terpisah; actingAs() mengganti guard default,
    // jadi guard default dikembalikan ke `web` (pola SiswaLoginTest).
    $this->actingAs($siswa, 'siswa');
    auth()->shouldUse('web');

    $this->get(route('admin.verifikasi-penilaian'))->assertRedirect(route('login'));

    $this->getJson(route('admin.verifikasi-penilaian.paket', [
        'jenis' => 'misi',
        'id' => $submission->submission_id,
    ]))->assertUnauthorized();
});

test('verifikasi lewat halaman memberi poin dan menghapus antrean', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    Livewire::actingAs($guru)
        ->test('pages::admin.verifikasi-penilaian')
        ->call('buka', 'misi', $submission->submission_id)
        ->assertSee('Laporan Perangkat Siswa')
        ->call('verifikasi')
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(Submission::STATUS_DINILAI)
        ->and($submission->fresh()->grading_source)->toBe(Submission::SUMBER_TERVERIFIKASI)
        ->and(ScoreEntry::query()->where('source_type', 'mission_result')->exists())->toBeTrue();
});

test('bukti pemeriksaan guru tersimpan di state dan dipakai saat verifikasi', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'hitung(4000000, 0.5)',
        'expected_value' => '20000.0',
        'tolerance' => 0.01,
    ]);

    // Perangkat melaporkan lulus; guru akan memeriksa dan hasilnya sama.
    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    Livewire::actingAs($guru)
        ->test('pages::admin.verifikasi-penilaian')
        ->call('buka', 'misi', $submission->submission_id)
        ->call('simpanPemeriksaan', [
            'hasil' => [[
                'id' => (int) $kasus->test_case_id,
                'lulus' => true,
                'aktual' => '20000.0',
                'harapan' => '20000.0',
            ]],
        ])
        // Rincian bukti (termasuk nilai harapan) dirender server-side.
        ->assertSee('Jalankan Ulang di Perangkat Guru')
        ->assertSee('harapan:')
        ->assertSee('Bukti pemeriksaan tersimpan')
        ->assertSet('pemeriksaan.hasil.0.lulus', true)
        ->call('verifikasi');

    $segar = $submission->fresh();

    expect($segar->status)->toBe(Submission::STATUS_DINILAI)
        // Hasil guru sama dengan laporan perangkat â†’ tidak ada selisih.
        ->and($segar->has_mismatch)->toBeFalse();
});

test('minta ulang lewat halaman mengembalikan data ke antrean siswa', function () {
    [$guru, , $team, $misi, $siswa] = konteksVerifikasi();

    $kasus = TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Bunga',
        'check_target' => 'x',
        'expected_value' => '20000.0',
    ]);

    $submission = submissionVerifikasi($team, $misi, $siswa, (int) $kasus->test_case_id);

    Livewire::actingAs($guru)
        ->test('pages::admin.verifikasi-penilaian')
        ->call('buka', 'misi', $submission->submission_id)
        ->call('mintaUlang')
        ->assertSet('terpilihId', null);

    expect($submission->fresh()->status)->toBe(Submission::STATUS_MENUNGGU);
});
