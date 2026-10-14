<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom setup_code pada test_cases.
 *
 * Skema lama tidak punya tempat untuk "input awal" yang dijalankan sebelum
 * kode siswa. Tanpa kolom ini, kasus uji Level 2 ("untuk saldo X, variabel
 * status harus Y") tidak bisa dinyatakan — dan karena satu baris tabel Level 2
 * melahirkan DUA kasus uji dari satu nilai saldo, setup harus menjadi kolom
 * sendiri, bukan ditempel ke label.
 *
 * Nullable karena Level 1/3/4 tidak membutuhkan kode awal.
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->string('setup_code', 200)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            $table->dropColumn('setup_code');
        });
    }
};
