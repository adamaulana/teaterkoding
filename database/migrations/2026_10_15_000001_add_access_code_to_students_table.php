<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode akses siswa (login tanpa akun & tanpa kata sandi).
 *
 * Sisi siswa tidak memakai email/password: siswa masuk dengan kode akses
 * 8 karakter yang dibagikan guru (lihat App\Services\AccessCodeService).
 *
 * Kolom:
 *   access_code              — 8 karakter tanpa tanda hubung, unik. Panjang
 *                              12 memberi ruang bila format berubah nanti.
 *   access_code_generated_at — kapan kode dibuat/dibuat ulang guru.
 *   last_login_at            — jejak login terakhir (indikator "pernah main").
 *
 * Migration ini ADITIF dan TIDAK mengubah kolom lama. Siswa yang sudah ada
 * dibiarkan access_code NULL (guru membuatkan kode lewat halaman Siswa);
 * NULL aman karena kolomnya nullable dan unique mengizinkan banyak NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('access_code', 12)->nullable()->unique()->after('student_no');
            $table->dateTime('access_code_generated_at')->nullable()->after('access_code');
            $table->dateTime('last_login_at')->nullable()->after('access_code_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['access_code']);
            $table->dropColumn(['access_code', 'access_code_generated_at', 'last_login_at']);
        });
    }
};
