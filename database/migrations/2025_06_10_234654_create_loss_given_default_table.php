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
        Schema::create('loss_given_default', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('reporting_period')->index()->comment('Reporting period for the loss given default');
            $table->date('start_period')->index()->comment('Start date of the reporting period');
            $table->string('portfolio_group')->nullable()->index();
            $table->decimal('start_total_stage3', 18, 2)->comment('Total amount in stage 3');
            $table->decimal('end_total_stage3', 18, 2)->comment('Total amount in stage 3 at the end of the reporting period');
            $table->decimal('loss_given_default_percentage', 18, 4)->comment('Loss given default percentage');
            $table->decimal('cured_amount', 18 , 2)->nullable();
            $table->decimal('cure_rate', 18, 2)->nullable()->comment('Cure rate percentage');
            $table->decimal('cure_rate_average_monthly', 18, 4)->nullable()->comment('Average monthly cure rate percentage');
            $table->decimal('cure_amount_stage1', 18, 2)->nullable();
            $table->decimal('cure_amount_stage2', 18, 2)->nullable();
            $table->decimal('recovered_amount', 18,2 )->nullable();
            $table->decimal('recovery_rate', 18, 4)->nullable()->comment('Recovery rate percentage');
            $table->decimal('recovery_rate_average_monthly', 18, 4)->nullable()->comment('Average monthly recovery rate percentage');
            $table->date('last_reporting_period')->nullable()->comment('Last Period Calculated');
            $table->integer('run_no')->default(0)->comment('Run number for the calculation');
            $table->string('type_lgd')->default('customer_lgd')->comment('indicates the type of lgd that has been calculated');
            $table->string('is_active_or_closed')->default('active')->comment('Indicates if reporting period is active or closed');
            $table->string('created_by')->nullable()->comment('User who created the record');
            $table->string('updated_by')->nullable()->comment('User who last updated the record');
            $table->softDeletes();
            $table->index('deleted_at');
            //$table->unique(['reporting_period', 'is_active_or_closed'], 'lgd_period_status_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loss_given_default');
    }
};
