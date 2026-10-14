<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kasus uji untuk Kartu Bug (paralel dengan test_cases pada misi).
 *
 * Strukturnya SENGAJA dibuat identik dengan test_cases supaya:
 *   1. aturan validasinya bisa memakai ULANG App\Actions\ValidasiKasusUji
 *      (tidak ada duplikasi logika), dan
 *   2. runner sandbox nanti bisa memakai jalur kode yang sama.
 *
 * bug_attempt_results menyimpan hasil per kasus uji untuk tiap percobaan bug,
 * meniru submission_results.
 *
 * Toleransi default 0 (bukan nullable) karena di sini tidak ada baris lama
 * yang perlu kompatibilitas.
 *
 * Migration ini ADITIF (tabel baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_test_cases', function (Blueprint $table) {
            $table->id();
            $table->integer('bug_id');
            $table->string('label', 100);
            $table->string('setup_code', 200)->nullable();
            $table->string('check_target', 100);
            $table->string('expected_value', 100);
            $table->decimal('tolerance', 10, 2)->default(0);

            $table->foreign('bug_id')->references('bug_id')->on('bug_cards')->cascadeOnDelete();
        });

        Schema::create('bug_attempt_results', function (Blueprint $table) {
            $table->id();
            $table->integer('attempt_id');
            $table->unsignedBigInteger('bug_test_case_id');
            $table->boolean('is_passed');
            $table->string('actual_value', 100)->nullable();

            $table->foreign('attempt_id')->references('attempt_id')->on('bug_attempts')->cascadeOnDelete();
            $table->foreign('bug_test_case_id')->references('id')->on('bug_test_cases')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_attempt_results');
        Schema::dropIfExists('bug_test_cases');
    }
};
