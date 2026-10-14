<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda aktif/nonaktif akun pengguna.
 *
 * Menu Pengguna sudah bisa membuat akun, mengubah peran, dan mereset kata
 * sandi, tetapi belum ada cara menonaktifkan akun. Kolom ini menampungnya,
 * dan login akun nonaktif diblokir di FortifyServiceProvider.
 *
 * Default true agar seluruh akun yang sudah ada tetap bisa login.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
