<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\FeeImportService;
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
            $t->boolean('integral')->default(true);
            $t->string('gl_account_ref')->nullable();
            $t->timestamps();
        });
    }

    private function seedLoan(string $contractId, float $disbursed = 100_000_000): void
    {
        DB::table('loan_books')->insert([
            'contract_id'       => $contractId,
            'reporting_period'  => '2026-06-30',
            'disbursed'         => $disbursed,
            'principal_balance' => $disbursed,
            'create_date'       => '2025-05-22',
        ]);
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
