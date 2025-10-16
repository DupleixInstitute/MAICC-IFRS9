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
        Schema::table('balance_sheets', function (Blueprint $table) {
            $table->decimal('total_current_assets', 65)->nullable();
            $table->decimal('total_current_liabilities', 65)->nullable();
            $table->decimal('total_long_term_liabilities', 65)->nullable();
            $table->decimal('total_other_current_assets', 65)->nullable();
            $table->decimal('total_other_assets', 65)->nullable();
            $table->decimal('total_fixed_assets', 65)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balance_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'total_current_assets',
                'total_current_liabilities',
                'total_long_term_liabilities',
                'total_other_current_assets',
                'total_other_assets',
                'total_fixed_assets'
            ]);
        });
    }
};
