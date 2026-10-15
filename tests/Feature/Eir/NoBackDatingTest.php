<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\QualitativeSicrService;
use App\Services\Eir\StagingThresholdService;
use App\Services\Fli\FliModelRegistry;
use App\Support\BackDatedChangeException;
use App\Support\ReportingPeriodLock;
use Database\Seeders\GovernanceSettingsSeeder;
use Database\Seeders\SicrQualitativeFactorsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 8.8, item 4 (acceptance 18;
 * decision S10): no change to a governed setting or rule takes effect inside a
 * locked month. A deliberate restatement goes through the unlock approval;
 * once the month is unlocked the change can be made.
 *
 * August and September 2026 are locked throughout.
 */
class NoBackDatingTest extends TestCase
{
    use RefreshDatabase;

    private int $maker;

    private int $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')])->id;
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')])->id;
        (new GovernanceSettingsSeeder())->run();
        $this->lock('2026-08');
        $this->lock('2026-09');
    }

    private function lock(string $period): void
    {
        DB::table('reporting_period_locks')->insert(['reporting_period' => $period, 'locked_by' => $this->checker, 'locked_at' => now(), 'reason' => 'Reported to the Board', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function refused(callable $change, array $locked): void
    {
        try {
            $change();
            $this->fail('a change inside a locked month was allowed');
        } catch (BackDatedChangeException $e) {
            $this->assertSame($locked, $e->locked);
            $this->assertStringContainsString('unlock approval', $e->getMessage());
        }
    }

    public function test_the_locked_months_a_date_would_alter(): void
    {
        $this->assertSame(['2026-08', '2026-09'], ReportingPeriodLock::lockedFrom('2026-08-01'));
        $this->assertSame(['2026-08', '2026-09'], ReportingPeriodLock::lockedFrom('2026-07-15'));
        // a date inside September still governs September's month-end
        $this->assertSame(['2026-09'], ReportingPeriodLock::lockedFrom('2026-09-30'));
        $this->assertSame([], ReportingPeriodLock::lockedFrom('2026-10-01'));
        $this->assertSame(['2026-09'], ReportingPeriodLock::lockedFrom('2026-09'));
    }

    public function test_a_governance_setting_cannot_be_back_dated_and_is_checked_again_on_approval(): void
    {
        $governance = new GovernanceService();
        $this->refused(fn () => $governance->propose('stage_cure_months', '6 months', '2026-09-01', 'Credit Committee lengthens the probation', $this->maker), ['2026-09']);
        // from the first open month it is allowed
        $governance->approve($governance->propose('stage_cure_months', '6 months', '2026-10-01', 'Credit Committee lengthens the probation', $this->maker)->id, $this->checker);
        $this->assertSame('6 months', $governance->get('stage_cure_months', \Carbon\CarbonImmutable::parse('2026-10-31')));
        $this->assertSame('0 months (no probation)', (new GovernanceService())->get('stage_cure_months', \Carbon\CarbonImmutable::parse('2026-09-30')), 'the locked month keeps the value it ran on');

        // a month locked between the proposal and the approval
        $proposal = $governance->propose('stage_cure_months', '12 months', '2026-11-01', 'Board resolution on probation periods', $this->maker);
        $this->lock('2026-11');
        $this->refused(fn () => $governance->approve($proposal->id, $this->checker), ['2026-11']);

        // the unlock approved by a second person: the month is no longer locked, and the change can be approved
        DB::table('reporting_period_locks')->where('reporting_period', '2026-11')->delete();
        $governance->approve($proposal->id, $this->checker);
        $this->assertSame('12 months', (new GovernanceService())->get('stage_cure_months', \Carbon\CarbonImmutable::parse('2026-11-30')));
    }

    public function test_a_staging_threshold_cannot_be_back_dated(): void
    {
        $thresholds = new StagingThresholdService();
        $this->refused(fn () => $thresholds->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-08-15', 'reason' => 'Short-term threshold restated'], $this->maker), ['2026-08', '2026-09']);
        $row = $thresholds->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-10-01', 'reason' => 'Short-term threshold from October'], $this->maker);
        $this->assertSame('PROPOSED', $row->status);
    }

    public function test_a_sicr_trigger_cannot_start_or_end_inside_a_locked_month(): void
    {
        (new SicrQualitativeFactorsSeeder())->run();
        $item = (int) DB::table('finance_sicr_items')->where('name', 'Breach of a financial covenant')->value('id');
        // the loan the trigger is raised on, in a month's book
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'customer_id' => '737', 'reporting_period' => '2026-07', 'reporting_year' => 2026, 'reporting_month' => 7, 'external_identity_id' => '000104420000005']);
        $register = new QualitativeSicrService();
        $raise = fn (string $from) => $register->propose(['item_id' => $item, 'scope' => 'contract', 'contract_id' => '104420000005', 'reason' => 'Covenant test failed', 'evidence' => 'Management accounts, credit file CF-12', 'effective_from' => $from], $this->maker);

        $this->refused(fn () => $raise('2026-09-10'), ['2026-09']);

        // a trigger in force since July: closing it in August would uncover the locked August and September month-ends
        DB::table('reporting_period_locks')->delete();
        $id = $raise('2026-07-01');
        $register->approve($id, $this->checker);
        $this->lock('2026-08');
        $this->lock('2026-09');
        $this->refused(fn () => $register->close($id, $this->maker, 'Covenant waived', '2026-08-20'), ['2026-08', '2026-09']);
        // closed after the last locked month-end, it is allowed
        $register->close($id, $this->maker, 'Covenant waived', '2026-10-05');
        $this->assertSame('2026-10-05', (string) DB::table('finance_sicr_triggers')->where('id', $id)->value('effective_to'));
    }

    public function test_a_forward_looking_model_version_cannot_be_back_dated_or_retired_under_a_locked_month(): void
    {
        // the driver and the credit-loss measure the model reads, held by the system
        DB::table('macro_statistics')->insert(['statistic_code' => 'PLR', 'statistic_name' => 'Policy lending rate']);
        DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'observation_period' => '2026-06', 'value' => 0.12]);
        $registry = new FliModelRegistry();
        $model = ['statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'lag_months' => 3, 'slope' => 0.02, 'intercept' => 0.05, 'calibrated_period' => '2026-06', 'evidence' => 'validation workbook'];
        $this->refused(fn () => $registry->proposeExternal($model + ['effective_from' => '2026-09'], $this->maker), ['2026-09']);

        DB::table('reporting_period_locks')->delete();
        $id = $registry->proposeExternal($model + ['effective_from' => '2026-07'], $this->maker);
        $this->lock('2026-08');
        $this->refused(fn () => $registry->approveExternal($id, $this->checker), ['2026-08']);

        DB::table('reporting_period_locks')->delete();
        $registry->approveExternal($id, $this->checker);
        $this->lock('2026-08');
        // retiring it would take it away from the locked August it governed
        $this->refused(fn () => $registry->retire($id, $this->checker, 'Superseded by the new calibration'), ['2026-08']);
    }

    public function test_the_governance_centre_shows_the_refusal_as_a_message(): void
    {
        $user = User::find($this->maker);
        $user->assignRole('admin');
        $this->actingAs($user)->post(route('eir-governance.propose'), ['key' => 'stage_cure_months', 'value' => '6 months', 'effective_from' => '2026-09-01', 'reason' => 'Credit Committee lengthens the probation'])
            ->assertSessionHasErrors(['governance']);
    }
}
