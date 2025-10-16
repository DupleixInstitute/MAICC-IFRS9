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
        Schema::create('income_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('year')->nullable();
            $table->date('as_at_date')->nullable();
            $table->decimal('total_sales', 65)->nullable();
            $table->decimal('total_cost_of_goods_sold', 65)->nullable();
            $table->decimal('total_operating_expenses', 65)->nullable();
            $table->decimal('total_operating_profit', 65)->nullable();
            $table->decimal('total_gross_margin', 65)->nullable();
            $table->decimal('total_other_income', 65)->nullable();
            $table->decimal('total_other_expenses', 65)->nullable();
            $table->decimal('total_income_before_tax', 65)->nullable();
            $table->decimal('total_income_tax', 65)->nullable();
            $table->decimal('net_profit', 65)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_statements');
    }
};
