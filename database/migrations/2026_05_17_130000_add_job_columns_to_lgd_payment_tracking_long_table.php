<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns written by App\Jobs\ProcessLGDPayments (queued path) that are
     * not produced by the synchronous LGDCalculationController path.
     */
    public function up(): void
    {
        Schema::table('lgd_payment_tracking_long', function (Blueprint $table) {
            $table->decimal('disbursement_amount', 20, 2)->default(0)->after('payment_amount');
            $table->boolean('is_missing')->default(false)->after('cure_stage');
            $table->boolean('has_gap')->default(false)->after('is_missing');
            $table->integer('gap_months')->default(0)->after('has_gap');
        });
    }

    public function down(): void
    {
        Schema::table('lgd_payment_tracking_long', function (Blueprint $table) {
            $table->dropColumn(['disbursement_amount', 'is_missing', 'has_gap', 'gap_months']);
        });
    }
};
