<?php

use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\Submission;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TestCase;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Ekspor CSV: BOM UTF-8 agar Excel membaca huruf beraksen, dan netralisasi
 * sel yang dimulai karakter berbahaya (CSV injection).
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->guru = User::factory()->create(['role' => 'guru']);

    $this->kelas = SchoolClass::create([
        'class_name' => 'X RPL 1',
        'school_year' => '2026/2027',
        'user_id' => $this->guru->id,
    ]);

    $this->team = Team::create(['class_id' => $this->kelas->class_id, 'team_name' => '=Kelompok Nakal']);

    $siswa = Student::create([
        'class_id' => $this->kelas->class_id,
        'full_name' => '=SUM(A1:A9)',
        'student_no' => null,
    ]);
    TeamMember::create(['team_id' => $this->team->team_id, 'student_id' => $siswa->student_id]);

    ScoreEntry::create(['team_id' => $this->team->team_id, 'source_type' => 'plan_bonus', 'points' => 10]);
    ScoreEntry::create(['team_id' => $this->team->team_id, 'student_id' => $siswa->student_id, 'source_type' => 'first_try_bonus', 'points' => 5]);

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

/**
 * Ambil isi CSV dengan memanggil ekspor() pada instance komponen dan
 * menjalankan callback stream-nya ke buffer keluaran.
 *
 * Livewire::call() tidak mengembalikan respons stream (unduhan dijalankan
 * oleh browser), sehingga isi berkas diperiksa lewat jalur ini.
 */
function isiCsv(mixed $komponen, string $method = 'ekspor'): string
{
    $respons = $komponen->instance()->{$method}();

    ob_start();
    $respons->getCallback()();
    $isi = (string) ob_get_clean();

    return $isi;
}

test('ekspor peringkat kelompok memakai BOM UTF-8', function () {
    $komponen = Livewire::test('pages::admin.skor-peringkat', ['kelas' => $this->kelas->class_id]);

    expect(substr(isiCsv($komponen), 0, 3))->toBe("\xEF\xBB\xBF");
});

test('ekspor peringkat kelompok menetralkan sel berbahaya', function () {
    $isi = isiCsv(Livewire::test('pages::admin.skor-peringkat', ['kelas' => $this->kelas->class_id]));

    // Nama kelompok aslinya "=Kelompok Nakal" → diberi awalan kutip tunggal.
    expect($isi)->toContain("'=Kelompok Nakal")
        ->and($isi)->toContain('Peringkat')
        ->and($isi)->toContain('Bonus Rencana');
});

test('ekspor peringkat individu menetralkan nama berbahaya', function () {
    $komponen = Livewire::test('pages::admin.skor-peringkat', ['kelas' => $this->kelas->class_id])
        ->set('activeTab', 'individu');

    $isi = isiCsv($komponen);

    expect($isi)->toContain("'=SUM(A1:A9)")
        ->and($isi)->toContain('Peringkat')
        ->and($isi)->toContain('Fungsi Dipegang');
});

test('ekspor rekap nilai memakai BOM dan menetralkan nama siswa', function () {
    $isi = isiCsv(Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ]));

    // Tim belum submit → baris berstatus "Belum ada data" (netral), dan
    // kolom Nilai Akhir dibiarkan kosong (bukan 0 yang menyesatkan).
    expect(substr($isi, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($isi)->toContain("'=SUM(A1:A9)")
        ->and($isi)->toContain('Nilai Akhir')
        ->and($isi)->toContain('Belum ada data');
});

test('ekspor rekap nilai menandai sementara setelah tim submit', function () {
    $kasus = TestCase::create([
        'mission_id' => $this->misi->mission_id,
        'label' => 'Total',
        'check_target' => 'hitung_total(1, 5000)',
        'expected_value' => '5000',
        'tolerance' => 0,
    ]);

    $submission = Submission::create([
        'team_id' => $this->team->team_id,
        'mission_id' => $this->misi->mission_id,
        'submitted_by' => TeamMember::query()->where('team_id', $this->team->team_id)->value('student_id'),
        'code_text' => 'print(1)',
        'submitted_at' => Carbon::now(),
        'all_passed' => true,
        // Rekap sumatif hanya memakai submission FINAL ('dinilai'); status
        // 'menunggu_verifikasi' sengaja diabaikan sampai guru memverifikasi.
        'status' => Submission::STATUS_DINILAI,
    ]);

    SubmissionResult::create([
        'submission_id' => $submission->submission_id,
        'test_case_id' => $kasus->test_case_id,
        'is_passed' => true,
    ]);

    $isi = isiCsv(Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ]));

    expect($isi)->toContain('Sementara')
        ->and($isi)->not->toContain('Belum ada data');
});

test('ekspor memuat komponen nilai sesuai label config', function () {
    $isi = isiCsv(Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $this->misi->mission_id,
    ]));

    foreach (config('teater.komponen_labels') as $label) {
        expect($isi)->toContain($label);
    }
});

test('amanCsv membiarkan teks biasa tanpa perubahan', function () {
    $komponen = Livewire::test('pages::admin.skor-peringkat', ['kelas' => $this->kelas->class_id]);

    expect($komponen->instance()->amanCsv('Kelompok Biasa'))->toBe('Kelompok Biasa')
        ->and($komponen->instance()->amanCsv('=Rumus'))->toBe("'=Rumus")
        ->and($komponen->instance()->amanCsv('+62'))->toBe("'+62")
        ->and($komponen->instance()->amanCsv('-5'))->toBe("'-5")
        ->and($komponen->instance()->amanCsv('@user'))->toBe("'@user")
        ->and($komponen->instance()->amanCsv(''))->toBe('');
});

test('halaman menolak kelas milik guru lain', function () {
    $guruLain = User::factory()->create(['role' => 'guru']);
    $kelasLain = SchoolClass::create([
        'class_name' => 'X RPL 9',
        'school_year' => '2026/2027',
        'user_id' => $guruLain->id,
    ]);

    $this->actingAs($this->guru);

    $this->get(route('admin.skor-peringkat', ['kelas' => $kelasLain->class_id]))
        ->assertForbidden();
});
