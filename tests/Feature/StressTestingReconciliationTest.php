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

    /** The booked (discounted) allowance as a share of the undiscounted ECL on every seeded loan. */
    private const BOOKED = 0.95;

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
     * Each carries its ECL (EAD x stage PD x LGD, undiscounted) and the booked
     * allowance discounted at the EIR, 95 percent of it: the stress base is the
     * booked allowance (documentation audit of 14 October 2026).
     */
    private function seedBook(): void
    {
        $rows = [
            [1, 1000000.0, 0.05, 0.40],
            [2, 500000.0, 0.20, 0.50],
            [3, 200000.0, 0.90, 0.60],
        ];
        foreach ($rows as [$stage, $ead, $pd, $lgd]) {
            $ecl = $ead * ($stage === 3 ? 1.0 : $pd) * $lgd;
            DB::table('loan_books')->insert([
                'ecl_value' => $ecl,
                'ecl_value_discounted' => round($ecl * self::BOOKED, 2),
                'ecl_discount_status' => 'CALCULATED_TIME_PHASED',
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

        // On the ECL basis (audit H5 and step 5): Stage 1 and 2 over a twelve-month
        // horizon (no tenor on the rows), Stage 3 at a PD of 1 as in every engine;
        // the tape's 0.9 on the Stage 3 row is not what the allowance is measured on.
        $base = 1000000 * 0.05 * 0.40    // 20,000
              + 500000 * 0.20 * 0.50     // 50,000
              + 200000 * 1.00 * 0.60;    // 120,000
        $stress = 1000000 * 0.10 * 0.50  // 50,000
                + 500000 * 0.40 * 0.60   // 120,000
                + 200000 * 1.00 * 0.70;  // 140,000

        // the base is the booked allowance, and the stress scales it: both on the booked basis
        $this->assertEqualsWithDelta($base * self::BOOKED, $response->json('total_base_ecl'), 0.05);
        $this->assertEqualsWithDelta($stress * self::BOOKED, $response->json('total_stress_ecl'), 0.05);
        $this->assertEqualsWithDelta(($stress - $base) * self::BOOKED, $response->json('delta'), 0.05);
        // the undiscounted measure beside it
        $this->assertEqualsWithDelta($base, $response->json('basis.undiscounted.base_ecl'), 0.05);
        $this->assertEqualsWithDelta($stress, $response->json('basis.undiscounted.stress_ecl'), 0.05);
        $this->assertSame('booked', $response->json('basis.base'));
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
        $this->assertEqualsWithDelta(1700000.0 * self::BOOKED, $response->json('total_stress_ecl'), 0.05);
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

    /** A loan with no ECL calculated has no allowance to stress: counted with its exposure, never estimated. */
    public function test_a_loan_without_an_ecl_is_not_measured_but_counted()
    {
        $this->seedBook();
        DB::table('loan_books')->where('contract_id', 'STRESS-1')->update(['ecl_value' => null, 'ecl_value_discounted' => null, 'ecl_discount_status' => 'NOT_CALCULATED']);

        $response = $this->actingAs($this->admin())->postJson(route('stress-testing.run'), ['period' => self::PERIOD, 's1_pd_mult' => 2, 's2_pd_mult' => 2, 's3_pd_mult' => 1]);

        $response->assertOk();
        $this->assertSame(1, $response->json('basis.not_measured.loans'));
        $this->assertEqualsWithDelta(1000000, $response->json('basis.not_measured.exposure'), 0.01);
        $this->assertEqualsWithDelta((50000 + 120000) * self::BOOKED, $response->json('total_base_ecl'), 0.05);
    }
}
