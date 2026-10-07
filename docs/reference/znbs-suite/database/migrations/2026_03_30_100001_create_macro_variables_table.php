<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macro_variables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();          // e.g. GDP_GROWTH, CPI, ZMW_USD
            $table->string('name', 255);                   // Display name
            $table->string('category', 80);                // e.g. Growth, Inflation, FX, Rates, Credit, Commodity
            $table->string('unit', 60)->default('%');       // %, ZMW/USD, USD/bbl, index
            $table->string('frequency', 20)->default('quarterly'); // monthly, quarterly, annual
            $table->string('source', 120)->nullable();     // BOZ, ZNBS, World Bank, IMF
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            // Stress sensitivity metadata
            $table->string('shock_direction', 10)->default('both'); // up, down, both
            $table->decimal('default_mild_shock', 10, 4)->nullable();
            $table->decimal('default_severe_shock', 10, 4)->nullable();
            $table->string('shock_unit', 20)->default('pp'); // pp (percentage points), pct (% change), abs
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macro_variables');
    }
};
