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
        Schema::table('scoring_attribute_groups', function (Blueprint $table) {
            $table->string('system_name')->nullable();
            $table->tinyInteger('is_system')->default(0);
            $table->tinyInteger('is_ratio')->default(0);
            $table->tinyInteger('is_corporate')->default(0);
            $table->tinyInteger('is_industry_analysis')->default(0);
            $table->tinyInteger('is_shareholder_analysis')->default(0);
            $table->tinyInteger('is_management_analysis')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scoring_attribute_groups', function (Blueprint $table) {
            $table->dropColumn([
                'system_name',
                'is_system',
                'is_ratio',
                'is_corporate'
            ]);
        });
    }
};
