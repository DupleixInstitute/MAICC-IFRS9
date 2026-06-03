<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_calculation_logs', function (Blueprint $table) {
            $table->id();

            $table->date('start_period');
            $table->date('end_period');

            $table->unsignedBigInteger('portfolio_group')->nullable();

            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_seconds')->nullable();

            $table->integer('total_contracts_processed')->default(0);
            $table->integer('total_records_generated')->default(0);
            $table->decimal('total_payments_detected', 20, 2)->default(0);
            $table->integer('total_cured_contracts')->default(0);
            $table->decimal('total_defaulted_amount', 20, 2)->default(0);

            $table->string('status')->default('pending')->index();

            // Referenced by scopeTriggeredBySource(), getTriggerSourceBadge()
            // and createRecalculation(). Note: 'trigger_source' is NOT in the
            // model's $fillable, so the default below is what persists unless
            // it is set explicitly outside mass-assignment.
            $table->string('trigger_source')->default('manual');

            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->unsignedBigInteger('parent_calculation_id')->nullable();
            $table->text('recalculation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('portfolio_group');
            $table->index('parent_calculation_id');
            $table->index(['start_period', 'end_period']);

            $table->foreign('portfolio_group')
                ->references('id')->on('loan_portfolios')
                ->nullOnDelete();

            $table->foreign('triggered_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->foreign('parent_calculation_id')
                ->references('id')->on('lgd_calculation_logs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgd_calculation_logs');
    }
};
