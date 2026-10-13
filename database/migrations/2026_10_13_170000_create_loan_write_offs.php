<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The governed write-off register (specification v4 section 3.8, built 13 October
 * 2026): write-offs that are not on the loan account in E-Banker, such as the
 * 31 December 2025 write-off of MUSECO that exists only in the audit's TB-to-AFS
 * mapping and, from 31 January 2026, at GL level. One person proposes with the
 * evidence, a different person approves; only an approved entry reaches the
 * engines, as a write-off on the loan's actual transactions (source
 * WRITE_OFF_REGISTER), which the EIR treats as derecognition, not cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_write_offs', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id', 50)->index();
            $t->string('account', 30)->nullable();
            $t->string('customer_name')->nullable();
            $t->date('write_off_date');
            $t->decimal('amount', 20, 2);
            $t->string('reason', 500);
            $t->text('evidence')->nullable()->comment('Where the write-off is recorded: the board minute, the GL batch, the audit adjustment');
            $t->string('gl_reference', 100)->nullable()->comment('For example GL 1064 / 1876, batch TRF 26 of 31 Jan 2026');
            $t->string('status', 12)->default('PROPOSED')->comment('PROPOSED, APPROVED, REJECTED, WITHDRAWN, REVERSED');
            $t->unsignedBigInteger('proposed_by')->nullable();
            $t->timestamp('proposed_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('approver_label')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('closed_by')->nullable()->comment('Who rejected, withdrew or reversed');
            $t->timestamp('closed_at')->nullable();
            $t->string('close_reason', 500)->nullable();
            $t->unsignedBigInteger('transaction_id')->nullable()->comment('The eir_actual_transactions row written on approval');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_write_offs');
    }
};
