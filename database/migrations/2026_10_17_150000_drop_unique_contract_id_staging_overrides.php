<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * loan_staging_basis_overrides was given a hard, permanent unique constraint on contract_id
 * alone, which blocks a contract from ever being proposed again after a withdrawal or rejection
 * — contradicting LoanStagingBasisOverrideService::propose()'s own application-level check, which
 * only refuses a *live* (PROPOSED or APPROVED) override for the same contract. A loan's history
 * of overrides proposed, decided and withdrawn over time is the normal, expected shape, the same
 * way loan_book_builds keeps every build rather than one row per period. The application check
 * remains the real safeguard; the database index is kept, non-unique, for lookup speed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_staging_basis_overrides')) {
            Schema::table('loan_staging_basis_overrides', function ($table) {
                $table->dropUnique('loan_staging_basis_overrides_contract_id_unique');
                $table->index('contract_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_staging_basis_overrides')) {
            Schema::table('loan_staging_basis_overrides', function ($table) {
                $table->dropIndex(['contract_id']);
                $table->unique('contract_id');
            });
        }
    }
};
