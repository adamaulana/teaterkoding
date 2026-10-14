<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana misi: penguncian rencana + urutan langkah + pencatat pembuka.
 *
 * Kolom baru:
 *   locked_at   — kapan Analyst menekan "Kunci Rencana". Selama NULL, rencana
 *                 masih bisa diubah dan ConceptUsageAnalyzer menganggap tim
 *                 BELUM merencanakan.
 *   steps_note  — catatan urutan langkah (maks 1000 karakter, divalidasi form).
 *   unlocked_by — siswa yang memasukkan kode unlock (jejak audit).
 *
 * Unique (team_id, mission_id) mencegah rencana ganda untuk tim+misi yang sama.
 *
 * PENGAMAN: sebelum menambah unique index, migration ini MEMERIKSA baris
 * ganda. Bila ditemukan, migration DIHENTIKAN dengan laporan — baris tidak
 * dihapus diam-diam. Guru/admin harus memutuskan mana yang dipertahankan.
 *
 * BACKFILL: locked_at diisi dari planned_at untuk rencana lama yang SUDAH
 * punya kartu konsep (dianggap sudah final), supaya perilaku
 * ConceptUsageAnalyzer tidak berubah setelah migration ini.
 *
 * Migration ini ADITIF — tidak menghapus atau mengubah kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Periksa duplikat SEBELUM menambah unique index.
        $duplikat = DB::table('mission_plans')
            ->select('team_id', 'mission_id', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('team_id', 'mission_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplikat->isNotEmpty()) {
            $rincian = $duplikat
                ->map(fn ($baris) => "  team_id={$baris->team_id}, mission_id={$baris->mission_id} ({$baris->jumlah} baris)")
                ->implode(PHP_EOL);

            throw new RuntimeException(
                'Ditemukan rencana misi GANDA pada (team_id, mission_id). '
                .'Unique index tidak dapat dibuat dan tidak ada baris yang dihapus.'.PHP_EOL
                .'Periksa tabel mission_plans lalu tentukan baris mana yang dipertahankan:'.PHP_EOL
                .$rincian,
            );
        }

        Schema::table('mission_plans', function (Blueprint $table) {
            $table->dateTime('locked_at')->nullable()->after('planned_at');
            $table->text('steps_note')->nullable()->after('locked_at');
            $table->integer('unlocked_by')->nullable()->after('steps_note');
        });

        // 2. Backfill locked_at untuk rencana lama yang sudah punya kartu konsep.
        DB::statement(
            'UPDATE mission_plans SET locked_at = planned_at '
            .'WHERE locked_at IS NULL AND plan_id IN (SELECT DISTINCT plan_id FROM mission_plan_concepts)',
        );

        // 3. Unique + foreign key (dipisah agar SQLite/MySQL sama-sama aman).
        Schema::table('mission_plans', function (Blueprint $table) {
            $table->unique(['team_id', 'mission_id'], 'mission_plans_team_mission_unique');
            $table->foreign('unlocked_by')->references('student_id')->on('students')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mission_plans', function (Blueprint $table) {
            $table->dropForeign(['unlocked_by']);
            $table->dropUnique('mission_plans_team_mission_unique');
            $table->dropColumn(['locked_at', 'steps_note', 'unlocked_by']);
        });
    }
};
