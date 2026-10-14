<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrol buka/tutup level per kelas (dikendalikan guru).
 *
 * Sisi siswa hanya boleh membuka misi pada level yang sudah DIBUKA guru.
 * TANPA baris di tabel ini, level dianggap TERTUTUP (default aman) —
 * jadi kelas baru tidak otomatis bisa dimainkan sampai guru membukanya.
 *
 * Jendela waktu opsional:
 *   opens_at  — sebelum waktu ini, level belum bisa dibuka.
 *   closes_at — setelah waktu ini, level tidak bisa dikerjakan lagi.
 *
 * Unique (class_id, level_id) menjamin satu pengaturan per kelas per level.
 *
 * Migration ini ADITIF — tidak ada baris lama yang perlu di-backfill karena
 * tabelnya baru; perilaku lama (siswa belum bisa main) tetap sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_level_access', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id');
            $table->integer('level_id');
            $table->boolean('is_open')->default(false);
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'level_id']);
            $table->foreign('class_id')->references('class_id')->on('classes');
            $table->foreign('level_id')->references('level_id')->on('levels');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_level_access');
    }
};
