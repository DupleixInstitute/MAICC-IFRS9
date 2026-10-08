<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The take-on workbook landed, not typed (spec v4 section 6.9): two blocks in
 * a generated original, a mapping workbook with the account, tick and fees;
 * every value lands with its cell; the gates name the block and cell; the
 * build writes contract_takeon under the seeded basis, recomputing only
 * where the block and its fees exist.
 */
class TakeonLandingServiceTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_08_200000_create_takeon_tables'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
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
        $this->block($am, 4, 'Micholess Creamery', 10000000, 0.202, [[1, 44774, 44804, 10000000, 500000, 9500000], [2, 44805, 44834, 9500000, 500000, 9000000], [3, 45474, 45504, 9000000, 500000, 8500000]]);
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
        $this->assertSame(4, $r['lines']);
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
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Take-on Population Built')->count());
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
