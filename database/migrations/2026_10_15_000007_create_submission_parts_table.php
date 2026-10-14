<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan kode per siswa yang masuk ke sebuah submission (jejak audit).
 *
 * Untuk misi sumatif, satu submission tim disusun dari beberapa draf fungsi
 * yang masing-masing dimiliki seorang siswa. Tabel ini mencatat potongan mana
 * milik siapa, sehingga nilai individu (dan sengketa nilai) bisa ditelusuri
 * sampai kode asalnya — tanpa perlu menebak dari teks kode gabungan.
 *
 * function_name = '' berarti potongan PROGRAM UTAMA.
 *
 * Migration ini ADITIF (tabel baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_parts', function (Blueprint $table) {
            $table->id();
            $table->integer('submission_id');
            $table->string('function_name', 50)->default('');
            $table->integer('student_id')->nullable();
            $table->longText('code_text');

            $table->foreign('submission_id')->references('submission_id')->on('submissions')->cascadeOnDelete();
            $table->foreign('student_id')->references('student_id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_parts');
    }
};
