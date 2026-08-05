<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eir_accounting_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('fee_type', 50)->nullable();
            $table->string('description_contains')->nullable();
            $table->string('gl_account_ref', 50)->nullable();
            $table->enum('cashflow_direction', ['RECEIVED', 'PAID'])->nullable();
            $table->boolean('proposed_integral');
            $table->text('rationale');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['active', 'priority']);
        });

        Schema::table('contract_fees', function (Blueprint $table) {
            $table->boolean('integral')->nullable()->default(null)->change();
            $table->string('description')->nullable()->after('fee_type');
            $table->date('transaction_date')->nullable()->after('amount');
            $table->enum('cashflow_direction', ['RECEIVED', 'PAID'])->nullable()->after('transaction_date');
            $table->string('currency', 3)->nullable()->after('cashflow_direction');
            $table->string('source_system', 50)->nullable()->after('currency');
            $table->string('source_reference')->nullable()->after('source_system');
            $table->string('external_transaction_id')->nullable()->after('source_reference');
            $table->enum('classification_status', ['PENDING', 'CLASSIFIED', 'REVIEWED', 'REJECTED'])
                ->default('PENDING')->after('integral');
            $table->text('classification_reason')->nullable()->after('classification_status');
            $table->foreignId('suggested_rule_id')->nullable()->after('classification_reason')
                ->constrained('eir_accounting_rules');
            $table->boolean('suggested_integral')->nullable()->after('suggested_rule_id');
            $table->foreignId('classified_by')->nullable()->after('suggested_integral')->constrained('users');
            $table->timestamp('classified_at')->nullable()->after('classified_by');
            $table->foreignId('reviewed_by')->nullable()->after('classified_at')->constrained('users');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->index(['classification_status', 'contract_id'], 'idx_fee_classification_contract');
            $table->unique(['source_system', 'external_transaction_id'], 'uq_fee_source_transaction');
        });

        Schema::create('eir_fee_classification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_fee_id')->constrained('contract_fees');
            $table->enum('action', ['CLASSIFIED', 'REVIEWED', 'REJECTED', 'REOPENED']);
            $table->boolean('integral')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('accounting_rule_id')->nullable()->constrained('eir_accounting_rules');
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eir_fee_classification_events');
        Schema::table('contract_fees', function (Blueprint $table) {
            $table->dropUnique('uq_fee_source_transaction');
            $table->dropIndex('idx_fee_classification_contract');
            $table->dropForeign(['suggested_rule_id']);
            $table->dropForeign(['classified_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'description', 'transaction_date', 'cashflow_direction', 'currency',
                'source_system', 'source_reference', 'external_transaction_id',
                'classification_status', 'classification_reason', 'suggested_rule_id',
                'suggested_integral', 'classified_by', 'classified_at', 'reviewed_by', 'reviewed_at',
            ]);
            $table->boolean('integral')->nullable(false)->default(true)->change();
        });
        Schema::dropIfExists('eir_accounting_rules');
    }
};
