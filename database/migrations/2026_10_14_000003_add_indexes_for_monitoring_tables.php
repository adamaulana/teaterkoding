<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index tambahan untuk pemantauan & penilaian.
 *
 * CATATAN PENTING: index single-column pada kolom foreign key
 * (score_entries.team_id, score_entries.student_id, submission_results.
 * submission_id) SUDAH dibuat otomatis oleh Laravel saat foreign key
 * dideklarasikan. Menambahkannya lagi hanya akan menduplikasi index yang
 * sudah ada, jadi tidak dilakukan.
 *
 * Yang benar-benar belum ada dan dipakai oleh query Fase 4:
 *   - submissions (team_id, mission_id, submitted_at)
 *     → "submission acuan" = submission terakhir tiap tim per misi,
 *       dan riwayat submission terurut waktu.
 *   - score_entries (team_id, source_type) & (student_id, source_type)
 *     → agregasi peringkat per jenis sumber skor (misi, bonus rencana,
 *       bonus bug, penalti petunjuk, bonus first try).
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus index lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->index(['team_id', 'mission_id', 'submitted_at'], 'submissions_team_mission_submitted_index');
        });

        Schema::table('score_entries', function (Blueprint $table) {
            $table->index(['team_id', 'source_type'], 'score_entries_team_source_index');
            $table->index(['student_id', 'source_type'], 'score_entries_student_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex('submissions_team_mission_submitted_index');
        });

        Schema::table('score_entries', function (Blueprint $table) {
            $table->dropIndex('score_entries_team_source_index');
            $table->dropIndex('score_entries_student_source_index');
        });
    }
};
