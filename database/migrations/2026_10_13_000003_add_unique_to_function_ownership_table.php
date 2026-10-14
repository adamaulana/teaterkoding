<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jamin keunikan kepemilikan fungsi per misi + kelompok.
 *
 * Skema lama hanya punya foreign key, tanpa jaminan keunikan, padahal aturan
 * bisnisnya "satu fungsi tepat satu pemilik per kelompok per misi". Tanpa
 * index ini database akan diam-diam mengizinkan baris ganda yang merusak
 * penilaian pada tahap berikutnya.
 *
 * Aman dijalankan: tabel function_ownership masih kosong (0 baris), sehingga
 * tidak ada duplikat existing yang membuat index gagal dibuat.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('function_ownership', function (Blueprint $table) {
            $table->unique(['mission_id', 'team_id', 'function_name'], 'function_ownership_unique');
        });
    }

    public function down(): void
    {
        Schema::table('function_ownership', function (Blueprint $table) {
            $table->dropUnique('function_ownership_unique');
        });
    }
};
