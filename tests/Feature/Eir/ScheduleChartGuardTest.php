<?php

namespace Tests\Feature\Eir;

use App\Models\ContractEir;
use App\Models\User;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\ScheduleChartCheck;
use App\Services\Eir\ScheduleWorkflowService;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Release review of 16 October 2026: no automated path approves a generated
 * version 1 that differs from E-Banker's live instalment chart. Kadava
 * 104450000041 was locked on 24 level instalments; its chart has three nil
 * months, then 21 instalments. A person may still approve it, with a reason.
 */
class ScheduleChartGuardTest extends TestCase
{
    use RefreshDatabase;

    private const KADAVA = '104450000041';

    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reviewer = User::create(['name' => 'Reviewer', 'email' => 'r@example.com', 'password' => bcrypt('x')]);
        (new GovernanceSettingsSeeder())->run();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('d', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function contract(string $id): ContractEir
    {
        return ContractEir::create(['contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'origination_date' => '2022-08-02', 'maturity_date' => '2024-08-01', 'approved_amount' => 12_000_000,
            'drawn_amount' => 12_000_000, 'contractual_rate' => 0.10, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'moratorium_months' => 0,
            'schedule_source' => 'GENERATED', 'schedule_approval_status' => 'DRAFT']);
    }

    /** Our draft: 24 level monthly instalments of 500,000 principal from 2 Sep 2022. */
    private function draft(string $id): void
    {
        for ($i = 0; $i < 24; $i++) {
            DB::table('contract_cashflow_schedule')->insert(['contract_id' => $id, 'schedule_version' => 1, 'due_date' => \Carbon\CarbonImmutable::parse('2022-09-02')->addMonthsNoOverflow($i)->toDateString(),
                'principal_due' => 500000, 'interest_due' => 50000, 'fee_due' => 0, 'schedule_source' => 'GENERATED', 'source_system' => 'EIR_GENERATOR', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** E-Banker's chart: $nil months of nothing, then instalments that retire $principal. */
    private function chart(string $id, int $nil, int $instalments, float $principal, string $query = 'P2_10'): void
    {
        $account = str_pad($id, 15, '0', STR_PAD_LEFT);
        for ($i = 1; $i <= $nil + $instalments; $i++) {
            $p = $i <= $nil ? 0 : round($principal / $instalments, 2);
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $query, 'source_key' => "{$id}-{$i}", 'account' => $account, 'row_date' => null,
                'payload' => json_encode(['INST_CHART_DET_ID' => "{$id}-{$i}", 'NEW_AC_NUMBER' => $account, 'TRANSACTION_DATE' => '3/28/2024', 'INST_DET_ID' => '1268', 'INST_SR_NO' => (string) $i,
                    'INSTALLMENT_DATE' => \Carbon\CarbonImmutable::parse('2022-09-01')->addMonthsNoOverflow($i - 1)->format('n/j/Y'), 'INSTALLMENT_AMT' => (string) ($p + 50000), 'PRIN_INSTALLMENT_AMT' => (string) $p,
                    'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N']),
                'row_hash' => md5($id . $i), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_a_draft_that_differs_from_the_chart_is_held_from_the_automated_approval(): void
    {
        $c = $this->contract(self::KADAVA);
        $this->draft(self::KADAVA);
        $this->chart(self::KADAVA, 3, 21, 12_382_426.76, 'IC_01');
        $workflow = app(ScheduleWorkflowService::class);
        try {
            $workflow->approve($c, null, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);
            $this->fail('the bootstrap approved a schedule that differs from the chart');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Not approved automatically', $e->getMessage());
        }
        $c = ContractEir::where('contract_id', self::KADAVA)->first();
        $this->assertSame('DRAFT', $c->schedule_approval_status);
        $this->assertStringContainsString("24 instalments with principal against E-Banker's 21", $c->schedule_review_notes);
        $this->assertStringContainsString("the first principal falls due on 2022-09-02 against E-Banker's 2022-12-01", $c->schedule_review_notes);
        $check = ScheduleChartCheck::held($c);
        $this->assertSame(ScheduleChartCheck::DIFFERS, $check['status']);
        $this->assertSame(['instalments' => 21, 'first_principal_due' => '2022-12-01', 'principal' => 12382426.77], $check['chart']);
        $codes = array_column(app(EirReadinessService::class)->assess(self::KADAVA)['issues'], 'code');
        $this->assertContains('SCHEDULE_DIFFERS_FROM_CHART', $codes);

        // a person may approve it, with a reason
        try {
            $workflow->approve($c, $this->reviewer->id, '');
            $this->fail('approved without a reason');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("Give the reason for approving a schedule that differs from E-Banker's chart", $e->getMessage());
        }
        $workflow->approve($c, $this->reviewer->id, 'Checked against the offer letter: no moratorium was agreed; E-Banker\'s chart is wrong here.');
        $c = ContractEir::where('contract_id', self::KADAVA)->first();
        $this->assertSame('APPROVED', $c->schedule_approval_status);
        $this->assertNotContains('SCHEDULE_DIFFERS_FROM_CHART', array_column(app(EirReadinessService::class)->assess(self::KADAVA)['issues'], 'code'));
    }

    public function test_a_draft_that_agrees_with_the_chart_or_has_none_is_approved_as_before(): void
    {
        $workflow = app(ScheduleWorkflowService::class);
        $agrees = $this->contract('104450000099');
        $this->draft('104450000099');
        $this->chart('104450000099', 0, 24, 12_000_000);
        $workflow->approve($agrees, null, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);
        $this->assertSame('APPROVED', ContractEir::where('contract_id', '104450000099')->value('schedule_approval_status'));
        $this->assertSame(ScheduleChartCheck::AGREES, ScheduleChartCheck::held(ContractEir::where('contract_id', '104450000099')->first())['status']);

        $none = $this->contract('104450000098');
        $this->draft('104450000098');
        $workflow->approve($none, null, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);
        $this->assertSame('APPROVED', ContractEir::where('contract_id', '104450000098')->value('schedule_approval_status'));
        $this->assertSame(ScheduleChartCheck::NO_CHART, ScheduleChartCheck::held(ContractEir::where('contract_id', '104450000098')->first())['status']);
    }

    public function test_the_three_figures_and_their_bands(): void
    {
        $check = app(ScheduleChartCheck::class);
        $c = $this->contract('104450000097');
        $this->draft('104450000097');
        // principal 0.8 percent off: inside the band; the first instalment a day earlier: inside one period
        $this->chart('104450000097', 0, 24, 12_096_000);
        $this->assertSame(ScheduleChartCheck::AGREES, $check->compare($c)['status']);
        // a deleted chart row does not count
        DB::table('ebanker_raw_rows')->where('source_key', '104450000097-24')->update(['payload' => DB::raw("replace(payload, '\"DELETE_FLAG\":\"N\"', '\"DELETE_FLAG\":\"Y\"')")]);
        $r = $check->compare($c);
        $this->assertSame(ScheduleChartCheck::DIFFERS, $r['status']);
        $this->assertSame(23, $r['chart']['instalments']);
    }
}
