<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace team board — a lightweight message channel so everyone working a
 * reporting period can communicate inside the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_messages', function (Blueprint $table) {
            $table->id();
            $table->string('reporting_period', 10)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 120);
            $table->text('body');
            $table->timestamps();

            $table->index(['reporting_period', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_messages');
    }
};
