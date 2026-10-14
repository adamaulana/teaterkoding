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
        Schema::create('score_entries', function (Blueprint $table) {
            $table->integer('score_id')->autoIncrement();
            $table->integer('team_id');
            $table->integer('student_id')->nullable();
            $table->integer('mission_id')->nullable();
            $table->integer('bug_id')->nullable();
            $table->enum('source_type', [
                'mission_result',
                'plan_bonus',
                'bug_bonus',
                'hint_penalty',
                'first_try_bonus',
            ]);
            $table->integer('points');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('student_id')->references('student_id')->on('students');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
            $table->foreign('bug_id')->references('bug_id')->on('bug_cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_entries');
    }
};
