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
        Schema::create('scenario_probabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
            $table->string('scenario_name');
            $table->decimal('probability', 5, 2); // 0.00 to 100.00
            $table->integer('order_position')->default(0);
            $table->timestamps();
            
            $table->index('scenario_set_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_probabilities');
    }
};
