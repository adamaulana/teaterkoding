<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom isi kartu pada concept_cards.
 *
 * Skema lama hanya menyimpan concept_name. Kolom isi ini dibutuhkan agar
 * kartu konsep bisa ditampilkan utuh (dan nanti dibaca sisi siswa).
 *
 * SEMUA kolom NULLABLE karena tabel sudah berisi 5 baris (hasil
 * ConceptCardsSeeder) — menambah kolom NOT NULL tanpa default akan gagal
 * pada baris yang sudah ada. Dipakai tipe `text` (bukan string) karena
 * syntax_example dapat berisi banyak baris kode.
 *
 * Migration ini ADITIF — tidak mengubah atau menghapus kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concept_cards', function (Blueprint $table) {
            $table->text('function_desc')->nullable()->after('concept_name');
            $table->text('syntax_example')->nullable()->after('function_desc');
            $table->text('when_to_use')->nullable()->after('syntax_example');
            $table->text('common_pitfalls')->nullable()->after('when_to_use');
            $table->text('trigger_question')->nullable()->after('common_pitfalls');
        });
    }

    public function down(): void
    {
        Schema::table('concept_cards', function (Blueprint $table) {
            $table->dropColumn([
                'function_desc',
                'syntax_example',
                'when_to_use',
                'common_pitfalls',
                'trigger_question',
            ]);
        });
    }
};
