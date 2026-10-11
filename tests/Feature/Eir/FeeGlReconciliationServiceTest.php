<?php

namespace Tests\Feature\Eir;

use App\Models\GlAccountScope;
use App\Models\GlTrialBalanceLine;
use App\Services\Eir\FeeGlReconciliationService;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Imported fees against the fee GLs by month and year (spec v4 sections 3.7
 * and 7.4). The trial balance figures are MAIIC's own year-to-date balances
 * for 2025: 4873 stood at 14,500,000 from January to April and 110,156,000
 * at the end of May; 4871 at 8,908,500 in January; December comes pre-closing
 * from the AFS workbook (311,342,792 and 171,128,135).
 */
class FeeGlReconciliationServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('gl_account_scope', function (Blueprint $t) {
            $t->increments('id');
            $t->string('gl_code', 20);
            $t->string('gl_title');
            $t->string('chart', 20)->default('EBANKER');
            $t->string('quickbooks_code', 20)->nullable();
            $t->string('statement', 2);
            $t->string('normal_balance', 2);
            $t->string('category', 30);
            $t->tinyInteger('eir_door')->nullable();
            $t->boolean('in_eir_scope')->default(false);
            $t->string('portfolio', 30)->nullable();
            $t->boolean('retired')->default(false);
            $t->string('notes', 500)->nullable();
            $t->timestamps();
        });
        Schema::create('gl_trial_balance_lines', function (Blueprint $t) {
            $t->increments('id');
            $t->date('period');
            $t->date('source_period_stamp')->nullable();
            $t->string('gl_code', 20);
            $t->string('gl_title');
            $t->decimal('debit', 20, 2)->default(0);
            $t->decimal('credit', 20, 2)->default(0);
            $t->string('basis', 12)->default('POSTCLOSING');
            $t->string('source_file');
            $t->string('source_sheet', 100)->nullable();
            $t->timestamps();
        });
        Schema::create('contract_fees', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('fee_type');
            $t->decimal('amount', 20, 2);
            $t->date('transaction_date')->nullable();
            $t->string('cashflow_direction')->nullable();
            $t->string('gl_account_ref')->nullable();
            $t->timestamps();
        });

        foreach (['4871' => 'Legal Fees', '4873' => 'Arrangement Fees'] as $code => $title) {
            GlAccountScope::create(['gl_code' => $code, 'gl_title' => $title, 'statement' => 'PL', 'normal_balance' => 'CR',
                'category' => 'FEE_INCOME', 'in_eir_scope' => true, 'chart' => 'EBANKER']);
        }
    }

    private function governance(?string $value): void
    {
        $mock = Mockery::mock(GovernanceService::class);
        $value === null
            ? $mock->shouldReceive('get')->andThrow(new \RuntimeException('not seeded'))
            : $mock->shouldReceive('get')->with(FeeGlReconciliationService::SETTING)->andReturn($value);
        $mock->shouldReceive('reconciliationTolerance')->andReturn(['percent' => 0.0, 'floor' => 1.0]);
        $this->app->instance(GovernanceService::class, $mock);
    }

    private function tb(string $code, string $period, float $credit, string $basis = 'POSTCLOSING'): void
    {
        GlTrialBalanceLine::create(['period' => $period, 'gl_code' => $code, 'gl_title' => $code, 'credit' => $credit,
            'debit' => 0, 'basis' => $basis, 'source_file' => 'test']);
    }

    private function fee(string $contract, string $type, float $amount, ?string $date, ?string $direction = 'RECEIVED', ?string $gl = null): void
    {
        DB::table('contract_fees')->insert(['contract_id' => $contract, 'fee_type' => $type, 'amount' => $amount,
            'transaction_date' => $date, 'cashflow_direction' => $direction, 'gl_account_ref' => $gl]);
    }

    private function service(): FeeGlReconciliationService
    {
        return $this->app->make(FeeGlReconciliationService::class);
    }

    /** January to May 2025 on 4873, the months MAIIC's ledger moved. */
    private function arrangementYearToMay(): void
    {
        foreach (['2025-01-01', '2025-02-01', '2025-03-01', '2025-04-01'] as $p) {
            $this->tb('4873', $p, 14500000);
        }
        $this->tb('4873', '2025-05-01', 110156000);
    }

    public function test_fees_that_carry_the_whole_gl_agree_month_by_month(): void
    {
        $this->governance(FeeGlReconciliationService::ON);
        $this->arrangementYearToMay();
        $this->fee('000104430000070', 'arrangement', 14500000, '2025-01-02');
        $this->fee('000104430000071', 'arrangement', 30000000, '2025-05-02');
        $this->fee('000104430000072', 'arrangement', 30000000, '2025-05-07');
        $this->fee('000104460000109', 'arrangement', 35656000, '2025-05-31');

        $gl = $this->service()->reconcile([2025])['years'][2025]['gls']['4873'];
        $byMonth = collect($gl['months'])->keyBy('month');

        $this->assertSame('AGREES', $byMonth[1]['status']);
        $this->assertSame(95656000.0, $byMonth[5]['imported']);
        $this->assertSame('AGREES', $byMonth[5]['status']);
        $this->assertSame(95656000.0, $byMonth[5]['gl']);
        $this->assertFalse($byMonth->has(2), 'a month with nothing imported and no movement is not listed');
    }

    public function test_a_fee_the_import_has_not_carried_is_a_difference_for_the_month_and_the_year(): void
    {
        $this->governance(FeeGlReconciliationService::ON);
        $this->arrangementYearToMay();
        $this->fee('000104430000070', 'arrangement', 14500000, '2025-01-02');

        $gl = $this->service()->reconcile([2025])['years'][2025]['gls']['4873'];
        $may = collect($gl['months'])->firstWhere('month', 5);

        $this->assertSame('DIFFERENCE', $may['status']);
        $this->assertSame(-95656000.0, $may['difference']);
        $this->assertSame('INCOMPLETE', $gl['status'], 'June to December have no trial balance in this test');
        $this->assertSame(7, $gl['months_without_tb']);
    }

    public function test_paid_costs_stay_off_the_income_gls_and_legal_maps_to_4871(): void
    {
        $this->governance(FeeGlReconciliationService::ON);
        $this->tb('4871', '2025-01-01', 8908500);
        $this->fee('000104430000070', 'legal', 8908500, '2025-01-02');
        $this->fee('000104430000070', 'legal', 2400000, '2025-01-02', 'PAID', '6752');

        $year = $this->service()->reconcile([2025])['years'][2025];
        $jan = collect($year['gls']['4871']['months'])->firstWhere('month', 1);

        $this->assertSame('AGREES', $jan['status']);
        $this->assertSame(1, $year['paid_excluded']['lines']);
        $this->assertSame(2400000.0, $year['paid_excluded']['amount']);
    }

    public function test_december_2025_reads_the_pre_closing_balance(): void
    {
        $this->governance(FeeGlReconciliationService::ON);
        $this->tb('4873', '2025-11-01', 274681000);
        $this->tb('4873', '2025-12-01', 0);
        $this->tb('4873', '2025-12-01', 311342792, 'PRECLOSING');
        $this->fee('000104460000114', 'arrangement', 36661792, '2025-12-18');

        $dec = collect($this->service()->reconcile([2025])['years'][2025]['gls']['4873']['months'])->firstWhere('month', 12);

        $this->assertSame(36661792.0, $dec['gl']);
        $this->assertSame('PRECLOSING', $dec['tb_basis']);
        $this->assertSame('AGREES', $dec['status']);
    }

    public function test_switched_off_in_the_governance_centre_it_says_so(): void
    {
        $this->governance(FeeGlReconciliationService::OFF);
        $this->fee('000104430000070', 'arrangement', 14500000, '2025-01-02');

        $report = $this->service()->report([2025]);

        $this->assertFalse($report['enabled']);
        $this->assertSame(FeeGlReconciliationService::OFF, $report['setting']);
        $this->assertArrayNotHasKey('years', $report);
    }

    public function test_a_missing_setting_runs_the_check_and_undated_fees_are_reported(): void
    {
        $this->governance(null);
        $this->fee('000104430000070', 'arrangement', 1000, null);

        $report = $this->service()->report();

        $this->assertTrue($report['enabled']);
        $this->assertSame([], $report['years']);
        $this->assertSame(1, $report['undated']['lines']);
    }
}
