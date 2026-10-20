<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\ClassLevelAccess;
use App\Models\CodeDraft;
use App\Models\ConceptCard;
use App\Models\Level;
use App\Models\Mission;
use App\Models\Reflection;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubmissionResult;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MateriKoperasiSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    User::factory()->create(['role' => 'guru', 'is_active' => true]);
});

/**
 * Potret jumlah baris materi (untuk memastikan tidak berlipat / tidak hilang).
 *
 * @return array<string, int>
 */
function potretMateri(): array
{
    return [
        'levels' => Level::count(),
        'missions' => Mission::count(),
        'test_cases' => TestCase::count(),
        'concept_cards' => ConceptCard::count(),
        'bug_cards' => BugCard::count(),
        'bug_test_cases' => BugTestCase::count(),
    ];
}

/**
 * @param  array<string, int>  $a
 * @param  array<string, int>  $b
 */
function expectPotretSama(array $a, array $b): void
{
    foreach ($a as $kunci => $nilai) {
        expect($b[$kunci])->toBe($nilai, "Jumlah {$kunci} berubah.");
    }
}

test('MateriKoperasiSeeder lalu DemoDataSeeder menghasilkan materi utuh', function () {
    $this->seed(MateriKoperasiSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(BugCard::count())->toBe(8)
        ->and(BugCard::query()->whereNull('symptom_text')->count())->toBe(0)
        ->and(TestCase::count())->toBe(39) // 5 + 16 + 5 + 13
        ->and(Mission::query()->whereNull('starter_code')->count())->toBe(0);

    // Semua kartu bug punya kasus uji.
    foreach (BugCard::query()->get() as $kartu) {
        expect(BugTestCase::query()->where('bug_id', $kartu->bug_id)->count())->toBeGreaterThan(0);
    }

    // Kartu yang dirilis demo punya kasus uji.
    foreach (BugAttempt::query()->whereNotNull('released_at')->get() as $attempt) {
        expect(BugTestCase::query()->where('bug_id', $attempt->bug_id)->count())->toBeGreaterThan(0);
    }
});

test('urutan terbalik (DemoDataSeeder dulu) menghasilkan materi yang sama', function () {
    $this->seed(DemoDataSeeder::class);
    $setelahDemo = potretMateri();

    $this->seed(MateriKoperasiSeeder::class);
    $setelahMateri = potretMateri();

    expectPotretSama($setelahDemo, $setelahMateri);
    expect(BugCard::count())->toBe(8);
});

test('menjalankan tiap seeder dua kali tidak mengubah jumlah baris', function () {
    $this->seed(MateriKoperasiSeeder::class);
    $this->seed(DemoDataSeeder::class);

    $awal = potretMateri();

    $this->seed(MateriKoperasiSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expectPotretSama($awal, potretMateri());

    // Data kelas demo juga tidak berlipat.
    expect(Student::count())->toBe(24)
        ->and(Team::count())->toBe(6);
});

test('teater:hapus-demo menghapus data kelas demo tetapi materi tetap utuh', function () {
    $this->seed(MateriKoperasiSeeder::class);
    $this->seed(DemoDataSeeder::class);

    $materiSebelum = potretMateri();
    $jumlahStarter = Mission::query()->whereNotNull('starter_code')->count();

    $this->artisan('teater:hapus-demo', ['--force' => true])->assertSuccessful();

    // Data kelas demo hilang.
    expect(SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->exists())->toBeFalse()
        ->and(Student::count())->toBe(0)
        ->and(Team::count())->toBe(0)
        ->and(BugAttempt::count())->toBe(0)
        ->and(CodeDraft::count())->toBe(0)
        ->and(Reflection::count())->toBe(0)
        ->and(ClassLevelAccess::count())->toBe(0);

    // Materi TETAP utuh.
    expectPotretSama($materiSebelum, potretMateri());
    expect(Mission::query()->whereNotNull('starter_code')->count())->toBe($jumlahStarter);
});

test('kartu bug yang dirilis demo punya kasus uji dan tiga keadaan berbeda', function () {
    $this->seed(DemoDataSeeder::class);

    $attempts = BugAttempt::query()->whereNotNull('released_at')->get();

    expect($attempts)->not->toBeEmpty();

    // Setiap kartu yang dirilis punya kasus uji.
    foreach ($attempts as $attempt) {
        expect(BugTestCase::query()->where('bug_id', $attempt->bug_id)->count())
            ->toBeGreaterThan(0, "Kartu #{$attempt->bug_id} tidak punya kasus uji.");
    }

    // Tiga keadaan: belum dikerjakan, sudah pakai petunjuk, sudah solved.
    expect($attempts->where('is_solved', false)->where('hints_used', 0)->count())->toBeGreaterThan(0)
        ->and($attempts->where('is_solved', false)->where('hints_used', '>', 0)->count())->toBeGreaterThan(0)
        ->and($attempts->where('is_solved', true)->count())->toBeGreaterThan(0);

    // Yang solved harus punya solved_at dan grading_status 'dinilai'.
    foreach ($attempts->where('is_solved', true) as $solved) {
        expect($solved->solved_at)->not->toBeNull()
            ->and($solved->grading_status)->toBe(BugAttempt::STATUS_DINILAI);
    }
});

test('submission_results demo memakai test_cases nyata dari database', function () {
    $this->seed(DemoDataSeeder::class);

    $hasil = SubmissionResult::query()->with('testCase')->get();

    expect($hasil)->not->toBeEmpty();

    foreach ($hasil as $satu) {
        // test_case_id harus benar-benar ada di tabel test_cases.
        expect($satu->testCase)->not->toBeNull()
            ->and($satu->testCase->mission_id)->not->toBeNull();
    }

    // actual_value bukan "None" mentah dan tidak kosong untuk yang gagal.
    $gagal = $hasil->where('is_passed', false);

    expect($gagal)->not->toBeEmpty();

    foreach ($gagal as $satu) {
        expect((string) $satu->actual_value)->not->toBe('')
            ->and((string) $satu->actual_value)->not->toBe('None');
    }
});
test('teater:hapus-demo tidak menyentuh kelas nyata', function () {
    $this->seed(MateriKoperasiSeeder::class);

    $guru = User::query()->where('role', 'guru')->value('id');
    $nyata = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2025/2026', 'user_id' => $guru]);
    $siswa = Student::create(['class_id' => $nyata->class_id, 'full_name' => 'Siswa Nyata']);

    $this->artisan('teater:hapus-demo', ['--force' => true])->assertSuccessful();

    expect(SchoolClass::query()->where('class_id', $nyata->class_id)->exists())->toBeTrue()
        ->and(Student::query()->where('student_id', $siswa->student_id)->exists())->toBeTrue();
});
