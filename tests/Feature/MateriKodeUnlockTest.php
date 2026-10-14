<?php

use App\Actions\GenerateKodeUnlock;
use App\Models\Level;
use App\Models\Mission;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LevelsSeeder::class);
});

// ---------- Normalisasi & keunikan kode unlock ----------

test('kode unlock dinormalisasi ke huruf besar dan karakter aman', function () {
    $generator = new GenerateKodeUnlock;

    expect($generator->normalisasi('kop-l1-budi'))->toBe('KOP-L1-BUDI')
        ->and($generator->normalisasi('  kop l1 budi  '))->toBe('KOPL1BUDI')
        ->and($generator->normalisasi('KOP_L1_BUDI!'))->toBe('KOPL1BUDI');
});

test('normalisasi memotong kode lebih dari 30 karakter', function () {
    $generator = new GenerateKodeUnlock;

    expect(mb_strlen($generator->normalisasi(str_repeat('A', 50))))->toBe(30);
});

test('kode unlock harus unik saat menyimpan misi', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    Mission::create(['level_id' => $level->level_id, 'unlock_code' => 'KOP-L1-BUDI', 'client_brief' => 'Brief.']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.misi')
        ->call('bukaTambah')
        ->set('form.level_id', $level->level_id)
        ->set('form.unlock_code', 'KOP-L1-BUDI')
        ->set('form.client_brief', 'Brief lain.')
        ->call('simpanMisi')
        ->assertHasErrors(['form.unlock_code']);

    expect(Mission::query()->count())->toBe(1);
});

test('kode unlock menolak karakter tidak sah', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    $this->actingAs($guru);

    // Normalisasi membuang spasi/underscore, jadi kode jadi KOPL1BUDI (valid).
    Livewire::test('pages::admin.misi')
        ->call('bukaTambah')
        ->set('form.level_id', $level->level_id)
        ->set('form.unlock_code', 'kop l1_budi')
        ->set('form.client_brief', 'Brief.')
        ->call('simpanMisi')
        ->assertHasNoErrors();

    expect(Mission::query()->firstOrFail()->unlock_code)->toBe('KOPL1BUDI');
});

// ---------- Generator kode otomatis ----------

test('generator kode memakai format prefix-level-kata', function () {
    $generator = new GenerateKodeUnlock;

    expect($generator->buat(2, []))->toBe('KOP-L2-BUDI')
        ->and($generator->prefix())->toBe('KOP');
});

test('generator kode menghindari kode yang sudah terpakai', function () {
    $generator = new GenerateKodeUnlock;

    // Kode pertama sudah dipakai → harus menghasilkan kode lain.
    $kode = $generator->buat(2, ['KOP-L2-BUDI']);

    expect($kode)->not->toBe('KOP-L2-BUDI')
        ->and($kode)->toStartWith('KOP-L2-');
});

test('generator kode tetap unik walau semua kata terpakai', function () {
    $generator = new GenerateKodeUnlock;
    $kata = $generator->kataTersedia();

    $terpakai = array_map(fn (string $k): string => "KOP-L2-{$k}", $kata);

    $kode = $generator->buat(2, $terpakai);

    expect($kode)->not->toBeIn($terpakai)
        ->and($kode)->toStartWith('KOP-L2-');
});

test('tombol buat kode otomatis mengisi form dan tidak bentrok dengan data tersimpan', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $level = Level::query()->orderBy('level_number')->firstOrFail();

    $generator = new GenerateKodeUnlock;
    Mission::create(['level_id' => $level->level_id, 'unlock_code' => $generator->buat($level->level_number), 'client_brief' => 'Brief.']);

    $this->actingAs($guru);

    $komponen = Livewire::test('pages::admin.misi')
        ->call('bukaTambah')
        ->set('form.level_id', $level->level_id)
        ->call('buatKodeOtomatis');

    $kode = $komponen->get('form')->unlock_code;

    expect($kode)->not->toBe('')
        ->and(Mission::query()->where('unlock_code', $kode)->exists())->toBeFalse();
});
