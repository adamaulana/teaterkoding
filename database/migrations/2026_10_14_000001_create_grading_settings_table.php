<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bobot penilaian sumatif per kelas.
 *
 * Belum ada tabel yang menyimpan bobot, padahal nilai akhir siswa =
 * jumlah (komponen x bobot kelas) / 100. Satu baris per kelas (class_id unik).
 *
 * Validasi "total harus tepat 100" sengaja dilakukan di aplikasi, bukan
 * lewat constraint DB, agar pesan kesalahannya ramah untuk guru.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus tabel lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('class_id')->unique();
            $table->integer('w_individual')->default(50);
            $table->integer('w_team')->default(20);
            $table->integer('w_bug_plan')->default(10);
            $table->integer('w_reflection')->default(10);
            $table->integer('w_observation')->default(10);
            $table->timestamps();

            $table->foreign('class_id')->references('class_id')->on('classes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_settings');
    }
};
