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
        Schema::create('transition_matrix_cummulative_data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('cummulative_id')->comment('Link to cumulative header');
            $table->foreign('cummulative_id')->references('id')->on('transition_matrix_cummulative')->onDelete('cascade');
            $table->string('start_stage');
            $table->string('end_stage');
            $table->string('stage_transitions');
            $table->decimal('start_total_cummulated', 18, 2);
            $table->decimal('transition_balance_cummulated', 18, 2);
            $table->decimal('transition_probability_cummulated', 8, 4);
            $table->string('status')->default('draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_matrix_cummulative_data');
    }
};
