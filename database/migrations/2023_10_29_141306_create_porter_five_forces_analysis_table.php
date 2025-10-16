<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('porter_five_forces_analysis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('industry_cyclicality')->nullable();
            $table->string('industry_performance')->nullable();
            $table->enum('time_and_cost_of_entry', ['low', 'medium', 'high'])->nullable();
            $table->text('time_and_cost_of_entry_comment')->nullable();
            $table->enum('specialist_knowledge', ['low', 'medium', 'high'])->nullable();
            $table->text('specialist_knowledge_comment')->nullable();
            $table->enum('economies_of_scale', ['low', 'medium', 'high'])->nullable();
            $table->text('economies_of_scale_comment')->nullable();
            $table->enum('cost_advantages', ['low', 'medium', 'high'])->nullable();
            $table->text('cost_advantages_comment')->nullable();
            $table->enum('technology_protection', ['low', 'medium', 'high'])->nullable();
            $table->text('technology_protection_comment')->nullable();
            $table->enum('barriers_to_entry', ['low', 'medium', 'high'])->nullable();
            $table->text('barriers_to_entry_comment')->nullable();
            $table->enum('threats_of_new_entry', ['low', 'medium', 'high'])->nullable();
            $table->enum('number_of_competitors', ['low', 'medium', 'high'])->nullable();
            $table->text('number_of_competitors_comment')->nullable();
            $table->enum('quality_differences', ['low', 'medium', 'high'])->nullable();
            $table->text('quality_differences_comment')->nullable();
            $table->enum('other_differences', ['low', 'medium', 'high'])->nullable();
            $table->text('other_differences_comment')->nullable();
            $table->enum('switching_costs', ['low', 'medium', 'high'])->nullable();
            $table->text('switching_costs_comment')->nullable();
            $table->enum('customer_loyalty', ['low', 'medium', 'high'])->nullable();
            $table->text('customer_loyalty_comment')->nullable();
            $table->enum('competitive_rivalry', ['low', 'medium', 'high'])->nullable();
            $table->enum('number_of_suppliers', ['low', 'medium', 'high'])->nullable();
            $table->text('number_of_suppliers_comment')->nullable();
            $table->enum('size_of_suppliers', ['low', 'medium', 'high'])->nullable();
            $table->text('size_of_suppliers_comment')->nullable();
            $table->enum('uniqueness_of_service', ['low', 'medium', 'high'])->nullable();
            $table->text('uniqueness_of_service_comment')->nullable();
            $table->enum('costs_of_supplier_change', ['low', 'medium', 'high'])->nullable();
            $table->text('costs_of_supplier_change_comment')->nullable();
            $table->enum('supplier_switching_costs', ['low', 'medium', 'high'])->nullable();
            $table->text('supplier_switching_costs_comment')->nullable();
            $table->enum('supplier_power', ['low', 'medium', 'high'])->nullable();
            $table->enum('substitute_performance', ['low', 'medium', 'high'])->nullable();
            $table->text('substitute_performance_comment')->nullable();
            $table->enum('costs_of_substitution', ['low', 'medium', 'high'])->nullable();
            $table->text('costs_of_substitution_comment')->nullable();
            $table->enum('threats_of_substitution', ['low', 'medium', 'high'])->nullable();
            $table->enum('number_of_customers', ['low', 'medium', 'high'])->nullable();
            $table->text('number_of_customers_comment')->nullable();
            $table->enum('single_order_size', ['low', 'medium', 'high'])->nullable();
            $table->text('single_order_size_comment')->nullable();
            $table->enum('competitor_differences', ['low', 'medium', 'high'])->nullable();
            $table->text('competitor_differences_comment')->nullable();
            $table->enum('price_sensitivity', ['low', 'medium', 'high'])->nullable();
            $table->text('price_sensitivity_comment')->nullable();
            $table->enum('ability_to_substitute', ['low', 'medium', 'high'])->nullable();
            $table->text('ability_to_substitute_comment')->nullable();
            $table->enum('customers_switching_costs', ['low', 'medium', 'high'])->nullable();
            $table->text('customers_switching_costs_comment')->nullable();
            $table->enum('buyer_power', ['low', 'medium', 'high'])->nullable();
            $table->enum('grand_total', ['low', 'medium', 'high'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('porter_five_forces_analysis');
    }
};
