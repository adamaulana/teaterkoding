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
        Schema::create('test_cases', function (Blueprint $table) {
            $table->integer('test_case_id')->autoIncrement();
            $table->integer('mission_id');
            $table->string('label', 100);
            $table->string('check_target', 100);
            $table->string('expected_value', 100);
            $table->decimal('tolerance', 10, 2)->nullable()->default(0);

            $table->foreign('mission_id')->references('mission_id')->on('missions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_cases');
    }
};
