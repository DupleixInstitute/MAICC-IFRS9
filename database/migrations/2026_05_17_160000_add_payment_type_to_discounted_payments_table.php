<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * discountedPaymentsIndex() filters on payment_type
     * (->where('payment_type', ...)), but the column did not exist.
     */
    public function up(): void
    {
        Schema::table('discounted_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('discounted_payments', 'payment_type')) {
                $table->string('payment_type')->nullable()->after('discount_rate_source')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('discounted_payments', function (Blueprint $table) {
            if (Schema::hasColumn('discounted_payments', 'payment_type')) {
                $table->dropIndex(['payment_type']);
                $table->dropColumn('payment_type');
            }
        });
    }
};
