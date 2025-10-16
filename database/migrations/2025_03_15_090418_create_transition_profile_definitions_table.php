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
        Schema::create('transition_profile_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('profile_code', 15)->unique();
            $table->string('short_name', 255);
            $table->text('description'); // Change from string to text
            $table->string('start_table', 255);
            $table->string('end_table', 255);
            $table->string('start_grading_col', 255);
            $table->string('end_grading_col', 255);
            $table->string('start_value_type', 50);
            $table->string('end_value_type', 50);
            $table->string('start_client_id_col', 255);
            $table->string('end_client_id_col', 255);
            $table->string('aggregation_criteria', 50);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP')); // Matches SQL
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_profile_definitions');
    }
};
