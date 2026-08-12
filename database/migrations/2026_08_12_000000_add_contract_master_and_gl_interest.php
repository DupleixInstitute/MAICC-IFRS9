<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.7 — contract master (Extract A) and GL interest postings (Extract C).
 *
 * Extract A is a facility master: one row per contract, upserted, never a
 * monthly snapshot. Only the origination attributes that are not already
 * reachable through a stable loan_books/client join are added to
 * contract_eir — customer name, portfolio and product stay on their existing
 * owners rather than being copied into a second facility master.
 *
 * Extract C is genuinely new: nothing in the schema holds what the GL
 * actually posted, which is the other half of the Phase 6 reconciliation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_eir', function (Blueprint $table) {
            // Contractual terms carried by Extract A but not derivable from the
            // tape. contractual_rate is stored as a decimal fraction (0.3210),
            // matching the reader's 'percent' transform, so it is never
            // ambiguous whether 32.1 means percent or multiple.
            $table->decimal('contractual_rate', 8, 5)->nullable()->after('markup');
            $table->string('rate_basis', 40)->nullable()->after('contractual_rate');
            $table->unsignedSmallInteger('tenor_months')->nullable()->after('payments_per_year');
            $table->date('first_repayment_date')->nullable()->after('origination_date');
            $table->date('maturity_date')->nullable()->after('first_repayment_date');
            $table->date('closure_date')->nullable()->after('maturity_date');
            $table->date('last_restructure_date')->nullable()->after('closure_date');

            $table->string('currency', 3)->nullable()->after('drawn_amount');
            $table->string('sub_account_no', 60)->nullable()->after('contract_id');
            $table->string('gl_account_code', 60)->nullable()->after('sub_account_no');

            // IFRS 9 transition anchor: the amortised cost MAIIC carried at the
            // start of the comparative period. The roll-forward needs an
            // opening balance it did not itself compute.
            $table->decimal('opening_amortised_cost', 20, 2)->nullable()->after('currency');
            $table->date('opening_amortised_cost_date')->nullable()->after('opening_amortised_cost');

            // Import lineage — which delivered file last wrote these terms.
            $table->string('terms_source_system', 40)->nullable()->after('schedule_source');
            $table->string('terms_source_reference', 120)->nullable()->after('terms_source_system');
            $table->timestamp('terms_imported_at')->nullable()->after('terms_source_reference');
        });

        Schema::create('gl_interest_postings', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id', 199)->index();
            $table->string('gl_account_code', 60)->nullable();

            // Period grain. reporting_period is the first of the month so the
            // table joins to eir_amortisation and loan_books without every
            // consumer re-deriving a date from year/month integers.
            $table->string('period_type', 20)->default('MONTHLY');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('reporting_period')->index();

            $table->decimal('interest_income_posted', 20, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->text('posting_references')->nullable();
            $table->text('row_note')->nullable();
            $table->date('generated_on')->nullable();

            $table->string('source_system', 40);
            $table->string('source_reference', 120)->nullable();
            $table->string('external_transaction_id', 191);
            $table->timestamps();

            // Two protections, deliberately distinct: the natural key stops the
            // same loan/period/account being counted twice by a re-delivered
            // file, and the external id stops the same source row being
            // re-imported under a different natural key after a vendor fix.
            $table->unique(
                ['contract_id', 'period_year', 'period_month', 'gl_account_code'],
                'gl_interest_period_unique'
            );
            $table->unique(['source_system', 'external_transaction_id'], 'gl_interest_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_interest_postings');

        Schema::table('contract_eir', function (Blueprint $table) {
            $table->dropColumn([
                'contractual_rate', 'rate_basis', 'tenor_months',
                'first_repayment_date', 'maturity_date', 'closure_date',
                'last_restructure_date', 'currency', 'sub_account_no',
                'gl_account_code', 'opening_amortised_cost',
                'opening_amortised_cost_date', 'terms_source_system',
                'terms_source_reference', 'terms_imported_at',
            ]);
        });
    }
};
