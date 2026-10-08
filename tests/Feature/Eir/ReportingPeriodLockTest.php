<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirRevenueService;
use App\Services\Eir\StagingService;
use App\Services\Fli\FliRouteService;
use App\Services\Lgd\LgdEngineService;
use App\Services\Pd\PdEngineService;
use App\Support\LockedPeriodException;
use App\Support\ReportingPeriodLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * System audit of 9 October 2026, finding H4: a locked reporting period is
 * never restated, by any engine. Before, only the loan-book build read the
 * lock table.
 */
class ReportingPeriodLockTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->timestamps(); });
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2025-12', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_lock_is_read_in_any_period_spelling(): void
    {
        $this->assertTrue(ReportingPeriodLock::isLocked('2025-12'));
        $this->assertTrue(ReportingPeriodLock::isLocked('202512'));
        $this->assertTrue(ReportingPeriodLock::isLocked('2025-12-31'));
        $this->assertFalse(ReportingPeriodLock::isLocked('2026-01'));
    }

    public function test_every_engine_refuses_a_locked_period_before_touching_a_table(): void
    {
        foreach ([
            'PD' => fn () => (new PdEngineService())->run('2025-12', 1),
            'LGD' => fn () => (new LgdEngineService())->run('2025-12', 1),
            'FLI route' => fn () => app(FliRouteService::class)->apply('2025-12'),
            'staging' => fn () => app(StagingService::class)->stage('2025-12'),
        ] as $name => $call) {
            try {
                $call();
                $this->fail("{$name} wrote into a locked period");
            } catch (LockedPeriodException $e) {
                $this->assertSame('2025-12', $e->period, $name);
                $this->assertStringContainsString('never restated', $e->getMessage());
            }
        }
    }

    public function test_the_revenue_engine_reports_a_locked_period_as_blocked(): void
    {
        $result = (new EirRevenueService())->run('C-1', '2025-12', true, 1, 'a reason');

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('locked', $result['error']);
    }
}
