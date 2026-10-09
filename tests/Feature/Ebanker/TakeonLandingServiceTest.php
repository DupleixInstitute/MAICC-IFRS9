<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The take-on workbook landed, not typed (spec v4 section 6.9): two blocks in
 * a generated original, a mapping workbook with the account, tick and fees;
 * every value lands with its cell; the gates name the block and cell; the
 * build writes contract_takeon under the seeded basis, recomputing only
 * where the block and its fees exist. The recompute itself (system audit of
 * 9 October 2026, finding M7): the workbook schedule becomes version 1 with
 * schedule_source TAKEON_WORKBOOK, the EIR is solved from origination on
 * the net investment, and the amortised cost is rolled forward month by
 * month to 31 July 2024 beside the take-on balance.
 */
class TakeonLandingServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_08_200000_create_takeon_tables', '2026_10_09_500000_takeon_recompute_from_origination'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->decimal('principal_due', 20, 2)->default(0); $t->decimal('interest_due', 20, 2)->default(0); $t->decimal('fee_due', 20, 2)->default(0); $t->string('schedule_source', 20)->default('IMPORTED'); $t->string('source_system', 50)->nullable(); $t->string('source_reference')->nullable(); $t->string('external_transaction_id')->nullable(); $t->timestamps(); $t->unique(['contract_id', 'schedule_version', 'due_date']); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('schedule_source', 20)->nullable(); $t->timestamps(); });
        DB::table('contract_eir')->insert(['contract_id' => '104420000005', 'schedule_source' => 'GENERATED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('c', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $a = '000104420000005'; $b = '000104420000009';
        foreach ([[$a, 'Micholess Creamery'], [$b, 'Edge View']] as [$acc, $name]) {
            $this->raw('P1_02', $acc, $acc, '2024-07-01', ['NEW_AC_NUMBER' => $acc, 'GLCODE' => '1050101', 'ACCOUNT_NAME' => $name, 'STATUS_CODE' => 'A']);
        }
        // the three opening legs of account A, back-dated to the loan but operated on the migration day
        $this->raw('P1_01', '1', $a, '2022-09-01', ['NEW_AC_NUMBER' => $a, 'TRANSAMT' => '-10000000.00', 'TRANTYPE' => '301', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening Account Balance', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '2', $a, '2022-09-01', ['NEW_AC_NUMBER' => $a, 'TRANSAMT' => '-500000.00', 'TRANTYPE' => '303', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening interest charge', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '3', $a, '2024-07-31', ['NEW_AC_NUMBER' => $a, 'TRANSAMT' => '2000000.00', 'TRANTYPE' => '305', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'By Trf Opening Recover Amount', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '4', $b, '2024-07-31', ['NEW_AC_NUMBER' => $b, 'TRANSAMT' => '-7000000.00', 'TRANTYPE' => '301', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening Account Balance', 'DELETE_FLAG' => 'N']);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'takeon_' . uniqid();
        mkdir($this->dir);
        $this->writeWorkbooks($a, $b);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function raw(string $q, string $key, string $account, string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date, 'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function writeWorkbooks(string $a, string $b): void
    {
        $o = new Spreadsheet();
        $lb = $o->getActiveSheet(); $lb->setTitle('Loan Book');
        $lb->fromArray(['#', 'Name', 'Type', 'Value Date', 'Maturity Date', 'Tenor', 'Moratorium', 'Interest Rate', 'Approved', 'Disbursed', 'Not yet', 'Principal', 'Interest', 'Repayments', 'Carrying'], null, 'D3');
        $lb->fromArray([1, 'Micholess Creamery', 'Debt', 44036, 44766, 2.0, '6 (Cap+Int)', 20.2, 20000000, 18240000, 1760000, 18240000, 1004624.69, -9000000, 10244624.69], null, 'D5');
        $lb->fromArray([2, 'Edge View', 'Debt', 43935, 45747, 5.0, '-', 0.3289, 300000000, 300000000, 0, 7000000, 0, 0, 7000000], null, 'D6');
        $am = $o->createSheet(); $am->setTitle('Amortisation and Repayments');
        // three instalments before the take-on and a balloon a year after it, so the EIR from origination solves on receipts that repay the loan
        $this->block($am, 4, 'Micholess Creamery', 10000000, 0.202, [[1, 44774, 44804, 10000000, 500000, 9500000], [2, 44805, 44834, 9500000, 500000, 9000000], [3, 45474, 45504, 9000000, 500000, 8500000], [4, 45505, 45869, 8500000, 9000000, 0]]);
        $this->block($am, 40, 'Edge View', 7000000, 0.25, [[1, 45444, 45473, 7000000, 100000, 6900000]]);
        (new Xlsx($o))->save($this->dir . DIRECTORY_SEPARATOR . 'original.xlsx');

        $m = new Spreadsheet();
        $map = $m->getActiveSheet(); $map->setTitle('Mapping');
        $map->fromArray(['Loan Book row'], null, 'A1');
        $map->setCellValue('A2', 5); $map->setCellValue('J2', $a); $map->setCellValue('K2', 'High'); $map->setCellValue('X2', 'Y');
        $map->setCellValue('A3', 6); $map->setCellValue('J3', $b); $map->setCellValue('K3', 'Medium');
        $us = $m->createSheet(); $us->setTitle('Upload summary');
        $us->setCellValue('C2', 5); $us->setCellValue('AL2', 1); $us->setCellValue('AO2', 150000); $us->setCellValue('AP2', 50000); $us->setCellValue('AR2', '15/07/2020'); $us->setCellValue('AS2', 'Y'); $us->setCellValue('AT2', 'Offer letter 12');
        $us->setCellValue('C3', 6); $us->setCellValue('AL3', 2);
        $bl = $m->createSheet(); $bl->setTitle('Blocks');
        $bl->fromArray(['Block #', 'Title', 'Principal', 'Rate', 'Sheet row', 'Go', 'Loan Book row', 'Facility', 'Account', 'Restructured'], null, 'A1');
        $bl->fromArray([1, '', '', '', 4, '', 5, '', '', 'False'], null, 'A2');
        $bl->fromArray([2, '', '', '', 40, '', 6, '', '', 'True'], null, 'A3');
        (new Xlsx($m))->save($this->dir . DIRECTORY_SEPARATOR . 'mapping.xlsx');
    }

    private function block($ws, int $row, string $title, float $principal, float $rate, array $lines): void
    {
        $ws->setCellValue("C{$row}", $title);
        $ws->setCellValue('C' . ($row + 2), 'Principal (MK)'); $ws->setCellValue('D' . ($row + 2), $principal);
        $ws->setCellValue('C' . ($row + 3), 'Rate'); $ws->setCellValue('D' . ($row + 3), $rate);
        $ws->setCellValue('C' . ($row + 4), 'n'); $ws->setCellValue('D' . ($row + 4), 12);
        $ws->setCellValue('C' . ($row + 5), 'Per'); $ws->setCellValue('D' . ($row + 5), 12);
        $ws->setCellValue('C' . ($row + 6), 'nPer'); $ws->setCellValue('D' . ($row + 6), count($lines));
        $ws->setCellValue('C' . ($row + 8), 'Pmt (MK)'); $ws->setCellValue('D' . ($row + 8), 500000);
        $ws->fromArray(['Period Start', 'Period End', 'Days', 'Opening bal.', 'Pmt', 'Interest', 'Principal', 'Closing balance', 'Amount Repaid', 'Accumulated Arrears'], null, 'C' . ($row + 10));
        foreach ($lines as $i => [$serial, $start, $end, $opening, $pmt, $closing]) {
            $ws->fromArray([$serial, $start, $end, 30, $opening, $pmt, 100000, $pmt - 100000, $closing, $pmt, 0], null, 'B' . ($row + 11 + $i));
        }
    }

    private function service(): TakeonLandingService
    {
        return new TakeonLandingService(new LandingZoneReader(), new GovernanceService());
    }

    public function test_the_workbook_lands_with_cells_gates_and_fees(): void
    {
        $r = $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(2, $r['blocks']);
        $this->assertSame(5, $r['lines']);
        $one = DB::table('takeon_blocks')->where('block_no', 1)->first();
        $this->assertSame('000104420000005', $one->account);
        $this->assertSame('Y', $one->confirmed);
        $this->assertEquals(10000000, $one->principal);
        $this->assertEquals(20.2, $one->rate);
        $this->assertSame('2020-07-24', $one->value_date);
        $this->assertEquals(200000, $one->total_fees);
        $this->assertSame('2020-07-15', $one->fee_date);
        $this->assertSame('Y', $one->fee_deducted);
        $this->assertSame('Amortisation and Repayments!D6', json_decode($one->cells, true)['principal']);
        $this->assertSame('Upload summary!AO2', json_decode($one->cells, true)['arrangement_fee']);
        $this->assertSame('MAPPED', $one->status);
        $two = DB::table('takeon_blocks')->where('block_no', 2)->first();
        $this->assertSame('FLAGGED', $two->status);
        $this->assertNull($two->total_fees);
        $this->assertStringContainsString('no fee row', json_decode($two->gates, true)['flags'][0]);
        $this->assertSame(1, (int) $two->restructured);
        $lines = DB::table('takeon_schedule_lines')->where('block_id', $one->id)->orderBy('serial')->get();
        $this->assertSame('2022-08-31', $lines[0]->period_end);
        $this->assertEquals(9500000, $lines[0]->closing_balance);
        $this->assertSame(15, (int) $lines[0]->sheet_row);
        $this->assertSame(2, $r['gates']['summary']['principal_exact']);
    }

    public function test_the_build_recomputes_only_where_the_block_and_fees_exist(): void
    {
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $c = $this->service()->build(1);
        $this->assertSame(['accounts' => 2, 'recomputed' => 1, 'takeon_balance' => 1, 'refused' => 0], array_intersect_key($c, array_flip(['accounts', 'recomputed', 'takeon_balance', 'refused'])));
        $a = DB::table('contract_takeon')->where('account', '000104420000005')->first();
        $this->assertSame('RECOMPUTED', $a->basis);
        $this->assertSame('104420000005', $a->contract_id);
        $this->assertEquals(10000000, $a->takeon_posting);
        $this->assertEquals(500000, $a->takeon_opening_interest);
        $this->assertEquals(2000000, $a->takeon_opening_recovery);
        $this->assertEquals(8500000, $a->schedule_balance_at_takeon);  // the line ending 31 Jul 2024 is the last on or before the take-on
        $this->assertEquals(-500000, $a->difference_at_takeon);          // 10m - 2m recovered - 8.5m scheduled: a prepayment
        $this->assertEquals(200000, $a->fees_total);
        $b = DB::table('contract_takeon')->where('account', '000104420000009')->first();
        $this->assertSame('TAKEON_BALANCE', $b->basis);
        $this->assertStringContainsString('fees not supplied', json_decode($b->flags, true)[0]);
        $this->assertStringContainsString('not yet confirmed', json_decode($b->flags, true)[1]);
        $this->assertNull($b->recomputed_eir);
        $this->assertSame(0, (int) $b->schedule_lines_written);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->where('contract_id', '104420000009')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Take-on Population Built')->count());
    }

    /** Finding M7: the recompute writes the schedule, solves the EIR from origination and rolls the amortised cost to the take-on date. */
    public function test_the_recompute_writes_the_workbook_schedule_solves_the_eir_and_rolls_forward_to_the_takeon(): void
    {
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $c = $this->service()->build(1);
        $this->assertSame(4, $c['schedule_lines']);
        $a = DB::table('contract_takeon')->where('account', '000104420000005')->first();
        $this->assertSame('RECOMPUTED', $a->basis);

        // the version 1 schedule from the workbook, dated and sourced
        $lines = DB::table('contract_cashflow_schedule')->where('contract_id', '104420000005')->orderBy('due_date')->get();
        $this->assertCount(4, $lines);
        $this->assertSame(4, (int) $a->schedule_lines_written);
        $this->assertSame(['2022-08-31', '2022-09-30', '2024-07-31', '2025-07-31'], $lines->pluck('due_date')->all());
        $this->assertTrue($lines->every(fn ($l) => $l->schedule_source === 'TAKEON_WORKBOOK' && (int) $l->schedule_version === 1 && $l->effective_from === '2020-07-24'));
        $this->assertEquals(400000, $lines[0]->principal_due);
        $this->assertEquals(100000, $lines[0]->interest_due);
        $this->assertSame('block 1 row 15', $lines[0]->source_reference);
        $this->assertSame('TAKEON_WORKBOOK', DB::table('contract_eir')->where('contract_id', '104420000005')->value('schedule_source'));

        // the fees found, with their cells; the net investment is the principal less them
        $fees = json_decode($a->fees_detail, true);
        $this->assertEquals(200000, $fees['total']);
        $this->assertSame(['arrangement_fee', 'legal_fees'], array_column($fees['lines'], 'type'));
        $this->assertSame('Upload summary!AO2', $fees['lines'][0]['cell']);
        $this->assertEquals(9800000, $a->net_investment);

        // the EIR is the dated solve on the same inputs, under the governed day count
        $expected = (new CalculateEirService())->calculateDated(9800000, [
            ['due_date' => '2022-08-31', 'amount' => 500000], ['due_date' => '2022-09-30', 'amount' => 500000], ['due_date' => '2024-07-31', 'amount' => 500000], ['due_date' => '2025-07-31', 'amount' => 9000000],
        ], 12, '2020-07-24', 'ACT/365');
        $this->assertEqualsWithDelta($expected['eir_effective_annual'], (float) $a->recomputed_eir, 1e-8);
        $this->assertGreaterThan(0, (float) $a->recomputed_eir);
        $this->assertEqualsWithDelta(pow(1 + $expected['eir_effective_annual'], 1 / 12) - 1, (float) $a->recomputed_eir_monthly, 1e-8);

        // the roll-forward: 49 month-ends from July 2020 to July 2024, the first a part month of 7 days,
        // whole months at the monthly EIR, the schedule's cash given up in its month, closing at the take-on
        $detail = json_decode($a->recompute_detail, true);
        $roll = $detail['roll_forward'];
        $this->assertCount(49, $roll);
        $this->assertSame('2020-07', $roll[0]['period']);
        $this->assertSame(7, $roll[0]['days']);
        $this->assertEqualsWithDelta(9800000 * (pow(1 + $expected['eir_effective_annual'], 7 / 365) - 1), $roll[0]['interest'], 0.01);
        $this->assertEqualsWithDelta($roll[1]['opening'] * ((float) $a->recomputed_eir_monthly), $roll[1]['interest'], 0.01);
        $this->assertSame('2024-07', $roll[48]['period']);
        $this->assertEquals(1500000, array_sum(array_column($roll, 'cash')));
        $this->assertEquals(500000, collect($roll)->firstWhere('period', '2022-08')['cash']);
        $this->assertEqualsWithDelta(9800000 + array_sum(array_column($roll, 'interest')) - 1500000, $roll[48]['closing'], 0.05);
        $this->assertEquals($roll[48]['closing'], (float) $a->recomputed_amortised_cost);

        // beside the take-on balance (principal plus opening interest less recovery) and the difference
        $this->assertEquals(8500000, $a->takeon_balance);
        $this->assertEqualsWithDelta(8500000 - (float) $a->recomputed_amortised_cost, (float) $a->recomputed_difference, 0.001);
        $this->assertSame('ACT/365', $detail['solve']['day_count']);
        $this->assertEquals(10500000, $detail['solve']['receipts']);
    }

    /** The take-on-balance option keeps the behaviour as it was: a label, no schedule, no solve. */
    public function test_the_takeon_balance_option_writes_no_schedule_and_solves_nothing(): void
    {
        DB::table('governance_settings')->where('key', 'takeon_history_basis')->update(['value' => 'Start every take-on loan at its take-on balance']);
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $c = $this->service()->build(1);
        $this->assertSame(['accounts' => 2, 'recomputed' => 0, 'takeon_balance' => 2, 'refused' => 0, 'schedule_lines' => 0], array_intersect_key($c, array_flip(['accounts', 'recomputed', 'takeon_balance', 'refused', 'schedule_lines'])));
        $a = DB::table('contract_takeon')->where('account', '000104420000005')->first();
        $this->assertSame('TAKEON_BALANCE', $a->basis);
        $this->assertNull($a->recomputed_eir);
        $this->assertNull($a->recomputed_amortised_cost);
        $this->assertEquals(8500000, $a->takeon_balance);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
        $this->assertSame('GENERATED', DB::table('contract_eir')->where('contract_id', '104420000005')->value('schedule_source'));
    }

    /** An imported version 1 is never written over; the row says so and the solve still runs on the workbook lines. */
    public function test_an_imported_version_one_schedule_is_left_alone(): void
    {
        DB::table('contract_cashflow_schedule')->insert(['contract_id' => '104420000005', 'schedule_version' => 1, 'due_date' => '2021-01-31', 'principal_due' => 1, 'interest_due' => 1, 'schedule_source' => 'IMPORTED', 'created_at' => now(), 'updated_at' => now()]);
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $this->service()->build(1);
        $a = DB::table('contract_takeon')->where('account', '000104420000005')->first();
        $this->assertSame('RECOMPUTED', $a->basis);
        $this->assertSame(0, (int) $a->schedule_lines_written);
        $this->assertNotNull($a->recomputed_eir);
        $this->assertStringContainsString('version 1 schedule is IMPORTED', implode(' ', json_decode($a->flags, true)));
        $this->assertSame(1, DB::table('contract_cashflow_schedule')->where('contract_id', '104420000005')->count());
    }

    public function test_landing_twice_is_a_no_op(): void
    {
        $first = $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $second = $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $this->assertSame('ALREADY_LANDED', $second['status']);
        $this->assertSame($first['load_id'], $second['load_id']);
        $this->assertSame(2, DB::table('takeon_blocks')->count());
    }
}
