<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stage override (Client Book Reconciliation Specification v1, section 6a, 17 October 2026): a
 * governed, per-loan stage for one month, whatever the day count gives, for a client who does not
 * use E-Banker's ageing for that month. Applied when the month is staged (StagingService), the
 * system's own stage kept beside it. The three override tables also name the override schedule
 * (a client book) they were proposed from, and the label of an automated approval (the bootstrap
 * landing a committed schedule), as the account exclusions do.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_stage_overrides')) {
            Schema::create('loan_stage_overrides', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 30)->index();
                $t->string('reporting_period', 7)->index();
                $t->unsignedTinyInteger('override_stage');
                $t->string('reason', 2000);
                $t->unsignedBigInteger('proposed_by');
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->string('status', 20)->default('PROPOSED'); // PROPOSED, APPROVED, REJECTED, WITHDRAWN
                $t->timestamp('approved_at')->nullable();
                $t->timestamps();
            });
        }
        foreach (['loan_stage_overrides', 'loan_staging_basis_overrides', 'loan_gross_amount_overrides'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'client_book_id')) {
                    $t->unsignedBigInteger('client_book_id')->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'approver_label')) {
                    $t->string('approver_label', 120)->nullable();
                }
            });
        }
        if (Schema::hasTable('client_book_differences') && ! Schema::hasColumn('client_book_differences', 'stage_override_id')) {
            Schema::table('client_book_differences', fn (Blueprint $t) => $t->unsignedBigInteger('stage_override_id')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['loan_staging_basis_overrides', 'loan_gross_amount_overrides'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['client_book_id', 'approver_label'] as $c) {
                    if (Schema::hasColumn($table, $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }
        Schema::dropIfExists('loan_stage_overrides');
    }
};
