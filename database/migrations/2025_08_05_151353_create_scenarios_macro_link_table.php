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
        Schema::create('scenarios_macro_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('scenarios');
            $table->foreignId('macro_value_id')->constrained('macro_statistics_data');
            $table->decimal('adjustment_factor', 10, 4)->nullable(); // Optional adjustment
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenarios_macro_link');
    }
};
