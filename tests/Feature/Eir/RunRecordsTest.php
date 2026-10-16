<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Eir\GovernanceService;
use App\Services\RunRecordService;
use App\Support\RunContext;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The record of what each month ran on (Staging Module Specification v1,
 * section 8.8; acceptance 16 and 17): every run of a month frozen and
 * fingerprinted, its number on the month's results, the run of record named
 * at the lock, earlier runs kept and superseded, and the "Settings in force"
 * and "What changed" reports.
 */
class RunRecordsTest extends TestCase
{
    use RefreshDatabase;

    private RunRecordService $runs;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (new GovernanceSettingsSeeder())->run();
        $this->runs = new RunRecordService();
        $this->user = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
    }

    private function bookRow(string $period, string $contract = '900000000001'): void
    {
        $pf = DB::table('loan_portfolios')->value('id') ?? DB::table('loan_portfolios')->insertGetId(['name' => 'Loans', 'created_by_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_books')->insert(['contract_id' => $contract, 'customer_id' => $contract, 'reporting_period' => $period, 'reporting_year' => (int) substr($period, 0, 4),
            'reporting_month' => (int) substr($period, 5, 2), 'loan_portfolio_id' => $pf, 'product_group' => 'Term loan', 'ifrs9stage_post_qualitative' => 1, 'carrying_amount' => 1000,
            'commitments' => 0, 'facility_utilisation_rate' => 1, 'pd_post_fli' => 0.1, 'lgd_value' => 0.5, 'ecl_value' => 50, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A month-end run with two engine steps, as the month-end job runs it. */
    private function monthEnd(string $period): int
    {
        $id = $this->runs->start($period, RunRecordService::MONTH_END, $this->user->id, 'Month-end job');
        RunContext::open($period, $id);
        RunContext::engine($period, 'Staging', $this->user->id, fn () => true);
        RunContext::engine($period, 'ECL', $this->user->id, fn () => true);
        RunContext::close($period);
        $this->runs->finish($id, true);

        return $id;
    }

    public function test_a_month_end_run_is_frozen_fingerprinted_and_stamped_on_the_months_results(): void
    {
        $this->bookRow('2026-09');
        $this->bookRow('2026-08', '900000000002');
        $id = $this->monthEnd('2026-09');

        $run = DB::table('run_records')->find($id);
        $this->assertSame(RunRecordService::COMPLETED, $run->status);
        $this->assertSame(['Staging', 'ECL'], array_column(json_decode($run->steps, true), 'engine'));
        $this->assertSame(64, strlen($run->fingerprint));
        $this->assertTrue($this->runs->verifies($run));
        $snapshot = json_decode($run->snapshot, true);
        $this->assertArrayHasKey('day_count', $snapshot['governance_settings']);
        $this->assertNotEmpty($run->code_version);
        $this->assertSame($id, (int) DB::table('loan_books')->where('reporting_period', '2026-09')->value('run_record_id'), 'the month carries its run number');
        $this->assertNull(DB::table('loan_books')->where('reporting_period', '2026-08')->value('run_record_id'), 'another month does not');

        // a later month-end of the same unlocked month supersedes it, and both are kept
        $second = $this->monthEnd('2026-09');
        $this->assertSame([RunRecordService::SUPERSEDED, $second], [DB::table('run_records')->find($id)->status, (int) DB::table('run_records')->find($id)->superseded_by_run]);
        $this->assertSame($second, (int) DB::table('loan_books')->where('reporting_period', '2026-09')->value('run_record_id'));

        // a snapshot altered in the database no longer verifies
        DB::table('run_records')->where('id', $second)->update(['snapshot' => str_replace('"day_count"', '"day_count_x"', DB::table('run_records')->find($second)->snapshot)]);
        $this->assertFalse($this->runs->verifies(DB::table('run_records')->find($second)));
    }

    public function test_an_engine_run_on_its_own_gets_a_screen_run_and_a_real_engine_records_its_step(): void
    {
        RunContext::engine('2026-09', 'Mega Farm', $this->user->id, fn () => true);
        $run = DB::table('run_records')->where('kind', RunRecordService::SCREEN)->first();
        $this->assertSame([RunRecordService::COMPLETED, 'Mega Farm run on its own'], [$run->status, $run->label]);

        // a wired engine: the forward-looking route on a month with no book fails, and its run says so
        try {
            app(\App\Services\Fli\FliRouteService::class)->apply('2026-07', $this->user->id);
        } catch (\Throwable) {
        }
        $route = DB::table('run_records')->where('reporting_period', '2026-07')->first();
        $this->assertNotNull($route, 'the engine opened a run of its own');
        $this->assertSame('Forward-looking route', json_decode($route->steps, true)[0]['engine']);
    }

    public function test_the_lock_names_the_run_of_record_and_refuses_a_month_changed_since(): void
    {
        $this->bookRow('2026-09');
        $lock = fn (array $more = []) => Artisan::call('eir:lock-period', ['period' => '2026-09', '--user' => $this->user->id, '--reason' => 'September reported to the Board'] + $more);

        $this->assertSame(1, $lock(), 'no run of the month: refused');
        $id = $this->monthEnd('2026-09');
        RunContext::engine('2026-09', 'ECL', $this->user->id, fn () => true); // an engine run by hand afterwards
        $this->assertSame(1, $lock(), 'changed from a screen since the month-end: refused');
        try {
            $this->runs->runOfRecord('2026-09');
            $this->fail('a month changed from a screen was offered as the run of record');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('changed it from a screen afterwards', $e->getMessage());
        }

        $again = $this->monthEnd('2026-09');
        $this->assertSame(0, $lock());
        $this->assertSame($again, (int) DB::table('reporting_period_locks')->where('reporting_period', '2026-09')->value('run_record_id'));
        $this->assertNotNull(DB::table('run_records')->find($again)->of_record_at);
        $this->assertSame(RunRecordService::SUPERSEDED, DB::table('run_records')->find($id)->status);

        // unlocked: the run stays on record as the one the month reported until then
        app(\App\Services\RunRecordService::class)->releaseOfRecord('2026-09');
        $this->assertNotNull(DB::table('run_records')->find($again)->of_record_until);
    }

    public function test_a_month_computed_before_run_records_is_locked_only_when_told(): void
    {
        $this->bookRow('2026-06');
        $this->assertSame(0, Artisan::call('eir:lock-period', ['period' => '2026-06', '--user' => $this->user->id, '--reason' => 'Computed before run records existed', '--without-run-record' => true]));
        $this->assertNull(DB::table('reporting_period_locks')->where('reporting_period', '2026-06')->value('run_record_id'));
    }

    public function test_settings_in_force_and_what_changed(): void
    {
        $this->bookRow('2026-08');
        $this->bookRow('2026-09');
        $this->monthEnd('2026-08');
        $gov = new GovernanceService();
        $p = $gov->propose('day_count', '30/360', '2026-09-01', 'Finance moves the day count to 30/360', $this->user->id);
        $checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $gov->approve($p->id, $checker->id);
        $this->monthEnd('2026-09');

        $diff = RunRecordService::differences(json_decode($this->runs->monthRun('2026-08')->snapshot, true), json_decode($this->runs->monthRun('2026-09')->snapshot, true));
        $day = collect($diff)->first(fn ($d) => $d[1] === 'day_count');
        $this->assertNotNull($day);
        $this->assertStringStartsWith('ACT/365', $day[2]);
        $this->assertStringStartsWith('30/360', $day[3]);

        $viewer = User::create(['name' => 'Auditor', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $viewer->givePermissionTo(Permission::findOrCreate('eir.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer = $viewer->fresh();
        $this->actingAs($viewer)->get(route('run-records.index', ['from' => '2026-08', 'to' => '2026-09']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('RunRecords/Index')->where('compare.from', '2026-08')->has('compare.rows'));
        $this->actingAs($viewer)->get(route('run-records.show', $this->runs->monthRun('2026-09')->id))->assertOk()
            ->assertInertia(fn ($page) => $page->component('RunRecords/Show')->where('verifies', true)->has('sections.Governance Centre settings'));
        $this->actingAs($viewer)->get(route('run-records.settings', ['2026-09', 'xlsx']))->assertOk();
        $pdf = $this->actingAs($viewer)->get(route('run-records.changes', ['2026-08', '2026-09', 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }
}
