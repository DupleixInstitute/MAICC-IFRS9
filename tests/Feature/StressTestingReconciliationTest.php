<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ticket #003 reconciliation: the consolidated loan-level engine must
 * reproduce hand-computed ECL under driver shocks, macro-derived shocks,
 * and the 100% caps.
 */
class StressTestingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = '2099-01';

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    /**
     * Three loans, one per stage. EAD = carrying_amount (no commitments).
     *   Stage 1: EAD 1,000,000  PD 0.05  LGD 0.40
     *   Stage 2: EAD   500,000  PD 0.20  LGD 0.50
     *   Stage 3: EAD   200,000  PD 0.90  LGD 0.60
     */
    private function seedBook(): void
    {
        $rows = [
            [1, 1000000.0, 0.05, 0.40],
            [2, 500000.0, 0.20, 0.50],
            [3, 200000.0, 0.90, 0.60],
        ];
        foreach ($rows as [$stage, $ead, $pd, $lgd]) {
            DB::table('loan_books')->insert([
                'contract_id' => 'STRESS-' . $stage,
                'customer_id' => 'CUST-' . $stage,
                'reporting_period' => self::PERIOD,
                'reporting_year' => 2099,
                'reporting_month' => 1,
                'ifrs9stage_pre_qualitative' => $stage,
                'carrying_amount' => $ead,
                'commitments' => 0,
                'facility_utilisation_rate' => 1,
                'pd_post_fli' => $pd,
                'lgd_value' => $lgd,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_driver_run_matches_hand_computed_ecl()
    {
        $this->seedBook();

        // PD x2 on stages 1-2, x1 on stage 3; LGD +10pts on every stage.
        $response = $this->actingAs($this->admin())->postJson(route('stress-testing.run'), [
            'period' => self::PERIOD,
            's1_pd_mult' => 2, 's2_pd_mult' => 2, 's3_pd_mult' => 1,
            's1_lgd_add' => 10, 's2_lgd_add' => 10, 's3_lgd_add' => 10,
        ]);

        $response->assertOk();

        $base = 1000000 * 0.05 * 0.40    // 20,000
              + 500000 * 0.20 * 0.50     // 50,000
              + 200000 * 0.90 * 0.60;    // 108,000
        $stress = 1000000 * 0.10 * 0.50  // 50,000
                + 500000 * 0.40 * 0.60   // 120,000
                + 200000 * 0.90 * 0.70;  // 126,000

        $this->assertEqualsWithDelta($base, $response->json('total_base_ecl'), 0.01);
        $this->assertEqualsWithDelta($stress, $response->json('total_stress_ecl'), 0.01);
        $this->assertEqualsWithDelta($stress - $base, $response->json('delta'), 0.01);
    }

    public function test_caps_hold_at_one_hundred_percent()
    {
        $this->seedBook();

        // Extreme shocks: PD x50 and LGD +90pts push everything to the caps,
        // so stressed ECL collapses to plain EAD per loan.
        $response = $this->actingAs($this->admin())->postJson(route('stress-testing.run'), [
            'period' => self::PERIOD,
            's1_pd_mult' => 50, 's2_pd_mult' => 50, 's3_pd_mult' => 50,
            's1_lgd_add' => 90, 's2_lgd_add' => 90, 's3_lgd_add' => 90,
        ]);

        $response->assertOk();
        $this->assertEqualsWithDelta(1700000.0, $response->json('total_stress_ecl'), 0.01);
    }

    public function test_macro_run_derives_pd_multiplier_and_matches_driver_equivalent()
    {
        $this->seedBook();
        $admin = $this->admin();

        // slope 0.002, intercept 0.05, base macro 100 -> predicted 0.25.
        // +25% shock -> macro 125 -> predicted 0.30 -> adjustment +20%,
        // so the engine must apply a uniform PD multiplier of 1.2.
        $macro = $this->actingAs($admin)->postJson(route('stress-testing.run-macro'), [
            'period' => self::PERIOD,
            'reg_slope' => 0.002,
            'reg_intercept' => 0.05,
            'base_macro' => 100,
            'macro_shock' => 25,
        ]);

        $macro->assertOk();
        $this->assertEqualsWithDelta(1.2, $macro->json('macro.pd_multiplier'), 0.0001);

        // The same multiplier fed through the driver endpoint must give the
        // identical stressed ECL: one engine, two entry points.
        $driver = $this->actingAs($admin)->postJson(route('stress-testing.run'), [
            'period' => self::PERIOD,
            's1_pd_mult' => 1.2, 's2_pd_mult' => 1.2, 's3_pd_mult' => 1.2,
        ]);

        $this->assertEqualsWithDelta(
            $driver->json('total_stress_ecl'),
            $macro->json('total_stress_ecl'),
            0.01
        );
    }

    public function test_retired_hub_sensitivity_redirects_to_stress_testing()
    {
        $response = $this->actingAs($this->admin())->get('/ifrs9-reports/sensitivity');

        $response->assertRedirect(route('stress-testing.index'));
    }
}
