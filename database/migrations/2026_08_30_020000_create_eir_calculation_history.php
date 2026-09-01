<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eir_calculation_history', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->decimal('eir_period', 18, 12)->nullable();
            $table->decimal('eir_nominal_annual', 18, 12)->nullable();
            $table->decimal('eir_effective_annual', 18, 12)->nullable();
            $table->string('rate_source', 50)->nullable();
            $table->unsignedSmallInteger('solver_iterations')->nullable();
            $table->double('solver_residual')->nullable();
            $table->string('solver_method', 50)->nullable();
            $table->json('input_snapshot')->nullable();
            $table->string('calculation_status', 30);
            $table->text('calculation_error')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->string('archive_action', 30)->default('REOPENED');
            $table->string('archive_reason', 500);
            $table->foreignId('archived_by')->nullable()->constrained('users');
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eir_calculation_history');
    }
};
