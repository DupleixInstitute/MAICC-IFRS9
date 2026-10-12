<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qualitative SICR as a governed register (system review of 9 October 2026).
 *
 * Before this, a trigger raised on the SICR Trigger Alerts screen saved only
 * the account, the item, the reason and who raised it: the columns the screen
 * also sent (customer, effective period, removal date) had never been created
 * (their migration is commented out), so they were silently dropped, and the
 * staging engine never read the register at all. It read loan_books.sicr, a
 * flag the screen set on one row of the latest month only, which the next
 * build or re-stage could not reproduce, and nothing could move a loan to
 * Stage 3 on qualitative evidence.
 *
 * Items now say the stage they imply (2 = significant increase in credit
 * risk, 3 = credit-impaired, IFRS 9 Appendix A), whether evidence is needed
 * and the paragraph they rest on. A trigger carries its scope (one contract
 * or every loan of a customer), the stage it implies as approved, the
 * evidence, the dates it is in force, and a maker-checker life: proposed,
 * approved by a second person, rejected, or closed with a date and a reason.
 * The staging engine reads the approved triggers in force at each month-end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_sicr_groups', function (Blueprint $t) {
            if (! Schema::hasColumn('finance_sicr_groups', 'sort_order')) {
                $t->unsignedSmallInteger('sort_order')->default(0);
            }
        });

        Schema::table('finance_sicr_items', function (Blueprint $t) {
            if (! Schema::hasColumn('finance_sicr_items', 'description')) {
                $t->text('description')->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_items', 'stage_implied')) {
                $t->unsignedTinyInteger('stage_implied')->default(2);
            }
            if (! Schema::hasColumn('finance_sicr_items', 'evidence_required')) {
                $t->boolean('evidence_required')->default(true);
            }
            if (! Schema::hasColumn('finance_sicr_items', 'standard_reference')) {
                $t->string('standard_reference', 120)->nullable();
            }
        });

        Schema::table('finance_sicr_triggers', function (Blueprint $t) {
            if (! Schema::hasColumn('finance_sicr_triggers', 'scope')) {
                $t->string('scope', 20)->default('contract');
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'customer_id')) {
                $t->string('customer_id', 60)->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'customer_name')) {
                $t->string('customer_name')->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'stage_implied')) {
                $t->unsignedTinyInteger('stage_implied')->default(2);
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'evidence')) {
                $t->text('evidence')->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'effective_from')) {
                $t->date('effective_from')->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'effective_to')) {
                $t->date('effective_to')->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'status')) {
                $t->string('status', 20)->default('PROPOSED');
            }
            foreach (['approved', 'rejected', 'closed'] as $act) {
                if (! Schema::hasColumn('finance_sicr_triggers', "{$act}_by")) {
                    $t->unsignedBigInteger("{$act}_by")->nullable();
                }
                if (! Schema::hasColumn('finance_sicr_triggers', "{$act}_at")) {
                    $t->timestamp("{$act}_at")->nullable();
                }
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'approver_label')) {
                $t->string('approver_label', 60)->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'rejected_reason')) {
                $t->string('rejected_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'closed_reason')) {
                $t->string('closed_reason', 500)->nullable();
            }
            if (! Schema::hasColumn('finance_sicr_triggers', 'is_demo')) {
                $t->boolean('is_demo')->default(false);
            }
        });

        Schema::table('finance_sicr_triggers', function (Blueprint $t) {
            $t->index(['status', 'effective_from'], 'finance_sicr_triggers_status_from_index');
            $t->index('customer_id', 'finance_sicr_triggers_customer_index');
        });
    }

    public function down(): void
    {
        Schema::table('finance_sicr_triggers', function (Blueprint $t) {
            $t->dropIndex('finance_sicr_triggers_status_from_index');
            $t->dropIndex('finance_sicr_triggers_customer_index');
            $t->dropColumn(['scope', 'customer_id', 'customer_name', 'stage_implied', 'evidence', 'effective_from', 'effective_to', 'status',
                'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'closed_by', 'closed_at', 'approver_label', 'rejected_reason', 'closed_reason', 'is_demo']);
        });
        Schema::table('finance_sicr_items', function (Blueprint $t) {
            $t->dropColumn(['description', 'stage_implied', 'evidence_required', 'standard_reference']);
        });
        Schema::table('finance_sicr_groups', function (Blueprint $t) {
            $t->dropColumn('sort_order');
        });
    }
};
