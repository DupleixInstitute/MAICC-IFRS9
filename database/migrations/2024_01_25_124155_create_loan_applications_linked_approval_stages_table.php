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
        Schema::create('loan_applications_linked_approval_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_application_id');
            $table->unsignedBigInteger('loan_approval_stage_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('stage_received_at')->nullable();
            $table->timestamp('stage_started_at')->nullable();
            $table->timestamp('stage_finished_at')->nullable();
            $table->text('description')->nullable();
            $table->text('previous_description')->nullable();
            $table->tinyInteger('is_current')->default(0);
            $table->tinyInteger('completed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications_linked_approval_stages');
    }
};
