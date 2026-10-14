<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bug_cards', function (Blueprint $table) {
            $table->integer('bug_id')->autoIncrement();
            $table->integer('level_id');
            $table->string('bug_title', 100);
            $table->text('buggy_code');
            $table->text('hint_text')->nullable();
            $table->text('fix_explanation');
            $table->integer('points')->default(10);

            $table->foreign('level_id')->references('level_id')->on('levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_cards');
    }
};
