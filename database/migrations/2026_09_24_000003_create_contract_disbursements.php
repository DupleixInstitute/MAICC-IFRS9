<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P4 of the EIR engine (spec v3 sections 6.3 and 7.6): one row per
 * drawdown.
 *
 * A facility can be drawn in tranches, and the contract master carries exactly
 * one tranche per loan even where three were drawn. Without the dates and
 * amounts of each drawdown three things cannot be done: interest in the month
 * of a tranche cannot be reconstructed (Milele's month-end balance does not
 * reconcile without it, section 7.7), the second drawdown cannot enter the
 * cash-flow vector as a later outflow, and the undrawn commitment cannot be
 * reported per facility as IFRS 9 requires. At 31 August 2026 eight facilities
 * carried MWK 3,472,435,397 undrawn.
 *
 * The unique key is the source system and its own transaction id, so the same
 * extract loaded twice adds nothing. Where the file carries no transaction id
 * the importer builds a fingerprint from the facility, the date, the amount,
 * the tranche and the reference, which is what makes a re-import idempotent.
 * Amounts are positive: a drawdown is money out of MAIIC, and the sign is the
 * reader's business, not the row's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id');
            $table->string('sub_account_no', 20)->nullable();
            $table->unsignedSmallInteger('tranche_no')->nullable()
                ->comment('1 for the first drawdown, 2 for the next, as the file states it');
            $table->date('disbursement_date');
            $table->decimal('amount', 20, 2)->comment('Positive: the amount paid out on that date');
            $table->string('reference')->nullable()->comment('The voucher or payment reference the file carries');
            $table->string('source_system', 40)->nullable();
            $table->string('source_reference')->nullable();
            $table->string('external_transaction_id', 120)->nullable();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['source_system', 'external_transaction_id'], 'uq_disbursement_source_txn');
            $table->index('contract_id', 'ix_disbursement_contract');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_disbursements');
    }
};
