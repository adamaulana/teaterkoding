<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Status penilaian submission + metadata percobaan.
 *
 * Kolom baru:
 *   status        — menunggu | dinilai | galat | waktu_habis.
 *                   Submission baru berstatus 'menunggu' sampai job penilaian
 *                   (GradeSubmissionJob) selesai.
 *   attempt_no    — percobaan ke berapa untuk (team_id, mission_id). Dipakai
 *                   untuk aturan "first try bonus".
 *   graded_at     — kapan penilaian selesai.
 *   error_message — pesan galat dari runner (dipotong 1500 karakter).
 *   runtime_ms    — durasi eksekusi di sandbox (ms).
 *
 * BACKFILL baris lama:
 *   - status     = 'dinilai' (semua submission lama sudah punya hasil kasus uji
 *                  dan dianggap selesai dinilai, agar tidak "menggantung"
 *                  sebagai 'menunggu' selamanya).
 *   - attempt_no = nomor urut 1..n per (team_id, mission_id) menurut
 *                  submitted_at, submission_id.
 *
 * Migration ini ADITIF — tidak menghapus atau mengubah kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('status', 20)->default('menunggu')->after('all_passed');
            $table->unsignedSmallInteger('attempt_no')->default(1)->after('status');
            $table->dateTime('graded_at')->nullable()->after('attempt_no');
            $table->text('error_message')->nullable()->after('graded_at');
            $table->unsignedInteger('runtime_ms')->nullable()->after('error_message');
        });

        // 1. Semua baris lama dianggap sudah dinilai.
        DB::table('submissions')->update([
            'status' => 'dinilai',
            'graded_at' => DB::raw('submitted_at'),
        ]);

        // 2. Nomor percobaan berurutan per (team_id, mission_id).
        //    Diambil lewat query PHP (bukan window function) supaya kompatibel
        //    dengan MySQL 5.7 dan SQLite yang dipakai pengujian.
        $submission = DB::table('submissions')
            ->select('submission_id', 'team_id', 'mission_id')
            ->orderBy('team_id')
            ->orderBy('mission_id')
            ->orderBy('submitted_at')
            ->orderBy('submission_id')
            ->get();

        $nomor = [];

        foreach ($submission as $baris) {
            $kunci = $baris->team_id.'|'.$baris->mission_id;
            $nomor[$kunci] = ($nomor[$kunci] ?? 0) + 1;

            DB::table('submissions')
                ->where('submission_id', $baris->submission_id)
                ->update(['attempt_no' => $nomor[$kunci]]);
        }
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['status', 'attempt_no', 'graded_at', 'error_message', 'runtime_ms']);
        });
    }
};
