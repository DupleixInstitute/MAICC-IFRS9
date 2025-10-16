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
       Schema::create('transition_matrix_cummulative', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('start_period')->index()->comment('Start period for this cumulative run');
            $table->date('end_period')->index()->comment('End period for this cumulative run');
            $table->string('portfolio_group')->nullable()->index();
            $table->unsignedBigInteger('transition_profile_id')->comment('Profile ID');
            $table->integer('records_counted')->nullable();
            $table->decimal('transition_balance_total')->nullable()->comment('Total Balance Cummulated');
            $table->string('calculation_source')->default('system');
            $table->date('last_reporting_period')->nullable();
            $table->integer('periods_count')->default(1);
            $table->string('periods_list')->nullable()->comment('List of periods for this cumulative run');
            $table->integer('periods_limit')->default(60)->comment('Limit of periods to consider for this cumulative run');
            $table->boolean('default_flag')->nullable();
            $table->integer('run_no')->default(0);
            $table->string('status')->default('draft');
            $table->string('user_name')->nullable();
            $table->softDeletes();
            $table->index('deleted_at');
            $table->string('created_by')->nullable();
        });

    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_matrix_cummulative');
    }
};
