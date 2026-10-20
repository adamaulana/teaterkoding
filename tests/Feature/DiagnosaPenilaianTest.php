<?php

use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Services\DiagnosaPenilaian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Diagnosa penilaian: memastikan setiap kombinasi konfigurasi menghasilkan
 * pesan yang BISA DITINDAKLANJUTI, bukan spinner tanpa penjelasan.
 *
 * Latar belakang nyata: TEATER_RUNNER=fake + queue worker mati membuat
 * submission berhenti di "Sedang dinilai..." selamanya tanpa galat apa pun.
 */
uses(RefreshDatabase::class);

function diagnosa(): DiagnosaPenilaian
{
    return app(DiagnosaPenilaian::class);
}

/** Sisipkan job tiruan ke tabel jobs dengan umur tertentu (detik). */
function sisipkanJob(int $umurDetik = 0, int $attempts = 0): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{"displayName":"App\\\\Jobs\\\\GradeSubmissionJob"}',
        'attempts' => $attempts,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->subSeconds($umurDetik)->getTimestamp(),
    ]);
}

test('runner dibaca dari config dan pyodide dikenali', function () {
    config(['teater.runner' => 'pyodide']);

    expect(diagnosa()->runner())->toBe('pyodide')
        ->and(diagnosa()->memakaiPyodide())->toBeTrue();
});

test('runner kosong dianggap null (tidak menilai)', function () {
    config(['teater.runner' => null]);

    expect(diagnosa()->runner())->toBe(DiagnosaPenilaian::RUNNER_NULL);
});

test('runner null dilaporkan sebagai bahaya dengan saran konkret', function () {
    config(['teater.runner' => null, 'queue.default' => 'sync']);

    $masalah = collect(diagnosa()->masalah())->firstWhere('kode', 'runner_null');

    expect($masalah)->not->toBeNull()
        ->and($masalah['tingkat'])->toBe(DiagnosaPenilaian::TINGKAT_BAHAYA)
        ->and($masalah['saran'])->toContain('TEATER_RUNNER=pyodide');
});

test('aset pyodide yang hilang dilaporkan sebagai bahaya', function () {
    config([
        'teater.runner' => 'pyodide',
        'teater.pyodide.asset_path' => '/vendor/pyodide-tidak-ada',
    ]);

    $masalah = collect(diagnosa()->masalah())->firstWhere('kode', 'aset_pyodide');

    expect($masalah)->not->toBeNull()
        ->and($masalah['tingkat'])->toBe(DiagnosaPenilaian::TINGKAT_BAHAYA)
        ->and($masalah['saran'])->toContain('npm run vendor:pyodide');
});

test('runner fake dengan job basi dilaporkan sebagai worker mati', function () {
    config([
        'teater.runner' => 'fake',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 90,
    ]);

    sisipkanJob(umurDetik: 300, attempts: 0);

    expect(diagnosa()->workerDidugaMati())->toBeTrue();

    $masalah = collect(diagnosa()->masalah())->firstWhere('kode', 'worker_mati');

    expect($masalah)->not->toBeNull()
        ->and($masalah['tingkat'])->toBe(DiagnosaPenilaian::TINGKAT_BAHAYA)
        ->and($masalah['saran'])->toContain('queue:work');
});

test('runner fake dengan job baru hanya memberi peringatan', function () {
    config([
        'teater.runner' => 'fake',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 90,
    ]);

    sisipkanJob(umurDetik: 5, attempts: 0);

    expect(diagnosa()->workerDidugaMati())->toBeFalse()
        ->and(collect(diagnosa()->masalah())->firstWhere('kode', 'job_antre'))->not->toBeNull();
});

test('runner pyodide TIDAK pernah menuduh worker mati', function () {
    config(['teater.runner' => 'pyodide', 'queue.default' => 'database']);

    sisipkanJob(umurDetik: 600, attempts: 0);

    expect(collect(diagnosa()->masalah())->firstWhere('kode', 'worker_mati'))->toBeNull();
});

