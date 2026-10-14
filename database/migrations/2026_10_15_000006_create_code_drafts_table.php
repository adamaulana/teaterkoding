<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draf kode yang disimpan otomatis (autosave) per tim per misi.
 *
 * Satu baris = satu "berkas" kode milik tim:
 *   function_name = ''  → draf PROGRAM UTAMA (atau satu-satunya kode di L1-L3)
 *   function_name = 'x' → draf fungsi x (misi sumatif Level 4)
 *
 * PENTING: function_name sengaja NOT NULL dengan default '' dan BUKAN
 * nullable. Unique index MySQL mengizinkan banyak NULL, sehingga baris
 * "program utama" bisa tergandakan tanpa batas. Dengan '' sebagai penanda,
 * unique (team_id, mission_id, function_name) benar-benar menjamin satu draf
 * per berkas.
 *
 * Penguncian optimis (mencegah saling menimpa antar anggota):
 *   version          — dinaikkan setiap simpan. Klien mengirim versi terakhir
 *                      yang diketahui; bila versi DB lebih baru, simpan ditolak
 *                      dan siswa ditawari memuat versi terbaru / menimpa.
 *   updated_by       — siswa yang terakhir menyimpan.
 *   reviewed_version — versi yang sudah ditinjau Reviewer.
 *   reviewed_by      — siswa Reviewer yang menandai sudah ditinjau.
 *
 * Migration ini ADITIF (tabel baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_drafts', function (Blueprint $table) {
            $table->id();
            $table->integer('team_id');
            $table->integer('mission_id');
            $table->string('function_name', 50)->default('');
            $table->integer('owner_student_id')->nullable();
            // CATATAN: MySQL melarang DEFAULT pada kolom TEXT/LONGTEXT, jadi
            // code_text dibuat NOT NULL TANPA default. Aplikasi selalu mengirim
            // nilainya (CodeDraftService) sehingga tidak pernah NULL.
            $table->longText('code_text');
            $table->unsignedInteger('version')->default(1);
            $table->integer('updated_by')->nullable();
            $table->unsignedInteger('reviewed_version')->nullable();
            $table->integer('reviewed_by')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'mission_id', 'function_name'], 'code_drafts_team_mission_function_unique');
            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
            $table->foreign('owner_student_id')->references('student_id')->on('students')->nullOnDelete();
            $table->foreign('updated_by')->references('student_id')->on('students')->nullOnDelete();
            $table->foreign('reviewed_by')->references('student_id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_drafts');
    }
};
