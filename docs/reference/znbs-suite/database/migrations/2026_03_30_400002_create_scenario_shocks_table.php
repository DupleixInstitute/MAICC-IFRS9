<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_shocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->unsignedBigInteger('macro_variable_id')->nullable();
            $table->string('shock_label', 120);          // human label e.g. "NPL ratio shock"
            $table->string('shock_target', 80);           // macro var code or internal key
            $table->string('shock_type', 20);            // pct_change | absolute | replace | multiplier
            $table->decimal('shock_value', 12, 4);        // e.g. 5.0 = +5pp, -15.0 = -15%
            $table->unsignedTinyInteger('year_offset')->default(1); // 1=year1, 2=year2, etc.
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->foreign('scenario_id')->references('id')->on('scenarios')->cascadeOnDelete();
            $table->foreign('macro_variable_id')->references('id')->on('macro_variables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_shocks');
    }
};