test('submission menunggu tanpa nonce terdeteksi saat runner pyodide', function () {
    config(['teater.runner' => 'pyodide']);

    $kelas = SchoolClass::create(['class_name' => 'X RPL', 'school_year' => '2025/2026']);
    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Alpha']);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Siswa', 'access_code' => 'ABCD2345']);
    $level = Level::create(['level_number' => 1, 'level_name' => 'Level 1', 'is_summative' => false]);
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1', 'client_brief' => 'b', 'starter_code' => 'print(1)']);

    Submission::create([
        'team_id' => $team->team_id,
        'mission_id' => $misi->mission_id,
        'submitted_by' => $siswa->student_id,
        'code_text' => 'print(1)',
        'submitted_at' => now()->subMinutes(10),
        'all_passed' => false,
        'status' => Submission::STATUS_MENUNGGU,
        'attempt_no' => 1,
    ]);

    expect(diagnosa()->submissionTanpaNonce())->toBe(1)
        ->and(diagnosa()->submissionMenggantung())->toBe(1);

    $kode = collect(diagnosa()->masalah())->pluck('kode');

    expect($kode)->toContain('tanpa_nonce')->toContain('menggantung');
});

test('pesanUntukPenantian mengutamakan masalah berTingkat bahaya', function () {
    config(['teater.runner' => null, 'queue.default' => 'sync']);

    expect(diagnosa()->pesanUntukPenantian())->toContain('Penilaian tidak aktif');
});

test('perintah diagnosa berhasil saat konfigurasi sehat', function () {
    config(['teater.runner' => 'pyodide', 'queue.default' => 'sync']);

    $this->artisan('teater:diagnosa-penilaian')
        ->assertSuccessful();
});

// ============================================================
// Dev server Vite (public/hot) — penyebab "cannot be accessed from origin"
// ============================================================

/** Tulis public/hot sementara lalu kembalikan seperti semula. */
function denganHot(string $isi, Closure $uji): void
{
    $berkas = public_path('hot');
    $adaSebelumnya = is_file($berkas);
    $isiSebelumnya = $adaSebelumnya ? (string) file_get_contents($berkas) : null;

    file_put_contents($berkas, $isi);

    try {
        $uji();
    } finally {
        if ($adaSebelumnya) {
            file_put_contents($berkas, (string) $isiSebelumnya);
        } else {
            @unlink($berkas);
        }
    }
}

test('dev server lintas-origin dilaporkan sebagai bahaya', function () {
    config([
        'teater.runner' => 'pyodide',
        'app.url' => 'https://teater-koding.test',
    ]);

    denganHot('https://teater-koding.test:5173', function () {
        $dev = diagnosa()->devServer();

        expect($dev['aktif'])->toBeTrue()
            ->and($dev['origin_beda'])->toBeTrue();

        $masalah = collect(diagnosa()->masalah())->firstWhere('kode', 'dev_origin');

        expect($masalah)->not->toBeNull()
            ->and($masalah['tingkat'])->toBe(DiagnosaPenilaian::TINGKAT_BAHAYA)
            ->and($masalah['saran'])->toContain('npm run build');
    });
});

test('dev server same-origin hanya memberi peringatan', function () {
    config([
        'teater.runner' => 'pyodide',
        'app.url' => 'https://teater-koding.test',
    ]);

    denganHot('https://teater-koding.test', function () {
        expect(diagnosa()->devServer()['origin_beda'])->toBeFalse()
            ->and(collect(diagnosa()->masalah())->firstWhere('kode', 'dev_origin'))->toBeNull()
            ->and(collect(diagnosa()->masalah())->firstWhere('kode', 'dev_aktif'))->not->toBeNull();
    });
});

test('tanpa public/hot tidak ada masalah dev server', function () {
    config(['teater.runner' => 'pyodide']);

    @unlink(public_path('hot'));

    $dev = diagnosa()->devServer();

    expect($dev['aktif'])->toBeFalse()
        ->and($dev['url'])->toBeNull()
        ->and(collect(diagnosa()->masalah())->firstWhere('kode', 'dev_origin'))->toBeNull()
        ->and(collect(diagnosa()->masalah())->firstWhere('kode', 'dev_aktif'))->toBeNull();
});

test('beda skema saja sudah dianggap origin berbeda', function () {
    config([
        'teater.runner' => 'pyodide',
        'app.url' => 'https://teater-koding.test',
    ]);

    denganHot('http://teater-koding.test', function () {
        expect(diagnosa()->devServer()['origin_beda'])->toBeTrue();
    });
});

test('perintah diagnosa menyebut mode dev saat public/hot aktif', function () {
    config([
        'teater.runner' => 'pyodide',
        'app.url' => 'https://teater-koding.test',
        'queue.default' => 'sync',
    ]);

    denganHot('https://teater-koding.test:5173', function () {
        $this->artisan('teater:diagnosa-penilaian')
            ->expectsOutputToContain('Dev server Vite')
            ->assertFailed();
    });
});
