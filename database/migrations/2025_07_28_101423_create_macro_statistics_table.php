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
            Schema::create('macro_statistics', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('statistic_code')->nullable();
                $table->string('statistic_name');
                $table->string('statistic_description')->nullable();
                $table->string('unit')->nullable(); // e.g., %, index, USD
                $table->enum('frequency', ['monthly', 'quarterly', 'yearly'])->nullable();
                $table->integer('historical_periods')->nullable(); // how many periods of history are available
                $table->integer('forecasting_periods')->nullable(); // how many periods are projected
                $table->text('comments')->nullable();
                $table->string('data_source')->nullable();
                $table->string('website_link')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_statistics');
    }
};
