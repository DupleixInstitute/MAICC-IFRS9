<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->string('calculation_status', 20)->default('PENDING')->after('input_snapshot')->index();
            $table->string('solver_method', 30)->nullable()->after('solver_residual');
            $table->text('calculation_error')->nullable()->after('calculation_status');
            $table->timestamp('calculated_at')->nullable()->after('calculation_error');
            $table->foreignId('calculated_by')->nullable()->after('calculated_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropForeign(['calculated_by']);
            $table->dropIndex(['calculation_status']);
            $table->dropColumn(['calculation_status', 'solver_method', 'calculation_error', 'calculated_at', 'calculated_by']);
        });
    }
};
