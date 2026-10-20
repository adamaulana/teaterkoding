<?php

use App\Models\BugCard;
use App\Models\BugTestCase;
use App\Models\ConceptCard;
use App\Models\Level;
use App\Models\Mission;
use App\Models\TestCase;
use Database\Seeders\MateriKoperasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder materi koperasi mengisi starter_code tiap misi', function () {
    $this->seed(MateriKoperasiSeeder::class);

    expect(Mission::count())->toBe(4);

    $l1 = Mission::query()->where('unlock_code', 'KOP-L1-BUDI')->firstOrFail();
    expect($l1->starter_code)->toContain('saldo_awal')
        ->and($l1->starter_code)->toContain('setoran_1');

    $l4 = Mission::query()->where('unlock_code', 'KOP-L4-KASIR')->firstOrFail();
    expect($l4->starter_code)->toContain('# === FUNGSI: hitung_bunga ===')
        ->and($l4->starter_code)->toContain('# === FUNGSI: simulasi_tabungan ===')
        ->and($l4->starter_code)->toContain('def hitung_bunga(saldo, persen):');
});

test('seeder materi koperasi mengisi symptom_text 8 kartu bug', function () {
    $this->seed(MateriKoperasiSeeder::class);

    expect(BugCard::count())->toBe(8)
        ->and(BugCard::query()->whereNull('symptom_text')->count())->toBe(0);
});

test('seeder materi koperasi membuat kasus uji bug yang lolos validasi', function () {
    $this->seed(MateriKoperasiSeeder::class);

    // Minimal satu kasus per kartu.
    foreach (BugCard::query()->get() as $kartu) {
        expect(BugTestCase::query()->where('bug_id', $kartu->bug_id)->count())->toBeGreaterThan(0);
    }

    // Nilai spesifik sesuai dokumen.
    $bunga = BugCard::query()->where('bug_title', 'Saldo yang Berupa Teks')->firstOrFail();
    expect(BugTestCase::query()->where('bug_id', $bunga->bug_id)->where('expected_value', '12500.0')->exists())->toBeTrue();

    $gold = BugCard::query()->where('bug_title', 'Return yang Terlalu Cepat')->firstOrFail();
    expect(BugTestCase::query()->where('bug_id', $gold->bug_id)->where('check_target', 'jumlah_gold([3000000, 12000000, 15000000])')->where('expected_value', '2')->exists())->toBeTrue();
});

test('seeder materi koperasi memakai judul, level, dan poin sesuai dokumen', function () {
    $this->seed(MateriKoperasiSeeder::class);

    $acuan = [
        ['Saldo yang Berupa Teks', 1, 10],
        ['Pecahan Tertukar', 1, 10],
        ['Gold yang Tak Pernah Muncul', 2, 15],
        ['Batas yang Meleset', 2, 10],
        ['Total yang Selalu Ulang dari Nol', 3, 15],
        ['Bulan yang Tak Pernah Berganti', 3, 15],
        ['Fungsi yang Hanya Bicara', 4, 20],
        ['Return yang Terlalu Cepat', 4, 20],
    ];

    $levelPerNomor = Level::query()->pluck('level_id', 'level_number');

    foreach ($acuan as [$judul, $nomorLevel, $poin]) {
        $kartu = BugCard::query()
            ->where('level_id', $levelPerNomor[$nomorLevel])
            ->where('bug_title', $judul)
            ->first();

        expect($kartu)->not->toBeNull("Kartu \"{$judul}\" tidak ditemukan.")
            ->and((int) $kartu->points)->toBe($poin)
            ->and($kartu->symptom_text)->not->toBeNull();
    }
});

test('seeder materi koperasi memakai kasus uji misi sesuai dokumen', function () {
    $this->seed(MateriKoperasiSeeder::class);

    $jumlah = [
        'KOP-L1-BUDI' => 5,
        'KOP-L2-STATUS' => 16,
        'KOP-L3-LOOP' => 5,
        'KOP-L4-KASIR' => 13,
    ];

    foreach ($jumlah as $kode => $total) {
        $misi = Mission::query()->where('unlock_code', $kode)->firstOrFail();

        expect(TestCase::query()->where('mission_id', $misi->mission_id)->count())->toBe($total);
    }

    // Toleransi ±1 pada kasus yang disebutkan dokumen.
    $l3 = Mission::query()->where('unlock_code', 'KOP-L3-LOOP')->firstOrFail();
    expect((float) TestCase::query()->where('mission_id', $l3->mission_id)->where('check_target', 'saldo_tabungan')->value('tolerance'))->toBe(1.0);

    // Level 2 memakai setup_code saldo = <nilai>.
    $l2 = Mission::query()->where('unlock_code', 'KOP-L2-STATUS')->firstOrFail();
    expect(TestCase::query()->where('mission_id', $l2->mission_id)->where('setup_code', 'saldo = 10000000')->count())->toBe(2);
});

test('seeder materi koperasi mengisi isi kartu konsep', function () {
    $this->seed(MateriKoperasiSeeder::class);

    expect(ConceptCard::query()->whereNotNull('function_desc')->count())->toBe(5)
        ->and(ConceptCard::query()->whereNotNull('syntax_example')->count())->toBe(5)
        ->and(ConceptCard::query()->whereNotNull('trigger_question')->count())->toBe(5);
});

test('seeder materi koperasi idempoten', function () {
    $this->seed(MateriKoperasiSeeder::class);

    $misi = Mission::count();
    $bug = BugCard::count();
    $kasus = BugTestCase::count();

    $this->seed(MateriKoperasiSeeder::class);

    expect(Mission::count())->toBe($misi)
        ->and(BugCard::count())->toBe($bug)
        ->and(BugTestCase::count())->toBe($kasus);
});
