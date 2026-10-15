<?php

namespace Tests\Feature\Eir\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * contract_fees as the engine now reads it (with the superseded mark of
 * fee_source_precedence, spec v4 section 7.8.2) and the fee rulebook the
 * importer asks for a suggestion, on the private in-memory schema the EIR
 * tests use.
 */
trait CreatesFeeSchema
{
    protected function createFeeSchema(): void
    {
        if (! Schema::hasTable('contract_fees')) {
            Schema::create('contract_fees', function (Blueprint $t) {
                $t->increments('id');
                $t->string('contract_id');
                $t->string('fee_type');
                $t->double('amount');
                $t->string('basis')->default('ON_APPROVED');
                $t->string('description')->nullable();
                $t->string('transaction_date')->nullable();
                $t->string('cashflow_direction')->nullable();
                $t->string('currency')->nullable();
                $t->string('source_system')->nullable();
                $t->string('source_reference')->nullable();
                $t->string('external_transaction_id')->nullable();
                $t->boolean('integral')->nullable();
                $t->string('classification_status')->default('PENDING');
                $t->text('classification_reason')->nullable();
                $t->unsignedInteger('suggested_rule_id')->nullable();
                $t->boolean('suggested_integral')->nullable();
                $t->unsignedInteger('classified_by')->nullable();
                $t->string('classified_at')->nullable();
                $t->unsignedInteger('reviewed_by')->nullable();
                $t->string('reviewed_at')->nullable();
                $t->string('gl_account_ref')->nullable();
                $t->string('superseded_by_source', 50)->nullable();
                $t->string('superseded_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('eir_accounting_rules')) {
            Schema::create('eir_accounting_rules', function (Blueprint $t) { $t->string('status', 20)->default('PROPOSED');
                $t->increments('id');
                $t->string('name');
                $t->string('fee_type')->nullable();
                $t->string('description_contains')->nullable();
                $t->string('gl_account_ref')->nullable();
                $t->string('cashflow_direction')->nullable();
                $t->boolean('proposed_integral');
                $t->text('rationale');
                $t->unsignedInteger('priority')->default(100);
                $t->boolean('active')->default(true);
                $t->string('approved_at')->nullable();
                $t->timestamps();
            });
        }
    }
}
