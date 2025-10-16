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
        Schema::create('loss_given_default_cummulative', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('reporting_period')->index()->comment('Reporting period for the loss given default');
            $table->date('start_period')->index()->comment('Start date of the reporting period');
            $table->string('portfolio_group')->nullable()->index();
            $table->decimal('start_total_stage3', 18, 4)->comment('Total amount in stage 3');
            $table->decimal('end_total_stage3', 18, 4)->comment('Total amount in stage 3 at the end of the reporting period');
            $table->decimal('lgd_cummulative', 18, 4)->comment('Loss given default percentage');
            $table->decimal('lgd_cummulative_percent', 18, 4)->comment('Loss given default percentage');
            $table->decimal('cured_amount', 18, 2)->nullable();
            $table->decimal('cure_rate_cummulative', 18, 4)->nullable()->comment('Cure rate percentage');
            $table->decimal('cure_rate_average_monthly', 18, 4)->nullable()->comment('Average monthly cure rate percentage');
            $table->decimal('cure_amount_stage1',18,2)->nullable();
            $table->decimal('cure_amount_stage2',18,2)->nullable();
            $table->decimal('recovered_amount',18,2)->nullable();
            $table->decimal('recovery_rate_cummulative', 18, 4)->nullable()->comment('Recovery rate percentage');
            $table->decimal('recovery_rate_average_monthly', 18, 4)->nullable()->comment('Average monthly recovery rate percentage');
            $table->date('last_reporting_period')->nullable()->comment('Last Period Calculated');
            $table->integer('run_no')->default(0)->comment('Run number for the calculation');
            $table->string('is_active_or_closed')->default(1)->comment('Indicates if reporting period is active or closed');
            $table->string('created_by')->nullable()->comment('User who created the record');
            $table->string('updated_by')->nullable()->comment('User who last updated the record');
            $table->decimal('partially_recovered_amount',18,2)->nullable(); 
            $table->decimal('fully_recovered_amount',18,2)->nullable(); 
            $table->decimal('total_disbursments',18,2)->nullable();
            $table->integer('periods_count')->default(1);
            $table->json('periods_list')->nullable()->comment('List of Periods In cummulative');
            $table->string('calculation_source')->default('system');
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loss_given_default_cummulative');
    }
};
