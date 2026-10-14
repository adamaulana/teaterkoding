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
        Schema::create('submission_results', function (Blueprint $table) {
            $table->integer('result_id')->autoIncrement();
            $table->integer('submission_id');
            $table->integer('test_case_id');
            $table->boolean('is_passed');
            $table->string('actual_value', 100)->nullable();

            $table->foreign('submission_id')->references('submission_id')->on('submissions');
            $table->foreign('test_case_id')->references('test_case_id')->on('test_cases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_results');
    }
};
