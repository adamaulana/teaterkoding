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
        Schema::create('function_ownership', function (Blueprint $table) {
            $table->integer('ownership_id')->autoIncrement();
            $table->integer('mission_id');
            $table->string('function_name', 50);
            $table->integer('team_id');
            $table->integer('student_id');

            $table->foreign('mission_id')->references('mission_id')->on('missions');
            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('student_id')->references('student_id')->on('students');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_ownership');
    }
};
