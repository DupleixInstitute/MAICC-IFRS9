<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transition_matrices_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calculation_header_id');
            $table->boolean('is_payments_included');
            $table->string('start_period', 199);
            $table->integer('start_year')->default(0);
            $table->integer('start_month')->default(0);
            $table->string('end_period', 199);
            $table->integer('end_year');
            $table->integer('end_month');
            $table->string('portfolio_group', 255);
            $table->string('start_stage', 100)->default('0');
            $table->string('end_stage', 100)->nullable();
            $table->string('stage_transition', 15)->nullable();
            $table->boolean('default_flag')->default(0);
            $table->tinyInteger('transition_years', false, true)->default(0);
            $table->decimal('transition_balance_month', 20, 2)->nullable();
            $table->decimal('transition_balance_YTD', 20, 2)->nullable();
            $table->decimal('transition_balance_n_months', 20, 2)->nullable();
            $table->decimal('start_total_balance_month', 20, 2)->nullable()->comment('total amount before transition');
            $table->decimal('start_total_balance_YTD', 20, 2)->nullable()->comment('total amount before transition');
            $table->decimal('start_total_balance_n_months', 20, 2)->nullable()->comment('total amount before transition');
            $table->decimal('transition_probability_month', 7, 4)->nullable();
            $table->decimal('transition_probability_avg_YTD', 7, 4)->default(0.0000);
            $table->decimal('transition_probability_avg_n_months', 7, 4)->nullable();
            $table->tinyInteger('n_months_count')->default(0)->comment('no of months in db during calculation');
            $table->tinyInteger('n_months_setting')->default(60);
            $table->decimal('pd_mgt_overlay', 10, 4)->default(0.0200);
            $table->timestamp('transaction_date')->nullable();
            $table->boolean('take_on_flag')->default(0);
            $table->string('user_name', 20)->nullable();
            $table->timestamps(); // includes created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transition_matrices_data');
    }
};

