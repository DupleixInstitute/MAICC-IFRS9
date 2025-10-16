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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->unique();
            $table->string('customer_id');
            $table->date('create_date');
            $table->date('due_date');
            $table->decimal('opening_score', 65, 2);
            $table->string('opening_score_period', 6); // YYYYMM format
            $table->date('closed_date')->nullable();
            $table->date('write_off_date')->nullable();
            $table->string('update_period', 6); // YYYYMM format
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['opening_score_period']);
            $table->index(['update_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
