<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgd_payment_tracking_long', function (Blueprint $table) {
            $table->id();

            $table->string('contract_id')->index();
            $table->unsignedBigInteger('portfolio_group')->nullable();

            $table->date('reporting_period');
            $table->date('cohort_period')->nullable();
            $table->date('payment_period')->nullable();

            $table->decimal('starting_balance', 20, 2)->default(0);
            $table->decimal('ending_balance', 20, 2)->default(0);
            $table->decimal('payment_amount', 20, 2)->default(0);
            $table->decimal('cumulative_payments', 20, 2)->default(0);

            $table->string('payment_type')->default('none');
            $table->string('ifrs9_stage')->nullable();
            $table->integer('months_since_default')->default(0);
            $table->boolean('is_cured')->default(false);
            $table->string('cure_stage')->nullable();

            $table->unsignedBigInteger('calculation_id');

            $table->timestamps();

            $table->index('portfolio_group');
            $table->index('calculation_id');
            $table->index(['contract_id', 'reporting_period']);
            $table->index('cohort_period');
            $table->index('ifrs9_stage');

            $table->foreign('portfolio_group')
                ->references('id')->on('loan_portfolios')
                ->nullOnDelete();

            $table->foreign('calculation_id')
                ->references('id')->on('lgd_calculation_logs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgd_payment_tracking_long');
    }
};
