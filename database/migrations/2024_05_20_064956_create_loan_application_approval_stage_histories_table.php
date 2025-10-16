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
        Schema::create('loan_application_approval_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('approver_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('actioned_by');
            $table->string('action');
            $table->string('additional_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_application_approval_stage_histories');
    }
};
