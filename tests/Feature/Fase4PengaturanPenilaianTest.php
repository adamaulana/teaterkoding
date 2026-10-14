<?php

use App\Models\GradingSetting;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Pengaturan Penilaian: bobot per kelas, total wajib tepat 100.
 */
beforeEach(function () {
    $this->seed(LevelsSeeder::class);

    $this->guru = User::factory()->create(['role' => 'guru']);

    $this->kelas = SchoolClass::create([
        'class_name' => 'X RPL 1',
        'school_year' => '2026/2027',
        'user_id' => $this->guru->id,
    ]);

    $this->actingAs($this->guru);
});

test('bobot default dimuat saat kelas belum punya pengaturan', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->assertSet('w_individual', 50)
        ->assertSet('w_team', 20)
        ->assertSet('w_bug_plan', 10)
        ->assertSet('w_reflection', 10)
        ->assertSet('w_observation', 10)
        ->assertSet('total', 100)
        ->assertSet('tersimpan', false);
});

test('bobot tersimpan dimuat saat kelas sudah punya pengaturan', function () {
    GradingSetting::create([
        'class_id' => $this->kelas->class_id,
        'w_individual' => 40,
        'w_team' => 30,
        'w_bug_plan' => 10,
        'w_reflection' => 10,
        'w_observation' => 10,
    ]);

    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->assertSet('w_individual', 40)
        ->assertSet('w_team', 30)
        ->assertSet('total', 100)
        ->assertSet('tersimpan', true);
});

test('total dihitung langsung tanpa menyimpan', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 30)
        ->assertSet('total', 80)
        ->assertSee('80%');

    expect(GradingSetting::query()->where('class_id', $this->kelas->class_id)->exists())->toBeFalse();
});

test('bobot dengan total bukan 100 ditolak', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 90)
        ->call('simpan')
        ->assertHasErrors('w_individual');

    expect(GradingSetting::query()->where('class_id', $this->kelas->class_id)->exists())->toBeFalse();
});

test('bobot dengan total kurang dari 100 ditolak', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 10)
        ->set('w_team', 10)
        ->set('w_bug_plan', 10)
        ->set('w_reflection', 10)
        ->set('w_observation', 10)
        ->call('simpan')
        ->assertHasErrors('w_individual');

    expect(GradingSetting::query()->where('class_id', $this->kelas->class_id)->exists())->toBeFalse();
});

test('bobot dengan total tepat 100 tersimpan', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 40)
        ->set('w_team', 30)
        ->set('w_bug_plan', 10)
        ->set('w_reflection', 10)
        ->set('w_observation', 10)
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertSet('tersimpan', true);

    $tersimpan = GradingSetting::query()->where('class_id', $this->kelas->class_id)->firstOrFail();

    expect($tersimpan->w_individual)->toBe(40)
        ->and($tersimpan->w_team)->toBe(30)
        ->and($tersimpan->total())->toBe(100);
});

test('menyimpan ulang memperbarui baris yang sama, bukan menambah', function () {
    $komponen = Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id]);

    $komponen->call('simpan');

    $komponen->set('w_individual', 60)
        ->set('w_team', 10)
        ->call('simpan');

    expect(GradingSetting::query()->where('class_id', $this->kelas->class_id)->count())->toBe(1)
        ->and(GradingSetting::query()->where('class_id', $this->kelas->class_id)->firstOrFail()->w_individual)->toBe(60);
});

test('bobot di luar rentang 0-100 ditolak validasi', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 150)
        ->call('simpan')
        ->assertHasErrors('w_individual');
});

test('pakai bobot default mengembalikan nilai 50/20/10/10/10', function () {
    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->set('w_individual', 25)
        ->set('w_team', 25)
        ->set('w_bug_plan', 25)
        ->set('w_reflection', 25)
        ->set('w_observation', 0)
        ->call('pakaiDefault')
        ->assertSet('w_individual', 50)
        ->assertSet('w_team', 20)
        ->assertSet('total', 100);
});

test('berganti kelas memuat bobot kelas tersebut', function () {
    $kelasLain = SchoolClass::create([
        'class_name' => 'X RPL 2',
        'school_year' => '2026/2027',
        'user_id' => $this->guru->id,
    ]);

    GradingSetting::create([
        'class_id' => $kelasLain->class_id,
        'w_individual' => 70,
        'w_team' => 10,
        'w_bug_plan' => 10,
        'w_reflection' => 5,
        'w_observation' => 5,
    ]);

    Livewire::test('pages::admin.pengaturan-penilaian', ['kelas' => $this->kelas->class_id])
        ->assertSet('w_individual', 50)
        ->set('kelasId', $kelasLain->class_id)
        ->assertSet('w_individual', 70)
        ->assertSet('w_team', 10)
        ->assertSet('tersimpan', true);
});

test('bobot efektif kelas dipakai oleh model SchoolClass', function () {
    GradingSetting::create([
        'class_id' => $this->kelas->class_id,
        'w_individual' => 45,
        'w_team' => 25,
        'w_bug_plan' => 10,
        'w_reflection' => 10,
        'w_observation' => 10,
    ]);

    expect($this->kelas->bobotEfektif()['w_individual'])->toBe(45);

    $kelasTanpaPengaturan = SchoolClass::create(['class_name' => 'Tanpa', 'school_year' => '2026/2027']);

    expect($kelasTanpaPengaturan->bobotEfektif())->toBe(GradingSetting::bobotDefault());
});
