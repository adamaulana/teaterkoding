<?php

use App\Models\BugCard;
use App\Models\FunctionOwnership;
use App\Models\Level;
use App\Models\ManualScore;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Services\SumativeScore;
use App\Services\SumativeScoreCalculator;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Rumus nilai sumatif: lima komponen, pembobotan, dan penanda "Sementara".
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->kalkulator = new SumativeScoreCalculator;

    $this->level = Level::query()->where('is_summative', true)->firstOrFail();

    $this->misi = Mission::create([
        'level_id' => $this->level->level_id,
        'unlock_code' => 'SUM-1',
        'client_brief' => 'Brief.',
    ]);

    $this->kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027']);
    $this->team = Team::create(['class_id' => $this->kelas->class_id, 'team_name' => 'Kelompok 1']);

    $this->siswa = collect();

    foreach (['Adi', 'Bella'] as $nama) {
        $s = Student::create([
            'class_id' => $this->kelas->class_id,
            'full_name' => $nama,
            'student_no' => null,
        ]);
        TeamMember::create(['team_id' => $this->team->team_id, 'student_id' => $s->student_id]);
        $this->siswa->push($s);
    }

    $this->bobot = [
        'w_individual' => 50,
        'w_team' => 20,
        'w_bug_plan' => 10,
        'w_reflection' => 10,
        'w_observation' => 10,
    ];
});

/**
 * Buat kasus uji yang menargetkan sebuah fungsi.
 *
 * @return array<int, TestCase>
 */
function kasusUjiFungsi(Mission $misi, string $namaFungsi, int $jumlah): array
{
    $hasil = [];

    for ($i = 1; $i <= $jumlah; $i++) {
        $hasil[] = TestCase::create([
            'mission_id' => $misi->mission_id,
            'label' => "{$namaFungsi} kasus {$i}",
            'check_target' => "{$namaFungsi}({$i})",
            'expected_value' => '1',
            'tolerance' => 0,
        ]);
    }

    return $hasil;
}

/**
 * Buat submission dengan sejumlah kasus lulus (yang pertama dianggap lulus).
 *
 * @param  array<int, TestCase>  $kasus
 */
function submissionLulus(Team $team, Mission $misi, array $kasus, int $lulus, ?Carbon $waktu = null): Submission
{
    $submission = Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => TeamMember::query()->where('team_id', $team->team_id)->value('student_id'),
        'code_text' => 'print(1)',
        'submitted_at' => $waktu ?? Carbon::now(),
        'all_passed' => $lulus >= count($kasus),
    ]);

    foreach ($kasus as $urutan => $satu) {
        SubmissionResult::create([
            'submission_id' => $submission->submission_id,
            'test_case_id' => $satu->test_case_id,
            'is_passed' => $urutan < $lulus,
        ]);
    }

    return $submission;
}

test('tanpa submission semua komponen otomatis bernilai null', function () {
    kasusUjiFungsi($this->misi, 'hitung', 2);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    $satu = $hasil->get($this->siswa[0]->student_id);

    // Tim belum pernah submit → SELURUH komponen otomatis null, termasuk
    // bug & rencana, supaya sel tampil "—" netral (bukan 0 merah).
    expect($satu)->toBeInstanceOf(SumativeScore::class)
        ->and($satu->komponenIndividu)->toBeNull()
        ->and($satu->komponenKelompok)->toBeNull()
        ->and($satu->komponenBugPlan)->toBeNull()
        ->and($satu->nilaiAkhir)->toBe(0.0)
        ->and($satu->adaData())->toBeFalse()
        ->and($satu->statusLabel())->toBe('Belum ada data')
        ->and($satu->sementara)->toBeTrue();
});

test('komponen kelompok adalah persen kasus uji yang lulus', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 4);
    submissionLulus($this->team, $this->misi, $kasus, 3);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    expect($hasil->get($this->siswa[0]->student_id)?->komponenKelompok)->toBe(75.0);
});

test('komponen individu memakai hanya kasus uji fungsi milik siswa', function () {
    $kasus = array_merge(
        kasusUjiFungsi($this->misi, 'hitung', 2),
        kasusUjiFungsi($this->misi, 'status', 2),
    );

    // Hanya dua kasus pertama (milik fungsi "hitung") yang lulus.
    submissionLulus($this->team, $this->misi, $kasus, 2);

    FunctionOwnership::create([
        'mission_id' => $this->misi->mission_id,
        'team_id' => $this->team->team_id,
        'student_id' => $this->siswa[0]->student_id,
        'function_name' => 'hitung',
    ]);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $adi = $hasil->get($this->siswa[0]->student_id);
    $bella = $hasil->get($this->siswa[1]->student_id);

    // Adi memegang "hitung" → 2 dari 2 kasus lulus = 100.
    expect($adi?->komponenIndividu)->toBe(100.0)
        ->and($adi?->punyaFungsi)->toBeTrue()
        ->and($adi?->rincianIndividu)->toContain('hitung');

    // Bella tidak memegang fungsi apa pun → null, bukan 0.
    expect($bella?->komponenIndividu)->toBeNull()
        ->and($bella?->punyaFungsi)->toBeFalse();
});

