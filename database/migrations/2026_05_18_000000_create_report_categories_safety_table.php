<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety-net table for the legacy ReportCategory model. Prevents a fatal
 * 500 on /report when stale OPcache serves the old clinic controller.
 * Intentionally empty — the live Reports hub is hardcoded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_categories')) {
            Schema::create('report_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('report_category_id')->nullable();
                $table->string('name')->nullable();
                $table->string('symbol')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_categories');
    }
};
