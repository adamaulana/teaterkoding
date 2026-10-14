<?php

use App\Actions\ParserBarisKasusUji;
use App\Actions\ValidasiKasusUji;
use App\Models\Level;
use App\Models\Mission;
use App\Models\TestCase;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LevelsSeeder::class);
    $this->validator = new ValidasiKasusUji;
});

// ---------- check_target: varian valid ----------

test('check_target menerima nama variabel python valid', function () {
    expect($this->validator->periksaCheckTarget('status'))->toBeNull()
        ->and($this->validator->periksaCheckTarget('_saldo'))->toBeNull()
        ->and($this->validator->periksaCheckTarget('total2'))->toBeNull();
});

test('check_target menerima satu pemanggilan fungsi', function () {
    expect($this->validator->periksaCheckTarget('hitung_total(100)'))->toBeNull()
        ->and($this->validator->periksaCheckTarget('status_pinjaman(10_000_000)'))->toBeNull()
        ->and($this->validator->periksaCheckTarget('cek("lunas")'))->toBeNull();
});

// ---------- check_target: varian tidak valid ----------

test('check_target menolak tanda kurung tidak seimbang', function () {
    expect($this->validator->periksaCheckTarget('hitung_total(100'))->not->toBeNull()
        ->and($this->validator->periksaCheckTarget('hitung_total)100('))->not->toBeNull();
});

test('check_target menolak tanda kutip tidak seimbang', function () {
    expect($this->validator->periksaCheckTarget('cek("lunas)'))->not->toBeNull();
});

test('check_target menolak token terlarang', function () {
    foreach (['import os', 'exec("x")', 'eval("1")', 'open("f")', '__class__', 'subprocess.run', 'input()'] as $terlarang) {
        expect($this->validator->periksaCheckTarget($terlarang))->not->toBeNull("Gagal menolak: {$terlarang}");
    }
});

test('check_target menolak titik koma dan baris baru', function () {
    expect($this->validator->periksaCheckTarget('status; print(1)'))->not->toBeNull()
        ->and($this->validator->periksaCheckTarget("status\nprint(1)"))->not->toBeNull();
});

test('check_target menolak ekspresi bebas', function () {
    expect($this->validator->periksaCheckTarget('1 + 1'))->not->toBeNull()
        ->and($this->validator->periksaCheckTarget('data[0]'))->not->toBeNull();
});

// ---------- expected_value ----------

test('expected_value menerima literal skalar', function () {
    foreach (['1500', '10_000_000', '-5', '3.5', '-0.5', '"lunas"', "'belum'", 'True', 'False', 'None'] as $valid) {
        expect($this->validator->periksaExpectedValue($valid))->toBeNull("Gagal menerima: {$valid}");
    }
});

test('expected_value menolak list dan ekspresi', function () {
    foreach (['[1, 2, 3]', '(1, 2)', '1 + 1', 'len("abc")', '{1: 2}', 'status'] as $invalid) {
        expect($this->validator->periksaExpectedValue($invalid))->not->toBeNull("Gagal menolak: {$invalid}");
    }
});

// ---------- tolerance ----------

test('tolerance menolak nilai negatif', function () {
    expect($this->validator->periksaTolerance(-1, '100'))->not->toBeNull();
});

test('tolerance menolak bila expected bukan angka', function () {
    expect($this->validator->periksaTolerance(1, '"lunas"'))->not->toBeNull();
});

test('tolerance menerima nol dan nilai positif pada expected numerik', function () {
    expect($this->validator->periksaTolerance(0, '100'))->toBeNull()
        ->and($this->validator->periksaTolerance(null, '100'))->toBeNull()
        ->and($this->validator->periksaTolerance(1, '100'))->toBeNull()
        ->and($this->validator->periksaTolerance(0.5, '3.5'))->toBeNull();
});

// ---------- setup_code ----------

test('setup_code menerima assignment sederhana', function () {
    expect($this->validator->periksaSetupCode('saldo = 10_000_000'))->toBeNull()
        ->and($this->validator->periksaSetupCode("saldo = 10_000_000\npinjaman = 5_000"))->toBeNull()
        ->and($this->validator->periksaSetupCode('nama = "Budi"'))->toBeNull();
});

test('setup_code menolak selain assignment', function () {
    foreach (['print(1)', 'import os', 'saldo += 100', 'for i in range(3): pass', 'saldo = 1; print(1)'] as $invalid) {
        expect($this->validator->periksaSetupCode($invalid))->not->toBeNull("Gagal menolak: {$invalid}");
    }
});

