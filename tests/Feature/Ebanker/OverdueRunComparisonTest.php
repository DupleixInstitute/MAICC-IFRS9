<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\BaselineService;
use App\Services\Eir\StagingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E-Banker's overdue run at month-end (OD_01, pack 9 RUN 8b) against the days past due staging
 * reads (BaselineService::overdueRunComparison): the run's days are counted from its own oldest
 * overdue date to its calculation date; the book's are StagingService::daysPastDueOf. Agree and
 * differ are counted, never asserted against a target.
 */
class OverdueRunComparisonTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        (new EbankerQuerySeeder())->run();
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            foreach (['contract_id', 'reporting_period', 'build_flag', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); }
            $t->integer('overdue_days')->nullable();
        });
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('b', 64), 'pack_name' => 'pack 9', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function od(string $key, string $account, string $calc, array $p): void
    {
        $iso = \Carbon\CarbonImmutable::createFromFormat('!n/j/Y', $calc)->toDateString();
        $payload = $p + ['OVERDUE_DET_ID' => $key, 'NEW_AC_NUMBER' => $account, 'OVD_CALC_DATE' => $calc, 'RESCHEDULEMENT_DATE' => '', 'DELETE_FLAG' => 'N'];
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'OD_01', 'source_key' => $key, 'account' => $account, 'row_date' => $iso,
            'payload' => json_encode($payload), 'row_hash' => md5($key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function book(string $contract, string $period, int $days, array $more = []): void
    {
        DB::table('loan_books')->insert(['contract_id' => $contract, 'reporting_period' => $period, 'overdue_days' => $days] + $more);
    }

    public function test_the_overdue_run_is_compared_with_the_days_staging_reads(): void
    {
        $a = '000104420000011'; $b = '000104420000005'; $c = '000104420000009';
        // agrees: principal overdue since 20 Sep 2023, 527 days at 28 Feb 2025; the interest date is later
        $this->od('502023', $a, '2/28/2025', ['OVERDUE_AMOUNT' => '112,159,641.78', 'OVERDUE_PRINCIPLE' => '28890083.83', 'OVERDUE_PRINCI_DATE' => '9/20/2023', 'OVERDUE_INTEREST' => '83269557.95', 'OVERDUE_INT_DATE' => '7/31/2024']);
        $this->book('104420000011', '2025-02', 527);
        // differs: only interest is overdue, from 31 Jan 2025 (28 days); the book reads nil and no bucket
        $this->od('502024', $b, '2/28/2025', ['OVERDUE_AMOUNT' => '500.00', 'OVERDUE_PRINCIPLE' => '0.00', 'OVERDUE_PRINCI_DATE' => '', 'OVERDUE_INTEREST' => '500.00', 'OVERDUE_INT_DATE' => '1/31/2025']);
        $this->book('104420000005', '2025-02', 0);
        // nothing overdue (an advance payment: a negative overdue amount) against a bucket-aged book row: staging reads 31
        $this->od('502025', $b, '3/31/2025', ['OVERDUE_AMOUNT' => '-64225196.26', 'OVERDUE_PRINCIPLE' => '-64225196.26', 'OVERDUE_PRINCI_DATE' => '1/31/2025']);
        $this->book('104420000005', '2025-03', 0, ['arrears_30_to_90' => '1000.00']);
        // two rows for one month-end: the later key wins (nothing overdue; the book agrees at nil)
        $this->od('600001', $c, '3/31/2025', ['OVERDUE_AMOUNT' => '900.00', 'OVERDUE_PRINCIPLE' => '900.00', 'OVERDUE_PRINCI_DATE' => '1/1/2025']);
        $this->od('600002', $c, '3/31/2025', ['OVERDUE_AMOUNT' => '0.00', 'OVERDUE_PRINCIPLE' => '0.00']);
        $this->book('104420000009', '2025-03', 0);
        // a deleted row is not read; a month-end the book does not hold is counted apart
        $this->od('600003', $c, '4/30/2025', ['OVERDUE_AMOUNT' => '900.00', 'OVERDUE_PRINCIPLE' => '900.00', 'OVERDUE_PRINCI_DATE' => '1/1/2025', 'DELETE_FLAG' => 'Y']);
        $this->od('600004', $c, '5/31/2025', ['OVERDUE_AMOUNT' => '0.00']);

        $r = (new BaselineService(new LandingZoneReader()))->overdueRunComparison();
        $this->assertSame(6, $r['od_rows']);
        $this->assertSame(4, $r['compared']);
        $this->assertSame(2, $r['agree']);
        $this->assertSame(2, $r['differ']);
        $this->assertSame(1, $r['run_higher']);
        $this->assertSame(1, $r['book_higher']);
        $this->assertSame(1, $r['differ_over_30']);
        $this->assertSame(1, $r['not_in_book']);
        $this->assertSame(['account' => $b, 'period' => '2025-03', 'overdue_run_days' => 0, 'overdue_run_oldest' => null, 'staging_days' => 31, 'difference' => -31], $r['differences'][0]);
        $this->assertSame(['account' => $b, 'period' => '2025-02', 'overdue_run_days' => 28, 'overdue_run_oldest' => '2025-01-31', 'staging_days' => 0, 'difference' => 28], $r['differences'][1]);
    }

    public function test_staging_reads_the_build_count_and_falls_back_to_the_bucket_only_without_a_chart_count(): void
    {
        $this->assertSame(61, StagingService::daysPastDueOf((object) ['overdue_days' => 61, 'arrears_271_to_360' => '5.00']));
        $this->assertSame(91, StagingService::daysPastDueOf((object) ['overdue_days' => 0, 'arrears_91_to_180' => '5.00']));
        $counted = (object) ['overdue_days' => 0, 'arrears_91_to_180' => '5.00', 'build_flag' => \App\Services\Ebanker\LoanBookBuildService::SCHEDULE_COUNT . ': the reported 120 days are not accepted'];
        $this->assertSame(0, StagingService::daysPastDueOf($counted));
    }

    public function test_with_no_overdue_run_landed_nothing_is_compared(): void
    {
        $r = (new BaselineService(new LandingZoneReader()))->overdueRunComparison();
        $this->assertSame(0, $r['od_rows']);
        $this->assertSame(0, $r['compared']);
    }
}
