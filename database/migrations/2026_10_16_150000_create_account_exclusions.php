<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account exclusion register (MAIIC's confirmation of 16 October 2026):
 * E-Banker accounts that are not loans of the book, such as the vendor's UAT
 * test accounts and approvals duplicated on a sister account and never drawn.
 *
 *   account_exclusions         one row per account: the category, the reason,
 *                              the evidence, the sister account where there is
 *                              one, and the maker-checker trail (PROPOSED,
 *                              APPROVED, REJECTED, WITHDRAWN; an approved
 *                              exclusion is withdrawn only when a second person
 *                              approves the withdrawal someone else asked for)
 *   account_exclusion_archive  the rows an approval moved out of the book, one
 *                              per source row, kept as JSON with the table they
 *                              came from, so a withdrawal puts them back
 *
 * See App\Services\Ebanker\AccountExclusionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_exclusions')) {
            Schema::create('account_exclusions', function (Blueprint $t) {
                $t->id();
                $t->string('contract_id', 40)->unique();
                $t->string('account', 40)->nullable();                 // the E-Banker account as the extracts carry it (padded)
                $t->string('customer_name', 200)->nullable();
                $t->string('category', 40);                            // UAT_TEST_ACCOUNT | DUPLICATE_APPROVAL
                $t->text('reason');
                $t->text('evidence');
                $t->string('sister_contract_id', 40)->nullable();      // the account the loan runs on, for a duplicate approval
                $t->string('status', 20)->index();                     // PROPOSED | APPROVED | REJECTED | WITHDRAWN
                $t->string('source', 30)->default('SCREEN');           // SCREEN | CLIENT_INPUT | DECISIONS_PACK
                $t->unsignedBigInteger('proposed_by')->nullable();
                $t->timestamp('proposed_at')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->string('approver_label', 191)->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->unsignedInteger('rows_archived')->default(0);
                $t->unsignedBigInteger('withdrawal_requested_by')->nullable();
                $t->timestamp('withdrawal_requested_at')->nullable();
                $t->text('withdrawal_reason')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->text('close_reason')->nullable();
                $t->unsignedInteger('rows_restored')->default(0);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('account_exclusion_archive')) {
            Schema::create('account_exclusion_archive', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('exclusion_id')->index();
                $t->string('contract_id', 40)->index();
                $t->string('table_name', 64);
                $t->unsignedBigInteger('source_id')->nullable();       // the row's id in its table, given back on restore where free
                $t->longText('row');                                   // the row as it stood, JSON
                $t->timestamp('archived_at')->nullable();
                $t->timestamp('restored_at')->nullable();
                $t->timestamps();
                $t->index(['exclusion_id', 'table_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_exclusion_archive');
        Schema::dropIfExists('account_exclusions');
    }
};
