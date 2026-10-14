<?php

use App\Actions\ParserCsvSiswa;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('baris valid diurai dengan benar', function () {
    $csv = "Budi Santoso,24001\nSiti Aminah,24002\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(2)
        ->and($hasil['invalid'])->toHaveCount(0)
        ->and($hasil['valid'][0]['name'])->toBe('Budi Santoso')
        ->and($hasil['valid'][0]['nis'])->toBe('24001');
});

test('header nama,nis dilewati', function () {
    $csv = "nama,nis\nBudi,1\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(1)
        ->and($hasil['valid'][0]['name'])->toBe('Budi');
});

test('nama kosong ditandai bermasalah', function () {
    $csv = ",24001\nBudi,24002\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(1)
        ->and($hasil['invalid'])->toHaveCount(1)
        ->and($hasil['invalid'][0]['reason'])->toContain('Nama kosong');
});

test('NIS ganda di dalam berkas ditolak', function () {
    $csv = "Budi,24001\nSiti,24001\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(1)
        ->and($hasil['invalid'])->toHaveCount(1)
        ->and($hasil['invalid'][0]['reason'])->toContain('ganda di dalam berkas');
});

test('NIS yang sudah ada di kelas ditolak', function () {
    $csv = "Budi,24001\nSiti,24002\n";

    $hasil = (new ParserCsvSiswa)->parse($csv, ['24002']);

    expect($hasil['valid'])->toHaveCount(1)
        ->and($hasil['invalid'])->toHaveCount(1)
        ->and($hasil['invalid'][0]['reason'])->toContain('sudah ada di kelas');
});

test('pemisah titik-koma terdeteksi otomatis', function () {
    $csv = "Budi Santoso;24001\nSiti Aminah;24002\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(2)
        ->and($hasil['valid'][0]['nis'])->toBe('24001');
});

test('BOM UTF-8 ditoleransi', function () {
    $csv = "\xEF\xBB\xBFBudi,24001\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(1)
        ->and($hasil['valid'][0]['name'])->toBe('Budi');
});

test('NIS opsional boleh kosong', function () {
    $csv = "Budi\nSiti,\n";

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['valid'])->toHaveCount(2)
        ->and($hasil['valid'][0]['nis'])->toBeNull()
        ->and($hasil['valid'][1]['nis'])->toBeNull();
});

test('batas 500 baris dipatuhi', function () {
    $baris = [];
    for ($i = 1; $i <= 510; $i++) {
        $baris[] = "Siswa {$i},".(20000 + $i);
    }
    $csv = implode("\n", $baris);

    $hasil = (new ParserCsvSiswa)->parse($csv);

    expect($hasil['total'])->toBe(500)
        ->and($hasil['truncated'])->toBeTrue()
        ->and($hasil['valid'])->toHaveCount(500);
});

// ---------- Alur impor pada halaman ----------

test('impor baris valid menyimpan siswa dan melewati baris bermasalah', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);

    $csv = "Budi Santoso,24001\n,24002\nSiti Aminah,24003\n";
    $berkas = UploadedFile::fake()->createWithContent('siswa.csv', $csv);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->set('imporKelasId', $kelas->class_id)
        ->set('berkas', $berkas)
        ->call('pratinjauImpor')
        ->assertSet('imporTahap', 'pratinjau')
        ->call('imporBarisValid');

    expect(Student::query()->where('class_id', $kelas->class_id)->count())->toBe(2)
        ->and(Student::query()->where('full_name', 'Budi Santoso')->exists())->toBeTrue()
        ->and(Student::query()->where('full_name', 'Siti Aminah')->exists())->toBeTrue();
});

test('impor menolak NIS yang sudah ada di kelas', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Lama', 'student_no' => '24001']);

    $csv = "Budi,24001\nSiti,24002\n";
    $berkas = UploadedFile::fake()->createWithContent('siswa.csv', $csv);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->set('imporKelasId', $kelas->class_id)
        ->set('berkas', $berkas)
        ->call('pratinjauImpor')
        ->assertSet('pratinjauInvalid', fn (array $invalid): bool => count($invalid) === 1)
        ->call('imporBarisValid');

    expect(Student::query()->where('class_id', $kelas->class_id)->count())->toBe(2)
        ->and(Student::query()->where('student_no', '24002')->exists())->toBeTrue();
});

test('NIS unik per kelas pada form tambah siswa', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelasA = SchoolClass::create(['class_name' => 'Kelas A', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $kelasB = SchoolClass::create(['class_name' => 'Kelas B', 'school_year' => '2026/2027', 'user_id' => $guru->id]);

    Student::create(['class_id' => $kelasA->class_id, 'full_name' => 'Lama', 'student_no' => '24001']);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->call('bukaTambah')
        ->set('form.class_id', $kelasA->class_id)
        ->set('form.full_name', 'Budi')
        ->set('form.student_no', '24001')
        ->call('simpanSiswa')
        ->assertHasErrors(['form.student_no']);

    Livewire::test('pages::admin.siswa')
        ->call('bukaTambah')
        ->set('form.class_id', $kelasB->class_id)
        ->set('form.full_name', 'Budi')
        ->set('form.student_no', '24001')
        ->call('simpanSiswa')
        ->assertHasNoErrors();

    expect(Student::query()->where('class_id', $kelasB->class_id)->count())->toBe(1);
});

test('hapus siswa ditolak saat masih anggota kelompok', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => null]);

    $team = Team::create(['class_id' => $kelas->class_id, 'team_name' => 'Kelompok 1']);
    TeamMember::create(['team_id' => $team->team_id, 'student_id' => $siswa->student_id]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->call('mintaHapus', $siswa->student_id)
        ->call('hapusSiswa');

    expect(Student::query()->where('student_id', $siswa->student_id)->exists())->toBeTrue();
});

test('hapus siswa berhasil saat tanpa relasi', function () {
    $guru = User::factory()->create(['role' => 'guru']);
    $kelas = SchoolClass::create(['class_name' => 'X RPL 1', 'school_year' => '2026/2027', 'user_id' => $guru->id]);
    $siswa = Student::create(['class_id' => $kelas->class_id, 'full_name' => 'Budi', 'student_no' => null]);

    $this->actingAs($guru);

    Livewire::test('pages::admin.siswa')
        ->call('mintaHapus', $siswa->student_id)
        ->call('hapusSiswa');

    expect(Student::query()->where('student_id', $siswa->student_id)->exists())->toBeFalse();
});
