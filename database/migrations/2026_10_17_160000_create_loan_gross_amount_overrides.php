<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governed, per-loan exception from the build's own rebuilt carrying amount to an explicit
 * audited figure, reconciling an already-closed, already-audited period to the gross loan amount
 * the external audit approved (MAIIC Finance Overlay Importers Specification v1, section 4.2,
 * and the staging basis override of the same date, section 10). contract_id is indexed, not
 * unique — a loan's history of overrides proposed, decided and withdrawn over time is the normal
 * shape (the same lesson learned, and fixed, on loan_staging_basis_overrides).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_gross_amount_overrides')) {
            Schema::create('loan_gross_amount_overrides', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 30)->index();
                $t->decimal('override_amount', 20, 2);
                $t->string('reason', 2000);
                $t->unsignedBigInteger('proposed_by');
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->string('status', 20)->default('PROPOSED'); // PROPOSED, APPROVED, REJECTED, WITHDRAWN
                $t->timestamp('approved_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_gross_amount_overrides');
    }
};
