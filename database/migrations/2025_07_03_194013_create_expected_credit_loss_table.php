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
        Schema::create('expected_credit_loss', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->decimal('total_ead',18,2)->nullable();
            $table->decimal('total_ecl', 18,2)->nullable();
            $table->decimal('lgd_value_used', 8,2)->nullable();
            $table->decimal('pd_value_used',8,2)->nullable();
            $table->string('ifrs9_stage')->nullable();
            $table->integer('total_loans')->nullable();
            $table->string('reporting_period');
            $table->string('last_reporting_period');
            $table->softDeletes('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expected_credit_loss');
    }
};
