<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_cashflow_schedule', function (Blueprint $table) {
            $table->string('source_system', 50)->nullable()->after('schedule_source');
            $table->string('source_reference')->nullable()->after('source_system');
            $table->string('external_transaction_id')->nullable()->after('source_reference');
            $table->unique(['source_system', 'external_transaction_id'], 'uq_schedule_source_transaction');
        });

        Schema::create('eir_actual_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->string('customer_id')->nullable();
            $table->string('sub_account_no')->nullable();
            $table->date('transaction_date');
            $table->string('transaction_type', 50);
            $table->decimal('principal_component', 20, 2)->default(0);
            $table->decimal('interest_component', 20, 2)->default(0);
            $table->decimal('fee_component', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->decimal('balance_after_transaction', 20, 2)->nullable();
            $table->string('source_system', 50);
            $table->string('source_reference')->nullable();
            $table->string('external_transaction_id');
            $table->text('row_note')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'external_transaction_id'], 'uq_actual_source_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eir_actual_transactions');
        Schema::table('contract_cashflow_schedule', function (Blueprint $table) {
            $table->dropUnique('uq_schedule_source_transaction');
            $table->dropColumn(['source_system', 'source_reference', 'external_transaction_id']);
        });
    }
};
