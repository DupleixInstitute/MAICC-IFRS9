<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The instalment chart as it stood on a date (verification of the PD, LGD and
 * staging inputs, 14 October 2026): the live chart is the latest version only,
 * the superseded rows carry when each earlier version was created and
 * replaced, a chart that dates a monthly definition's instalments on one day
 * is dated by its serial numbers, and receipts are cash net of reversals with
 * write-offs left out.
 */
class InstalmentChartHistoryTest extends TestCase
{
    protected $seed = false;

    private string $a = '000104420000062';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('c', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function raw(string $q, string $key, ?string $account, ?string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** One chart row: created on $created (m/d/Y), replaced on $replaced (null = live). */
    private function row(string $id, string $due, float $amount, string $created, ?string $replaced, string $det = '9', int $serial = 1): void
    {
        $p = ['INST_CHART_DET_ID' => $id, 'NEW_AC_NUMBER' => $this->a, 'INST_DET_ID' => $det, 'INST_SR_NO' => (string) $serial, 'INSTALLMENT_DATE' => date('n/j/Y', strtotime($due)),
            'INSTALLMENT_AMT' => number_format($amount, 2, '.', ''), 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => $replaced === null ? 'N' : 'Y', 'TRANSACTION_DATE' => $created, 'OPERATION_DATE' => $replaced ?? ''];
        $this->raw($replaced === null ? 'P2_10' : 'RS_02a', $id . ($replaced === null ? 'L' : 'S'), $this->a, $due, $p);
    }

    public function test_the_chart_in_force_is_the_version_created_before_the_date_and_not_yet_replaced(): void
    {
        // version 1: created 10 Dec 2024, replaced 5 Aug 2026, 7m a month
        foreach (['2025-07-04', '2025-08-04', '2025-09-04'] as $i => $due) {
            $this->row("v1-{$i}", $due, 7000000, '12/10/2024', '8/5/2026', '9', $i + 1);
        }
        // version 2 (live): created 5 Aug 2026, 13.5m a month back-dated over the same months
        foreach (['2025-07-04', '2025-08-04', '2025-09-04'] as $i => $due) {
            $this->row("v2-{$i}", $due, 13500000, '8/5/2026', null, '10', $i + 1);
        }
        $charts = new InstalmentChartHistory(new LandingZoneReader());

        $sep2025 = $charts->at($this->a, '2025-09-30');
        $this->assertSame([['2025-07-04', 7000000.0], ['2025-08-04', 7000000.0], ['2025-09-04', 7000000.0]], $sep2025['instalments'], 'the live chart of August 2026 is not read into 2025');
        $this->assertNull($charts->at($this->a, '2024-11-30'), 'no version existed: no chart, nothing assumed');
        $this->assertSame(13500000.0, $charts->at($this->a, '2026-09-30')['instalments'][0][1]);
        $this->assertTrue($charts->hasChart($this->a));
        $this->assertFalse($charts->hasChart('000104420000099'));
    }

    public function test_a_row_in_both_extracts_takes_the_replacement_date_of_the_superseded_extract(): void
    {
        // live when P2_10 was taken on 6 October, replaced on 9 October before RS_02a was taken
        $this->row('same', '2026-09-08', 5000000, '9/3/2026', null);
        $this->row('same', '2026-09-08', 5000000, '9/3/2026', '10/9/2026');
        $this->row('next', '2026-09-08', 6000000, '10/9/2026', null);
        $charts = new InstalmentChartHistory(new LandingZoneReader());
        $this->assertSame([['2026-09-08', 5000000.0]], $charts->at($this->a, '2026-09-30')['instalments'], 'one row, not the same row twice');
        $this->assertSame([['2026-09-08', 6000000.0]], $charts->at($this->a, '2026-10-31')['instalments']);
    }

    /**
     * Udon Business: the migrated chart dates serials 35 to 73 of a monthly
     * definition on 1 December 2025. Dated by their serials they fall monthly
     * to February 2029, and a borrower paid ahead is not past due.
     */
    public function test_a_collapsed_monthly_definition_is_dated_by_its_serial_numbers(): void
    {
        $this->raw('P3_11', '1284', $this->a, '2024-03-01', ['INST_DET_ID' => '1284', 'NEW_AC_NUMBER' => $this->a, 'INSTALLMENT_PAY_MODE' => 'M', 'INSTALLMENT_START_DATE' => '3/1/2024', 'DELETE_FLAG' => 'N']);
        foreach (range(1, 6) as $serial) {
            $due = $serial <= 3 ? date('Y-m-d', strtotime('2025-09-01 +' . ($serial - 1) . ' months')) : '2025-11-01';
            $this->row("u{$serial}", $due, 3000000, '3/28/2024', null, '1284', $serial);
        }
        $charts = new InstalmentChartHistory(new LandingZoneReader());
        $c = $charts->at($this->a, '2025-12-31');
        $this->assertTrue($c['collapsed']);
        $this->assertFalse($c['unreadable']);
        $this->assertSame(['2025-09-01', '2025-10-01', '2025-11-01', '2025-12-01', '2026-01-01', '2026-02-01'], array_column($c['instalments'], 0));
        // 12m received covers the four instalments due by December: not past due
        $this->assertSame([0, null], InstalmentChartHistory::daysPastDue($c['instalments'], 12000000, '2025-12-31'));
        // 9m received leaves December's instalment unpaid
        $this->assertSame([30, '2025-12-01'], InstalmentChartHistory::daysPastDue($c['instalments'], 9000000, '2025-12-31'));
        $this->assertSame(1, InstalmentChartHistory::missed($c['instalments'], 9000000, '2025-12-31'));
    }

    /**
     * Two versions left in force together (Sycamore Credit: rows created on
     * 1 January and on 1 November 2025 both live) carry each instalment twice.
     * The later version is the chart; nothing is counted twice and the chart
     * is not mistaken for a collapsed one.
     */
    public function test_two_versions_in_force_together_keep_the_later_one(): void
    {
        foreach ([1, 2, 3] as $serial) {
            $due = date('Y-m-d', strtotime('2025-09-01 +' . ($serial - 1) . ' months'));
            $this->row("old{$serial}", $due, 18000000, '1/1/2025', null, '8740', $serial);
            $this->row("new{$serial}", $due, 3200000, '11/1/2025', null, '8740', $serial);
        }
        $c = (new InstalmentChartHistory(new LandingZoneReader()))->at($this->a, '2025-12-31');
        $this->assertFalse($c['collapsed']);
        $this->assertSame([['2025-09-01', 3200000.0], ['2025-10-01', 3200000.0], ['2025-11-01', 3200000.0]], $c['instalments']);
        $this->assertSame(18000000.0, (new InstalmentChartHistory(new LandingZoneReader()))->at($this->a, '2025-10-31')['instalments'][0][1], 'before November only the January version existed');
    }

    public function test_a_collapsed_definition_with_no_monthly_mode_is_reported_unreadable(): void
    {
        $this->row('h1', '2026-03-03', 55000000, '9/5/2025', null, '8054', 1);
        $this->row('h2', '2026-03-03', 55000000, '9/5/2025', null, '8054', 2);
        $c = (new InstalmentChartHistory(new LandingZoneReader()))->at($this->a, '2026-03-31');
        $this->assertTrue($c['collapsed']);
        $this->assertTrue($c['unreadable']);
    }

    /**
     * Receipts: a credit later reversed (Chisitu Tea Blenders, 135m refunded
     * as another party's sale proceeds) is not cash kept; a write-off posted
     * as a repayment is not cash at all; an interest credit is not a receipt.
     */
    public function test_receipts_are_cash_net_of_reversals_with_write_offs_left_out(): void
    {
        $post = fn (string $id, string $date, string $amount, string $type, string $narration = '') => $this->raw('P1_01', $id, $this->a, $date,
            ['CUMVOUCH_DET_ID' => $id, 'NEW_AC_NUMBER' => $this->a, 'AC_GLCODE' => '1050101', 'TRANSACTION_DATE' => $date, 'TRANSAMT' => $amount, 'TRANTYPE' => $type, 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => $id, 'PARTICULARS' => $narration, 'DELETE_FLAG' => 'N']);
        $post('1', '2024-07-31', '-200000000.00', '301');
        $post('2', '2025-06-27', '135000000.00', '305', 'Loan repayment');
        $post('3', '2025-12-31', '2000000.00', '120', 'Diff Int. Credit');
        $post('4', '2026-05-29', '-135000000.00', '306', 'Refund of Sale Proceeds');
        $post('5', '2026-06-29', '1000000.00', '300', 'ACCOUNT CLOSURE');
        $charts = new InstalmentChartHistory(new LandingZoneReader());
        $this->assertSame(135000000.0, $charts->receiptsTo($this->a, '2025-12-31'));
        $this->assertSame(0.0, $charts->receiptsTo($this->a, '2026-06-30'), 'the refund reverses the receipt; the write-off is no cash');
        $this->assertSame(200000000.0, $charts->debitsTo($this->a, '2026-06-30'), 'the reversal of a receipt is not a charge to the loan');
    }
}
