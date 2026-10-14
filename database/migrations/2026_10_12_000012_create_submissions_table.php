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
        Schema::create('submissions', function (Blueprint $table) {
            $table->integer('submission_id')->autoIncrement();
            $table->integer('team_id');
            $table->integer('mission_id');
            $table->integer('submitted_by');
            $table->text('code_text');
            $table->dateTime('submitted_at')->useCurrent();
            $table->boolean('all_passed')->default(false);

            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
            $table->foreign('submitted_by')->references('student_id')->on('students');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
