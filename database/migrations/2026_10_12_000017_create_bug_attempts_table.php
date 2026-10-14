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
        Schema::create('bug_attempts', function (Blueprint $table) {
            $table->integer('attempt_id')->autoIncrement();
            $table->integer('bug_id');
            $table->integer('team_id');
            $table->text('fixed_code')->nullable();
            $table->boolean('is_solved')->default(false);
            $table->integer('hints_used')->default(0);
            $table->dateTime('attempted_at')->useCurrent();

            $table->foreign('bug_id')->references('bug_id')->on('bug_cards');
            $table->foreign('team_id')->references('team_id')->on('teams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bug_attempts');
    }
};
