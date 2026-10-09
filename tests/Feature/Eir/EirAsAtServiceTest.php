<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirAsAtController;
use App\Models\User;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\EirAsAtService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * The EIR as at any date (spec v4 section 6.11): a month-end reads the
 * locked roll-forward row; a date inside a month accrues the part month as
 * section 3 says, simple interest at the contractual rate on the prior
 * month-end balance for the actual days over 365, less the cash received;
 * whole months without a roll-forward row are rolled at the monthly EIR; a
 * take-on loan opens at its take-on basis; the gross carrying amount is the
 * ledger's running balance; a date after the last posting is refused with
 * that date named; the download comes as CSV, Excel or PDF (system audit of
 * 9 October 2026, finding M13).
 */
class EirAsAtServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->text('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('contract_takeon', function (Blueprint $t) { $t->increments('id'); $t->string('account'); $t->string('contract_id'); $t->string('basis'); $t->date('origination_date')->nullable(); $t->decimal('takeon_posting', 20, 4)->nullable(); $t->decimal('takeon_opening_interest', 20, 4)->nullable(); $t->decimal('takeon_opening_recovery', 20, 4)->nullable(); $t->decimal('takeon_balance', 20, 4)->nullable(); $t->decimal('recomputed_eir', 12, 8)->nullable(); $t->decimal('recomputed_amortised_cost', 20, 4)->nullable(); $t->string('setting_value')->nullable(); });
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

    /** Finding M13 (a): the part month is simple interest at the contractual rate on ACT/365, not the EIR compounded. */
    public function test_a_date_inside_a_month_accrues_the_part_month_at_the_contractual_rate_on_actual_days_less_cash_received(): void
    {
        $v = $this->service()->contract('104420000005', '2026-01-15');
        $this->assertFalse($v['is_month_end']);
        $expectedInterest = round(943500 * 0.26 * 15 / 365, 2);
        $this->assertEquals($expectedInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round(943500 + $expectedInterest - 30000, 2), $v['amortised_cost']);
        $this->assertStringContainsString('contractual rate 26.0000% simple on 15/365 days (section 3)', $v['amortised_cost_basis']);
        $this->assertEquals(911500, $v['gross_carrying_amount']);            // 941,500 - 30,000 received on 10 Jan
        $this->assertEquals(0, $v['interest']['contractual_period_to_date']);  // the January charge is posted on the 31st
        $this->assertEquals($expectedInterest, $v['interest']['eir_year_to_date']);
        $this->assertSame(0, $v['inputs']['months_rolled']);
        $this->assertSame('roll-forward row for 2025-12', $v['opening']['basis']);
        $this->assertNull($v['takeon']);
    }

    /** A month the revenue run has not reached is rolled at the monthly EIR before the part month is accrued. */
    public function test_whole_months_without_a_roll_forward_row_are_rolled_at_the_monthly_eir(): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => '99', 'account' => '000104420000005', 'row_date' => '2026-02-10', 'payload' => json_encode(['CUMVOUCH_DET_ID' => '99', 'TRANSAMT' => '40000.00', 'TRANTYPE' => '305', 'DELETE_FLAG' => 'N']), 'row_hash' => md5('99'), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $v = $this->service()->contract('104420000005', '2026-02-10');
        $monthly = 1.30 ** (1 / 12) - 1;
        $janInterest = round(943500 * $monthly, 2);
        $janClosing = round(943500 + $janInterest - 30000, 2);   // January rolled: interest at the monthly EIR, less the 30,000 received on 10 Jan
        $febInterest = round($janClosing * 0.26 * 10 / 365, 2);  // then the part month at the contractual rate
        $this->assertSame(1, $v['inputs']['months_rolled']);
        $this->assertEquals($janClosing, end($v['roll_forward'])['closing']);
        $this->assertSame('2026-01', end($v['roll_forward'])['period']);
        $this->assertEquals($febInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round($janClosing + $febInterest - 40000, 2), $v['amortised_cost']);
        $this->assertEquals(round($janInterest + $febInterest, 2), $v['interest']['eir_year_to_date']);
        $this->assertEquals(20000, $v['interest']['contractual_year_to_date']);
        $this->assertStringContainsString('1 month(s) rolled from the roll-forward row for 2025-12', $v['amortised_cost_basis']);
    }

    /** Finding M13 (c): a take-on loan the revenue run has not reached opens at its take-on basis. */
    public function test_a_takeon_loan_with_no_roll_forward_opens_at_its_takeon_basis(): void
    {
        DB::table('eir_amortisation')->delete();
        $a = '000104420000005';
        // the ledger: the three opening legs on the migration day, then a receipt and an interest posting
        DB::table('ebanker_raw_rows')->delete();
        $i = 0;
        foreach ([['2024-07-31', '301', '-1000000.00'], ['2024-07-31', '303', '-50000.00'], ['2024-07-31', '305', '200000.00'], ['2024-08-20', '305', '30000.00'], ['2024-08-31', '303', '-19000.00']] as [$d, $type, $amt]) {
            $i++;
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => 't' . $i, 'account' => $a, 'row_date' => $d, 'payload' => json_encode(['CUMVOUCH_DET_ID' => 't' . $i, 'TRANSAMT' => $amt, 'TRANTYPE' => $type, 'DELETE_FLAG' => 'N']), 'row_hash' => md5('t' . $i), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('contract_takeon')->insert(['account' => $a, 'contract_id' => '104420000005', 'basis' => 'RECOMPUTED', 'origination_date' => '2021-03-15', 'takeon_posting' => 1000000, 'takeon_opening_interest' => 50000, 'takeon_opening_recovery' => 200000,
            'takeon_balance' => 850000, 'recomputed_eir' => 0.28, 'recomputed_amortised_cost' => 842000.50, 'setting_value' => 'Recompute where block and fees exist, else take-on balance']);

        $v = $this->service()->contract('104420000005', '2024-08-20');
        $this->assertEquals(842000.50, $v['opening']['amount']);
        $this->assertSame('2024-07', $v['opening']['period']);
        $this->assertStringContainsString('take-on basis RECOMPUTED: amortised cost 842,000.50', $v['amortised_cost_basis']);
        $expectedInterest = round(842000.50 * 0.26 * 20 / 365, 2);
        $this->assertEquals($expectedInterest, $v['interest']['eir_period_to_date']);
        $this->assertEquals(round(842000.50 + $expectedInterest - 30000, 2), $v['amortised_cost']);
        $this->assertSame('RECOMPUTED', $v['takeon']['basis']);
        $this->assertEquals(850000, $v['takeon']['takeon_balance']);
        $this->assertSame('2024-08', $v['interest']['cumulative_from']);

        // under the take-on-balance basis the opening is E-Banker's carrying amount after the opening legs
        DB::table('contract_takeon')->update(['basis' => 'TAKEON_BALANCE', 'recomputed_eir' => null, 'recomputed_amortised_cost' => null]);
        $v = $this->service()->contract('104420000005', '2024-08-20');
        $this->assertEquals(850000, $v['opening']['amount']);
        $this->assertStringContainsString('take-on basis TAKEON_BALANCE: E-Banker take-on balance 850,000.00', $v['amortised_cost_basis']);
        $this->assertEquals(round(850000 + round(850000 * 0.26 * 20 / 365, 2) - 30000, 2), $v['amortised_cost']);

        // a date before the take-on has no opening at all
        $v = $this->service()->contract('104420000005', '2024-06-30');
        $this->assertNull($v['amortised_cost']);
        $this->assertStringContainsString('no take-on basis', $v['amortised_cost_basis']);
    }

    /** Finding M13 (b): the download comes as CSV, Excel or PDF from one payload. */
    public function test_the_download_comes_as_csv_excel_or_pdf(): void
    {
        DB::table('users')->insert(['id' => 7, 'name' => 'Finance Officer', 'email' => 'finance@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::findOrFail(7));
        $controller = new EirAsAtController();
        $service = $this->service();

        $csv = $controller->export(Request::create('/eir-as-at/export', 'GET', ['date' => '2025-12-31']), $service);
        $this->assertInstanceOf(StreamedResponse::class, $csv);
        $this->assertStringContainsString('EIR as at 2025-12-31.csv', $csv->headers->get('content-disposition'));

        $xlsx = $controller->export(Request::create('/eir-as-at/export', 'GET', ['date' => '2025-12-31', 'format' => 'xlsx']), $service);
        $this->assertInstanceOf(BinaryFileResponse::class, $xlsx);
        $this->assertStringContainsString('EIR as at 2025-12-31.xlsx', $xlsx->headers->get('content-disposition'));

        $pdf = $controller->export(Request::create('/eir-as-at/export', 'GET', ['date' => '2025-12-31', 'format' => 'pdf']), $service);
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringContainsString('EIR as at 2025-12-31.pdf', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $report = $controller->reportPayload($service->book('2025-12-31'));
        $this->assertSame('EIR as at 2025-12-31', $report['title']);
        $this->assertSame('2,000.00', $report['kpis'][3]['value']);
        $this->assertSame(['By product', 'By GL', 'By contract', 'How the figures are worked out'], array_column($report['sections'], 'heading'));
        $this->assertSame(['104420000005', 'Micholess Creamery', 'Term', '1050101', '43,500.00', '41,500.00', '2,000.00', '943,500.00', '941,500.00'], $report['sections'][2]['rows'][0]);
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'EIR As At Exported')->count());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->export(Request::create('/eir-as-at/export', 'GET', ['date' => '2025-12-31', 'format' => 'docx']), $service);
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
