<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai manual guru (refleksi/rubrik & observasi) per siswa per misi sumatif.
 *
 * Belum ada tempat menyimpan penilaian manual. Kolom nullable karena guru
 * boleh mengisi salah satu dulu; selama ada komponen yang kosong, baris
 * rekap ditandai "Sementara".
 *
 * Unique (student_id, mission_id) mencegah baris ganda untuk siswa+misi sama.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus tabel lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_scores', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id');
            $table->integer('mission_id');
            $table->unsignedTinyInteger('reflection_score')->nullable();
            $table->unsignedTinyInteger('observation_score')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'mission_id']);
            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_scores');
    }
};
