<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_books', function (Blueprint $table) {
            $table->decimal('ecl_value_discounted', 65, 2)->nullable()->after('ecl_value');
            $table->decimal('ecl_discounting_effect', 65, 2)->nullable()->after('ecl_value_discounted');
            $table->decimal('ecl_discount_rate', 18, 12)->nullable()->after('ecl_discounting_effect');
            $table->string('ecl_discount_rate_source', 50)->nullable()->after('ecl_discount_rate');
            $table->string('ecl_discount_status', 50)->default('NOT_CALCULATED')->after('ecl_discount_rate_source');
            $table->decimal('ecl_discount_horizon_years', 12, 8)->nullable()->after('ecl_discount_status');
            $table->uuid('ecl_calculation_run_id')->nullable()->after('ecl_discount_horizon_years');
            $table->timestamp('ecl_calculated_at')->nullable()->after('ecl_calculation_run_id');

            $table->index(['ecl_calculation_run_id', 'ecl_discount_status'], 'lb_ecl_discount_run_status_idx');
        });

        Schema::table('expected_credit_loss', function (Blueprint $table) {
            $table->decimal('total_ecl_discounted', 65, 2)->nullable()->after('total_ecl');
            $table->decimal('total_discounting_effect', 65, 2)->nullable()->after('total_ecl_discounted');
            $table->string('discount_status', 50)->default('NOT_REQUESTED')->after('total_discounting_effect');
            $table->unsignedInteger('discount_unresolved_loans')->default(0)->after('discount_status');
            $table->uuid('ecl_calculation_run_id')->nullable()->after('discount_unresolved_loans');
        });
    }

    public function down(): void
    {
        Schema::table('expected_credit_loss', function (Blueprint $table) {
            $table->dropColumn([
                'total_ecl_discounted', 'total_discounting_effect', 'discount_status',
                'discount_unresolved_loans', 'ecl_calculation_run_id',
            ]);
        });

        Schema::table('loan_books', function (Blueprint $table) {
            $table->dropIndex('lb_ecl_discount_run_status_idx');
            $table->dropColumn([
                'ecl_value_discounted', 'ecl_discounting_effect', 'ecl_discount_rate',
                'ecl_discount_rate_source', 'ecl_discount_status',
                'ecl_discount_horizon_years', 'ecl_calculation_run_id', 'ecl_calculated_at',
            ]);
        });
    }
};
