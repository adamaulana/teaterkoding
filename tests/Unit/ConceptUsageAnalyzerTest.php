<?php

use App\Models\ConceptCard;
use App\Models\MissionPlan;
use App\Services\ConceptUsageAnalyzer;
use Database\Seeders\ConceptCardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Heuristik teks ConceptUsageAnalyzer: komentar & string harus diabaikan,
 * dan perbandingan kartu dipilih vs terdeteksi harus akurat.
 */
beforeEach(function () {
    $this->seed(ConceptCardsSeeder::class);
    $this->analisis = new ConceptUsageAnalyzer;
});

test('mendeteksi variabel dari assignment', function () {
    expect($this->analisis->deteksi('nama = "Budi"'))->toContain('Variabel')
        ->and($this->analisis->deteksi('total = 0'))->toContain('Variabel');
});

test('assignment tidak salah terdeteksi dari perbandingan', function () {
    // Tidak ada assignment sama sekali → Variabel tidak boleh muncul.
    expect($this->analisis->deteksi('if saldo == 0:'))->not->toContain('Variabel')
        ->and($this->analisis->deteksi('if saldo <= 0:'))->not->toContain('Variabel')
        ->and($this->analisis->deteksi('if saldo != 0:'))->not->toContain('Variabel');
});

test('mendeteksi operator aritmetika dan perbandingan', function () {
    expect($this->analisis->deteksi('total = 5 + 3'))->toContain('Operator')
        ->and($this->analisis->deteksi('if a < b:'))->toContain('Operator');
});

test('mendeteksi percabangan, perulangan, dan fungsi', function () {
    $kode = "def hitung(jumlah):\n    if jumlah > 0:\n        for i in range(jumlah):\n            pass";

    $terdeteksi = $this->analisis->deteksi($kode);

    expect($terdeteksi)->toContain('Function')
        ->and($terdeteksi)->toContain('If/Else')
        ->and($terdeteksi)->toContain('Loop');
});

test('mengabaikan kata kunci yang ada di dalam komentar', function () {
    // Semua kata konsep hanya muncul di komentar → tidak boleh terdeteksi.
    $kode = "# pakai for dan while dan def dan if\nprint(1)";

    $terdeteksi = $this->analisis->deteksi($kode);

    expect($terdeteksi)->not->toContain('Loop')
        ->and($terdeteksi)->not->toContain('Function')
        ->and($terdeteksi)->not->toContain('If/Else');
});

test('mengabaikan kata kunci yang ada di dalam string literal', function () {
    $kode = 'pesan = "for while def if else"';

    $terdeteksi = $this->analisis->deteksi($kode);

    expect($terdeteksi)->not->toContain('Loop')
        ->and($terdeteksi)->not->toContain('Function')
        ->and($terdeteksi)->not->toContain('If/Else')
        // Variabel tetap terdeteksi karena assignment-nya nyata.
        ->and($terdeteksi)->toContain('Variabel');
});

test('kata kunci harus berupa kata utuh, bukan potongan', function () {
    // "format" mengandung "for", "define" mengandung "def".
    $terdeteksi = $this->analisis->deteksi('x = format(1)');

    expect($terdeteksi)->not->toContain('Loop')
        ->and($terdeteksi)->not->toContain('Function');
});

test('menandai konsep yang dipilih tetapi tidak dipakai di kode', function () {
    $dipilih = ConceptCard::query()->whereIn('concept_name', ['Variabel', 'Loop'])->get();

    $hasil = $this->analisis->bandingkan($dipilih, 'nama = "Budi"');

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_DIPILIH_TAK_DIPAKAI)
        ->and($hasil['dipilih_tak_dipakai'])->toContain('Loop')
        ->and($hasil['dipakai_tak_direncanakan'])->not->toContain('Loop');
});

test('menandai konsep yang dipakai tetapi tidak direncanakan', function () {
    $dipilih = ConceptCard::query()->whereIn('concept_name', ['Variabel'])->get();

    $hasil = $this->analisis->bandingkan($dipilih, "nama = \"Budi\"\nfor i in range(3):\n    pass");

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_DIPAKAI_TAK_DIRENCANAKAN)
        ->and($hasil['dipakai_tak_direncanakan'])->toContain('Loop');
});

test('menandai sesuai bila pilihan dan pemakaian cocok', function () {
    $dipilih = ConceptCard::query()->whereIn('concept_name', ['Variabel'])->get();

    $hasil = $this->analisis->bandingkan($dipilih, 'nama = "Budi"');

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_SESUAI)
        ->and($hasil['dipilih_tak_dipakai'])->toBe([])
        ->and($hasil['dipakai_tak_direncanakan'])->toBe([]);
});

test('menandai belum ada kode bila teks kosong', function () {
    $dipilih = ConceptCard::query()->whereIn('concept_name', ['Variabel'])->get();

    expect($this->analisis->bandingkan($dipilih, null)['status'])
        ->toBe(ConceptUsageAnalyzer::HASIL_BELUM_ADA_KODE)
        ->and($this->analisis->bandingkan($dipilih, '   ')['status'])
        ->toBe(ConceptUsageAnalyzer::HASIL_BELUM_ADA_KODE);
});

test('menandai belum merencanakan bila tidak ada kartu dipilih', function () {
    $hasil = $this->analisis->bandingkan(collect(), 'nama = "Budi"');

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_BELUM_MERENCANAKAN)
        ->and($hasil['terdeteksi'])->toContain('Variabel');
});

test('analisisRencana membaca kartu konsep dari rencana misi yang terkunci', function () {
    $rencana = new MissionPlan;
    $rencana->setRelation('concepts', ConceptCard::query()->where('concept_name', 'Loop')->get());
    $rencana->locked_at = now();

    $hasil = $this->analisis->analisisRencana($rencana, 'nama = "Budi"');

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_DIPILIH_TAK_DIPAKAI)
        ->and($hasil['dipilih_tak_dipakai'])->toContain('Loop');
});

test('analisisRencana tanpa rencana dianggap belum merencanakan', function () {
    expect($this->analisis->analisisRencana(null, 'nama = "Budi"')['status'])
        ->toBe(ConceptUsageAnalyzer::HASIL_BELUM_MERENCANAKAN);
});

test('analisisRencana dengan rencana belum terkunci dianggap belum merencanakan', function () {
    $rencana = new MissionPlan;
    $rencana->setRelation('concepts', ConceptCard::query()->where('concept_name', 'Loop')->get());
    $rencana->locked_at = null;

    $hasil = $this->analisis->analisisRencana($rencana, 'nama = "Budi"');

    expect($hasil['status'])->toBe(ConceptUsageAnalyzer::HASIL_BELUM_MERENCANAKAN);
});

test('label dan varian mengikuti status', function () {
    expect($this->analisis->label(['status' => ConceptUsageAnalyzer::HASIL_SESUAI]))->toBe('Sesuai')
        ->and($this->analisis->variant(['status' => ConceptUsageAnalyzer::HASIL_SESUAI]))->toBe('sukses')
        ->and($this->analisis->variant(['status' => ConceptUsageAnalyzer::HASIL_DIPILIH_TAK_DIPAKAI]))->toBe('peringatan')
        ->and($this->analisis->variant(['status' => ConceptUsageAnalyzer::HASIL_DIPAKAI_TAK_DIRENCANAKAN]))->toBe('info');
});
