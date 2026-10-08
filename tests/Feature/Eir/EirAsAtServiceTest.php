<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\EirAsAtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The EIR as at any date (spec v4 section 6.11): a month-end reads the
 * locked roll-forward row; a date inside a month accrues the EIR on the
 * actual days from the prior month-end less the cash received; the gross
 * carrying amount is the ledger's running balance; a date after the last
 * posting is refused with that date named.
 */
class EirAsAtServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('product_type')->nullable(); $t->string('gl_account_code')->nullable(); $t->decimal('eir_effective_annual', 12, 8)->nullable(); $t->decimal('eir_nominal_annual', 12, 8)->nullable(); $t->decimal('contractual_rate', 12, 8)->nullable(); $t->string('rate_type')->nullable(); $t->string('calculation_status')->nullable(); $t->timestamp('calculated_at')->nullable(); $t->timestamp('locked_at')->nullable(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->decimal('opening_gross', 20, 2); $t->decimal('interest_accrued', 20, 2); $t->string('interest_basis')->nullable(); $t->decimal('cash_received', 20, 2); $t->decimal('closing_gross', 20, 2); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version'); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->decimal('principal_due', 20, 2); $t->decimal('interest_due', 20, 2); $t->decimal('fee_due', 20, 2)->default(0); });
        Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 12, 8)->nullable(); $t->decimal('new_reference_rate', 12, 8)->nullable(); $t->integer('new_schedule_version')->nullable(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->timestamp('locked_at'); $t->string('reason'); $t->text('settings_snapshot')->nullable(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('external_identity_id')->nullable(); $t->string('reporting_period'); });
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('d', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $a = '000104420000005';
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'external_identity_id' => $a, 'reporting_period' => '2025-12']);
        DB::table('contract_eir')->insert(['contract_id' => '104420000005', 'customer_name' => 'Micholess Creamery', 'product_type' => 'Term', 'gl_account_code' => '1050101', 'eir_effective_annual' => 0.30, 'contractual_rate' => 0.26, 'rate_type' => 'FIXED', 'calculation_status' => 'LOCKED', 'calculated_at' => '2026-10-01 10:00:00', 'locked_at' => '2026-10-01 10:00:00']);
        DB::table('eir_amortisation')->insert([
            ['contract_id' => '104420000005', 'reporting_period' => '2025-11', 'opening_gross' => 1000000, 'interest_accrued' => 22000, 'cash_received' => 50000, 'closing_gross' => 972000],
            ['contract_id' => '104420000005', 'reporting_period' => '2025-12', 'opening_gross' => 972000, 'interest_accrued' => 21500, 'cash_received' => 50000, 'closing_gross' => 943500],
        ]);
        foreach (['2026-01-31', '2026-02-28'] as $d) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => '104420000005', 'schedule_version' => 1, 'effective_from' => '2025-01-01', 'due_date' => $d, 'principal_due' => 30000, 'interest_due' => 20000]);
        }
        $i = 0;
        foreach ([['2025-10-01', '301', '-1000000.00'], ['2025-11-30', '303', '-21000.00'], ['2025-11-30', '305', '50000.00'], ['2025-12-31', '303', '-20500.00'], ['2025-12-31', '305', '50000.00'], ['2026-01-10', '305', '30000.00'], ['2026-01-31', '303', '-20000.00']] as [$d, $type, $amt]) {
            $i++;
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => (string) $i, 'account' => $a, 'row_date' => $d, 'payload' => json_encode(['CUMVOUCH_DET_ID' => (string) $i, 'TRANSAMT' => $amt, 'TRANTYPE' => $type, 'DELETE_FLAG' => 'N']), 'row_hash' => md5((string) $i), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function service(): EirAsAtService
    {
        return new EirAsAtService(new LandingZoneReader());
    }

    public function test_a_month_end_reads_the_locked_roll_forward_row(): void
    {
        $v = $this->service()->contract('104420000005', '2025-12-31');
        $this->assertTrue($v['is_month_end']);
        $this->assertEquals(943500, $v['amortised_cost']);
        $this->assertStringContainsString('locked roll-forward row for 2025-12', $v['amortised_cost_basis']);
        $this->assertEquals(941500, $v['gross_carrying_amount']);          // 1,000,000 + 21,000 + 20,500 - 100,000
        $this->assertEquals(21500, $v['interest']['eir_period_to_date']);
        $this->assertEquals(20500, $v['interest']['contractual_period_to_date']);
        $this->assertEquals(43500, $v['interest']['eir_year_to_date']);
        $this->assertEquals(41500, $v['interest']['contractual_year_to_date']);
        $this->assertEquals(2000, $v['interest']['difference_year_to_date']);
        $this->assertSame('2025-11', $v['interest']['cumulative_from']);
        $this->assertCount(2, $v['remaining_cash_flows']);
        $this->assertSame(1, $v['eir']['schedule_version']);
    }

    public function test_a_date_inside_a_month_accrues_on_actual_days_less_cash_received(): void
    {
        $v = $this->service()->contract('104420000005', '2026-01-15');
        $this->assertFalse($v['is_month_end']);
        $expectedInterest = round(943500 * ((1.30) ** (15 / 365) - 1), 2);
        $this->assertEquals($expectedInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round(943500 + $expectedInterest - 30000, 2), $v['amortised_cost']);
        $this->assertStringContainsString('15/365 days', $v['amortised_cost_basis']);
        $this->assertEquals(911500, $v['gross_carrying_amount']);            // 941,500 - 30,000 received on 10 Jan
        $this->assertEquals(0, $v['interest']['contractual_period_to_date']);  // the January charge is posted on the 31st
        $this->assertEquals($expectedInterest, $v['interest']['eir_year_to_date']);
    }

    public function test_a_date_after_the_last_posting_is_refused_with_the_date_named(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last loaded posting is dated 2026-01-31');
        $this->service()->contract('104420000005', '2026-02-01');
    }

    public function test_the_book_rolls_up_by_product_and_gl(): void
    {
        $b = $this->service()->book('2025-12-31');
        $this->assertSame(1, $b['total']['contracts']);
        $this->assertEquals(2000, $b['total']['difference']);
        $this->assertSame('Term', $b['by_product'][0]['key']);
        $this->assertSame('1050101', $b['by_gl'][0]['key']);
        $this->assertEquals(943500, $b['by_gl'][0]['amortised_cost']);
    }
}
