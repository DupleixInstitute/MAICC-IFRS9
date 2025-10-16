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
        Schema::create('reporting_periods', function (Blueprint $table) {
            $table->id();
            $table->date('reporting_year');
            $table->date('reporting_month');
            $table->string('period'); 
            $table->integer('lgd_id')->nullable();
            $table->string('lgd_calculation_source')->nullable(); 
            $table->time('lgd_calculation_time')->nullable();
            $table->integer('pd_id')->nullable();
            $table->string('pd_calculation_source')->nullable(); 
            $table->time('pd_calculation_time')->nullable();
            $table->boolean('ecl_calculated')->default(false)
                   ->comment('Indicates if ECL has been calculated for this reporting period');
            $table->time('ecl_calculation_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_periods');
    }
};
