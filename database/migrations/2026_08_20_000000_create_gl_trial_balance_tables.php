<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The GL side of the EIR reconciliation (spec §3.4).
 *
 * Until now the engine could only compare itself against Extract C, which
 * covers 3 of ~181 accounts. The 19-month trial-balance corpus is the only
 * complete income statement MAIIC has ever delivered, so it becomes the
 * control-total counterpart to eir_amortisation.
 *
 * Two tables, deliberately separate:
 *
 *   gl_account_scope       reference data — what each GL code IS. Seeded and
 *                          reviewable, because "these are the accounts in EIR
 *                          scope" is a judgement Dr Thom has to sign off, and
 *                          a constant in a service class cannot be signed off.
 *   gl_trial_balance_lines the delivered balances, as delivered.
 *
 * Monthly movements are deliberately NOT stored. They are a subtraction of two
 * rows in this table (§3.4.1), and computing them on read lets the report show
 * both balances beside the difference. An auditor who can see the arithmetic
 * needs to trust less of it than one handed a derived figure — and a re-import
 * cannot leave a stale movement behind.
 *
 * Note this is unrelated to `chart_of_accounts`, which is the application's own
 * accounting module. These are MAIIC's E-Banker and QuickBooks ledgers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_account_scope', function (Blueprint $table) {
            $table->id();
            $table->string('gl_code', 20)->index();
            $table->string('gl_title', 191);

            // MAIIC keeps statutory accounts in QuickBooks and the loan book in
            // E-Banker, and the codes differ (§3.4.4) — term-loan interest is
            // 42019/4201 in one and 4206 in the other. Storing the chart on the
            // row is what stops the two being silently treated as one.
            $table->string('chart', 20)->default('EBANKER');
            $table->string('quickbooks_code', 20)->nullable();

            // Drives the cumulative-YTD rule: P&L balances are differenced,
            // balance-sheet balances are point-in-time and must not be.
            $table->string('statement', 2);          // PL | BS
            $table->string('normal_balance', 2);     // DR | CR

            $table->string('category', 30);          // INTEREST_INCOME, FEE_INCOME, INVESTMENT_INCOME, ...
            $table->unsignedTinyInteger('eir_door')->nullable();
            $table->boolean('in_eir_scope')->default(false);
            $table->string('portfolio', 30)->nullable();

            // 1050103 / 1050203 are retired codes MAIIC confirmed in writing
            // (closed item #20). Flagging them stops ingestion reporting a
            // missing TB counterpart as a mapping defect every single run.
            $table->boolean('retired')->default(false);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['chart', 'gl_code'], 'gl_account_scope_chart_code_unique');
        });

        Schema::create('gl_trial_balance_lines', function (Blueprint $table) {
            $table->id();

            // First of the month. The delivered files stamp 2025-12-01 on a file
            // named "31 December 2025" (open item #22), so the stamp is stored
            // raw alongside as evidence of what we read rather than what we
            // concluded — a month of movement rides on that reading.
            $table->date('period')->index();
            $table->date('source_period_stamp')->nullable();

            $table->string('gl_code', 20)->index();
            $table->string('gl_title', 191);
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);

            // December 2025 exists twice and both are legitimate: the standalone
            // monthly file is POST-closing (121 lines, no P&L — swept to retained
            // earnings) and the AFS workbook sheet is PRE-closing (191 lines, full
            // P&L). Which one the engine treats as authoritative is open item #23,
            // so both are stored and the choice is made at read time rather than
            // being baked in here by whichever file happened to import last.
            $table->string('basis', 12)->default('POSTCLOSING');

            $table->string('source_file', 191);
            $table->string('source_sheet', 100)->nullable();
            $table->timestamps();

            $table->unique(['period', 'gl_code', 'basis'], 'gl_tb_period_code_basis_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_trial_balance_lines');
        Schema::dropIfExists('gl_account_scope');
    }
};
