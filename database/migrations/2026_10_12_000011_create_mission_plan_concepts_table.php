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
        Schema::create('mission_plan_concepts', function (Blueprint $table) {
            $table->integer('plan_id');
            $table->integer('concept_id');

            $table->primary(['plan_id', 'concept_id']);
            $table->foreign('plan_id')->references('plan_id')->on('mission_plans');
            $table->foreign('concept_id')->references('concept_id')->on('concept_cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mission_plan_concepts');
    }
};
