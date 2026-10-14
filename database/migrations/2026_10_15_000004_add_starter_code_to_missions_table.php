<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode awal (starter code) tiap misi.
 *
 * Editor siswa diisi missions.starter_code saat pertama dibuka, sehingga
 * siswa tidak mulai dari halaman kosong. Untuk misi sumatif, starter_code
 * memuat penanda baris "# === FUNGSI: nama_fungsi ===" yang dipakai
 * SubmissionComposer untuk memecah kode per fungsi.
 *
 * Nullable: misi lama (dan misi tanpa kode awal) tetap sah dan editor
 * dimulai kosong.
 *
 * Migration ini ADITIF — tidak mengubah kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->text('starter_code')->nullable()->after('client_brief');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('starter_code');
        });
    }
};
