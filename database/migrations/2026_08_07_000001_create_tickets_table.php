<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhancement / issue / change-request tracking. Each ticket carries a unique
 * human reference (e.g. #001), a status, a priority and a responsible person so
 * MAIIC and Dupleix can track requests in a structured way — the tracking
 * section referred to in the platform-review correspondence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();          // e.g. 001, 002 (shown as #001)
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('category', 30)->default('enhancement'); // enhancement|issue|change_request|other
            $table->string('priority', 20)->default('medium');      // low|medium|high|critical
            $table->string('status', 20)->default('open');          // open|in_progress|on_hold|resolved|closed
            $table->string('requested_by')->nullable();             // who raised it (free text, e.g. "Barry — MAIIC")
            $table->string('source', 40)->nullable();               // email|meeting|phone|system|...
            $table->unsignedBigInteger('assigned_to')->nullable();  // responsible person (users.id)
            $table->unsignedBigInteger('created_by')->nullable();   // who logged it (users.id)
            $table->longText('resolution')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('category');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
