<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The chart of a loan held only in E-Banker's dated copies (RS_02b of
 * 30 August 2024, RS_02d of 28 September 2024, RS_02c of 14 November 2024;
 * 14 October 2026, with the restructure register). BenCal Technologies
 * 000104460000059: plans 313 and 75 in RS_02b, the same instalments on plans
 * 1326 to 1328 in RS_02d and RS_02c, which P3_11 holds live; serials 25 to 61
 * all dated 1 February 2025.
 */
class InstalmentChartCopiesTest extends TestCase
{
    protected $seed = false;

    private string $a = '000104460000059';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('b', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function raw(string $q, string $key, ?string $account, ?string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Serials 1 to 4 monthly from 1 November 2024, then 5 to 8 all on 1 March 2025 (the collapsed chart). */
    private function copy(string $query, string $plan, float $amount = 1000.0): void
    {
        for ($s = 1; $s <= 8; $s++) {
            $due = $s <= 4 ? date('n/j/Y', strtotime('2024-11-01 +' . ($s - 1) . ' months')) : '3/1/2025';
            $this->raw($query, "{$query}-{$s}", $this->a, null, ['NEW_AC_NUMBER' => $this->a, 'INST_DET_ID' => $plan, 'INST_SR_NO' => (string) $s, 'INSTALLMENT_DATE' => $due,
                'INSTALLMENT_AMT' => number_format($amount, 2, '.', ''), 'TRANSACTION_DATE' => '3/28/2024', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N']);
        }
    }

    private function plan(string $id, string $deleted): void
    {
        $this->raw('P3_11', "plan-{$id}", $this->a, null, ['INST_DET_ID' => $id, 'NEW_AC_NUMBER' => $this->a, 'INSTALLMENT_PAY_MODE' => 'M', 'DELETE_FLAG' => $deleted]);
    }

    public function test_a_chart_only_in_the_copies_is_in_force_while_its_plan_is_live_and_its_collapsed_serials_are_dated_by_the_plan(): void
    {
        $this->copy('RS_02b', '313');
        $this->copy('RS_02d', '1328');
        $this->copy('RS_02c', '1328');
        $this->plan('313', 'Y');
        $this->plan('1328', 'N');
        $charts = new InstalmentChartHistory(new LandingZoneReader());

        $this->assertNull($charts->at($this->a, '2024-03-27'), 'nothing before the rows were created');
        $in2024 = $charts->at($this->a, '2024-06-30');
        $this->assertNotNull($in2024, 'the same instalments in every copy: in force from their creation on 28 March 2024');
        $sep2025 = $charts->at($this->a, '2025-09-30');
        $this->assertNotNull($sep2025, 'plan 1328 is still live in P3_11: the chart stays in force after the last copy');
        $this->assertTrue($sep2025['collapsed']);
        $this->assertFalse($sep2025['unreadable']);
        $dates = array_column($sep2025['instalments'], 0);
        $this->assertSame(['2024-11-01', '2024-12-01', '2025-01-01', '2025-02-01', '2025-03-01', '2025-04-01', '2025-05-01', '2025-06-01'], $dates, 'serials 5 to 8 dated a month apart by the monthly plan');
        $this->assertStringContainsString('dated copy', (string) $sep2025['source']);
        $this->assertTrue($charts->hasChart($this->a));
    }

    public function test_copies_that_differ_leave_the_days_between_them_without_a_chart_and_a_deleted_plan_ends_the_chart_at_its_copy(): void
    {
        $this->copy('RS_02b', '313', 1000);
        $this->copy('RS_02d', '1328', 1200); // the instalments changed between 30 August and 28 September 2024
        $this->plan('1328', 'Y');            // and the later plan is no longer live
        $charts = new InstalmentChartHistory(new LandingZoneReader());

        $this->assertSame(1000.0, $charts->at($this->a, '2024-08-30')['instalments'][0][1]);
        $this->assertNull($charts->at($this->a, '2024-09-15'), 'no record dates the change: no chart is assumed');
        $this->assertSame(1200.0, $charts->at($this->a, '2024-09-28')['instalments'][0][1]);
        $this->assertNull($charts->at($this->a, '2024-10-31'), 'the plan was deleted: the chart is not carried past its copy');
    }

    public function test_a_loan_in_the_live_or_superseded_extract_never_reads_the_copies(): void
    {
        $this->copy('RS_02c', '1328', 999);
        $this->plan('1328', 'N');
        $this->raw('P2_10', 'live-1', $this->a, '2025-01-01', ['INST_CHART_DET_ID' => 'live-1', 'NEW_AC_NUMBER' => $this->a, 'INST_DET_ID' => '2000', 'INST_SR_NO' => '1',
            'INSTALLMENT_DATE' => '1/1/2025', 'INSTALLMENT_AMT' => '500.00', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N', 'TRANSACTION_DATE' => '12/1/2024']);
        $charts = new InstalmentChartHistory(new LandingZoneReader());

        $this->assertSame([['2025-01-01', 500.0]], $charts->at($this->a, '2025-06-30')['instalments']);
        $this->assertNull($charts->at($this->a, '2025-06-30')['source']);
    }
}
