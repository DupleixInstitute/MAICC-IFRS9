<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirFeedController;
use App\Http\Controllers\WorkspaceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * UI audit of 13 October 2026: the month-end can be finished from the screens.
 *  - A reporting period is locked (and unlocked, with a reason) from the
 *    E-Banker Feed's Periods Built tab, not only from `eir:lock-period`.
 *  - The Workspace lists what waits for a second person across the governed
 *    workflows, each line counting the rows its own screen lists.
 */
class MonthEndUiTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->timestamps(); });
        DB::table('users')->insert(['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->integer('locked_by')->nullable(); $t->timestamp('locked_at')->nullable(); $t->text('reason')->nullable(); $t->longText('settings_snapshot')->nullable(); $t->timestamps(); });
        if (! Schema::hasTable('loan_books')) {
            Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period')->nullable(); $t->string('contract_id')->nullable(); });
        }
        DB::table('loan_books')->insert(['reporting_period' => '2026-09', 'contract_id' => 'C1']);
    }

    private function lock(string $period, array $body): \Illuminate\Http\RedirectResponse
    {
        $this->actingAs(User::findOrFail(10));
        $request = Request::create("/eir-feed/periods/{$period}/lock", 'POST', $body);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);

        return app(EirFeedController::class)->lockPeriod($request, $period);
    }

    public function test_the_lock_route_is_registered_and_demands_the_govern_permission(): void
    {
        $this->assertTrue(Route::has('eir-feed.lock-period'));
        $govern = collect((new EirFeedController())->getMiddleware())->firstWhere('middleware', 'permission:eir.govern');
        $this->assertContains('lockPeriod', $govern['options']['only']);
    }

    public function test_a_period_is_locked_and_unlocked_from_the_screen_with_a_reason(): void
    {
        $this->lock('2026-09', ['reason' => 'September month-end signed off by the CFO.']);
        $this->assertStringContainsString('Period 2026-09 locked', session('success'));
        $row = DB::table('reporting_period_locks')->where('reporting_period', '2026-09')->first();
        $this->assertNotNull($row);
        $this->assertSame(10, (int) $row->locked_by);
        $this->assertSame('September month-end signed off by the CFO.', $row->reason);
        $this->assertTrue(\App\Support\ReportingPeriodLock::isLocked('2026-09'));

        $this->lock('2026-09', ['reason' => 'Restatement approved, board minute 12.', 'unlock' => 1]);
        $this->assertStringContainsString('Period 2026-09 unlocked', session('success'));
        $this->assertFalse(\App\Support\ReportingPeriodLock::isLocked('2026-09'));
    }

    public function test_a_period_without_a_loan_book_is_not_locked(): void
    {
        $this->lock('2026-10', ['reason' => 'October month-end signed off.']);
        $this->assertStringContainsString('has no loan book', session('error'));
        $this->assertSame(0, DB::table('reporting_period_locks')->count());
    }

    public function test_the_reason_is_required(): void
    {
        $this->expectException(ValidationException::class);
        $this->lock('2026-09', ['reason' => 'short']);
    }

    public function test_the_workspace_lists_only_what_waits_for_approval(): void
    {
        Schema::create('loan_book_builds', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('loan_write_offs', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('calculation_status')->nullable(); $t->string('schedule_approval_status')->nullable(); $t->timestamp('locked_at')->nullable(); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('classification_status')->nullable(); });
        Schema::create('eir_accounting_rules', function (Blueprint $t) { $t->increments('id'); $t->timestamp('approved_at')->nullable(); });
        Schema::create('finance_sicr_triggers', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('governed_scenario_sets', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('fli_fits', function (Blueprint $t) { $t->increments('id'); $t->string('approval_status'); });
        Schema::create('fli_model_versions', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('fli_overlays', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        Schema::create('fli_run_snapshots', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });

        DB::table('loan_book_builds')->insert([['status' => 'PROPOSED'], ['status' => 'BUILT']]);
        DB::table('loan_write_offs')->insert([['status' => 'PROPOSED'], ['status' => 'PROPOSED'], ['status' => 'APPROVED']]);
        DB::table('contract_eir')->insert([['calculation_status' => 'CALCULATED', 'schedule_approval_status' => 'APPROVED', 'locked_at' => null], ['calculation_status' => 'LOCKED', 'schedule_approval_status' => 'DRAFT', 'locked_at' => now()]]);
        DB::table('fli_run_snapshots')->insert([['status' => 'RECORDED'], ['status' => 'SIGNED_OFF']]);

        $method = new \ReflectionMethod(WorkspaceController::class, 'awaitingApproval');
        $method->setAccessible(true);
        $lines = collect($method->invoke(new WorkspaceController()))->keyBy('label');

        $this->assertSame(1, $lines['Loan book builds proposed']['count']);
        $this->assertSame(2, $lines['Write-offs proposed']['count']);
        $this->assertSame(1, $lines['EIRs calculated, awaiting approval and lock']['count']);
        $this->assertSame(1, $lines['Repayment schedules awaiting review']['count']);
        $this->assertSame(1, $lines['Forward-looking months recorded, awaiting sign-off']['count']);
        $this->assertStringContainsString('tab=builds', $lines['Loan book builds proposed']['href']);
        // nothing waiting, no line: the governance change proposed nowhere is not listed
        $this->assertFalse($lines->has('Governance setting changes proposed'));
        $this->assertFalse($lines->has('Manual overlays proposed'));
    }
}
