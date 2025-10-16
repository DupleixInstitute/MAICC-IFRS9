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
        Schema::table('loan_product_scoring_attribute_option_values', function (Blueprint $table) {
            $table->string('lower_value')->nullable();
            $table->string('upper_value')->nullable();
            $table->string('median_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_product_scoring_attribute_option_values', function (Blueprint $table) {
            $table->dropColumn([
                'lower_value',
                'upper_value',
                'median_value'
            ]);
        });
    }
};
