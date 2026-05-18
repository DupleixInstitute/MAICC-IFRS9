<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IFRS 9 period-close workspace: one checklist row per (reporting period,
 * task). Laravel port of the FDH period_progress idea.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_workspace_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('reporting_period', 10);          // YYYY-MM
            $table->string('task_key', 50);
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->string('completed_by', 120)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['reporting_period', 'task_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_workspace_tasks');
    }
};