test('setup_code menolak nilai yang bukan literal skalar', function () {
    expect($this->validator->periksaSetupCode('saldo = [1, 2]'))->not->toBeNull();
});

// ---------- jenis & nama fungsi ----------

test('jenis kasus uji dideteksi otomatis', function () {
    expect($this->validator->jenis('status'))->toBe(ValidasiKasusUji::JENIS_VARIABEL)
        ->and($this->validator->jenis('hitung_total(100)'))->toBe(ValidasiKasusUji::JENIS_FUNGSI);
});

test('daftar fungsi diturunkan unik dari check_target', function () {
    $fungsi = $this->validator->daftarFungsi(['hitung_total(100)', 'status', 'hitung_total(200)', 'cek_saldo(5)']);

    expect($fungsi)->toBe(['hitung_total', 'cek_saldo']);
});

// ---------- Duplikat & penyimpanan lewat halaman ----------

test('kasus uji duplikat ditolak', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-A', 'client_brief' => 'Brief.']);

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Status',
        'setup_code' => 'saldo = 100',
        'check_target' => 'status',
        'expected_value' => '"lunas"',
        'tolerance' => 0,
    ]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('bukaTambahKasus')
        ->set('kasusForm.label', 'Status lagi')
        ->set('kasusForm.setup_code', 'saldo = 100')
        ->set('kasusForm.check_target', 'status')
        ->set('kasusForm.expected_value', '"lunas"')
        ->call('simpanKasus')
        ->assertHasErrors(['kasusForm.check_target']);

    expect(TestCase::query()->count())->toBe(1);
});

test('kasus uji dengan target terlarang ditolak saat menyimpan', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-B', 'client_brief' => 'Brief.']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('bukaTambahKasus')
        ->set('kasusForm.label', 'Jahat')
        ->set('kasusForm.check_target', 'exec("rm -rf")')
        ->set('kasusForm.expected_value', '1')
        ->call('simpanKasus')
        ->assertHasErrors(['kasusForm.check_target']);

    expect(TestCase::query()->count())->toBe(0);
});

test('kasus uji valid tersimpan lengkap dengan setup_code', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-C', 'client_brief' => 'Brief.']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('bukaTambahKasus')
        ->set('kasusForm.label', 'Status lunas')
        ->set('kasusForm.setup_code', 'saldo = 10_000_000')
        ->set('kasusForm.check_target', 'status')
        ->set('kasusForm.expected_value', '"lunas"')
        ->call('simpanKasus')
        ->assertHasNoErrors();

    $kasus = TestCase::query()->firstOrFail();

    expect($kasus->setup_code)->toBe('saldo = 10_000_000')
        ->and($kasus->check_target)->toBe('status');
});

// ---------- Parser baris massal ----------

test('parser baris massal memisahkan valid dan tidak valid', function () {
    $teks = implode("\n", [
        'Status lunas | saldo = 10_000_000 | status | "lunas" | ',
        'Total | | hitung_total(100) | 1000 | 1',
        'Jahat | | exec("x") | 1 | ',
        'Kurang kolom | hanya | dua',
    ]);

    $hasil = (new ParserBarisKasusUji)->parse($teks);

    expect($hasil['total'])->toBe(4)
        ->and($hasil['valid'])->toHaveCount(2)
        ->and($hasil['invalid'])->toHaveCount(2);
});

test('parser baris massal melewati komentar dan baris kosong', function () {
    $teks = "# komentar\n\nStatus | | status | \"lunas\" | \n";

    $hasil = (new ParserBarisKasusUji)->parse($teks);

    expect($hasil['total'])->toBe(1)
        ->and($hasil['valid'])->toHaveCount(1);
});

test('tambah banyak sekaligus menyimpan baris valid saja', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();
    $misi = Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-D', 'client_brief' => 'Brief.']);

    $this->actingAs($guru);

    $teks = implode("\n", [
        'Status lunas | saldo = 10_000_000 | status | "lunas" | ',
        'Total | | hitung_total(100) | 1000 | 1',
        'Jahat | | eval("x") | 1 | ',
    ]);

    Livewire::test('pages::admin.misi')
        ->call('bukaDetail', $misi->mission_id)
        ->call('bukaMassal')
        ->set('teksMassal', $teks)
        ->call('pratinjauMassal')
        ->assertSet('pratinjauMassal', true)
        ->call('simpanMassal');

    expect(TestCase::query()->count())->toBe(2);
});
