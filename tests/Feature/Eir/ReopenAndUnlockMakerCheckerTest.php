<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirCalculationController;
use App\Http\Controllers\EirFeedController;
use App\Http\Controllers\WorkspaceController;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\EirContractInputService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\EirReopenRequestService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\PeriodUnlockService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * Documentation audit of 14 October 2026, items 1, 8 and 9: reopening a
 * locked original EIR and unlocking a locked reporting period are each
 * requested by one person with a reason and approved by a different one;
 * both requests are listed under the Workspace's "Awaiting approval", with
 * the restructure, rate-reset, take-on and staging threshold proposals.
 */
class ReopenAndUnlockMakerCheckerTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email')->nullable(); $t->string('password')->nullable(); $t->timestamps(); });
        DB::table('users')->insert([['id' => 10, 'name' => 'Maker'], ['id' => 20, 'name' => 'Checker']]);
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN');
            $t->string('rate_type')->default('FIXED'); $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable();
            $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('STATED'); $t->string('schedule_source')->nullable();
            $t->string('schedule_approval_status')->default('NOT_GENERATED');
            $t->double('eir_period')->nullable(); $t->double('eir_nominal_annual')->nullable(); $t->double('eir_effective_annual')->nullable();
            $t->string('rate_source')->nullable(); $t->integer('solver_iterations')->nullable(); $t->double('solver_residual')->nullable();
            $t->string('solver_method')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('calculation_status')->default('PENDING');
            $t->text('calculation_error')->nullable(); $t->string('calculated_at')->nullable(); $t->integer('calculated_by')->nullable();
            $t->string('locked_at')->nullable(); $t->integer('locked_by')->nullable(); $t->string('locked_label')->nullable(); $t->timestamps();
        });
        Schema::create('eir_calculation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('eir_period')->nullable(); $t->double('eir_nominal_annual')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->string('rate_source')->nullable(); $t->integer('solver_iterations')->nullable(); $t->double('solver_residual')->nullable(); $t->string('solver_method')->nullable(); $t->json('input_snapshot')->nullable(); $t->string('calculation_status'); $t->text('calculation_error')->nullable(); $t->string('calculated_at')->nullable(); $t->integer('calculated_by')->nullable(); $t->string('locked_at')->nullable(); $t->integer('locked_by')->nullable(); $t->string('archive_action'); $t->string('archive_reason'); $t->integer('archived_by')->nullable(); $t->string('archived_at'); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->timestamps(); });
        Schema::create('eir_amortisation_history', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis'); $t->double('unwind_amount'); $t->double('cash_received'); $t->string('cash_source'); $t->double('modification_gain_loss'); $t->double('closing_gross'); $t->double('ecl_allowance'); $t->string('originally_created_at')->nullable(); $t->string('superseded_at'); $t->integer('superseded_by')->nullable(); $t->string('supersession_reason'); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period')->nullable(); $t->double('ecl_value_discounted')->nullable(); $t->double('ecl_discounting_effect')->nullable(); $t->double('ecl_discount_rate')->nullable(); $t->string('ecl_discount_rate_source')->nullable(); $t->string('ecl_discount_status')->nullable(); $t->double('ecl_discount_horizon_years')->nullable(); $t->string('ecl_calculation_run_id')->nullable(); $t->string('ecl_calculated_at')->nullable(); });
        Schema::create('reporting_period_locks', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period')->unique(); $t->integer('locked_by')->nullable(); $t->timestamp('locked_at')->nullable(); $t->text('reason')->nullable(); $t->longText('settings_snapshot')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_14_240000_create_reopen_and_unlock_requests.php'))->up();

        DB::table('contract_eir')->insert(['contract_id' => 'C-1', 'eir_effective_annual' => 0.12, 'calculation_status' => 'LOCKED', 'locked_at' => '2025-02-01 10:00:00', 'locked_by' => 20, 'calculated_by' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('eir_amortisation')->insert(['contract_id' => 'C-1', 'reporting_period' => '2025-01', 'opening_gross' => 1000, 'interest_accrued' => 10, 'interest_basis' => 'GROSS', 'unwind_amount' => 0,
            'cash_received' => 0, 'cash_source' => 'DERIVED', 'modification_gain_loss' => 0, 'closing_gross' => 1010, 'ecl_allowance' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function reopens(): EirReopenRequestService
    {
        $readiness = new EirReadinessService();

        return new EirReopenRequestService(new EirCalculationService(new EirContractInputService($readiness, new GovernanceService()), new CalculateEirService()));
    }

    public function test_a_locked_eir_is_reopened_only_when_a_second_person_approves_the_request(): void
    {
        $svc = $this->reopens();
        $id = $svc->request('C-1', 10, 'The integral fee was classified on the wrong line.');
        $this->assertSame('LOCKED', DB::table('contract_eir')->value('calculation_status'), 'a request changes nothing');

        try {
            $svc->approve($id, 10);
            $this->fail('The person who asked approved their own reopening.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        try {
            $svc->request('C-1', 20, 'A second request for the same contract.');
            $this->fail('Two open requests for one contract.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('already awaiting approval', $e->getMessage());
        }

        $reopened = $svc->approve($id, 20);
        $this->assertSame('REOPENED', $reopened->calculation_status);
        $this->assertNull($reopened->locked_at);
        $this->assertSame('The integral fee was classified on the wrong line.', DB::table('eir_calculation_history')->value('archive_reason'));
        $this->assertSame(0, DB::table('eir_amortisation')->count());
        $r = DB::table('eir_reopen_requests')->first();
        $this->assertSame('APPROVED', $r->status);
        $this->assertSame(20, (int) $r->decided_by);
    }

    public function test_an_approval_across_a_locked_month_is_refused_and_the_request_stays_open(): void
    {
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2025-01', 'locked_by' => 1, 'locked_at' => now(), 'reason' => 'Year-end closed']);
        $svc = $this->reopens();
        $id = $svc->request('C-1', 10, 'The integral fee was classified on the wrong line.');

        try {
            $svc->approve($id, 20);
            $this->fail('A locked month must refuse the reopening.');
        } catch (\App\Support\LockedPeriodException $e) {
            $this->assertSame('LOCKED', DB::table('contract_eir')->value('calculation_status'));
            $this->assertSame('PROPOSED', DB::table('eir_reopen_requests')->value('status'));
        }
    }

    public function test_the_asker_withdraws_and_only_another_person_rejects(): void
    {
        $svc = $this->reopens();
        $id = $svc->request('C-1', 10, 'The integral fee was classified on the wrong line.');
        try {
            $svc->close($id, 10, 'reject', 'Changed my mind about it.');
            $this->fail('The asker rejected their own request.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('withdraw it instead', $e->getMessage());
        }
        $svc->close($id, 20, 'reject', 'The fee line is correct as reviewed.');
        $this->assertSame('REJECTED', DB::table('eir_reopen_requests')->value('status'));
        $this->assertSame('LOCKED', DB::table('contract_eir')->value('calculation_status'));
    }

    public function test_the_reopening_routes_need_eir_govern_not_the_administrator_override(): void
    {
        $this->assertTrue(Route::has('eir-calculations.reopen.approve'));
        $this->assertTrue(Route::has('eir-calculations.reopen.close'));
        $m = collect((new EirCalculationController())->getMiddleware());
        $govern = $m->firstWhere('middleware', 'permission:eir.govern');
        foreach (['reopen', 'approveReopen', 'closeReopen'] as $action) {
            $this->assertContains($action, $govern['options']['only']);
        }
        // the settings permission no longer stands in front of the reopening
        $settings = $m->firstWhere('middleware', 'permission:settings');
        $this->assertNotContains('reopen', $settings['options']['only'] ?? []);
        $this->assertStringNotContainsString('Only an administrator', file_get_contents(app_path('Http/Controllers/EirCalculationController.php')));
    }

    public function test_a_period_is_unlocked_only_when_a_second_person_approves(): void
    {
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-09', 'locked_by' => 10, 'locked_at' => now(), 'reason' => 'Signed off']);
        $svc = new PeriodUnlockService();
        $id = $svc->request('2026-09', 10, 'Restatement approved, board minute 12.');
        $this->assertTrue(\App\Support\ReportingPeriodLock::isLocked('2026-09'), 'a request changes nothing');
        try {
            $svc->approve($id, 10);
            $this->fail('The person who asked approved their own unlock.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        $this->assertSame('2026-09', $svc->approve($id, 20));
        $this->assertFalse(\App\Support\ReportingPeriodLock::isLocked('2026-09'));

        try {
            $svc->request('2026-08', 10, 'A period that was never locked.');
            $this->fail('An unlocked period cannot be asked to unlock.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('not locked', $e->getMessage());
        }
    }

    public function test_the_console_requests_and_a_second_user_approves(): void
    {
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-09', 'locked_by' => 10, 'locked_at' => now(), 'reason' => 'Signed off']);
        $this->assertSame(0, Artisan::call('eir:lock-period', ['period' => '2026-09', '--user' => 10, '--unlock' => true, '--reason' => 'Restatement approved, minute 12.']));
        $this->assertTrue(\App\Support\ReportingPeriodLock::isLocked('2026-09'));
        $this->assertSame(1, Artisan::call('eir:lock-period', ['period' => '2026-09', '--user' => 10, '--approve-unlock' => true]));
        $this->assertTrue(\App\Support\ReportingPeriodLock::isLocked('2026-09'));
        $this->assertSame(0, Artisan::call('eir:lock-period', ['period' => '2026-09', '--user' => 20, '--approve-unlock' => true]));
        $this->assertFalse(\App\Support\ReportingPeriodLock::isLocked('2026-09'));
    }

    public function test_the_unlock_routes_demand_the_govern_permission(): void
    {
        $this->assertTrue(Route::has('eir-feed.unlock.approve'));
        $this->assertTrue(Route::has('eir-feed.unlock.close'));
        $govern = collect((new EirFeedController())->getMiddleware())->firstWhere('middleware', 'permission:eir.govern');
        $this->assertContains('approveUnlock', $govern['options']['only']);
        $this->assertContains('closeUnlock', $govern['options']['only']);
    }

    public function test_the_workspace_lists_the_new_proposals_and_requests(): void
    {
        foreach (['loan_book_builds' => 'status', 'loan_write_offs' => 'status', 'contract_fees' => 'classification_status', 'finance_sicr_triggers' => 'status',
            'governed_scenario_sets' => 'status', 'fli_fits' => 'approval_status', 'fli_model_versions' => 'status', 'fli_overlays' => 'status', 'fli_run_snapshots' => 'status',
            'restructure_events' => 'status', 'rate_reset_events' => 'classification_status', 'takeon_build_requests' => 'status', 'staging_thresholds' => 'status'] as $table => $col) {
            Schema::create($table, function (Blueprint $t) use ($col) { $t->increments('id'); $t->string($col)->nullable(); });
        }
        Schema::create('eir_accounting_rules', function (Blueprint $t) { $t->increments('id'); $t->timestamp('approved_at')->nullable(); });
        DB::table('restructure_events')->insert([['status' => 'PROPOSED'], ['status' => 'CANDIDATE']]);
        DB::table('rate_reset_events')->insert([['classification_status' => 'PROPOSED'], ['classification_status' => 'PROPOSED'], ['classification_status' => 'DETECTED']]);
        DB::table('takeon_build_requests')->insert([['status' => 'PROPOSED'], ['status' => 'SUPERSEDED']]);
        DB::table('staging_thresholds')->insert([['status' => 'PROPOSED'], ['status' => 'APPROVED']]);
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-09', 'locked_by' => 10, 'locked_at' => now(), 'reason' => 'Signed off']);
        (new PeriodUnlockService())->request('2026-09', 10, 'Restatement approved, board minute 12.');
        $this->reopens()->request('C-1', 10, 'The integral fee was classified on the wrong line.');

        $method = new \ReflectionMethod(WorkspaceController::class, 'awaitingApproval');
        $method->setAccessible(true);
        $lines = collect($method->invoke(new WorkspaceController()))->keyBy('label');

        $this->assertSame(1, $lines['Restructure classifications proposed']['count']);
        $this->assertSame(2, $lines['Rate-reset classifications awaiting approval']['count']);
        $this->assertStringContainsString('tab=resets', $lines['Rate-reset classifications awaiting approval']['href']);
        $this->assertSame(1, $lines['Take-on builds proposed']['count']);
        $this->assertSame(1, $lines['Staging threshold proposals']['count']);
        $this->assertSame(1, $lines['Locked EIR reopenings requested']['count']);
        $this->assertSame(1, $lines['Period unlocks requested']['count']);
        $this->assertStringContainsString('tab=periods', $lines['Period unlocks requested']['href']);
    }
}
