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
        Schema::create('balance_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('year')->nullable();
            $table->date('as_at_date')->nullable();
            $table->decimal('total_assets', 65)->nullable();
            $table->decimal('total_equity', 65)->nullable();
            $table->decimal('total_liabilities', 65)->nullable();
            $table->decimal('total_working_capital', 65)->nullable();
            $table->decimal('total_equity_liabilities', 65)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_sheets');
    }
};
