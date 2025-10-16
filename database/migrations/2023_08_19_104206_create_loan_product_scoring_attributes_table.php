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
        Schema::create('loan_product_scoring_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('loan_product_id')->nullable();
            $table->unsignedBigInteger('scoring_attribute_group_id')->nullable();
            $table->unsignedBigInteger('scoring_attribute_id')->nullable();
            $table->integer('order_position')->nullable()->default(0);
            $table->decimal('weight')->default(0.0)->nullable();
            $table->decimal('effective_weight')->default(0.0)->nullable();
            $table->decimal('score')->default(0.0)->nullable();
            $table->decimal('weighted_score')->default(0.0)->nullable();
            $table->decimal('min_score')->default(0.0)->nullable();
            $table->decimal('max_score')->default(0.0)->nullable();
            $table->string('name')->nullable();
            $table->string('reject_value')->nullable();
            $table->string('accept_value')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('active')->default(1);
            $table->tinyInteger('is_group')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_scoring_attributes');
    }
};
