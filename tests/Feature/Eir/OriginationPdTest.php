<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Pd\OriginationPdService;
use App\Services\RunRecordService;
use App\Support\RunContext;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Each loan's starting risk (PD at Origination Specification v1, section 9):
 * one record per loan, written once and never changed, with the right label;
 * the first capture on a database fills every month already held; the
 * PD-change test in shadow names the loans it would move and moves none.
 */
class OriginationPdTest extends TestCase
{
    use RefreshDatabase;

    private int $pf;

    protected function setUp(): void
    {
        parent::setUp();
        (new GovernanceSettingsSeeder())->run();
        $user = User::create(['name' => 'Analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->pf = DB::table('loan_portfolios')->value('id') ?? DB::table('loan_portfolios')->insertGetId(['name' => 'Loans', 'created_by_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function loan(string $id, string $originated): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'rate_type' => 'FIXED', 'origination_date' => $originated,
            'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_source' => 'GENERATED', 'schedule_approval_status' => 'APPROVED',
            'calculation_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function month(string $id, string $period, ?float $pd, int $stage = 1): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'customer_id' => $id, 'customer_name' => "Customer {$id}", 'reporting_period' => $period,
            'reporting_year' => (int) substr($period, 0, 4), 'reporting_month' => (int) substr($period, 5, 2), 'loan_portfolio_id' => $this->pf, 'product_group' => 'Term loan',
            'ifrs9stage_post_qualitative' => $stage, 'ifrs9_stage' => $stage, 'carrying_amount' => 1000, 'commitments' => 0, 'facility_utilisation_rate' => 1,
            'pd_post_fli' => $pd, 'pd_prefli' => $pd, 'lgd_value' => 0.5, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(string $id): object
    {
        return DB::table('loan_origination_pds')->where('contract_id', $id)->first();
    }

    public function test_each_loan_gets_one_labelled_record_that_never_changes(): void
    {
        // the PD history begins in 2025-01
        $this->loan('900000000001', '2025-03-10'); // made inside the history, PD that month
        $this->loan('900000000002', '2025-01-15'); // made inside the history, first PD later
        $this->loan('900000000003', '2022-06-01'); // made before the history
        $this->loan('900000000004', '2025-02-01'); // never a PD
        $this->month('900000000003', '2025-01', 0.05);
        $this->month('900000000001', '2025-03', 0.10);
        $this->month('900000000002', '2025-03', 0.08);
        $this->month('900000000004', '2025-03', null);
        $service = app(OriginationPdService::class);
        $service->backfill();

        $this->assertSame('2025-01', $service->historyStart());
        $this->assertSame([OriginationPdService::AT_ORIGINATION, '2025-03', 0], [$this->record('900000000001')->basis, $this->record('900000000001')->reporting_period, (int) $this->record('900000000001')->months_after_origination]);
        $this->assertSame([OriginationPdService::AFTER_ORIGINATION, 2], [$this->record('900000000002')->basis, (int) $this->record('900000000002')->months_after_origination]);
        $this->assertSame(OriginationPdService::BEFORE_HISTORY, $this->record('900000000003')->basis);
        $this->assertSame(OriginationPdService::UNAVAILABLE, $this->record('900000000004')->basis);

        // a later month, a re-run and a rebuild leave every record as it was; only an unavailable mark gives way
        $this->month('900000000001', '2025-04', 0.40);
        $this->month('900000000004', '2025-04', 0.20);
        $service->capture('2025-04');
        $service->capture('2025-04');
        $this->assertEqualsWithDelta(0.10, (float) $this->record('900000000001')->pd_12m_post_fli, 1e-9);
        $this->assertSame([OriginationPdService::AFTER_ORIGINATION, '2025-04'], [$this->record('900000000004')->basis, $this->record('900000000004')->reporting_period]);
        $this->assertSame(4, DB::table('loan_origination_pds')->count(), 'one record per loan');
    }

    public function test_the_first_capture_on_a_database_fills_every_month_already_held(): void
    {
        $this->loan('900000000011', '2022-01-01');
        $this->month('900000000011', '2025-01', 0.05);
        $this->month('900000000011', '2025-06', 0.30);
        app(OriginationPdService::class)->capture('2025-06');
        $this->assertSame('2025-01', $this->record('900000000011')->reporting_period, 'the earliest PD held, not the month being captured');
    }

    public function test_a_months_run_writes_the_records_at_its_end(): void
    {
        $this->loan('900000000021', '2026-09-03');
        $this->month('900000000021', '2026-09', 0.12);
        $runs = new RunRecordService();
        $id = $runs->start('2026-09', RunRecordService::MONTH_END, null, 'Month-end job');
        RunContext::open('2026-09', $id);
        RunContext::engine('2026-09', 'PD', null, fn () => true);
        RunContext::close('2026-09');
        $runs->finish($id, true);
        $this->assertSame([OriginationPdService::AT_ORIGINATION, $id], [$this->record('900000000021')->basis, (int) $this->record('900000000021')->run_record_id]);
    }

    public function test_the_shadow_test_names_the_loans_it_would_move_and_moves_none(): void
    {
        foreach (['900000000031', '900000000032', '900000000033', '900000000034'] as $id) {
            $this->loan($id, '2022-01-01');
        }
        $this->month('900000000031', '2025-01', 0.10);       // 10% -> 30%: three times and 20 points higher: moves
        $this->month('900000000032', '2025-01', 0.02);       // 2% -> 5%: 2.5 times but 3 points: stays
        $this->month('900000000033', '2025-01', 0.30);       // 30% -> 45%: 15 points but 1.5 times: stays
        $this->month('900000000034', '2025-01', 0.10, 2);    // started in Stage 2: not tested (decision 3)
        $this->month('900000000031', '2026-09', 0.30);
        $this->month('900000000032', '2026-09', 0.05);
        $this->month('900000000033', '2026-09', 0.45);
        $this->month('900000000034', '2026-09', 0.40);
        app(OriginationPdService::class)->backfill();
        $before = DB::table('loan_books')->where('reporting_period', '2026-09')->orderBy('contract_id')->pluck('ifrs9_stage')->all();

        $s = app(OriginationPdService::class)->shadow('2026-09');
        $this->assertSame([2.0, 0.10], [$s['thresholds']['ratio'], $s['thresholds']['points']], 'decision 4 as seeded');
        $this->assertSame(['900000000031'], array_column(array_filter($s['rows'], fn ($r) => $r['would_move']), 'contract_id'));
        $this->assertSame(3, $s['tested']);
        $this->assertSame(['starting reading taken outside Stage 1 (decision 3)' => 1], $s['excluded']);
        $this->assertSame($before, DB::table('loan_books')->where('reporting_period', '2026-09')->orderBy('contract_id')->pluck('ifrs9_stage')->all(), 'staging unchanged');
        $this->assertSame('Not applied', app(\App\Services\Eir\GovernanceService::class)->get('sicr_pd_change', \Carbon\CarbonImmutable::parse('2026-09-30')));
    }

    public function test_the_screen_and_its_export(): void
    {
        $this->loan('900000000041', '2026-09-03');
        $this->month('900000000041', '2026-09', 0.12);
        app(OriginationPdService::class)->backfill();
        $viewer = User::create(['name' => 'Auditor', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $viewer->givePermissionTo(Permission::findOrCreate('reports.ifrs9', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer = $viewer->fresh();
        $this->actingAs($viewer)->get(route('origination-pd.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('PD/OriginationPd')->where('counts.AT_ORIGINATION', 1)->where('shadow.period', '2026-09'));
        $this->actingAs($viewer)->get(route('origination-pd.export', ['shadow' => '2026-09']))->assertOk();
    }
}
