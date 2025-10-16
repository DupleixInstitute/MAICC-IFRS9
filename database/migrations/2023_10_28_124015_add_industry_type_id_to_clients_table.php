<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('industry_type_id')->nullable();
            $table->unsignedBigInteger('registration_country_id')->nullable();
            $table->unsignedBigInteger('main_bank_id')->nullable();
            $table->unsignedBigInteger('second_bank_id')->nullable();
            $table->unsignedBigInteger('third_bank_id')->nullable();
            $table->string('registration_year')->nullable();
            $table->integer('registration_number')->nullable();
            $table->integer('years_in_business')->nullable();
            $table->unsignedBigInteger('legal_type_id')->nullable();
            $table->string('trading_name')->nullable();
            $table->string('audit_status')->nullable();
            $table->decimal('real_annual_inflation_rate')->nullable();
            $table->decimal('nominal_annual_inflation_rate')->nullable();
            $table->string('years_at_present_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'industry_type_id',
                'registration_country_id',
                'registration_year',
                'registration_number',
                'years_in_business',
                'legal_type_id',
                'trading_name',
                'main_bank_id',
                'second_bank_id',
                'third_bank_id',
                'audit_status',
                'real_annual_inflation_rate',
                'nominal_annual_inflation_rate',
                'years_at_present_address',
            ]);
        });
    }
};
