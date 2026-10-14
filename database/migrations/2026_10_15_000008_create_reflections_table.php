<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan & refleksi siswa per misi, dibedakan menurut peran.
 *
 * type:
 *   penjelasan  — Analyst menjelaskan cara kerja kode
 *   catatan_uji — Tester mencatat input yang dicoba dan hasilnya
 *   tinjauan    — Reviewer meninjau kode rekan
 *   refleksi    — refleksi PRIBADI (semua anggota, dianjurkan di level sumatif)
 *
 * role_id nullable: catatan tetap sah walau penugasan peran berubah atau
 * dihapus, karena isi catatan lebih penting daripada jabatannya saat itu.
 *
 * Unique (student_id, mission_id, type) → satu catatan per jenis per siswa
 * per misi; siswa mengedit catatan yang sama sampai level ditutup guru.
 *
 * Migration ini ADITIF (tabel baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reflections', function (Blueprint $table) {
            $table->id();
            $table->integer('team_id');
            $table->integer('mission_id');
            $table->integer('student_id');
            $table->integer('role_id')->nullable();
            $table->string('type', 20);
            $table->text('body');
            $table->timestamps();

            $table->unique(['student_id', 'mission_id', 'type'], 'reflections_student_mission_type_unique');
            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
            $table->foreign('student_id')->references('student_id')->on('students')->cascadeOnDelete();
            $table->foreign('role_id')->references('role_id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reflections');
    }
};
