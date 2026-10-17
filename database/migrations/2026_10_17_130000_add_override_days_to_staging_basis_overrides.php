<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An explicit, audited day count a staging basis override may carry, beside the directive's own
 * already-computed count it already supported (MAIIC Finance Overlay Importers Specification v1,
 * section 4.3: reconciling an already-closed, already-audited period to the figures the audit
 * approved, pending MAIIC's facility-level reasons). When present, the build uses this value;
 * when absent, the override still falls back to the directive's own count exactly as before —
 * nothing about the JAT/BenCal evidence-based overrides changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_staging_basis_overrides') && ! Schema::hasColumn('loan_staging_basis_overrides', 'override_days')) {
            Schema::table('loan_staging_basis_overrides', function (Blueprint $t) {
                $t->integer('override_days')->nullable()->after('contract_id');
                $t->string('source', 40)->default('directive_count')->after('override_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('loan_staging_basis_overrides', 'override_days')) {
            Schema::table('loan_staging_basis_overrides', function (Blueprint $t) {
                $t->dropColumn(['override_days', 'source']);
            });
        }
    }
};
