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
      Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action'); 
    $table->string('entity_type'); 
    $table->unsignedBigInteger('entity_id')->nullable();
    $table->string('scope')->nullable(); 
    $table->string('reporting_period')->nullable();
    $table->integer('rows_affected')->nullable();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->json('meta')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamps();
    $table->index(['entity_type', 'entity_id']);
    $table->index(['action']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
