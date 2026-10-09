<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The manual-overlay register (spec v4 sections 14.6 and 15.7)
|--------------------------------------------------------------------------
| The system audit of 9 October 2026 (finding M3) found that the "Manual
| overlay" route read the latest legacy fli_adj row: a free field with no
| scope, reason, evidence, owner, expiry, proposer or approver. The register
| replaces it. Every entry says what it covers (the book, a product group or
| one contract), by how much it moves the PD (a signed fraction: 0.15 means
| the PD times 1.15), why, on what evidence, who owns it and when it lapses;
| one person proposes it and a different person approves it; and the ECL
| shows each overlay as its own line.
|
| The overlay names the scenario set it sits on (15.7), so a set cannot be
| locked while an overlay against its period is still proposed (finding M4);
| the set records the overlays in force at the moment it is approved. The
| loan records which overlays moved its PD (14.8), so two loans adjusted
| differently in the same period can both be explained.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fli_overlays', function (Blueprint $t) {
            $t->id();
            $t->string('reporting_period', 7)->index();
            $t->string('scope', 16)->comment('book | product_group | contract');
            $t->string('scope_value')->nullable()->comment('The product group or the contract id; empty for the book');
            $t->decimal('adjustment', 12, 8)->comment('A signed fraction on the PD: post = pre x (1 + adjustment)');
            $t->text('reason');
            $t->text('evidence')->nullable()->comment('A reference or the text of the evidence');
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('expiry_period', 7)->comment('The last reporting period the overlay is in force for');
            $t->string('status', 12)->default('PROPOSED')->comment('PROPOSED | APPROVED | REJECTED | EXPIRED');
            $t->unsignedBigInteger('set_id')->nullable()->comment('The scenario set the overlay sits on');
            $t->unsignedBigInteger('proposed_by')->nullable();
            $t->timestamp('proposed_at')->nullable();
            $t->unsignedBigInteger('approved_by')->nullable();
            $t->string('approver_label')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('rejected_by')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->string('rejected_reason', 500)->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'expiry_period']);
        });
        if (Schema::hasTable('loan_books') && ! Schema::hasColumn('loan_books', 'fli_overlay_ids')) {
            Schema::table('loan_books', function (Blueprint $t) {
                $t->json('fli_overlay_ids')->nullable()->comment('The register overlays that moved this loan\'s PD');
            });
        }
        if (Schema::hasTable('governed_scenario_sets') && ! Schema::hasColumn('governed_scenario_sets', 'overlays_at_approval')) {
            Schema::table('governed_scenario_sets', function (Blueprint $t) {
                $t->json('overlays_at_approval')->nullable()->comment('The register overlays in force when the set was approved');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('governed_scenario_sets', 'overlays_at_approval')) {
            Schema::table('governed_scenario_sets', fn (Blueprint $t) => $t->dropColumn('overlays_at_approval'));
        }
        if (Schema::hasColumn('loan_books', 'fli_overlay_ids')) {
            Schema::table('loan_books', fn (Blueprint $t) => $t->dropColumn('fli_overlay_ids'));
        }
        Schema::dropIfExists('fli_overlays');
    }
};
