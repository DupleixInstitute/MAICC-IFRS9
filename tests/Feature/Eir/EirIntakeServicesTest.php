<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\FeeImportService;
use App\Services\Eir\ExtractBImportService;
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

    public function test_extract_b_splits_schedule_actuals_and_fees(): void
    {
        $this->seedLoan('000104450000053', 100);
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

        $result = app(ExtractBImportService::class)->import($rows);

        $this->assertSame(2, $result['scheduled_rows_routed']);
        $this->assertSame(1, $result['actual_rows_loaded']);
        $this->assertSame(1, $result['fee_rows_routed']);
        $this->assertSame(2, DB::table('contract_cashflow_schedule')->count());
        $this->assertSame(1, DB::table('eir_actual_transactions')->count());
        $this->assertSame('PENDING', DB::table('contract_fees')->value('classification_status'));
        $this->assertSame('MAIIC_EXTRACT_B', DB::table('contract_fees')->value('source_system'));
    }

    public function test_extract_b_holds_unknown_loans_and_rejects_customer_conflicts(): void
    {
        $this->seedLoan('KNOWN', 100, '93');
        $base = [
            'sub_account_no' => '1', 'transaction_date' => '2025-01-31',
            'transaction_type' => 'Scheduled Repayment', 'principal_component' => 100,
            'interest_component' => 10, 'fee_component' => 0, 'total_amount' => 110,
            'scheduled_actual_flag' => 'Scheduled',
        ];
        $result = app(ExtractBImportService::class)->import([
            $base + ['contract_id' => 'MISSING', 'customer_id' => '93', 'gl_posting_ref' => 'X-1'],
            $base + ['contract_id' => 'KNOWN', 'customer_id' => 'WRONG', 'gl_posting_ref' => 'X-2'],
        ]);

        $this->assertArrayHasKey('MISSING', $result['held']);
        $this->assertArrayHasKey('KNOWN', $result['skipped']);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
    }

    public function test_extract_b_partial_schedule_is_not_misrepresented_as_original_schedule(): void
    {
        $this->seedLoan('PARTIAL', 100, '93');
        $result = app(ExtractBImportService::class)->import([[
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
