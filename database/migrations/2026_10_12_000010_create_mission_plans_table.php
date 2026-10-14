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
        Schema::create('mission_plans', function (Blueprint $table) {
            $table->integer('plan_id')->autoIncrement();
            $table->integer('team_id');
            $table->integer('mission_id');
            $table->dateTime('planned_at')->useCurrent();

            $table->foreign('team_id')->references('team_id')->on('teams');
            $table->foreign('mission_id')->references('mission_id')->on('missions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_plans');
    }
};
