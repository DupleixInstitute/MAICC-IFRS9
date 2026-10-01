<?php

namespace Tests\Feature\Eir;

use App\Models\ContractEir;
use App\Services\Eir\ScheduleWorkflowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Version 1 against E-Banker's own schedule, on JAT Group's figures
 * (104430000087, quarterly, EMI chart printed 28 Sep 2026).
 *
 * The chart is E-Banker's current schedule: it is regenerated at each rate
 * reset and when arrears are spread over the instalments left, and it repays
 * capitalised interest as principal. So the comparison is on total cash, and
 * only the instalments before the first regeneration are held to version 1.
 * Private in-memory sqlite schema, as in ContractMasterNewFieldsTest.
 */
class ScheduleComparisonTest extends TestCase
{
    protected $seed = false;

    private const ID = '104430000087';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        foreach (['contract_cashflow_schedule', 'contract_remaining_cashflow_schedule'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->increments('id');
                $t->string('contract_id');
                if ($table === 'contract_cashflow_schedule') {
                    $t->unsignedInteger('schedule_version')->default(1);
                }
                $t->string('due_date');
                $t->double('principal_due')->default(0);
                $t->double('interest_due')->default(0);
                $t->double('fee_due')->default(0);
                $t->timestamps();
            });
        }
        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code')->default('PLR');
            $t->string('effective_date');
            $t->double('rate');
        });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('reporting_period');
            $t->integer('overdue_days')->default(0);
        });
    }

    private function contract(array $attributes = []): ContractEir
    {
        return (new ContractEir())->forceFill($attributes + [
            'contract_id' => self::ID, 'emi_calc_type' => 'E', 'interest_policy' => null,
            'reprice_flag' => null, 'origination_date' => '2025-07-23',
        ]);
    }

    /** @param list<array{0:string,1:float,2:float}> $rows due date, principal, interest */
    private function schedule(string $table, array $rows): void
    {
        foreach ($rows as [$due, $principal, $interest]) {
            DB::table($table)->insert(['contract_id' => self::ID, 'due_date' => $due,
                'principal_due' => $principal, 'interest_due' => $interest, 'fee_due' => 0]);
        }
    }

    private function compare(ContractEir $contract): array
    {
        return app(ScheduleWorkflowService::class)->comparison($contract);
    }

    public function test_capitalised_interest_repaid_as_principal_is_not_a_variance(): void
    {
        // Same cash on the same dates, split differently: E-Banker repays the
        // interest of the months with no instalment as principal.
        $this->schedule('contract_cashflow_schedule', [
            ['2025-10-23', 10_709_660.00, 17_915_322.00], ['2026-01-23', 12_000_000.00, 16_624_982.00],
        ]);
        $this->schedule('contract_remaining_cashflow_schedule', [
            ['2025-10-23', 24_050_066.00, 4_574_916.00], ['2026-01-23', 24_050_066.00, 4_574_916.00],
        ]);

        $result = $this->compare($this->contract());

        $this->assertSame(ScheduleWorkflowService::COMPARISON_WITHIN_TOLERANCE, $result['status']);
        $this->assertSame(0.0, $result['cash_variance']);
        $this->assertSame(2, $result['compared_rows']);
        $this->assertSame(['Agrees'], $result['rows'][0]['causes']);
    }

    public function test_only_the_instalments_before_ebanker_recalculated_are_compared(): void
    {
        $this->schedule('contract_cashflow_schedule', [
            ['2025-10-23', 10_721_565.22, 17_749_524.59], ['2026-01-23', 11_630_641.87, 16_840_447.94],
            ['2026-04-23', 12_616_798.73, 15_854_291.08], ['2026-07-23', 13_686_000.00, 14_785_089.81],
            ['2026-10-23', 14_846_000.00, 13_625_089.81],
        ]);
        // JAT's chart: the instalment moves once E-Banker regenerates it, and
        // the last one is shorter because it retires what is left.
        $this->schedule('contract_remaining_cashflow_schedule', [
            ['2025-10-23', 24_050_066.00, 4_574_916.00], ['2026-01-23', 24_094_469.00, 5_144_751.00],
            ['2026-04-23', 30_290_676.00, 3_659_550.00], ['2026-07-23', 30_309_648.00, 3_076_396.00],
        ]);
        DB::table('loan_books')->insert(['contract_id' => self::ID, 'reporting_period' => '2025-11', 'overdue_days' => 60]);

        $result = $this->compare($this->contract());

        $this->assertSame(1, $result['compared_rows']);
        $this->assertSame(3, $result['recalculated_rows']);
        $this->assertSame('2026-01-23', $result['recalculated_from']);
        $this->assertSame(-153_892.19, $result['cash_variance']);
        $this->assertSame(ScheduleWorkflowService::COMPARISON_WITHIN_TOLERANCE, $result['status'], '154k is 0.54% of the instalment');
        $this->assertSame('RECALCULATED', $result['rows'][1]['segment']);
        $this->assertStringContainsString('Arrears', $result['rows'][1]['causes'][0], 'the November loan book shows the account 60 days overdue');
        $this->assertSame(['After E-Banker recalculated'], $result['rows'][2]['causes']);
        $this->assertSame('ONLY_GENERATED', $result['rows'][4]['segment']);
    }

    public function test_a_prime_rate_change_is_named_as_the_cause(): void
    {
        $this->schedule('contract_cashflow_schedule', [
            ['2025-10-23', 10_000_000, 18_000_000], ['2026-01-23', 10_000_000, 18_000_000], ['2026-04-23', 10_000_000, 18_000_000],
        ]);
        $this->schedule('contract_remaining_cashflow_schedule', [
            ['2025-10-23', 10_000_000, 18_000_000], ['2026-01-23', 10_000_000, 17_000_000], ['2026-04-23', 10_000_000, 17_000_000],
        ]);
        DB::table('reference_rate_series')->insert(['effective_date' => '2025-12-01', 'rate' => 24.2]);

        $result = $this->compare($this->contract(['interest_policy' => 'P']));

        $this->assertSame(1, $result['compared_rows']);
        $this->assertSame(['Rate reset on 2025-12-01 (compared in P5)'], $result['rows'][1]['causes']);
    }

    public function test_the_first_full_instalment_after_an_interest_only_spell_is_not_a_regeneration(): void
    {
        $this->schedule('contract_cashflow_schedule', [
            ['2025-06-30', 0, 3_300_000], ['2025-07-31', 12_000_000, 3_300_000], ['2025-08-31', 12_100_000, 3_200_000],
        ]);
        $this->schedule('contract_remaining_cashflow_schedule', [
            ['2025-06-30', 0, 3_300_000], ['2025-07-31', 12_000_000, 3_300_000], ['2025-08-31', 12_100_000, 3_200_000],
        ]);

        $result = $this->compare($this->contract());

        $this->assertNull($result['recalculated_from']);
        $this->assertSame(3, $result['compared_rows']);
    }

    public function test_no_ebanker_schedule_means_nothing_to_compare(): void
    {
        $this->schedule('contract_cashflow_schedule', [['2025-10-23', 10_000_000, 18_000_000]]);

        $result = $this->compare($this->contract());

        $this->assertSame(ScheduleWorkflowService::COMPARISON_NO_REMAINING_DATA, $result['status']);
        $this->assertNull($result['cash_variance']);
    }
}
