<?php

use App\Models\GradingSetting;
use App\Models\Level;
use App\Models\Mission;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TestCase;
use App\Models\User;
use App\Services\SumativeScore;
use Database\Seeders\LevelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Langkah 0 Fase 5: regresi perbaikan Fase 4 (dashboard, label bobot,
 * waktu seeder, sel kosong netral).
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

// ---------- 1. Dashboard: grid progres + aktivitas ----------

test('dashboard menaruh progres dan aktivitas dalam satu grid', function () {
    // Regresi: sebelumnya </div> menutup grid terlalu awal sehingga
    // "Aktivitas Terbaru" keluar dari grid dan progres terjepit 1/3 kolom.
    $html = Livewire::test('pages::admin.dashboard', ['kelas' => $this->kelas->class_id])
        ->html();

    $posGrid = mb_strpos($html, 'mt-6 grid gap-6 lg:grid-cols-3');
    $posProgres = mb_strpos($html, 'Progres Kelompok');
    $posAktivitas = mb_strpos($html, 'Aktivitas Terbaru');

    expect($posGrid)->not->toBeFalse()
        ->and($posProgres)->not->toBeFalse()
        ->and($posAktivitas)->not->toBeFalse()
        ->and($posProgres)->toBeGreaterThan($posGrid)
        ->and($posAktivitas)->toBeGreaterThan($posProgres);
});

test('dashboard memakai lebar 2/3 untuk progres dan 1/3 untuk aktivitas', function () {
    $html = Livewire::test('pages::admin.dashboard', ['kelas' => $this->kelas->class_id])->html();

    expect($html)->toContain('lg:col-span-2')
        ->and($html)->toContain('lg:col-span-1');
});

test('dashboard menampilkan kolom rencana vs kode', function () {
    Team::create(['class_id' => $this->kelas->class_id, 'team_name' => 'Kelompok Alpha']);

    Livewire::test('pages::admin.dashboard', ['kelas' => $this->kelas->class_id])
        ->assertSee('Rencana vs Kode');
});

// ---------- 2. Label bobot tidak boleh menampilkan nama kolom mentah ----------

test('label bobot memetakan semua kolom ke label indonesia', function () {
    $label = GradingSetting::labelPerBobot();

    expect($label)->toHaveKeys([
        'w_individual', 'w_team', 'w_bug_plan', 'w_reflection', 'w_observation',
    ])
        ->and($label['w_individual'])->toBe('Fungsi Individu')
        ->and($label['w_team'])->toBe('Kelompok')
        ->and($label['w_bug_plan'])->toBe('Kartu Bug & Rencana')
        ->and($label['w_reflection'])->toBe('Refleksi/Rubrik')
        ->and($label['w_observation'])->toBe('Observasi');

    foreach ($label as $teks) {
        expect($teks)->not->toContain('w_');
    }
});

test('banner bobot rekap nilai tidak menampilkan nama kolom mentah', function () {
    $level = Level::query()->where('is_summative', true)->firstOrFail();
    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'SUM-1',
        'client_brief' => 'Brief.',
    ]);

    $komponen = Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $misi->mission_id,
    ]);

    $html = $komponen->html();

    // Regresi: w_individual, w_team, w_reflection, w_observation pernah bocor.
    foreach (['w_individual', 'w_team', 'w_bug_plan', 'w_reflection', 'w_observation'] as $kolom) {
        expect($html)->not->toContain($kolom.':');
    }

    $komponen->assertSee('Fungsi Individu')
        ->assertSee('Kartu Bug & Rencana')
        ->assertSee('Refleksi/Rubrik')
        ->assertSee('Observasi');
});

// ---------- 3. Sel kosong netral, bukan 0 merah ----------

test('sel komponen kosong dirender sebagai tanda pisah netral', function () {
    $level = Level::query()->where('is_summative', true)->firstOrFail();
    $misi = Mission::create([
        'level_id' => $level->level_id,
        'unlock_code' => 'SUM-1',
        'client_brief' => 'Brief.',
    ]);

    TestCase::create([
        'mission_id' => $misi->mission_id,
        'label' => 'Total',
        'check_target' => 'hitung(1)',
        'expected_value' => '1',
        'tolerance' => 0,
    ]);

    $siswa = Student::create([
        'class_id' => $this->kelas->class_id,
        'full_name' => 'Adi Nugroho',
        'student_no' => 'D001',
    ]);

    $html = Livewire::test('pages::admin.rekap-nilai', [
        'kelas' => $this->kelas->class_id,
        'misi' => $misi->mission_id,
    ])->html();

    // Siswa tanpa kelompok & tanpa submission → netral, bukan merah.
    expect($html)->toContain('text-slate-400')
        ->and($html)->not->toContain('text-rose-600');

    expect($siswa->full_name)->toBe('Adi Nugroho');
});

test('warna netral dan merah dibedakan pada SumativeScore', function () {
    expect(SumativeScore::warna(null))->toBe('text-slate-400')
        ->and(SumativeScore::warna(0))->toBe('text-rose-600');
});
