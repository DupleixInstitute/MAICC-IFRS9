<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Eir\StagingService;
use App\Services\ReperformanceService;
use App\Services\RunRecordService;
use App\Support\RunContext;
use Database\Seeders\GovernanceSettingsSeeder;
use Database\Seeders\StagingThresholdSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Re-performance (Staging Module Specification v1, section 8.8, item 6;
 * acceptance 19): a month re-performed from its frozen run record reproduces
 * its stored stages and figures loan by loan, or names each difference; an
 * input that moved after the run is named; a snapshot altered after the run
 * cannot be re-performed from.
 */
class ReperformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        (new GovernanceSettingsSeeder())->run();
        (new StagingThresholdSeeder())->run();
        $this->user = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
    }

    private function book(string $period): void
    {
        $pf = DB::table('loan_portfolios')->value('id') ?? DB::table('loan_portfolios')->insertGetId(['name' => 'Loans', 'created_by_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['900000000001', 0, 1000], ['900000000002', 45, 2000], ['900000000003', 200, 3000]] as [$c, $dpd, $bal]) {
            DB::table('loan_books')->insert(['contract_id' => $c, 'customer_id' => $c, 'reporting_period' => $period, 'reporting_year' => (int) substr($period, 0, 4),
                'reporting_month' => (int) substr($period, 5, 2), 'loan_portfolio_id' => $pf, 'product_group' => 'Term loan', 'tenor' => 36, 'overdue_days' => $dpd,
                'ifrs9stage_post_qualitative' => 1, 'carrying_amount' => $bal, 'ead' => $bal, 'commitments' => 0, 'facility_utilisation_rate' => 1,
                'pd_post_fli' => 0.1, 'lgd_value' => 0.5, 'ecl_value' => $bal * 0.05, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** A month-end run of the month that stages the book, as the month-end job runs it. */
    private function monthEnd(string $period): object
    {
        $runs = new RunRecordService();
        $id = $runs->start($period, RunRecordService::MONTH_END, $this->user->id, 'Month-end job');
        RunContext::open($period, $id);
        app(StagingService::class)->stage($period, $this->user->id);
        RunContext::close($period);
        $runs->finish($id, true);

        return DB::table('run_records')->find($id);
    }

    public function test_a_month_reperformed_in_place_reproduces_its_stages_and_figures(): void
    {
        $this->book('2026-09');
        $run = $this->monthEnd('2026-09');
        $this->assertSame(3, DB::table('run_record_loans')->where('run_record_id', $run->id)->count(), 'each loan kept as the run left it');
        $this->assertSame(['Staging'], array_column(json_decode($run->steps, true), 'engine'));

        $service = app(ReperformanceService::class);
        $id = $service->inPlace($service->runFor('2026-09'), $this->user->id);
        $p = DB::table('reperformances')->find($id);
        $this->assertSame('REPRODUCED', $p->status, json_encode(DB::table('reperformance_rows')->where('reperformance_id', $id)->get()));
        $this->assertSame(3, json_decode($p->summary, true)['loans']);
    }

    public function test_every_difference_is_named_loan_by_loan(): void
    {
        $this->book('2026-09');
        $this->monthEnd('2026-09');
        // after the run: a figure restated in the table, a stage overwritten, and an input of the month moved
        DB::table('loan_books')->where('contract_id', '900000000002')->update(['ecl_value' => 123.45]);
        DB::table('loan_books')->where('contract_id', '900000000003')->update(['ifrs9_stage' => 2]);
        $c = \App\Services\Eir\GovernanceService::catalogue()['day_count'];
        DB::table('governance_settings')->insert(['key' => 'day_count', 'value' => '30/360', 'options' => json_encode($c['options']), 'label' => $c['label'], 'description' => 'test',
            'effective_from' => '2026-09-01', 'status' => 'APPROVED', 'set_by' => $this->user->id, 'approved_by' => $this->user->id, 'approved_at' => now(), 'reason' => 'back-dated by hand', 'created_at' => now(), 'updated_at' => now()]);
        app()->forgetInstance(\App\Services\Eir\GovernanceService::class);

        $service = app(ReperformanceService::class);
        $id = $service->inPlace($service->runFor('2026-09'), $this->user->id);
        $this->assertSame('DIFFERENCES', DB::table('reperformances')->find($id)->status);
        $rows = DB::table('reperformance_rows')->where('reperformance_id', $id)->get();
        $this->assertNotNull($rows->first(fn ($r) => $r->check === 'figure' && $r->contract_id === '900000000002' && $r->item === 'ecl' && (float) $r->reperformed === 123.45));
        $this->assertNotNull($rows->first(fn ($r) => $r->check === 'figure' && $r->contract_id === '900000000003' && $r->item === 'stage'));
        $this->assertNotNull($rows->first(fn ($r) => $r->check === 'input' && str_contains($r->item, 'day_count')));
        $this->assertNull($rows->first(fn ($r) => $r->check === 'stage'), 'staged again under the frozen rules, every loan gives the stage the run left');
    }

    public function test_a_snapshot_altered_after_the_run_cannot_be_reperformed_and_a_workspace_is_never_this_database(): void
    {
        $this->book('2026-09');
        $run = $this->monthEnd('2026-09');
        $service = app(ReperformanceService::class);
        try {
            $service->inWorkspace($run, 'maiic_ifrs9', $this->user->id);
            $this->fail('a workspace that is not a throwaway copy was accepted');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('_reperform', $e->getMessage());
        }
        DB::table('run_records')->where('id', $run->id)->update(['snapshot' => str_replace('"day_count"', '"day_count_x"', $run->snapshot)]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not verify');
        $service->runFor('2026-09');
    }

    public function test_the_run_page_reperforms_and_exports(): void
    {
        $this->book('2026-09');
        $run = $this->monthEnd('2026-09');
        $governor = User::create(['name' => 'Governor', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $governor->givePermissionTo(Permission::findOrCreate('eir.govern', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $governor = $governor->fresh();

        $this->actingAs($governor)->from(route('run-records.show', $run->id))->post(route('run-records.reperform', $run->id))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($governor)->get(route('run-records.show', $run->id))->assertOk()
            ->assertInertia(fn ($page) => $page->where('reperformances.0.status', 'REPRODUCED')->where('canReperform', true));
        $id = DB::table('reperformances')->value('id');
        $this->actingAs($governor)->get(route('run-records.reperformance', [$id, 'xlsx']))->assertOk();
    }
}
