<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\ContractMasterImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\GlInterestImportService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\ScheduleImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Import-service coverage on a private in-memory sqlite schema (same
 * isolation pattern as EclGoldenNumberTest): only the tables the services
 * touch, no migrations, no developer database.
 */
class EirIntakeServicesTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();
    }

    private function buildSchema(): void
    {
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('customer_id')->nullable();
            $t->string('reporting_period')->nullable();
            $t->double('disbursed')->nullable();
            $t->double('principal_balance')->nullable();
            $t->string('create_date')->nullable();
            $t->timestamps();
        });

        Schema::create('contract_cashflow_schedule', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->unsignedSmallInteger('schedule_version')->default(1);
            $t->string('effective_from')->nullable();
            $t->string('due_date');
            $t->double('principal_due')->default(0);
            $t->double('interest_due')->default(0);
            $t->double('fee_due')->default(0);
            $t->string('schedule_source')->default('IMPORTED');
            $t->string('source_system')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id')->nullable();
            $t->timestamps();
        });

        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('instrument_type')->default('AMORTISED_LOAN');
            $t->string('schedule_source')->nullable();
            $t->string('rate_source')->default('CONTRACTUAL_PROXY');
            // Contract master (Extract A) terms — mirrors the production
            // defaults, including the ones that are dangerous when imported.
            $t->string('sub_account_no')->nullable();
            $t->string('gl_account_code')->nullable();
            $t->string('currency', 3)->nullable();
            $t->string('rate_type')->default('FIXED');
            $t->double('contractual_rate')->nullable();
            $t->string('rate_basis')->nullable();
            $t->double('reference_rate_at_origination')->nullable();
            $t->double('markup')->nullable();
            $t->string('origination_date')->nullable();
            $t->string('first_repayment_date')->nullable();
            $t->string('maturity_date')->nullable();
            $t->string('closure_date')->nullable();
            $t->string('last_restructure_date')->nullable();
            $t->double('approved_amount')->nullable();
            $t->double('drawn_amount')->nullable();
            $t->unsignedSmallInteger('payments_per_year')->default(12);
            $t->string('frequency_source')->default('ASSUMED');
            $t->unsignedSmallInteger('tenor_months')->nullable();
            $t->unsignedSmallInteger('moratorium_months')->default(0);
            $t->double('opening_amortised_cost')->nullable();
            $t->string('opening_amortised_cost_date')->nullable();
            $t->string('terms_source_system')->nullable();
            $t->string('terms_source_reference')->nullable();
            $t->string('terms_imported_at')->nullable();
            $t->string('locked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('gl_interest_postings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('gl_account_code')->nullable();
            $t->string('period_type')->default('MONTHLY');
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->string('reporting_period');
            $t->double('interest_income_posted')->default(0);
            $t->unsignedInteger('transaction_count')->default(0);
            $t->text('posting_references')->nullable();
            $t->text('row_note')->nullable();
            $t->string('generated_on')->nullable();
            $t->string('source_system');
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id');
            $t->timestamps();
        });

        Schema::create('contract_fees', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('fee_type');
            $t->double('amount');
            $t->string('basis')->default('ON_APPROVED');
            $t->string('description')->nullable();
            $t->string('transaction_date')->nullable();
            $t->string('cashflow_direction')->nullable();
            $t->string('currency')->nullable();
            $t->string('source_system')->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id')->nullable();
            $t->boolean('integral')->nullable();
            $t->string('classification_status')->default('PENDING');
            $t->text('classification_reason')->nullable();
            $t->unsignedInteger('suggested_rule_id')->nullable();
            $t->boolean('suggested_integral')->nullable();
            $t->unsignedInteger('classified_by')->nullable();
            $t->string('classified_at')->nullable();
            $t->unsignedInteger('reviewed_by')->nullable();
            $t->string('reviewed_at')->nullable();
            $t->string('gl_account_ref')->nullable();
            $t->timestamps();
        });

        Schema::create('eir_accounting_rules', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('fee_type')->nullable();
            $t->string('description_contains')->nullable();
            $t->string('gl_account_ref')->nullable();
            $t->string('cashflow_direction')->nullable();
            $t->boolean('proposed_integral');
            $t->text('rationale');
            $t->unsignedInteger('priority')->default(100);
            $t->boolean('active')->default(true);
            $t->string('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('eir_actual_transactions', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('customer_id')->nullable();
            $t->string('sub_account_no')->nullable();
            $t->string('transaction_date');
            $t->string('transaction_type');
            $t->double('principal_component')->default(0);
            $t->double('interest_component')->default(0);
            $t->double('fee_component')->default(0);
            $t->double('total_amount')->default(0);
            $t->double('balance_after_transaction')->nullable();
            $t->string('source_system');
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id');
            $t->text('row_note')->nullable();
            $t->timestamps();
        });
    }

    private function seedLoan(string $contractId, float $disbursed = 100_000_000, string $customerId = '93'): void
    {
        DB::table('loan_books')->insert([
            'contract_id'       => $contractId,
            'customer_id'       => $customerId,
            'reporting_period'  => '2026-06-30',
            'disbursed'         => $disbursed,
            'principal_balance' => $disbursed,
            'create_date'       => '2025-05-22',
        ]);
    }

    /**
     * The tape is seeded unpadded and the extract rows padded, because that is
     * exactly how the two sources arrive: E-Banker pads to 15 characters, the
     * loan tape does not. The join only works if contract_id is canonicalised.
     */
    public function test_contract_transactions_split_schedule_actuals_and_fees(): void
    {
        $this->seedLoan('104450000053', 100);
        $rows = [
            [
                'run_id' => '1', 'customer_id' => '93', 'contract_id' => '000104450000053',
                'sub_account_no' => '1', 'gl_posting_ref' => 'S-1', 'transaction_date' => '2025-01-31',
                'transaction_type' => 'Scheduled Repayment', 'principal_component' => 50,
                'interest_component' => 10, 'fee_component' => 0, 'total_amount' => 60,
                'scheduled_actual_flag' => 'Scheduled',
            ],
            [
                'run_id' => '1', 'customer_id' => '93', 'contract_id' => '000104450000053',
                'sub_account_no' => '1', 'gl_posting_ref' => 'S-2', 'transaction_date' => '2025-02-28',
                'transaction_type' => 'Scheduled Repayment', 'principal_component' => 50,
                'interest_component' => 5, 'fee_component' => 0, 'total_amount' => 55,
                'scheduled_actual_flag' => 'Scheduled',
            ],
            [
                'run_id' => '1', 'customer_id' => '93', 'contract_id' => '000104450000053',
                'sub_account_no' => '1', 'gl_posting_ref' => 'A-1', 'transaction_date' => '2025-01-15',
                'transaction_type' => 'Fee', 'principal_component' => 0,
                'interest_component' => 0, 'fee_component' => 4, 'total_amount' => 4,
                'scheduled_actual_flag' => 'Actual', 'row_note' => 'sample fee',
            ],
        ];

        $result = app(ContractTransactionImportService::class)->import($rows);

        $this->assertSame(2, $result['scheduled_rows_routed']);
        $this->assertSame(1, $result['actual_rows_loaded']);
        $this->assertSame(1, $result['fee_rows_routed']);
        $this->assertSame(2, DB::table('contract_cashflow_schedule')->count());
        $this->assertSame(1, DB::table('eir_actual_transactions')->count());
        $this->assertSame('PENDING', DB::table('contract_fees')->value('classification_status'));
        $this->assertSame('MAIIC_EXTRACT_B', DB::table('contract_fees')->value('source_system'));

        // Everything downstream must be keyed on the canonical identifier, not
        // the padded form the extract happened to arrive in.
        $this->assertSame('104450000053', DB::table('contract_cashflow_schedule')->value('contract_id'));
        $this->assertSame('104450000053', DB::table('eir_actual_transactions')->value('contract_id'));
        $this->assertSame('104450000053', DB::table('contract_fees')->value('contract_id'));
    }

    public function test_contract_transactions_hold_unknown_loans_and_rejects_customer_conflicts(): void
    {
        $this->seedLoan('KNOWN', 100, '93');
        $base = [
            'sub_account_no' => '1', 'transaction_date' => '2025-01-31',
            'transaction_type' => 'Scheduled Repayment', 'principal_component' => 100,
            'interest_component' => 10, 'fee_component' => 0, 'total_amount' => 110,
            'scheduled_actual_flag' => 'Scheduled',
        ];
        $result = app(ContractTransactionImportService::class)->import([
            $base + ['contract_id' => 'MISSING', 'customer_id' => '93', 'gl_posting_ref' => 'X-1'],
            $base + ['contract_id' => 'KNOWN', 'customer_id' => 'WRONG', 'gl_posting_ref' => 'X-2'],
        ]);

        $this->assertArrayHasKey('MISSING', $result['held']);
        $this->assertArrayHasKey('KNOWN', $result['skipped']);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
    }

    public function test_contract_transactions_partial_schedule_is_not_misrepresented_as_original_schedule(): void
    {
        $this->seedLoan('PARTIAL', 100, '93');
        $result = app(ContractTransactionImportService::class)->import([[
            'customer_id' => '93', 'contract_id' => 'PARTIAL', 'sub_account_no' => '1',
            'gl_posting_ref' => 'P-1', 'transaction_date' => '2025-12-31',
            'transaction_type' => 'Scheduled Repayment', 'principal_component' => 20,
            'interest_component' => 2, 'fee_component' => 0, 'total_amount' => 22,
            'scheduled_actual_flag' => 'Scheduled',
        ]]);

        $this->assertArrayHasKey('PARTIAL', $result['skipped']);
        $this->assertStringContainsString('does not reconcile', $result['skipped']['PARTIAL']);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
    }

    /* ------------------------------------------------------------------ */
    /* Contract master (Extract A)                                        */
    /* ------------------------------------------------------------------ */

    private function masterRow(array $overrides = []): array
    {
        return $overrides + [
            'run_id' => '7',
            'customer_id' => '93',
            'contract_id' => '000104450000053',
            'sub_account_no' => '1',
            'gl_account_code' => '30310',
            'currency' => 'MWK',
            'origination_date' => '2025-05-22',
            'first_repayment_date' => '2025-08-22',
            'maturity_date' => '2027-05-22',
            'approved_amount' => 100_000_000,
            'drawn_amount' => 100_000_000,
            'contractual_rate' => 32.1,
            'repayment_frequency' => 'Quarterly',
            'tenor_months' => 24,
            'moratorium_months' => 3,
            'arrangement_fee' => 2_500_000,
            'legal_fees' => 1_510_000,
        ];
    }

    public function test_contract_master_creates_terms_and_routes_origination_fees_as_pending(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(ContractMasterImportService::class)->import([$this->masterRow()]);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['incomplete']);

        $contract = DB::table('contract_eir')->where('contract_id', '104450000053')->first();
        $this->assertSame(4, (int) $contract->payments_per_year);
        $this->assertSame('STATED', $contract->frequency_source);
        $this->assertEqualsWithDelta(0.3210, (float) $contract->contractual_rate, 0.00001);
        $this->assertSame('2027-05-22', $contract->maturity_date);
        $this->assertSame('MAIIC_EXTRACT_A', $contract->terms_source_system);
        // Classification is an accounting judgement, never a product code.
        $this->assertSame('AMORTISED_LOAN', $contract->instrument_type);

        $fees = DB::table('contract_fees')->where('contract_id', '104450000053')->get();
        $this->assertCount(2, $fees);
        $this->assertSame(['PENDING', 'PENDING'], $fees->pluck('classification_status')->all());
        $this->assertNull($fees->first()->integral);
    }

    /**
     * The same master file arrives every month. A re-delivery of unchanged
     * terms must be a no-op, and it must not create a second fee line.
     */
    public function test_contract_master_reimport_is_idempotent(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        $service = app(ContractMasterImportService::class);

        $service->import([$this->masterRow()]);
        $second = $service->import([$this->masterRow()]);

        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(2, DB::table('contract_fees')->count());
    }

    public function test_contract_master_updates_changed_terms_only(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        $service = app(ContractMasterImportService::class);
        $service->import([$this->masterRow()]);

        $service->import([$this->masterRow([
            'maturity_date' => '2028-05-22',
            'last_restructure_date' => '2026-07-01',
        ])]);

        $contract = DB::table('contract_eir')->where('contract_id', '104450000053')->first();
        $this->assertSame('2028-05-22', $contract->maturity_date);
        $this->assertSame('2026-07-01', $contract->last_restructure_date);
        // Untouched terms survive the update.
        $this->assertSame(4, (int) $contract->payments_per_year);
    }

    /**
     * The solved rate's audited basis is the terms it was solved from. A file
     * that disagrees with a locked contract raises an exception; it does not
     * quietly invalidate the rate and its input snapshot.
     */
    public function test_contract_master_never_overwrites_a_locked_contract(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        app(ContractMasterImportService::class)->import([$this->masterRow()]);
        DB::table('contract_eir')->where('contract_id', '104450000053')
            ->update(['locked_at' => '2026-07-31 00:00:00']);

        $result = app(ContractMasterImportService::class)->import([
            $this->masterRow(['drawn_amount' => 90_000_000]),
        ]);

        $this->assertSame(0, $result['updated']);
        $this->assertArrayHasKey('104450000053', $result['skipped']);
        $this->assertStringContainsString('locked', $result['skipped']['104450000053']);
        $this->assertStringContainsString('drawn_amount', $result['skipped']['104450000053']);
        $this->assertEqualsWithDelta(100_000_000.0, (float) DB::table('contract_eir')
            ->where('contract_id', '104450000053')->value('drawn_amount'), 0.01);
    }

    /**
     * payments_per_year defaults to 12 in the schema. A facility whose
     * frequency the file spells unrecognisably must not inherit that default
     * silently — it would solve monthly and produce a plausible wrong rate.
     */
    public function test_unrecognised_frequency_is_reported_not_defaulted(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(ContractMasterImportService::class)->import([
            $this->masterRow(['repayment_frequency' => 'On demand']),
        ]);

        $this->assertSame(1, $result['created']);
        $this->assertArrayHasKey('on demand', $result['unknown_frequencies']);
        $this->assertArrayHasKey('104450000053', $result['incomplete']);
        $this->assertStringContainsString('repayment frequency', $result['incomplete']['104450000053']);

        // The contract keeps the monthly default — it still anchors schedules
        // and fees — but the default is recorded as an assumption, which is
        // what the readiness gate blocks on.
        $contract = DB::table('contract_eir')->where('contract_id', '104450000053')->first();
        $this->assertSame(12, (int) $contract->payments_per_year);
        $this->assertSame('ASSUMED', $contract->frequency_source);
    }

    /**
     * A later file that does state the frequency promotes the contract; a
     * sparse one that omits it must not demote a frequency already stated.
     */
    public function test_stated_frequency_survives_a_later_sparse_delivery(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        $service = app(ContractMasterImportService::class);

        $service->import([$this->masterRow(['repayment_frequency' => 'On demand'])]);
        $this->assertSame('ASSUMED', DB::table('contract_eir')->value('frequency_source'));

        $service->import([$this->masterRow(['repayment_frequency' => 'Quarterly'])]);
        $this->assertSame('STATED', DB::table('contract_eir')->value('frequency_source'));

        $service->import([$this->masterRow(['repayment_frequency' => ''])]);
        $contract = DB::table('contract_eir')->first();
        $this->assertSame('STATED', $contract->frequency_source);
        $this->assertSame(4, (int) $contract->payments_per_year);
    }

    /**
     * The delivered Extract A carries every facility twice. Where one row
     * simply omits a value the pair is merged — a blank is not a statement.
     */
    public function test_duplicate_rows_merge_when_one_side_is_blank(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(ContractMasterImportService::class)->import([
            $this->masterRow(['repayment_frequency' => '']),
            $this->masterRow(['repayment_frequency' => 'Quarterly']),
        ]);

        $this->assertSame(2, $result['source_rows']);
        $this->assertSame(1, $result['facilities']);
        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['skipped']);

        $contract = DB::table('contract_eir')->where('contract_id', '104450000053')->first();
        $this->assertSame(4, (int) $contract->payments_per_year);
        $this->assertSame('STATED', $contract->frequency_source);
    }

    /**
     * 17 facilities in the delivered file state two different frequencies
     * (Monthly vs Yearly among them). Resolving that by file order would be a
     * twelvefold error in the annualised rate decided by nothing, so the
     * facility is rejected with both values named.
     */
    public function test_duplicate_rows_that_genuinely_disagree_are_rejected(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(ContractMasterImportService::class)->import([
            $this->masterRow(['repayment_frequency' => 'Monthly']),
            $this->masterRow(['repayment_frequency' => 'Yearly']),
        ]);

        $this->assertSame(0, $result['created']);
        $this->assertArrayHasKey('104450000053', $result['skipped']);
        $this->assertStringContainsString('conflicting', $result['skipped']['104450000053']);
        $this->assertStringContainsString('Monthly', $result['skipped']['104450000053']);
        $this->assertStringContainsString('Yearly', $result['skipped']['104450000053']);
        $this->assertSame(0, DB::table('contract_eir')->count());
    }

    public function test_contract_master_holds_accounts_absent_from_the_tape(): void
    {
        $result = app(ContractMasterImportService::class)->import([
            $this->masterRow(['contract_id' => 'GHOST-9']),
        ]);

        $this->assertSame(0, $result['created']);
        $this->assertArrayHasKey('GHOST-9', $result['held']);
        $this->assertSame(0, DB::table('contract_eir')->count());
    }

    /* ------------------------------------------------------------------ */
    /* GL interest postings (Extract C)                                   */
    /* ------------------------------------------------------------------ */

    private function glRow(array $overrides = []): array
    {
        return $overrides + [
            'run_id' => '3',
            'contract_id' => '000104450000053',
            'gl_account_code' => '30310',
            'period_type' => 'MONTHLY',
            'period_year' => 2025,
            'period_month' => 10,
            'interest_income_posted' => 2_675_000,
            'transaction_count' => 4,
            'posting_references' => 'JV-1|JV-2',
        ];
    }

    public function test_gl_interest_loads_and_totals_by_period(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(GlInterestImportService::class)->import([
            $this->glRow(),
            $this->glRow(['period_month' => 11, 'interest_income_posted' => 2_594_082.67]),
        ]);

        $this->assertSame(2, $result['loaded_rows']);
        $this->assertSame(0, $result['negative_rows']);
        $this->assertEqualsWithDelta(5_269_082.67, $result['total_posted'], 0.01);
        $this->assertEqualsWithDelta(2_675_000.0, $result['periods']['2025-10'], 0.01);

        $posting = DB::table('gl_interest_postings')->first();
        $this->assertSame('104450000053', $posting->contract_id);
        $this->assertSame('2025-10-01', $posting->reporting_period);
        $this->assertSame('MAIIC_EXTRACT_C', $posting->source_system);
    }

    /** A re-delivered file must not double-count a period. */
    public function test_gl_interest_reimport_does_not_double_count(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        $service = app(GlInterestImportService::class);

        $service->import([$this->glRow()]);
        $second = $service->import([$this->glRow()]);

        $this->assertSame(0, $second['loaded_rows']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, DB::table('gl_interest_postings')->count());
    }

    /**
     * A changed figure for a period already loaded is a GL restatement. It is
     * applied, but named individually — the period may already have been
     * reconciled and signed off.
     */
    public function test_gl_restatement_is_applied_and_named(): void
    {
        $this->seedLoan('104450000053', 100_000_000);
        $service = app(GlInterestImportService::class);
        $service->import([$this->glRow()]);

        $result = $service->import([$this->glRow(['interest_income_posted' => 2_700_000])]);

        $this->assertSame(1, $result['restated_rows']);
        $this->assertNotEmpty($result['restatements']);
        $this->assertStringContainsString('2,675,000.00', implode(' ', $result['restatements']));
        $this->assertSame(1, DB::table('gl_interest_postings')->count());
        $this->assertEqualsWithDelta(2_700_000.0, (float) DB::table('gl_interest_postings')
            ->value('interest_income_posted'), 0.01);
    }

    /**
     * The Extract C sign convention is an open item. Negative rows are stored
     * as delivered and counted, never flipped — a silently corrected sign
     * would hide a real misstatement inside the reconciliation.
     */
    public function test_gl_interest_reports_negative_rows_without_correcting_them(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(GlInterestImportService::class)->import([
            $this->glRow(['interest_income_posted' => -2_675_000]),
        ]);

        $this->assertSame(1, $result['negative_rows']);
        $this->assertEqualsWithDelta(-2_675_000.0, (float) DB::table('gl_interest_postings')
            ->value('interest_income_posted'), 0.01);
    }

    public function test_gl_interest_rejects_rows_without_a_usable_period(): void
    {
        $this->seedLoan('104450000053', 100_000_000);

        $result = app(GlInterestImportService::class)->import([
            $this->glRow(['period_year' => null, 'period_month' => null]),
            $this->glRow(['period_month' => 13]),
        ]);

        $this->assertSame(0, $result['loaded_rows']);
        $this->assertCount(2, $result['skipped']);
        $this->assertSame(0, DB::table('gl_interest_postings')->count());
    }

    private function scheduleRows(string $contractId, int $n = 4, float $principalEach = 25_000_000): array
    {
        $rows = [];
        for ($i = 1; $i <= $n; $i++) {
            $rows[] = [
                'contract_id'   => $contractId,
                'due_date'      => sprintf('2025-%02d-22', 5 + $i),
                'principal_due' => $principalEach,
                'interest_due'  => 2_000_000,
            ];
        }

        return $rows;
    }

    /* ------------------------------------------------------------------ */
    /* Schedule import                                                    */
    /* ------------------------------------------------------------------ */

    public function test_happy_path_loads_schedule_and_stub(): void
    {
        $this->seedLoan('C-1');

        $result = (new ScheduleImportService())->import($this->scheduleRows('C-1'));

        $this->assertSame(1, $result['loaded_contracts']);
        $this->assertSame(4, $result['loaded_rows']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['held']);

        $this->assertSame(4, DB::table('contract_cashflow_schedule')->where('contract_id', 'C-1')->count());
        $this->assertSame('IMPORTED', DB::table('contract_eir')->where('contract_id', 'C-1')->value('schedule_source'));
        $this->assertSame(['covered' => 1, 'total' => 1], $result['coverage']);
    }

    public function test_contract_not_on_tape_is_held_not_rejected(): void
    {
        $result = (new ScheduleImportService())->import($this->scheduleRows('GHOST-1'));

        $this->assertSame(0, $result['loaded_contracts']);
        $this->assertArrayHasKey('GHOST-1', $result['held']);
        $this->assertStringContainsString('not on the loan tape', $result['held']['GHOST-1']);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
    }

    public function test_existing_schedule_is_never_overwritten(): void
    {
        $this->seedLoan('C-2');
        (new ScheduleImportService())->import($this->scheduleRows('C-2'));

        // Second import with different figures must be refused.
        $result = (new ScheduleImportService())->import($this->scheduleRows('C-2', 4, 10));

        $this->assertSame(0, $result['loaded_contracts']);
        $this->assertStringContainsString('restructure', $result['skipped']['C-2']);
        $this->assertSame(25_000_000.0, (float) DB::table('contract_cashflow_schedule')
            ->where('contract_id', 'C-2')->min('principal_due'));
    }

    public function test_principal_sum_must_reconcile_to_drawn(): void
    {
        $this->seedLoan('C-3', 100_000_000);

        // 4 x 20m = 80m vs 100m drawn: a truncated export, reject.
        $result = (new ScheduleImportService())->import($this->scheduleRows('C-3', 4, 20_000_000));

        $this->assertSame(0, $result['loaded_contracts']);
        $this->assertStringContainsString('does not reconcile', $result['skipped']['C-3']);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
    }

    public function test_duplicate_due_dates_reject_contract(): void
    {
        $this->seedLoan('C-4');
        $rows = $this->scheduleRows('C-4');
        $rows[1]['due_date'] = $rows[0]['due_date'];

        $result = (new ScheduleImportService())->import($rows);

        $this->assertStringContainsString('duplicate due dates', $result['skipped']['C-4']);
    }

    public function test_one_bad_contract_does_not_sink_the_file(): void
    {
        $this->seedLoan('GOOD-1');
        $this->seedLoan('BAD-1', 999_999_999); // principal won't reconcile

        $rows = array_merge(
            $this->scheduleRows('GOOD-1'),
            $this->scheduleRows('BAD-1')
        );

        $result = (new ScheduleImportService())->import($rows);

        $this->assertSame(1, $result['loaded_contracts']);
        $this->assertArrayHasKey('BAD-1', $result['skipped']);
        $this->assertSame(4, DB::table('contract_cashflow_schedule')->where('contract_id', 'GOOD-1')->count());
    }

    /* ------------------------------------------------------------------ */
    /* Fee import                                                         */
    /* ------------------------------------------------------------------ */

    public function test_fee_import_signed_lines_and_totals(): void
    {
        $result = (new FeeImportService())->import([
            ['contract_id' => 'NYAM-1', 'fee_type' => 'legal',       'amount' => 4_450_000.0],
            ['contract_id' => 'NYAM-1', 'fee_type' => 'legal',       'amount' => -1_990_000.0],
            ['contract_id' => 'NYAM-1', 'fee_type' => 'arrangement', 'amount' => 6_000_000.0],
        ]);

        $this->assertSame(3, $result['loaded_rows']);
        $this->assertSame(1, $result['negative_lines']);
        $this->assertEqualsWithDelta(2_460_000.0, $result['totals_by_type']['legal'], 0.01);
        $this->assertEqualsWithDelta(6_000_000.0, $result['totals_by_type']['arrangement'], 0.01);
    }

    public function test_unknown_fee_types_become_other_and_are_reported(): void
    {
        $result = (new FeeImportService())->import([
            ['contract_id' => 'BERL-1', 'fee_type' => 'MLS Levy Fee', 'amount' => 11_000.0],
        ]);

        $this->assertSame(1, $result['loaded_rows']);
        $this->assertArrayHasKey('mls levy fee', $result['unknown_types']);
        $this->assertSame('other', DB::table('contract_fees')->where('contract_id', 'BERL-1')->value('fee_type'));
    }

    public function test_imported_fee_stays_pending_and_rule_is_only_a_suggestion(): void
    {
        $ruleId = DB::table('eir_accounting_rules')->insertGetId([
            'name' => 'Arrangement fees', 'fee_type' => 'arrangement',
            'proposed_integral' => true, 'rationale' => 'Direct origination fee',
            'priority' => 10, 'active' => true, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new FeeImportService())->import([
            ['contract_id' => 'C-10', 'fee_type' => 'arrangement', 'amount' => 1000],
        ]);

        $fee = DB::table('contract_fees')->where('contract_id', 'C-10')->first();
        $this->assertSame('PENDING', $fee->classification_status);
        $this->assertNull($fee->integral);
        $this->assertSame($ruleId, $fee->suggested_rule_id);
        $this->assertSame(1, $fee->suggested_integral);
    }

    public function test_blank_contract_or_zero_amount_skipped(): void
    {
        $result = (new FeeImportService())->import([
            ['contract_id' => '',    'fee_type' => 'legal', 'amount' => 100.0],
            ['contract_id' => 'C-9', 'fee_type' => 'legal', 'amount' => 0.0],
        ]);

        $this->assertSame(0, $result['loaded_rows']);
        $this->assertSame(2, $result['skipped_rows']);
    }
}
