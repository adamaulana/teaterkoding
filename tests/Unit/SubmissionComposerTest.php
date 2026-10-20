<?php

use App\Models\CodeDraft;
use App\Services\SubmissionComposer;

test('split memisahkan program utama dan tiap fungsi sesuai penanda', function () {
    $kode = "# komentar utama\nprint('halo')\n\n# === FUNGSI: hitung_bunga ===\ndef hitung_bunga():\n    pass\n\n# === FUNGSI: cek_status ===\ndef cek_status():\n    pass";

    $hasil = (new SubmissionComposer)->split($kode);

    expect($hasil->pluck('function_name')->all())->toBe(['', 'hitung_bunga', 'cek_status'])
        ->and($hasil->first()['code'])->toContain("print('halo')")
        ->and($hasil->get(1)['code'])->toContain('def hitung_bunga()');
});

test('urutanFungsi mengembalikan nama fungsi sesuai penanda', function () {
    $kode = "# === FUNGSI: b ===\nx\n# === FUNGSI: a ===\ny";

    expect((new SubmissionComposer)->urutanFungsi($kode)->all())->toBe(['b', 'a']);
});

test('fungsiGanda mendeteksi nama fungsi yang muncul lebih dari sekali', function () {
    $kode = "# === FUNGSI: a ===\n# === FUNGSI: b ===\n# === FUNGSI: a ===";

    expect((new SubmissionComposer)->fungsiGanda($kode))->toBe(['a']);
});

test('fungsiGanda kosong bila tidak ada duplikat', function () {
    $kode = "# === FUNGSI: a ===\n# === FUNGSI: b ===";

    expect((new SubmissionComposer)->fungsiGanda($kode))->toBe([]);
});

test('susun menyusun fungsi sesuai urutan lalu program utama, dipisah baris kosong', function () {
    $urutan = collect(['hitung_bunga', 'cek_status']);

    $draf = collect([
        CodeDraft::make(['function_name' => 'cek_status', 'code_text' => "def cek_status():\n    return 1", 'owner_student_id' => 2]),
        CodeDraft::make(['function_name' => 'hitung_bunga', 'code_text' => "def hitung_bunga():\n    return 2", 'owner_student_id' => 1]),
        CodeDraft::make(['function_name' => '', 'code_text' => 'print("utama")', 'owner_student_id' => 3]),
    ]);

    $hasil = (new SubmissionComposer)->susun($urutan, $draf);

    expect($hasil['code'])->toBe("def hitung_bunga():\n    return 2\n\ndef cek_status():\n    return 1\n\nprint(\"utama\")")
        ->and($hasil['parts']->pluck('function_name')->all())->toBe(['hitung_bunga', 'cek_status', '']);
});

test('susun mengganti draf kosong dengan stub', function () {
    $urutan = collect(['hitung_bunga']);

    $draf = collect([
        CodeDraft::make(['function_name' => 'hitung_bunga', 'code_text' => '   ', 'owner_student_id' => 1]),
    ]);

    $hasil = (new SubmissionComposer)->susun($urutan, $draf);

    expect($hasil['code'])->toContain('def hitung_bunga():')
        ->and($hasil['code'])->toContain('pass');
});

test('susun tanpa penanda tetap menyertakan program utama', function () {
    $urutan = collect();

    $draf = collect([
        CodeDraft::make(['function_name' => '', 'code_text' => 'print(1)', 'owner_student_id' => null]),
    ]);

    $hasil = (new SubmissionComposer)->susun($urutan, $draf);

    expect($hasil['code'])->toBe('print(1)')
        ->and($hasil['parts']->pluck('function_name')->all())->toBe(['']);
});