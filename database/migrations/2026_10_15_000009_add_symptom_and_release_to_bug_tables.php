<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kartu Bug: gejala yang dilihat siswa + siklus rilis & pengerjaannya.
 *
 * bug_cards.symptom_text — deskripsi GEJALA yang tampak oleh siswa (mis.
 *   "Output 50000 12, padahal seharusnya 12 lembar dan sisa 50.000").
 *   Ini yang boleh dibaca siswa; fix_explanation tetap rahasia sampai lulus.
 *
 * bug_attempts tambahan:
 *   released_by     — guru yang merilis kartu ini ke kelompok.
 *   released_at     — kapan dirilis (kartu baru muncul setelah ini).
 *   solved_at       — kapan kelompok berhasil memperbaiki.
 *   grading_status  — belum | menunggu | dinilai | galat | waktu_habis
 *                     (paralel dengan submissions.status).
 *   error_message   — pesan galat runner saat penilaian bug gagal.
 *
 * BACKFILL: baris lama dianggap 'belum' dinilai dan belum dirilis lewat
 * alur baru, sehingga released_at/released_by dibiarkan NULL.
 *
 * Migration ini ADITIF — tidak menghapus atau mengubah kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bug_cards', function (Blueprint $table) {
            $table->text('symptom_text')->nullable()->after('bug_title');
        });

        Schema::table('bug_attempts', function (Blueprint $table) {
            $table->foreignId('released_by')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable()->after('released_by');
            $table->dateTime('solved_at')->nullable()->after('is_solved');
            $table->string('grading_status', 20)->default('belum')->after('solved_at');
            $table->text('error_message')->nullable()->after('grading_status');
        });

        // Baris lama yang sudah terpecahkan: isi solved_at dari attempted_at
        // agar riwayat tidak kehilangan jejak waktu penyelesaian.
        DB::table('bug_attempts')
            ->where('is_solved', true)
            ->whereNull('solved_at')
            ->update(['solved_at' => DB::raw('attempted_at')]);
    }

    public function down(): void
    {
        Schema::table('bug_attempts', function (Blueprint $table) {
            $table->dropForeign(['released_by']);
            $table->dropColumn(['released_by', 'released_at', 'solved_at', 'grading_status', 'error_message']);
        });

        Schema::table('bug_cards', function (Blueprint $table) {
            $table->dropColumn('symptom_text');
        });
    }
};
