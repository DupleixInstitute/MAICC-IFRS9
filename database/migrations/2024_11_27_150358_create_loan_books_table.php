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
        Schema::create('loan_books', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->unique()->index();
            $table->string('customer_id')->index();
            $table->string('customer_name')->nullable();
            $table->string('external_identity_id')->index()->default('TBA');
            $table->string('portfolio_group')->nullable();
            $table->integer('reporting_year');
            $table->integer('reporting_month');
            $table->string('reporting_period', 6)->index(); // YYYYMM format
            $table->date('create_date');
            $table->date('due_date');
            $table->string('industry_code')->nullable();
            $table->string('industry_type')->nullable();
            $table->integer('overdue_days')->default(0);
            $table->decimal('remaining_tenor',5,2)->default(0);
            $table->integer('tenor')->default(0);
            $table->decimal('interest_rate',8,2)->default(0);
            $table->decimal('principal_balance', 65, 4)->default(0);
            $table->decimal('approved_amount', 65, 4)->default(0);
            $table->decimal('disbursed',65,4)->default(0);
            $table->decimal('repayments',65,4)->default(0);
            $table->decimal('carrying_amount', 65, 4)->default(0);
            $table->decimal('commitments', 16,4)->default(0);            
            $table->string('collateral_id')->nullable();
            $table->decimal('expected_loss_provision', 65, 4)->default(0);
            $table->string('overdue_status')->nullable();
            $table->boolean('is_month_end')->default(false);
            $table->integer('ifrs9_stage')->default(0);
            $table->string('ifrs9stage_pre_qualitative')->nullable();
            $table->boolean('sicr')->default(0)->nullable();
            $table->string('ifrs9stage_post_qualitative')->nullable();
            $table->string('< 30')->nullable();
            $table->string('30 to =< 90')->nullable();
            $table->string('30 =< 180')->nullable();
            $table->string('arrears > 180')->nullable();
            $table->timestamps();
            
            // Composite indexes for efficient querying
            $table->index(['reporting_year', 'reporting_month']);
            $table->index(['customer_id', 'reporting_period']);
            $table->index(['overdue_status', 'reporting_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_books');
    }
};
