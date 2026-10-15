<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EIR fee-readiness control (15 October 2026; results review of 15 October, section 6.4).
 *
 * fee_gl_candidate_dismissals  a person's statement, with the reason, that a held GL fee posting is not the
 *                              fee of the loan the system named as its likely owner. Kept apart from
 *                              fee_gl_unassigned because the GL fee build rewrites that table from the
 *                              landing zone; a dismissal survives the rebuild by the posting's external id.
 * eir_lock_reviews             a locked EIR flagged for review because fee evidence for the loan was not
 *                              in its inputs when it was locked. A flag never reopens the EIR: it is cleared
 *                              by a second person with a reason, or closed when a reopen of the EIR is approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fee_gl_candidate_dismissals')) {
            Schema::create('fee_gl_candidate_dismissals', function (Blueprint $t) {
                $t->id();
                $t->string('external_transaction_id', 120);
                $t->string('contract_id', 40);
                $t->string('reason', 500);
                $t->unsignedBigInteger('dismissed_by');
                $t->timestamps();
                $t->unique(['external_transaction_id', 'contract_id'], 'fee_gl_candidate_dismissals_unique');
            });
        }
        if (! Schema::hasTable('eir_lock_reviews')) {
            Schema::create('eir_lock_reviews', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 40)->index();
                $t->string('source', 20);          // AUTOMATIC (found by the system) or MANUAL (raised by a person, with evidence)
                $t->string('evidence_key', 160);   // what the flag is about, so the same evidence is never flagged twice
                $t->string('reason', 1000);
                $t->json('evidence')->nullable();
                $t->string('status', 12)->default('OPEN');   // OPEN, CLEARED, REOPENED
                $t->unsignedBigInteger('raised_by')->nullable();
                $t->timestamp('raised_at')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('closed_reason', 1000)->nullable();
                $t->timestamps();
                $t->unique(['contract_id', 'evidence_key'], 'eir_lock_reviews_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eir_lock_reviews');
        Schema::dropIfExists('fee_gl_candidate_dismissals');
    }
};
