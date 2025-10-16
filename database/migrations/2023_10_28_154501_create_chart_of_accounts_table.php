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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('tax_rate_id')->unsigned()->nullable();
            $table->integer('parent_id')->nullable();
            $table->text('name');
            $table->string('gl_code')->nullable();
            $table->string('external_id')->nullable();
            $table->string('account_type');
            $table->tinyInteger('enable_reconciliation')->default(0);
            $table->tinyInteger('allow_manual')->default(0);
            $table->tinyInteger('active')->default(1);
            $table->decimal('balance')->default(0.00);
            $table->decimal('opening_balance')->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
