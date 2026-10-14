<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci idempotensi pemberian poin (award_key).
 *
 * ScoringService dapat dipanggil ulang (mis. job di-retry, atau guru menilai
 * ulang). Tanpa penanda, poin yang sama bisa diberikan berkali-kali.
 *
 * award_key unik merangkum "pemberian poin" tertentu, contoh:
 *   mr:{team}:{mission}:{submission}    → hasil misi tim
 *   mri:{student}:{mission}:{submission}→ hasil misi individu (sumatif)
 *   first:{team}:{mission}              → bonus percobaan pertama
 *   plan:{team}:{mission}               → bonus rencana
 *   bug:{team}:{bug}                    → bonus kartu bug
 *   hint:{team}:{bug}                   → penalti petunjuk
 *
 * NULL diizinkan untuk baris lama (dan baris yang tidak butuh idempotensi);
 * unique index MySQL mengizinkan banyak NULL sehingga backfill tidak wajib.
 *
 * Migration ini ADITIF — tidak menghapus atau mengubah kolom lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_entries', function (Blueprint $table) {
            $table->string('award_key', 100)->nullable()->unique()->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('score_entries', function (Blueprint $table) {
            $table->dropUnique(['award_key']);
            $table->dropColumn('award_key');
        });
    }
};
