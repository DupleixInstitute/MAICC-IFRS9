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
        Schema::create('date_dimensions', function (Blueprint $table) {
            $table->id();
            $table->date('full_date')->unique();
            $table->integer('year');
            $table->integer('month');
            $table->string('month_name');
            $table->integer('quarter');
            $table->string('year_month')->index(); // Format: YYYYMM
            $table->boolean('is_month_end')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('date_dimensions');
    }
};
