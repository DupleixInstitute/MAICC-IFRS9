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
        Schema::table('loan_product_scoring_attributes', function (Blueprint $table) {
            $table->tinyInteger('is_ratio')->default(0);
            $table->tinyInteger('is_industry_analysis')->default(0);
            $table->tinyInteger('is_shareholder_analysis')->default(0);
            $table->tinyInteger('is_management_analysis')->default(0);
            $table->string('system_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_product_scoring_attributes', function (Blueprint $table) {
            //
        });
    }
};
