<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema is derived from App\Models\DiscountedPayment ($fillable + $casts)
     * and the upsertDiscountPayment() key (contract_id, reporting_period,
     * payment_period). The composite index is intentionally NON-unique:
     * CalculateDiscountingJob bulk-inserts via DB::table()->insert() (no
     * upsert), so a unique constraint would break chunked/re-runs; the
     * model's updateOrCreate() still works without a DB-level unique.
     */
    public function up(): void
    {
        Schema::create('discounted_payments', function (Blueprint $table) {
            $table->id();

            $table->string('contract_id')->index();
            $table->unsignedBigInteger('lgd_id');

            $table->date('reporting_period');
            $table->date('payment_period');

            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->integer('discounting_days')->default(0);
            $table->string('discount_rate_source')->nullable();

            $table->decimal('payment_amount', 18, 2)->default(0.00);
            $table->decimal('discounted_amount', 18, 2)->default(0.00);
            $table->decimal('discounted_loss', 18, 2)->default(0.00);

            $table->timestamps();

            $table->index('lgd_id');
            $table->index('discount_rate_source');
            $table->index(['contract_id', 'reporting_period', 'payment_period'], 'idx_dp_contract_periods');

            $table->foreign('lgd_id')
                ->references('id')->on('loss_given_default')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounted_payments');
    }
};
