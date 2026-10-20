<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak asal-usul penilaian submission (Fase 6 — Pyodide di browser).
 *
 * KENAPA DIPERLUKAN
 * Kode siswa kini dijalankan di BROWSER siswa (tidak dipercaya penuh), dan
 * Laravel tetap yang membandingkan dengan expected_value. Akibatnya satu
 * submission bisa punya dua jenis hasil:
 *   - hasil perangkat siswa (sementara), dan
 *   - hasil verifikasi guru (resmi, khusus level sumatif).
 * Tanpa kolom di bawah ini, guru tidak bisa membedakan keduanya, tidak bisa
 * menelusuri selisih, dan nonce sekali pakai tidak punya tempat penyimpanan.
 *
 * KOLOM BARU (semua NULLABLE / berdefault agar baris lama tetap sah)
 *   grading_source    klien | terverifikasi | server
 *   grading_nonce     nonce sekali pakai untuk mengirim laporan dari browser
 *   nonce_expires_at  batas waktu nonce (kedaluwarsa -> minta "Nilai Ulang")
 *   client_report     laporan mentah perangkat siswa (JSON)
 *   client_runtime_ms durasi eksekusi di perangkat siswa
 *   pyodide_version   versi Pyodide yang dipakai browser
 *   verified_at       kapan guru memverifikasi
 *   verified_by       guru/admin yang memverifikasi
 *   has_mismatch      true bila hasil guru berbeda dari klaim perangkat
 *   mismatch_detail   rincian kasus uji yang berbeda (JSON)
 *
 * STATUS BARU: 'menunggu_verifikasi'. Kolom status sudah varchar(20), jadi
 * TIDAK perlu diubah — nilai baru hanya kesepakatan aplikasi.
 *
 * BACKFILL: baris lama berstatus 'dinilai' dianggap hasil server (dulu semua
 * penilaian dilakukan server), sehingga laporan/riwayat tetap konsisten.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('grading_source', 20)->nullable()->after('status');
            $table->string('grading_nonce', 64)->nullable()->after('grading_source');
            $table->dateTime('nonce_expires_at')->nullable()->after('grading_nonce');
            $table->json('client_report')->nullable()->after('nonce_expires_at');
            $table->unsignedInteger('client_runtime_ms')->nullable()->after('client_report');
            $table->string('pyodide_version', 20)->nullable()->after('client_runtime_ms');
            $table->dateTime('verified_at')->nullable()->after('pyodide_version');
            $table->foreignId('verified_by')->nullable()->after('verified_at')
                ->constrained('users')->nullOnDelete();
            $table->boolean('has_mismatch')->default(false)->after('verified_by');
            $table->json('mismatch_detail')->nullable()->after('has_mismatch');

            // Pencarian nonce & daftar tunggu verifikasi harus cepat.
            $table->index('grading_nonce');
            $table->index(['status', 'grading_source']);
        });

        // BACKFILL: penilaian lama seluruhnya berasal dari server.
        DB::table('submissions')
            ->where('status', 'dinilai')
            ->update(['grading_source' => 'server']);
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'grading_source']);
            $table->dropIndex(['grading_nonce']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'grading_source',
                'grading_nonce',
                'nonce_expires_at',
                'client_report',
                'client_runtime_ms',
                'pyodide_version',
                'verified_at',
                'verified_by',
                'has_mismatch',
                'mismatch_detail',
            ]);
        });
    }
};
