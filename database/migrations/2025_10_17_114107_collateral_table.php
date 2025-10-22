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
        Schema::create("collateral_types", function (Blueprint $table) {
        
            $table->increments("id");
            $table->tinyInteger("type_code")->unique();
            $table->string("type_name", 100);
            $table->string("description", 255)->nullable();
            $table->unsignedInteger("realisation_period")->comment('Months')->nullable();
            $table->decimal("standard_haircut", 10, 2)->comment('Percentage')->nullable();
            $table->decimal('liquidity_factor',10,2)->default(0)->nullable();
            $table->boolean("is_active")->default(true);
            $table->timestamps();

            $table->index(["type_code","is_active"]);
        
        });

         Schema::create('collateral_registers', function (Blueprint $table) {
            $table->id();
            $table->string('register_number', 50)->unique();
            $table->string('customer_id', 20);
            $table->string('customer_name', 255);
            $table->string('collateral_type', 100);
            $table->string('property_use', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('location', 255)->nullable();
            $table->date('registration_date');
            $table->date('expiry_date');
            $table->date('valuation_date');
            $table->decimal('nominal_value', 20, 2)->default(0);
            $table->decimal('market_value', 20, 2)->default(0);
            $table->decimal('execution_value', 20, 2)->default(0);
            $table->enum('status', ['ACTIVE', 'EXPIRED', 'RELEASED'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id']);
            $table->index(['status']);
            $table->index(['expiry_date']);
        });

         Schema::create('collateral_allocations', function (Blueprint $table) {
            $table->id();
            $table->integer('reporting_year');
            $table->tinyInteger('reporting_month');
            $table->integer('reporting_period')->virtualAs('reporting_year * 100 + reporting_month');
            $table->foreignId('collateral_register_id')->constrained('collateral_registers');
            $table->string('customer_id', 20);
            $table->string('customer_name', 255);
            $table->string('contract_id', 50);
            $table->decimal('account_balance', 20, 2)->default(0);
            $table->decimal('total_customer_exposure', 20, 2)->default(0);
            $table->decimal('allocated_collateral', 20, 2)->default(0);
            $table->decimal('allocation_percentage', 8, 5)->default(0);
            $table->decimal('total_collateral_value', 20, 2)->default(0);
            $table->decimal('EIR', 8, 5)->default(0);
            $table->integer('realisation_months')->default(12);
            $table->decimal('discounted_collateral', 20, 2)->default(0);
            $table->decimal('coverage_ratio', 8, 4)->default(0)
                  ->comment('account_balance / allocated_collateral * 100');
            $table->enum('allocation_basis', ['AVERAGE', 'ACTUAL'])->default('AVERAGE');
            $table->text('allocation_notes')->nullable();
            $table->timestamp('allocation_date')->useCurrent();
            $table->timestamps();
            
            $table->unique([
                'reporting_period',
                'collateral_register_id', 
                'contract_id'
            ], 'unique_monthly_allocation');
            
            $table->index(['reporting_period']);
            $table->index(['customer_id', 'contract_id']);
            $table->index(['allocation_date']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('collateral_allocations');
        Schema::dropIfExists('collateral_registers');
        Schema::dropIfExists('collateral_types');
    }
};
