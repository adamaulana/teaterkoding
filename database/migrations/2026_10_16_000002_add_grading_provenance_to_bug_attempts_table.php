<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak asal-usul penilaian Kartu Bug (Fase 6 — Pyodide di browser).
 *
 * Alasan & aturan sama dengan migration submissions:
 *   - percobaan bug pada level sumatif juga harus DIVERIFIKASI guru,
 *   - nonce sekali pakai mencegah laporan dipalsukan/diulang,
 *   - selisih klaim perangkat vs hasil guru perlu terekam.
 *
 * CATATAN: kolom client_runtime_ms & pyodide_version sengaja TIDAK dibuat di
 * sini karena percobaan bug tidak menyimpan durasi/versi sendiri; bila nanti
 * dibutuhkan, tambahkan lewat migration baru (jangan ubah yang ini).
 *
 * STATUS BARU: grading_status = 'menunggu_verifikasi' (varchar(20) sudah cukup).
 *
 * BACKFILL: baris lama ber-grading_status 'dinilai' diberi grading_source
 * 'server', sesuai kondisi sebelum Fase 6.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bug_attempts', function (Blueprint $table) {
            $table->string('grading_source', 20)->nullable()->after('grading_status');
            $table->string('grading_nonce', 64)->nullable()->after('grading_source');
            $table->dateTime('nonce_expires_at')->nullable()->after('grading_nonce');
            $table->json('client_report')->nullable()->after('nonce_expires_at');
            $table->dateTime('verified_at')->nullable()->after('client_report');
            $table->foreignId('verified_by')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $table->boolean('has_mismatch')->default(false)->after('verified_by');
            $table->json('mismatch_detail')->nullable()->after('has_mismatch');

            $table->index('grading_nonce');
            $table->index(['grading_status', 'grading_source']);
        });

        DB::table('bug_attempts')
            ->where('grading_status', 'dinilai')
            ->update(['grading_source' => 'server']);
    }

    public function down(): void
    {
        Schema::table('bug_attempts', function (Blueprint $table) {
            $table->dropIndex(['grading_status', 'grading_source']);
            $table->dropIndex(['grading_nonce']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'grading_source',
                'grading_nonce',
                'nonce_expires_at',
                'client_report',
                'verified_at',
                'verified_by',
                'has_mismatch',
                'mismatch_detail',
            ]);
        });
    }
};
