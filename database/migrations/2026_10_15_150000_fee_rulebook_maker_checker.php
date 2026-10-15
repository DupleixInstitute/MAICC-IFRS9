<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fee rulebook control (Staging Module Specification v1, section 8.6; phase 3).
 * Until now the person who wrote a rule could approve it, no reason was asked,
 * and switching a rule on or off took effect at once with no second person.
 *
 *   status                  PROPOSED, APPROVED, REJECTED or WITHDRAWN; the
 *                           fee matcher uses only APPROVED, active rules
 *   updated_by              who last wrote the rule: the maker the approver
 *                           must differ from (else the creator)
 *   approval_reason         why the approver approved it
 *   approval_to_confirm     an approval given before this control, kept in use
 *                           and listed for a second person to re-approve
 *                           (decision S9, option (a), 15 October 2026)
 *   rejected_by/at/reason, withdrawn_by/at/reason
 *   proposed_active, switch_proposed_by/at, switch_reason
 *                           a proposal to switch the rule on or off, in force
 *                           only once a second person approves it
 *
 * Existing rows: a rule with an approval is APPROVED with approval_to_confirm
 * set; any other is PROPOSED. The seeder writes every rule unapproved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eir_accounting_rules')) {
            return;
        }
        if (Schema::hasColumn('eir_accounting_rules', 'status')) {
            return;
        }
        Schema::table('eir_accounting_rules', function (Blueprint $t) {
            $t->string('status', 20)->default('PROPOSED')->after('active');
            $t->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            $t->text('approval_reason')->nullable()->after('approved_at');
            $t->boolean('approval_to_confirm')->default(false)->after('approval_reason');
            $t->unsignedBigInteger('rejected_by')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->unsignedBigInteger('withdrawn_by')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->text('withdrawal_reason')->nullable();
            $t->boolean('proposed_active')->nullable();
            $t->unsignedBigInteger('switch_proposed_by')->nullable();
            $t->timestamp('switch_proposed_at')->nullable();
            $t->text('switch_reason')->nullable();
        });
        // once, when the control is added: an approval given before it stays in use, to be confirmed by a second person
        DB::table('eir_accounting_rules')->whereNotNull('approved_at')->update(['status' => 'APPROVED', 'approval_to_confirm' => true]);
        DB::table('eir_accounting_rules')->whereNull('approved_at')->update(['status' => 'PROPOSED']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('eir_accounting_rules') || ! Schema::hasColumn('eir_accounting_rules', 'status')) {
            return;
        }
        Schema::table('eir_accounting_rules', function (Blueprint $t) {
            $t->dropColumn(['status', 'updated_by', 'approval_reason', 'approval_to_confirm', 'rejected_by', 'rejected_at', 'rejection_reason',
                'withdrawn_by', 'withdrawn_at', 'withdrawal_reason', 'proposed_active', 'switch_proposed_by', 'switch_proposed_at', 'switch_reason']);
        });
    }
};
