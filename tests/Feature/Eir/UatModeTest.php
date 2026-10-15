<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\PeriodUnlockService;
use App\Services\Eir\QualitativeSicrService;
use App\Services\Eir\StagingThresholdService;
use App\Support\MakerChecker;
use Database\Seeders\GovernanceSettingsSeeder;
use Database\Seeders\SicrQualitativeFactorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 8.9 (acceptance 20 to 23; decision
 * S11): one maker-checker check for every workflow; the override works only on
 * a non-production server; every self-approval is recorded and reported, with
 * a banner while UAT mode is on; switching it off needs a second person.
 */
class UatModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'UAT administrator', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->admin->assignRole('admin');
        $this->admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('eir.govern', 'web'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = $this->admin->fresh();
        $this->second = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        (new GovernanceSettingsSeeder())->run();
    }

    private function uatOn(): void
    {
        $this->assertSame(0, Artisan::call('governance:uat-mode', ['action' => 'on', '--user' => $this->admin->id]));
    }

    private function production(): void
    {
        $this->app['env'] = 'production';
    }

    /** The self-approvals the workflows below attempt, each by the administrator on their own proposal. */
    private function selfApprovals(): array
    {
        $governance = new GovernanceService();
        (new SicrQualitativeFactorsSeeder())->run();
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'customer_id' => '737', 'reporting_period' => '2026-09', 'reporting_year' => 2026, 'reporting_month' => 9, 'external_identity_id' => '000104420000005']);
        $item = (int) DB::table('finance_sicr_items')->where('name', 'Breach of a financial covenant')->value('id');
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-01', 'locked_by' => $this->second->id, 'locked_at' => now(), 'reason' => 'Reported']);

        return [
            'Governance Centre setting' => function () use ($governance) {
                $p = $governance->propose('stage_cure_months', '6 months', '2026-11-01', 'Probation lengthened for testing', $this->admin->id);
                $governance->approve($p->id, $this->admin->id, $governance->adminOverrideAllowed($this->admin));
            },
            'Staging threshold' => function () {
                $t = new StagingThresholdService();
                $row = $t->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-11-01', 'reason' => 'Threshold tested in UAT'], $this->admin->id);
                $t->approve($row->id, $this->admin->id);
            },
            'SICR trigger' => function () use ($item, $governance) {
                $r = new QualitativeSicrService();
                $id = $r->propose(['item_id' => $item, 'scope' => 'contract', 'contract_id' => '104420000005', 'reason' => 'Covenant test failed', 'evidence' => 'Management accounts, file CF-12', 'effective_from' => '2026-11-01'], $this->admin->id);
                $r->approve($id, $this->admin->id, $governance->adminOverrideAllowed($this->admin));
            },
            'Period unlock' => function () {
                $u = new PeriodUnlockService();
                $id = $u->request('2026-01', $this->admin->id, 'Restatement tested in UAT');
                $u->approve($id, $this->admin->id);
            },
        ];
    }

    /** Acceptance 20: with the override not allowed, every workflow refuses a self-approval. */
    public function test_one_check_every_workflow_refuses_a_self_approval_without_uat_mode(): void
    {
        foreach ($this->selfApprovals() as $workflow => $attempt) {
            try {
                $attempt();
                $this->fail("{$workflow}: a self-approval was allowed with the override not allowed");
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->assertMatchesRegularExpression('/cannot approve|Maker-checker|different person/i', $e->getMessage(), $workflow);
            }
        }
        $this->assertSame(0, MakerChecker::selfApprovals()->count());
    }

    /** Acceptance 20: under UAT mode every workflow allows an administrator's self-approval, and records it. */
    public function test_one_check_every_workflow_allows_a_recorded_self_approval_under_uat_mode(): void
    {
        $this->uatOn();
        foreach ($this->selfApprovals() as $workflow => $attempt) {
            $attempt();
        }
        $this->assertEqualsCanonicalizing(['Governance Centre setting', 'Staging threshold', 'SICR trigger', 'Period unlock'], MakerChecker::selfApprovals()->pluck('workflow')->all());
    }

    /** Acceptance 20: every self-approval check in the code goes through the one check. */
    public function test_every_maker_checker_site_uses_the_one_check(): void
    {
        foreach ([
            'app/Services/Eir/GovernanceService.php', 'app/Services/Eir/EirCalculationService.php', 'app/Http/Controllers/EirFeeClassificationController.php', 'app/Services/Eir/QualitativeSicrService.php',
            'app/Services/Eir/RateResetService.php', 'app/Services/Eir/RestructureRegisterService.php', 'app/Services/Eir/StagingThresholdService.php', 'app/Services/Eir/EirReopenRequestService.php',
            'app/Services/Eir/PeriodUnlockService.php', 'app/Services/Eir/WriteOffRegisterService.php', 'app/Services/Ebanker/TakeonLandingService.php', 'app/Services/Ebanker/LoanBookBuildService.php',
            'app/Services/Eir/ScheduleWorkflowService.php', 'app/Services/Compliance/ComplianceAuditService.php', 'app/Services/Scenario/ScenarioSetService.php', 'app/Services/Fli/OverlayService.php',
            'app/Services/Fli/FliRouteService.php', 'app/Services/Fli/FliModelRegistry.php', 'app/Services/Fli/FliCloseService.php', 'app/Http/Controllers/RegressionController.php',
        ] as $file) {
            $this->assertMatchesRegularExpression('/MakerChecker::(selfApprovalAllowed|recordUse|allowedFor|environmentAllows)/', file_get_contents(base_path($file)), $file);
        }
    }

    /** Acceptance 21: on a production server a self-approval is refused even with the setting allowed, and the override cannot be switched on. */
    public function test_the_production_guard(): void
    {
        $this->uatOn();
        $this->production();
        $this->assertFalse(MakerChecker::overrideInForce());
        $t = new StagingThresholdService();
        $row = $t->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-11-01', 'reason' => 'Threshold tested in UAT'], $this->admin->id);
        $this->expectExceptionMessage('Maker-checker');
        try {
            $governance = new GovernanceService();
            DB::table('governance_settings')->where('key', MakerChecker::SETTING)->update(['value' => MakerChecker::NOT_ALLOWED]);
            try {
                $governance->propose(MakerChecker::SETTING, MakerChecker::ALLOWED, '2026-11-01', 'UAT on the live server', $this->admin->id);
                $this->fail('the override was proposed on a production server');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('production server', $e->getMessage());
            }
            $this->assertSame(1, Artisan::call('governance:uat-mode', ['action' => 'on']));
            $this->assertStringContainsString('production server', Artisan::output());
        } finally {
            $t->approve($row->id, $this->admin->id);
        }
    }

    /** Acceptance 22: each self-approval recorded and reported; the banner shows while UAT mode is on. */
    public function test_self_approvals_are_labelled_reported_and_the_banner_shows(): void
    {
        $this->actingAs($this->admin)->get(route('eir-governance.self-approvals'))->assertOk()->assertInertia(fn ($page) => $page->where('uatMode', false));
        $this->uatOn();
        $t = new StagingThresholdService();
        $t->approve($t->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-11-01', 'reason' => 'Threshold tested in UAT'], $this->admin->id)->id, $this->admin->id);

        $entry = DB::table('audit_logs')->where('action', MakerChecker::AUDIT_ACTION)->first();
        $this->assertSame(MakerChecker::LABEL, json_decode($entry->new_values, true)['label']);
        $this->actingAs($this->admin)->get(route('eir-governance.self-approvals'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Eir/SelfApprovals')->where('uatMode', true)->where('inForce', true)->has('rows', 1)->where('rows.0.workflow', 'Staging threshold')->where('rows.0.name', 'UAT administrator'));
        $csv = $this->actingAs($this->admin)->get(route('eir-governance.self-approvals', ['download' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Staging threshold', $csv);
        $this->assertStringContainsString(MakerChecker::LABEL, $csv);
    }

    /** Acceptance 23: switching the override off needs a second person; afterwards every workflow refuses a self-approval. */
    public function test_switching_it_off_needs_a_second_person(): void
    {
        $this->uatOn();
        $governance = new GovernanceService();
        $off = $governance->propose(MakerChecker::SETTING, MakerChecker::NOT_ALLOWED, now()->addDay()->toDateString(), 'Handover to live use, signed off by the CFO', $this->admin->id);
        try {
            $governance->approve($off->id, $this->admin->id, $governance->adminOverrideAllowed($this->admin));
            $this->fail('the override approved a change to its own setting');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('never approves a change to its own setting', $e->getMessage());
        }
        $governance->approve($off->id, $this->second->id);
        $this->travel(2)->days();
        $this->assertFalse(MakerChecker::overrideInForce());
        $t = new StagingThresholdService();
        $row = $t->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-12-01', 'reason' => 'Threshold after handover'], $this->admin->id);
        $this->expectExceptionMessage('Maker-checker');
        $t->approve($row->id, $this->admin->id);
    }

    /** A fresh install on a UAT server seeds the override allowed, labelled as such; any other server seeds it not allowed. */
    public function test_a_fresh_uat_install_seeds_the_override(): void
    {
        $this->assertSame(MakerChecker::NOT_ALLOWED, (new GovernanceService())->get(MakerChecker::SETTING));
        DB::table('governance_settings')->where('key', MakerChecker::SETTING)->delete();
        $this->app['env'] = 'uat';
        (new GovernanceSettingsSeeder())->run();
        $row = DB::table('governance_settings')->where('key', MakerChecker::SETTING)->first();
        $this->assertSame([MakerChecker::ALLOWED, GovernanceSettingsSeeder::UAT_REASON], [$row->value, $row->reason]);
    }
}
