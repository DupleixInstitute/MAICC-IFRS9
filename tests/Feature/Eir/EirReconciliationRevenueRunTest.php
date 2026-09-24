<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirReconciliationController;
use App\Services\Eir\EirGlReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The revenue run behind the reconciliation screen.
 *
 * The middleware is not exercised here; the controller is called directly so
 * the ordering and refusal rules are tested without standing up users, roles
 * and permissions. What matters is that the roll-forward is built in order
 * and that a run which would produce a figure following from nothing is
 * refused rather than quietly performed.
 */
class EirReconciliationRevenueRunTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    private const PERIODS = ['2025-07', '2025-08', '2025-09'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->string('portfolio')->nullable();
            $t->date('origination_date')->nullable();
            $t->double('drawn_amount')->default(0);
            $t->double('contractual_rate')->nullable();
            $t->double('eir_effective_annual')->nullable();
            $t->double('opening_amortised_cost')->nullable();
            $t->string('source_day_count_basis')->nullable();
            $t->text('input_snapshot')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('eir_amortisation', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('reporting_period', 7);
            $t->double('opening_gross')->default(0);
            $t->double('interest_accrued')->default(0);
            $t->string('interest_basis')->default('GROSS');
            $t->double('unwind_amount')->default(0);
            $t->double('cash_received')->default(0);
            $t->string('cash_source')->default('DERIVED');
            $t->double('modification_gain_loss')->default(0);
            $t->double('closing_gross')->default(0);
            $t->double('ecl_allowance')->default(0);
            $t->timestamps();
        });
        Schema::create('gl_interest_postings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('gl_account_code')->nullable();
            $t->integer('period_year');
            $t->integer('period_month');
            $t->double('interest_income_posted');
            $t->timestamps();
        });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('reporting_period');
            $t->double('expected_loss_provision')->default(0);
            $t->integer('calculated_ifrs9_stage')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->integer('schedule_version')->default(1);
            $t->date('due_date');
            $t->double('principal_due')->default(0);
            $t->double('interest_due')->default(0);
            $t->double('fee_due')->default(0);
            $t->timestamps();
        });
        Schema::create('eir_actual_transactions', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->date('transaction_date');
            $t->string('transaction_type')->nullable();
            $t->double('total_amount')->default(0);
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('rows_affected')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('meta')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();

        $this->seedLockedContract();
    }

    /**
     * One locked facility with a schedule that reaches every seeded period, a
     * loan-book snapshot per period, and no delivered actuals — so cash is
     * taken from the schedule and labelled DERIVED.
     */
    private function seedLockedContract(): void
    {
        $flows = [];
        foreach (['2025-07-31', '2025-08-31', '2025-09-30', '2025-10-31'] as $due) {
            $flows[] = ['due_date' => $due, 'amount' => 30_000.0];
            DB::table('contract_cashflow_schedule')->insert([
                'contract_id' => 'C-1', 'schedule_version' => 1, 'due_date' => $due,
                'principal_due' => 25_000, 'interest_due' => 5_000, 'fee_due' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('contract_eir')->insert([
            'contract_id' => 'C-1', 'portfolio' => 'MAIIC', 'origination_date' => '2025-06-30',
            'drawn_amount' => 100_000, 'contractual_rate' => 0.24, 'eir_effective_annual' => 0.30,
            'opening_amortised_cost' => 100_000, 'source_day_count_basis' => 'ACT/365',
            'input_snapshot' => json_encode([
                'initial_net_investment' => 100_000,
                'metadata' => ['origination_date' => '2025-06-30'],
                'cash_flows' => $flows,
            ]),
            'locked_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (self::PERIODS as $period) {
            DB::table('loan_books')->insert([
                'contract_id' => 'C-1', 'reporting_period' => $period . '-30',
                'expected_loss_provision' => 0, 'calculated_ifrs9_stage' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            [$year, $month] = array_map('intval', explode('-', $period));
            DB::table('gl_interest_postings')->insert([
                'contract_id' => 'C-1', 'gl_account_code' => '4010100',
                'period_year' => $year, 'period_month' => $month, 'interest_income_posted' => 2_000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function invokeRun(string $period, string $mode): array
    {
        $request = Request::create('/eir-reconciliation/run-revenue', 'POST', [
            'period' => $period, 'mode' => $mode,
        ]);
        $request->setLaravelSession(app('session.store'));

        $response = app(EirReconciliationController::class)
            ->runRevenue($request, new EirGlReconciliationService());

        return $response->getSession()->get('revenue_run');
    }

    public function test_a_single_period_is_refused_while_earlier_periods_have_no_rows(): void
    {
        $flash = $this->invokeRun('2025-09', 'period');

        $this->assertSame('REFUSED', $flash['status']);
        $this->assertSame(['2025-07', '2025-08'], $flash['missing_periods']);
        $this->assertSame(0, DB::table('eir_amortisation')->count(),
            'a refused run must not write the row it was refused for');
    }

    public function test_the_catch_up_builds_every_period_in_order(): void
    {
        $flash = $this->invokeRun('2025-09', 'catch_up');

        $this->assertSame('COMPLETED', $flash['status']);
        $this->assertSame(self::PERIODS, $flash['periods_run'],
            'the roll-forward must be built oldest first, because each opening is the prior closing');
        $this->assertSame(3, $flash['totals']['created']);
        $this->assertSame(0, $flash['totals']['blocked']);

        $rows = DB::table('eir_amortisation')->orderBy('reporting_period')->get();
        $this->assertCount(3, $rows);
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $this->assertEqualsWithDelta((float) $rows[$index - 1]->closing_gross, (float) $row->opening_gross, 0.01,
                "{$row->reporting_period} must open on the previous period's closing balance");
        }
    }

    public function test_a_single_period_run_is_allowed_once_the_chain_reaches_it(): void
    {
        $this->invokeRun('2025-08', 'catch_up');
        DB::table('eir_amortisation')->where('reporting_period', '2025-09')->delete();

        $flash = $this->invokeRun('2025-09', 'period');

        $this->assertSame('COMPLETED', $flash['status']);
        $this->assertSame(['2025-09'], $flash['periods_run']);
        $this->assertSame(1, $flash['totals']['created']);
    }

    public function test_re_running_a_calculated_period_leaves_it_unchanged(): void
    {
        $this->invokeRun('2025-09', 'catch_up');
        $before = DB::table('eir_amortisation')->orderBy('id')->pluck('interest_accrued')->all();

        $flash = $this->invokeRun('2025-09', 'catch_up');

        $this->assertSame(0, $flash['totals']['created']);
        $this->assertSame(3, $flash['totals']['unchanged']);
        $this->assertSame($before, DB::table('eir_amortisation')->orderBy('id')->pluck('interest_accrued')->all(),
            'a re-run must never silently restate a figure; that needs a stated reason');
    }

    public function test_a_period_with_no_gl_postings_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->invokeRun('2024-01', 'catch_up');
    }
}
