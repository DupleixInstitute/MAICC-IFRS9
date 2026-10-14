<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The take-on workbook landed, not typed (spec v4 section 6.9): two blocks in
 * a generated original, a mapping workbook with the account, tick and fees;
 * every value lands with its cell; the gates name the block and cell; the
 * build writes contract_takeon under the seeded basis, recomputing only
 * where the block and its fees exist. The recompute (system audit of
 * 9 October 2026, finding M7; results review of 13 October 2026, E2): the
 * workbook schedule becomes version 1 with schedule_source TAKEON_WORKBOOK,
 * the EIR is solved from the date the schedule starts on the balance chain
 * (every line's principal and interest received, every rise in the balance
 * the lines do not explain advanced on its date), checked against the rates
 * the lines charge, and the amortised cost is rolled forward to 31 July 2024.
 */
class TakeonLandingServiceTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesFeeSchema;

    protected $seed = false;
    private string $dir;
    private const A = '000104420000005';
    private const B = '000104420000009';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        $this->createFeeSchema();
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone', '2026_10_08_200000_create_takeon_tables', '2026_10_09_500000_takeon_recompute_from_origination', '2026_10_14_200000_eir_tranches_takeon_and_gl_fees'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->decimal('principal_due', 20, 2)->default(0); $t->decimal('interest_due', 20, 2)->default(0); $t->decimal('fee_due', 20, 2)->default(0); $t->string('schedule_source', 20)->default('IMPORTED'); $t->string('source_system', 50)->nullable(); $t->string('source_reference')->nullable(); $t->string('external_transaction_id')->nullable(); $t->timestamps(); $t->unique(['contract_id', 'schedule_version', 'due_date']); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('schedule_source', 20)->nullable(); $t->timestamps(); });
        DB::table('contract_eir')->insert(['contract_id' => '104420000005', 'schedule_source' => 'GENERATED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('c', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[self::A, 'Micholess Creamery'], [self::B, 'Edge View']] as [$acc, $name]) {
            $this->raw('P1_02', $acc, $acc, '2024-07-01', ['NEW_AC_NUMBER' => $acc, 'GLCODE' => '1050101', 'ACCOUNT_NAME' => $name, 'STATUS_CODE' => 'A']);
        }
        // the three opening legs of account A, back-dated to the loan but operated on the migration day
        $this->raw('P1_01', '1', self::A, '2022-08-01', ['NEW_AC_NUMBER' => self::A, 'TRANSAMT' => '-10000000.00', 'TRANTYPE' => '301', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening Account Balance', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '2', self::A, '2022-08-01', ['NEW_AC_NUMBER' => self::A, 'TRANSAMT' => '-500000.00', 'TRANTYPE' => '303', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening interest charge', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '3', self::A, '2024-07-31', ['NEW_AC_NUMBER' => self::A, 'TRANSAMT' => '2000000.00', 'TRANTYPE' => '305', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'By Trf Opening Recover Amount', 'DELETE_FLAG' => 'N']);
        $this->raw('P1_01', '4', self::B, '2024-07-31', ['NEW_AC_NUMBER' => self::B, 'TRANSAMT' => '-7000000.00', 'TRANTYPE' => '301', 'OPERATION_DATE' => '7/31/2024', 'PARTICULARS' => 'To Trf Opening Account Balance', 'DELETE_FLAG' => 'N']);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'takeon_' . uniqid();
        mkdir($this->dir);
        $this->writeWorkbooks($this->level(10000000, 0.202, '2022-08-01', 36));
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

    /**
     * A level monthly schedule as Tamanda lays it out: one line a calendar
     * month from the start, interest at the rate over twelve on the opening
     * balance, the last line retiring what is left. A tranche given as
     * [line index => amount] raises the opening of that line above the
     * closing of the one before, and the instalment is sized again.
     *
     * @return list<array{start:string,end:string,days:int,opening:float,pmt:float,interest:float,principal:float,closing:float}>
     */
    private function level(float $principal, float $rate, string $start, int $n, array $tranches = []): array
    {
        $r = $rate / 12;
        $balance = $principal;
        $pmt = round($balance * $r / (1 - pow(1 + $r, -$n)), 2);
        $lines = [];
        $from = \Carbon\CarbonImmutable::parse($start);
        for ($i = 0; $i < $n; $i++) {
            if (isset($tranches[$i])) {
                $balance = round($balance + $tranches[$i], 2);
                $pmt = round($balance * $r / (1 - pow(1 + $r, -($n - $i))), 2);
            }
            $end = $from->endOfMonth()->startOfDay();
            $interest = round($balance * $r, 2);
            $principalDue = $i === $n - 1 ? $balance : round($pmt - $interest, 2);
            $closing = round($balance - $principalDue, 2);
            $lines[] = ['start' => $from->toDateString(), 'end' => $end->toDateString(), 'days' => $from->diffInDays($end) + 1, 'opening' => $balance,
                'pmt' => round($principalDue + $interest, 2), 'interest' => $interest, 'principal' => $principalDue, 'closing' => $closing];
            $balance = $closing;
            $from = $end->addDay();
        }

        return $lines;
    }

    private function writeWorkbooks(array $linesA): void
    {
        $o = new Spreadsheet();
        $lb = $o->getActiveSheet(); $lb->setTitle('Loan Book');
        $lb->fromArray(['#', 'Name', 'Type', 'Value Date', 'Maturity Date', 'Tenor', 'Moratorium', 'Interest Rate', 'Approved', 'Disbursed', 'Not yet', 'Principal', 'Interest', 'Repayments', 'Carrying'], null, 'D3');
        $lb->fromArray([1, 'Micholess Creamery', 'Debt', ExcelDate::stringToExcel($linesA[0]['start']), 46600, 3.0, '-', 20.2, 20000000, 18240000, 1760000, 18240000, 1004624.69, -9000000, 10244624.69], null, 'D5');
        $lb->fromArray([2, 'Edge View', 'Debt', 43935, 45747, 5.0, '-', 0.3289, 300000000, 300000000, 0, 7000000, 0, 0, 7000000], null, 'D6');
        $am = $o->createSheet(); $am->setTitle('Amortisation and Repayments');
        $this->block($am, 4, 'Micholess Creamery', 10000000, 0.202, $linesA);
        $this->block($am, 60, 'Edge View', 7000000, 0.25, [['start' => '2024-06-01', 'end' => '2024-06-30', 'days' => 30, 'opening' => 7000000, 'pmt' => 100000, 'interest' => 100000, 'principal' => 0, 'closing' => 7000000]]);
        (new Xlsx($o))->save($this->dir . DIRECTORY_SEPARATOR . 'original.xlsx');

        $m = new Spreadsheet();
        $map = $m->getActiveSheet(); $map->setTitle('Mapping');
        $map->fromArray(['Loan Book row'], null, 'A1');
        $map->setCellValue('A2', 5); $map->setCellValue('J2', self::A); $map->setCellValue('K2', 'High'); $map->setCellValue('X2', 'Y');
        $map->setCellValue('A3', 6); $map->setCellValue('J3', self::B); $map->setCellValue('K3', 'Medium');
        $us = $m->createSheet(); $us->setTitle('Upload summary');
        $us->setCellValue('C2', 5); $us->setCellValue('AL2', 1); $us->setCellValue('AO2', 150000); $us->setCellValue('AP2', 50000); $us->setCellValue('AR2', '01/08/2022'); $us->setCellValue('AS2', 'Y'); $us->setCellValue('AT2', 'Offer letter 12');
        $us->setCellValue('C3', 6); $us->setCellValue('AL3', 2);
        $bl = $m->createSheet(); $bl->setTitle('Blocks');
        $bl->fromArray(['Block #', 'Title', 'Principal', 'Rate', 'Sheet row', 'Go', 'Loan Book row', 'Facility', 'Account', 'Restructured'], null, 'A1');
        $bl->fromArray([1, '', '', '', 4, '', 5, '', '', 'False'], null, 'A2');
        $bl->fromArray([2, '', '', '', 60, '', 6, '', '', 'True'], null, 'A3');
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
        $ws->setCellValue('C' . ($row + 8), 'Pmt (MK)'); $ws->setCellValue('D' . ($row + 8), $lines[0]['pmt']);
        $ws->fromArray(['Period Start', 'Period End', 'Days', 'Opening bal.', 'Pmt', 'Interest', 'Principal', 'Closing balance', 'Amount Repaid', 'Accumulated Arrears'], null, 'C' . ($row + 10));
        foreach ($lines as $i => $l) {
            $ws->fromArray([$i + 1, ExcelDate::stringToExcel($l['start']), ExcelDate::stringToExcel($l['end']), $l['days'], $l['opening'], $l['pmt'], $l['interest'], $l['principal'], $l['closing'], null, 0], null, 'B' . ($row + 11 + $i));
        }
    }

    private function service(): TakeonLandingService
    {
        return new TakeonLandingService(new LandingZoneReader(), new GovernanceService());
    }

    private function landAndBuild(): array
    {
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);

        return $this->service()->build(1);
    }

    public function test_the_workbook_lands_with_cells_gates_and_fees(): void
    {
        $r = $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(2, $r['blocks']);
        $this->assertSame(37, $r['lines']);
        $one = DB::table('takeon_blocks')->where('block_no', 1)->first();
        $this->assertSame(self::A, $one->account);
        $this->assertSame('Y', $one->confirmed);
        $this->assertEquals(10000000, $one->principal);
        $this->assertEquals(20.2, $one->rate);
        $this->assertSame('2022-08-01', $one->value_date);
        $this->assertEquals(200000, $one->total_fees);
        $this->assertSame('2022-08-01', $one->fee_date);
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
        $this->assertEquals($this->level(10000000, 0.202, '2022-08-01', 36)[0]['closing'], $lines[0]->closing_balance);
        $this->assertSame(15, (int) $lines[0]->sheet_row);
        $this->assertSame(2, $r['gates']['summary']['principal_exact']);
    }

    public function test_the_build_recomputes_only_where_the_block_and_fees_exist(): void
    {
        $c = $this->landAndBuild();
        $this->assertSame(['accounts' => 2, 'recomputed' => 1, 'takeon_balance' => 1, 'refused' => 0, 'eir_check_pass' => 1], array_intersect_key($c, array_flip(['accounts', 'recomputed', 'takeon_balance', 'refused', 'eir_check_pass'])));
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
        $this->assertSame('RECOMPUTED', $a->basis);
        $this->assertSame('104420000005', $a->contract_id);
        $this->assertEquals(10000000, $a->takeon_posting);
        $this->assertEquals(500000, $a->takeon_opening_interest);
        $this->assertEquals(2000000, $a->takeon_opening_recovery);
        $scheduled = $this->level(10000000, 0.202, '2022-08-01', 36)[23]['closing'];   // the line ending 31 Jul 2024
        $this->assertEquals($scheduled, $a->schedule_balance_at_takeon);
        $this->assertEqualsWithDelta(10000000 - 2000000 - $scheduled, (float) $a->difference_at_takeon, 0.01);
        $this->assertEquals(200000, $a->fees_total);
        $b = DB::table('contract_takeon')->where('account', self::B)->first();
        $this->assertSame('TAKEON_BALANCE', $b->basis);
        $this->assertStringContainsString('fees not supplied', json_decode($b->flags, true)[0]);
        $this->assertStringContainsString('not yet confirmed', json_decode($b->flags, true)[1]);
        $this->assertNull($b->recomputed_eir);
        $this->assertSame(0, (int) $b->schedule_lines_written);
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->where('contract_id', '104420000009')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Take-on Population Built')->count());
    }

    /** Finding M7 and review E2: the schedule written, the EIR solved on the balance chain and checked, the cost rolled to the take-on. */
    public function test_the_recompute_writes_the_workbook_schedule_solves_the_eir_and_rolls_forward_to_the_takeon(): void
    {
        $c = $this->landAndBuild();
        $this->assertSame(36, $c['schedule_lines']);
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
        $this->assertSame('RECOMPUTED', $a->basis);
        $this->assertSame('PASS', $a->eir_check);
        $this->assertNull($a->eir_check_reason);
        $this->assertSame('2022-08-01', $a->solve_start_date);

        // the version 1 schedule from the workbook, dated and sourced
        $plan = $this->level(10000000, 0.202, '2022-08-01', 36);
        $lines = DB::table('contract_cashflow_schedule')->where('contract_id', '104420000005')->orderBy('due_date')->get();
        $this->assertCount(36, $lines);
        $this->assertSame(36, (int) $a->schedule_lines_written);
        $this->assertTrue($lines->every(fn ($l) => $l->schedule_source === 'TAKEON_WORKBOOK' && (int) $l->schedule_version === 1 && $l->effective_from === '2022-08-01'));
        $this->assertEquals($plan[0]['principal'], $lines[0]->principal_due);
        $this->assertEquals($plan[0]['interest'], $lines[0]->interest_due);
        $this->assertSame('block 1 row 15', $lines[0]->source_reference);
        $this->assertSame('TAKEON_WORKBOOK', DB::table('contract_eir')->where('contract_id', '104420000005')->value('schedule_source'));

        // the fees found, with their cells; the net investment is the opening balance less them
        $fees = json_decode($a->fees_detail, true);
        $this->assertEquals(200000, $fees['total']);
        $this->assertSame(['arrangement_fee', 'legal_fees'], array_column($fees['lines'], 'type'));
        $this->assertEquals(9800000, $a->net_investment);

        // with no fee the schedule gives back the rate its lines charge
        $this->assertEqualsWithDelta(0.202, 12 * (pow(1 + (float) $a->nofee_eir, 1 / 12) - 1), 0.0005);
        // the EIR is the dated solve on the chain from the schedule's start, under the governed day count
        $flows = array_map(fn ($l) => ['due_date' => $l['end'], 'amount' => round($l['principal'] + $l['interest'], 4)], $plan);
        $expected = (new CalculateEirService())->calculateDated(9800000, $flows, 12, '2022-08-01', 'ACT/365');
        $this->assertEqualsWithDelta($expected['eir_effective_annual'], (float) $a->recomputed_eir, 1e-8);
        $this->assertGreaterThan((float) $a->nofee_eir, (float) $a->recomputed_eir);

        // the roll-forward: 24 month-ends from August 2022 to July 2024, the first a part month
        $detail = json_decode($a->recompute_detail, true);
        $roll = $detail['roll_forward'];
        $this->assertCount(24, $roll);
        $this->assertSame('2022-08', $roll[0]['period']);
        $this->assertSame(30, $roll[0]['days']);
        $this->assertSame('2024-07', $roll[23]['period']);
        $this->assertEqualsWithDelta(array_sum(array_map(fn ($l) => $l['pmt'], array_slice($plan, 0, 24))), array_sum(array_column($roll, 'cash')), 0.05);
        $this->assertEqualsWithDelta(9800000 + array_sum(array_column($roll, 'interest')) - array_sum(array_column($roll, 'cash')), $roll[23]['closing'], 0.05);
        $this->assertEquals($roll[23]['closing'], (float) $a->recomputed_amortised_cost);
        $this->assertEquals(8500000, $a->takeon_balance);
        $this->assertSame('ACT/365', $detail['solve']['day_count']);
    }

    /** Review E2: a second tranche in the block (the balance rises between two lines) is money paid out on its date, so the solve stays at the rate. */
    public function test_a_tranche_inside_the_block_is_paid_out_on_its_date(): void
    {
        $this->writeWorkbooks($this->level(10000000, 0.202, '2022-08-01', 36, [6 => 5000000]));
        $this->landAndBuild();
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
        $this->assertSame('PASS', $a->eir_check);
        $chain = json_decode($a->recompute_detail, true)['chain'];
        $this->assertCount(1, $chain['advances']);
        $this->assertSame('2023-02-01', $chain['advances'][0]['date']);
        $this->assertEquals(5000000, $chain['advances'][0]['amount']);
        $advance = collect($chain['flows'])->firstWhere('flow_type', CalculateEirService::FLOW_DISBURSEMENT);
        $this->assertEquals(-5000000, $advance['amount']);
        // without the tranche in the vector the EIR read about 65 percent; with it the schedule's own rate
        $this->assertEqualsWithDelta(0.202, 12 * (pow(1 + (float) $a->nofee_eir, 1 / 12) - 1), 0.0005);
        $this->assertStringContainsString('1 rise(s) in the balance read as money advanced', implode(' ', json_decode($a->flags, true)));
    }

    /** Review E2: a block that never retires the loan is an account history, not a schedule; the solve is refused with the reason. */
    public function test_a_schedule_that_does_not_retire_the_loan_is_refused_with_the_reason(): void
    {
        $this->writeWorkbooks(array_slice($this->level(10000000, 0.202, '2022-08-01', 36), 0, 30));
        $c = $this->landAndBuild();
        $this->assertSame(1, $c['eir_check_refused']);
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
        $this->assertSame('TAKEON_BALANCE', $a->basis);
        $this->assertSame('REFUSED', $a->eir_check);
        $this->assertStringContainsString('does not retire the loan', $a->eir_check_reason);
        $this->assertStringContainsString('take-on EIR refused', implode(' ', json_decode($a->flags, true)));
        $this->assertNull($a->recomputed_eir);
    }

    /** Review E2: instalments typed on one date (a drag-filled date column) solve far from the schedule's rate and are refused. */
    public function test_instalments_sharing_one_date_are_refused_by_the_governed_check(): void
    {
        $lines = $this->level(10000000, 0.202, '2022-08-01', 36);
        foreach (range(6, 35) as $i) {
            $lines[$i]['end'] = '2023-02-28';
        }
        $this->writeWorkbooks($lines);
        $this->landAndBuild();
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
        $this->assertSame('REFUSED', $a->eir_check);
        $this->assertStringContainsString('share a due date', $a->eir_check_reason);
        $this->assertStringContainsString('Inside the schedule\'s own rates, 0.5 point either side', $a->eir_check_reason);
        $this->assertSame('TAKEON_BALANCE', $a->basis);
    }

    /** Review E2 and E3: the workbook's fees land as fee lines for review, never as reviewed; a changed figure goes back for review. */
    public function test_the_takeon_fees_land_pending_for_review(): void
    {
        $c = $this->landAndBuild();
        $this->assertSame(2, $c['fees']['loaded_rows']);
        $fees = DB::table('contract_fees')->where('contract_id', '104420000005')->orderBy('fee_type')->get();
        $this->assertSame(['arrangement', 'legal'], $fees->pluck('fee_type')->all());
        $this->assertTrue($fees->every(fn ($f) => $f->classification_status === 'PENDING' && $f->integral === null && $f->source_system === 'TAKEON_WORKBOOK' && $f->cashflow_direction === 'RECEIVED'));
        $this->assertEquals([150000, 50000], $fees->pluck('amount')->map(fn ($v) => (float) $v)->all());
        $this->assertStringContainsString('Upload summary!AO2', $fees[0]->description);

        // building again loads nothing twice; a reviewed line whose figure changes goes back for review
        DB::table('contract_fees')->update(['classification_status' => 'REVIEWED', 'integral' => 1]);
        DB::table('takeon_blocks')->where('block_no', 1)->update(['arrangement_fee' => 175000]);
        $again = $this->service()->build(1);
        $this->assertSame(0, $again['fees']['loaded_rows']);
        $this->assertSame(1, $again['fees']['amounts_changed']);
        $changed = DB::table('contract_fees')->where('fee_type', 'arrangement')->first();
        $this->assertEquals(175000, $changed->amount);
        $this->assertSame('PENDING', $changed->classification_status);
    }

    /** No value is defaulted in code: without the governed basis the build stops. */
    public function test_the_build_fails_closed_without_the_governed_basis(): void
    {
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        DB::table('governance_settings')->where('key', 'takeon_history_basis')->delete();
        $this->expectException(\App\Exceptions\GovernanceSettingMissingException::class);
        $this->service()->build(1);
    }

    /** The take-on-balance option keeps the behaviour as it was: a label, no schedule, no solve. */
    public function test_the_takeon_balance_option_writes_no_schedule_and_solves_nothing(): void
    {
        DB::table('governance_settings')->where('key', 'takeon_history_basis')->update(['value' => 'Start every take-on loan at its take-on balance']);
        $c = $this->landAndBuild();
        $this->assertSame(['accounts' => 2, 'recomputed' => 0, 'takeon_balance' => 2, 'refused' => 0, 'schedule_lines' => 0], array_intersect_key($c, array_flip(['accounts', 'recomputed', 'takeon_balance', 'refused', 'schedule_lines'])));
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
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
        $this->landAndBuild();
        $a = DB::table('contract_takeon')->where('account', self::A)->first();
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

    /** Spec 6.9, the Build with approval: one person proposes, a different person approves, and only the approval builds. */
    public function test_the_build_is_proposed_by_one_person_and_approved_by_another(): void
    {
        (require base_path('database/migrations/2026_10_14_220000_go_live_controls.php'))->up();
        $this->service()->land($this->dir . '/original.xlsx', $this->dir . '/mapping.xlsx', 1);
        $request = $this->service()->proposeBuild(1, 'first build');
        $this->assertSame(0, DB::table('contract_takeon')->count(), 'a proposal builds nothing');
        foreach ([1, null] as $approver) {
            try {
                $this->service()->approveBuild($request['request'], $approver);
                $this->fail('the build was approved by ' . ($approver === null ? 'no one' : 'its proposer'));
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('different person', $e->getMessage());
            }
        }
        try {
            $this->service()->approveBuild($request['request'], 1, 'Administrator');
            $this->fail('a label other than the bootstrap label stood in for an approver');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Only the bootstrap label', $e->getMessage());
        }
        $c = $this->service()->approveBuild($request['request'], 2);
        $this->assertSame(2, $c['accounts']);
        $row = DB::table('takeon_build_requests')->where('id', $request['request'])->first();
        $this->assertSame('APPROVED', $row->status);
        $this->assertSame(2, (int) $row->approved_by);
        $this->assertNull($row->approver_label);
        $this->assertNull($this->service()->pendingBuild());

        // the bootstrap may approve under its own label, which is recorded
        $again = $this->service()->proposeBuild(1);
        $this->service()->approveBuild($again['request'], 1, \App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL);
        $this->assertSame(\App\Services\Ebanker\LoanBookBuildService::BOOTSTRAP_LABEL, DB::table('takeon_build_requests')->where('id', $again['request'])->value('approver_label'));
    }
}
