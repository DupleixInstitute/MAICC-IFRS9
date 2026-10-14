<?php

namespace Tests\Feature\FLI;

use App\Console\Commands\Bootstrap;
use App\Console\Commands\MaintenanceTask;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The bootstrap runs the Maintenance month-end chain for every month, not the
 * forward-looking route for the last month only (audit finding M17, 14 October
 * 2026). Until then the scenario set was seeded and approved for the last
 * period alone, so every earlier month's route ran with no set, its Stage 3
 * loans carried neither the set nor a scenario PD, and the time-phased ECL left
 * them unresolved ("The loan is not on the scenario set the rest of the run is
 * measured under"): on a fresh bootstrap December 2025, the year-end MAIIC
 * compares with, was discounted on 18 of its 123 loans.
 *
 * The chain is tested at the level of its step list (Bootstrap::periodChain,
 * which the per-month loop of stepEngines iterates): each month gets its
 * scenario set before its route, the route before the ECL, the ECL on the
 * post-FLI PD discounted at the EIR, the month record after the ECL and the
 * Mega Farm programme; and each step is a step of the Maintenance month-end.
 */
class BootstrapMonthEndChainTest extends TestCase
{
    protected $seed = false;

    public function test_every_month_gets_its_set_the_route_the_discounted_ecl_and_the_record_in_order(): void
    {
        $keys = array_column(Bootstrap::periodChain(), 0);
        foreach (['pd-lgd', 'scenario-set', 'fli-route', 'fli-projection', 'ecl', 'fli-record', 'mega-farm'] as $k) {
            $this->assertContains($k, $keys, "the month runs {$k}");
            $this->assertSame(1, count(array_keys($keys, $k, true)), "{$k} runs once a month");
        }
        $at = array_flip($keys);
        $this->assertLessThan($at['scenario-set'], $at['pd-lgd'], 'the PD before the set (the set\'s sensitivity reads it)');
        $this->assertLessThan($at['fli-route'], $at['scenario-set'], 'the set is approved before the route reads it');
        $this->assertLessThan($at['ecl'], $at['fli-route'], 'the route before the ECL on the post-FLI PD');
        $this->assertLessThan($at['fli-record'], $at['ecl'], 'the record after the ECL');
        $this->assertLessThan($at['mega-farm'], $at['fli-record'], 'the Mega Farm programme last, as the month-end runs it');
    }

    public function test_a_step_runs_only_when_what_it_needs_ran_and_the_projection_blocks_nothing(): void
    {
        $needs = array_column(Bootstrap::periodChain(), 3, 0);
        $this->assertSame(['fli-route'], $needs['ecl'], 'no ECL on a PD the route did not write (fail closed)');
        $this->assertSame(['ecl'], $needs['fli-record']);
        $this->assertSame(['pd-lgd'], $needs['fli-route'], 'the route runs without a set too: its own rule then holds the PD and the ECL names the loans');
        $this->assertSame([], $needs['mega-farm'], 'the programme is measured by its own engine');
        foreach ($needs as $k => $n) {
            $this->assertNotContains('fli-projection', $n, "{$k} does not wait for the projection: it is evidence, the month-end's optional step");
        }
    }

    public function test_the_month_ecl_is_the_booked_allowance_discounted_at_the_eir_on_the_post_fli_pd(): void
    {
        $args = Bootstrap::eclArguments('2025-12');
        $this->assertSame('2025-12', $args['from']);
        $this->assertSame('pd_post_fli', $args['--pd']);
        $this->assertSame('discounted', $args['--discounting']);
        $this->assertArrayNotHasKey('to', $args, 'one month at a time, each under its own set');
    }

    public function test_each_step_is_a_step_of_the_maintenance_month_end(): void
    {
        $monthEnd = array_column(MaintenanceTask::steps('month-end', '2025-12', ['--user' => '1']), 1);
        foreach (Bootstrap::periodChain() as [$key, , $command]) {
            $this->assertContains($command, $monthEnd, "{$key} is the month-end's {$command}");
        }
        // the forward-looking steps are those of fli:month-end --all: the set it needs, the route, the projection, the ECL and the record
        $fli = array_column(array_filter(Bootstrap::periodChain(), fn ($s) => $s[2] === 'fli:month-end'), 0);
        $this->assertSame(['scenario-set', 'fli-route', 'fli-projection', 'ecl', 'fli-record'], array_values($fli));
    }

    public function test_the_loop_runs_the_chain_for_every_period_and_no_step_for_the_last_period_alone(): void
    {
        $src = file_get_contents(app_path('Console/Commands/Bootstrap.php'));
        $engines = substr($src, strpos($src, 'private function stepEngines('));
        $this->assertSame(1, preg_match('/foreach \(\$periods as \$p\) \{\s*\$ok = \[\];\s*\$why = \[\];\s*foreach \(self::periodChain\(\) as/', $engines), 'the month-end loop runs the chain of the month for every period');
        // the set, the route and the ECL are no longer run for the last period only
        $this->assertStringNotContainsString('seedFirstSet($to', $engines);
        $this->assertStringNotContainsString('->apply($to', $engines);
        $this->assertStringNotContainsString("'ifrs9:segment-ecl', ['from' => \$to", $engines);
    }

    public function test_the_month_line_counts_the_loans_left_undiscounted_by_the_reason_the_run_named(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            foreach (['contract_id', 'reporting_period', 'product_code', 'ecl_discount_status', 'ecl_calculation_run_id'] as $c) {
                $t->string($c)->nullable();
            }
            $t->double('ecl_value')->nullable();
            $t->double('ecl_value_discounted')->nullable();
        });
        Schema::create('ecl_projection_runs', function (Blueprint $t) {
            $t->increments('id');
            $t->string('run_id');
            $t->text('exceptions')->nullable();
        });
        $rows = [
            ['A', '1050101', 'CALCULATED_TIME_PHASED', 'r1', 100.0, 90.0],
            ['B', '1050101', 'TIME_PHASED_UNRESOLVED', 'r1', 50.0, null],
            ['C', '1050102', 'TIME_PHASED_UNRESOLVED', 'r2', 20.0, null],
            ['M', '1050301', 'NOT_CALCULATED', null, 999.0, null], // a Mega Farm loan: its own engine, outside the line
        ];
        foreach ($rows as [$id, $gl, $status, $run, $ecl, $disc]) {
            DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => '2025-12', 'product_code' => $gl, 'ecl_discount_status' => $status, 'ecl_calculation_run_id' => $run, 'ecl_value' => $ecl, 'ecl_value_discounted' => $disc]);
        }
        DB::table('ecl_projection_runs')->insert([
            ['run_id' => 'r1', 'exceptions' => json_encode(['B' => 'A locked original EIR is unavailable.'])],
            ['run_id' => 'r2', 'exceptions' => json_encode(['C' => 'A locked original EIR is unavailable.'])],
            ['run_id' => 'other', 'exceptions' => json_encode(['X' => 'a run of another month'])],
        ]);

        $this->assertSame(['A locked original EIR is unavailable' => 2], Bootstrap::unresolvedReasons('2025-12'));
        $this->assertSame('2025-12: MAIIC book undiscounted 170; discounted at the EIR on 1 loans: 90; 2 loans not discountable (2 A locked original EIR is unavailable)', Bootstrap::discountedLine('2025-12'));
    }
}