test('komponen bug dan rencana memakai poin dibagi maksimal level', function () {
    BugCard::create([
        'level_id' => $this->level->level_id,
        'bug_title' => 'Bug A',
        'buggy_code' => 'x',
        'fix_explanation' => 'y',
        'points' => 10,
    ]);
    BugCard::create([
        'level_id' => $this->level->level_id,
        'bug_title' => 'Bug B',
        'buggy_code' => 'x',
        'fix_explanation' => 'y',
        'points' => 10,
    ]);

    // Maksimal = 20 (dua kartu) + 10 (bonus rencana dari config) = 30.
    ScoreEntry::create(['team_id' => $this->team->team_id, 'source_type' => 'bug_bonus', 'points' => 10]);
    ScoreEntry::create(['team_id' => $this->team->team_id, 'source_type' => 'plan_bonus', 'points' => 10]);
    ScoreEntry::create(['team_id' => $this->team->team_id, 'source_type' => 'hint_penalty', 'points' => -5]);

    $kasus = kasusUjiFungsi($this->misi, 'hitung', 1);
    submissionLulus($this->team, $this->misi, $kasus, 1);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    // (10 + 10 - 5) / 30 = 50%.
    expect($hasil->get($this->siswa[0]->student_id)?->komponenBugPlan)->toBe(50.0);
});

test('komponen bug dan rencana bernilai nol bila tim sudah submit tanpa poin', function () {
    BugCard::create([
        'level_id' => $this->level->level_id,
        'bug_title' => 'Bug A',
        'buggy_code' => 'x',
        'fix_explanation' => 'y',
        'points' => 10,
    ]);

    ScoreEntry::create(['team_id' => $this->team->team_id, 'source_type' => 'hint_penalty', 'points' => -50]);

    $kasus = kasusUjiFungsi($this->misi, 'hitung', 1);
    submissionLulus($this->team, $this->misi, $kasus, 1);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    // Sudah ada submission → komponennya NYATA nol (penalti melebihi bonus),
    // jadi 0.0 (merah) memang benar, bukan "belum ada data".
    expect($hasil->get($this->siswa[0]->student_id)?->komponenBugPlan)->toBe(0.0)
        ->and($hasil->get($this->siswa[0]->student_id)?->adaData())->toBeTrue();
});

test('nilai akhir mengikuti bobot kelas', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 2);
    submissionLulus($this->team, $this->misi, $kasus, 2);

    FunctionOwnership::create([
        'mission_id' => $this->misi->mission_id,
        'team_id' => $this->team->team_id,
        'student_id' => $this->siswa[0]->student_id,
        'function_name' => 'hitung',
    ]);

    // Tanpa kartu bug sama sekali → komponen bug_plan null (0 dalam hitungan).
    ManualScore::create([
        'student_id' => $this->siswa[0]->student_id,
        'mission_id' => $this->misi->mission_id,
        'reflection_score' => 90,
        'observation_score' => 80,
    ]);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $adi = $hasil->get($this->siswa[0]->student_id);

    // individu 100x50 + kelompok 100x20 + bug 0x10 + refleksi 90x10 + observasi 80x10
    // = 5000 + 2000 + 0 + 900 + 800 = 8700 / 100 = 87.0
    expect($adi?->nilaiAkhir)->toBe(87.0)
        ->and($adi?->sementara)->toBeFalse();
});

test('bobot nol membuat komponen tidak menyumbang nilai akhir', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 2);
    submissionLulus($this->team, $this->misi, $kasus, 2);

    $bobot = [
        'w_individual' => 0,
        'w_team' => 100,
        'w_bug_plan' => 0,
        'w_reflection' => 0,
        'w_observation' => 0,
    ];

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $bobot);

    expect($hasil->get($this->siswa[0]->student_id)?->nilaiAkhir)->toBe(100.0);
});

test('komponen manual kosong ditandai sementara', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 1);
    submissionLulus($this->team, $this->misi, $kasus, 1);

    ManualScore::create([
        'student_id' => $this->siswa[0]->student_id,
        'mission_id' => $this->misi->mission_id,
        'reflection_score' => 80,
        // observation_score sengaja kosong.
    ]);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $adi = $hasil->get($this->siswa[0]->student_id);

    expect($adi?->sementara)->toBeTrue()
        ->and($adi?->komponenRefleksi)->toBe(80.0)
        ->and($adi?->komponenObservasi)->toBeNull();
});

