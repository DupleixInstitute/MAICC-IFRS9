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
        Schema::table('income_statements', function (Blueprint $table) {
            $table->string('reporting_month')->nullable();
            $table->string('months_in_year')->nullable();
            $table->string('audit_status')->nullable();
            $table->decimal('real_annual_inflation_rate')->nullable();
            $table->decimal('nominal_annual_inflation_rate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_statements', function (Blueprint $table) {
            $table->dropColumn([
                'reporting_month',
                'months_in_year',
                'audit_status',
                'real_annual_inflation_rate',
                'nominal_annual_inflation_rate'
            ]);
        });
    }
};
