<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governed, per-loan exception from the staging policy's own bucket-ageing basis to the
 * schedule-aware count the build already computes for the RBM directive's own overdue_days
 * (December 2025 Loan Book Investigation Findings v1, section 10, and the discussion that
 * followed it, 17 October 2026). Not a free-text day count: the override only ever says "use the
 * count this loan's own row already carries", reasoned and maker-checker approved, one row per
 * loan. Every other loan keeps the governed staging policy's own basis unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_staging_basis_overrides')) {
            Schema::create('loan_staging_basis_overrides', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 30)->unique();
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
        Schema::dropIfExists('loan_staging_basis_overrides');
    }
};
