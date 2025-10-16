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
        Schema::table('loan_product_scoring_attributes', function (Blueprint $table) {
            $table->string('accept_condition')->comment("can be option,range,greater_than_or_equal_to,less_than_or_equal_to,equals")->nullable();
            $table->string('option_type')->nullable();
            $table->string('median_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_product_scoring_attributes', function (Blueprint $table) {
            $table->dropColumn(['accept_condition','option_type','median_value']);
        });
    }
};