test('submission acuan mengikuti config reference_submission', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 4);

    submissionLulus($this->team, $this->misi, $kasus, 4, Carbon::now()->subMinutes(10));
    submissionLulus($this->team, $this->misi, $kasus, 1, Carbon::now());

    // Default: submission terakhir (1 dari 4 lulus = 25%).
    $terakhir = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    expect($terakhir->get($this->siswa[0]->student_id)?->komponenKelompok)->toBe(25.0);

    // Diubah ke submission pertama (4 dari 4 lulus = 100%).
    config(['teater.reference_submission' => 'first']);

    $pertama = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    expect($pertama->get($this->siswa[0]->student_id)?->komponenKelompok)->toBe(100.0);
});

test('komponen dipetakan lengkap dengan lima kunci', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 1);
    submissionLulus($this->team, $this->misi, $kasus, 1);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $komponen = $hasil->get($this->siswa[0]->student_id)?->komponen();

    expect($komponen)->toHaveKeys(['individu', 'kelompok', 'bug_plan', 'refleksi', 'observasi']);
});

test('tampil mengembalikan tanda pisah untuk komponen kosong', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 1);
    submissionLulus($this->team, $this->misi, $kasus, 1);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $satu = $hasil->get($this->siswa[0]->student_id);

    expect($satu?->tampil('refleksi'))->toBe('—')
        ->and($satu?->tampil('kelompok'))->toBe('100');
});

test('warna nilai memakai jenjang 75 dan 50', function () {
    expect(SumativeScore::warna(null))->toBe('text-slate-400')
        ->and(SumativeScore::warna(80))->toBe('text-primary-700')
        ->and(SumativeScore::warna(60))->toBe('text-amber-600')
        ->and(SumativeScore::warna(30))->toBe('text-rose-600');
});

// ---------- Langkah 0 Fase 4: sel kosong harus netral, bukan 0 merah ----------

test('sel tanpa data memakai warna netral, bukan merah', function () {
    // Regresi: sebelumnya null dan 0 sama-sama merah sehingga sel kosong
    // terlihat seperti nilai nol yang buruk.
    expect(SumativeScore::warna(null))->toBe('text-slate-400')
        ->and(SumativeScore::warna(0))->toBe('text-rose-600')
        ->and(SumativeScore::warna(null))->not->toBe(SumativeScore::warna(0));
});

test('nilai akhir belum bermakna bila tidak ada komponen terisi', function () {
    kasusUjiFungsi($this->misi, 'hitung', 2);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);
    $satu = $hasil->get($this->siswa[0]->student_id);

    expect($satu?->adaData())->toBeFalse()
        ->and($satu?->statusLabel())->toBe('Belum ada data');
});

test('statusLabel mengikuti kelengkapan komponen', function () {
    $kasus = kasusUjiFungsi($this->misi, 'hitung', 2);
    submissionLulus($this->team, $this->misi, $kasus, 2);

    // Belum ada nilai manual → Sementara.
    $sementara = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot)
        ->get($this->siswa[0]->student_id);

    expect($sementara?->statusLabel())->toBe('Sementara');

    // Lengkapi komponen manual → Final.
    ManualScore::create([
        'student_id' => $this->siswa[0]->student_id,
        'mission_id' => $this->misi->mission_id,
        'reflection_score' => 80,
        'observation_score' => 85,
    ]);

    $final = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot)
        ->get($this->siswa[0]->student_id);

    expect($final?->statusLabel())->toBe('Final')
        ->and($final?->adaData())->toBeTrue();
});

test('kelompok yang sudah submit mengisi komponen individu dan kelompok anggotanya', function () {
    // Regresi Fase 4: kolom "Fungsi Individu" & "Kelompok" harus terisi untuk
    // anggota kelompok yang sudah punya submission pada misi sumatif.
    $kasus = array_merge(
        kasusUjiFungsi($this->misi, 'hitung', 2),
        kasusUjiFungsi($this->misi, 'status', 2),
    );

    submissionLulus($this->team, $this->misi, $kasus, 3);

    FunctionOwnership::create([
        'mission_id' => $this->misi->mission_id,
        'team_id' => $this->team->team_id,
        'student_id' => $this->siswa[0]->student_id,
        'function_name' => 'hitung',
    ]);

    $hasil = $this->kalkulator->hitungKelas($this->siswa, $this->misi, $this->bobot);

    $pemilik = $hasil->get($this->siswa[0]->student_id);
    $anggota = $hasil->get($this->siswa[1]->student_id);

    // Pemilik fungsi: individu 2/2 lulus = 100, kelompok 3/4 = 75.
    expect($pemilik?->komponenIndividu)->toBe(100.0)
        ->and($pemilik?->komponenKelompok)->toBe(75.0)
        ->and($pemilik?->adaData())->toBeTrue();

    // Anggota tanpa fungsi tetap dapat komponen kelompok.
    expect($anggota?->komponenIndividu)->toBeNull()
        ->and($anggota?->komponenKelompok)->toBe(75.0)
        ->and($anggota?->adaData())->toBeTrue();
});
