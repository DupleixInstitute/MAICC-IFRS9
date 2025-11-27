<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('reporting_year'); // CHANGED: date → integer
            $table->integer('reporting_month'); // CHANGED: date → integer
            $table->date('period'); // CHANGED: string → date
            $table->integer('lgd_id')->nullable();
            $table->string('lgd_calculation_source')->nullable(); 
            $table->time('lgd_calculation_time')->nullable();
            $table->integer('pd_id')->nullable();
            $table->string('pd_calculation_source')->nullable(); 
            $table->time('pd_calculation_time')->nullable();
            $table->boolean('ecl_calculated')->default(false);
            $table->time('ecl_calculation_time')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_periods');
    }
};