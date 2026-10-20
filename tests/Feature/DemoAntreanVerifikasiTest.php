<?php

use App\Models\BugAttempt;
use App\Models\BugCard;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Seeder demo menyiapkan antrean verifikasi guru (Fase 6 — Bagian D):
 *   - submission sumatif 'menunggu_verifikasi' TANPA poin,
 *   - satu di antaranya punya selisih (mismatch) dengan client_report konsisten,
 *   - satu submission formatif 'menunggu' (perangkat belum selesai),
 *   - satu percobaan Kartu Bug sumatif 'menunggu_verifikasi'.
 */
test('seeder demo membuat antrean verifikasi tanpa memberi poin', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $teamIds = Team::query()->where('class_id', $kelas->class_id)->pluck('team_id')->all();

    $menungguVerifikasi = Submission::query()
        ->whereIn('team_id', $teamIds)
        ->where('status', Submission::STATUS_MENUNGGU_VERIFIKASI)
        ->get();

    // Dua submission sumatif: satu tanpa selisih, satu dengan selisih.
    expect($menungguVerifikasi)->toHaveCount(2)
        ->and($menungguVerifikasi->where('has_mismatch', true))->toHaveCount(1)
        ->and($menungguVerifikasi->every(fn (Submission $s) => $s->grading_source === Submission::SUMBER_KLIEN))->toBeTrue()
        ->and($menungguVerifikasi->every(fn (Submission $s) => $s->results()->count() > 0))->toBeTrue()
        // client_report konsisten dengan jumlah kasus uji nyata.
        ->and($menungguVerifikasi->every(
            fn (Submission $s) => data_get($s->client_report, 'jumlah_hasil') === $s->results()->count(),
        ))->toBeTrue();

    // Satu submission formatif 'menunggu' dengan nonce masih berlaku.
    $menungguPerangkat = Submission::query()
        ->whereIn('team_id', $teamIds)
        ->where('status', Submission::STATUS_MENUNGGU)
        ->get();

    expect($menungguPerangkat)->toHaveCount(1)
        ->and(strlen((string) $menungguPerangkat->first()->grading_nonce))->toBe(64)
        ->and($menungguPerangkat->first()->nonce_expires_at->isFuture())->toBeTrue();

    // Satu percobaan bug sumatif menunggu verifikasi.
    $bug = BugAttempt::query()
        ->whereIn('team_id', $teamIds)
        ->where('grading_status', BugAttempt::STATUS_MENUNGGU_VERIFIKASI)
        ->get();

    expect($bug)->toHaveCount(1)
        ->and($bug->first()->is_solved)->toBeFalse();
});

test('teater:hapus-demo menghapus antrean verifikasi dan tidak menyentuh materi', function () {
    $this->seed(DemoDataSeeder::class);

    $kelas = SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->firstOrFail();
    $teamIds = Team::query()->where('class_id', $kelas->class_id)->pluck('team_id')->all();

    expect(Submission::query()->whereIn('team_id', $teamIds)->count())->toBeGreaterThan(0);

    $jumlahKasus = TestCase::query()->count();
    $jumlahBug = BugCard::query()->count();

    Artisan::call('teater:hapus-demo', ['--force' => true]);

    expect(SchoolClass::query()->where('class_name', config('teater.demo_class_name'))->exists())->toBeFalse()
        ->and(Submission::query()->whereIn('team_id', $teamIds)->count())->toBe(0)
        ->and(BugAttempt::query()->whereIn('team_id', $teamIds)->count())->toBe(0)
        // Materi global tidak berubah.
        ->and(TestCase::query()->count())->toBe($jumlahKasus)
        ->and(BugCard::query()->count())->toBe($jumlahBug);
});
