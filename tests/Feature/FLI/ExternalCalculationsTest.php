<?php

namespace Tests\Feature\FLI;

use App\Models\FliAdj;
use App\Models\FliReportingPeriodParameter;
use App\Models\LoanBook;
use App\Models\ScenarioSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExternalCalculationsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $scenarioSet;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create user manually to avoid UserFactory role dependency issues
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        
        $this->actingAs($this->user);

        $this->scenarioSet = ScenarioSet::create([
            'name' => 'Test Scenario Set',
            'is_active' => true,
            'created_by' => $this->user->id
        ]);
    }

    public function test_can_view_index_page()
    {
        $response = $this->get(route('fli.external.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('FLI/ExternalCalculations/Index')
        );
    }

    public function test_can_save_parameters()
    {
        $data = [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_date' => '2025-01',
            'economic_stat_id' => 1,
            'pd_proxy_stat_id' => 1,
            'regression_intercept' => 0.5,
            'regression_slope' => 1.2,
        ];

        $response = $this->post(route('fli.external.save-parameters'), $data);

        $response->assertRedirect(route('fli.external.index'));
        $this->assertDatabaseHas('fli_reporting_periods_parameters', [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_period' => '2025-01-01',
            'regression_intercept' => 0.5,
            'regression_slope' => 1.2,
        ]);
    }

    public function test_can_generate_forecasts()
    {
        $data = [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_date' => '2025-01',
            'regression_intercept' => 0.1,
            'regression_slope' => 0.5,
        ];

        $response = $this->post(route('fli.external.generate'), $data);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'forecasts' => [
                '*' => [
                    'period_window',
                    'forecast_date',
                    'weighted_macro_value',
                    'predicted_value',
                    'fli_adj'
                ]
            ]
        ]);
    }

    public function test_can_save_adjustments()
    {
        // First save parameters
        FliReportingPeriodParameter::create([
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_period' => '2025-01-01',
            'economic_stat_id' => 1,
            'pd_proxy_stat_id' => 1,
            'regression_intercept' => 0.1,
            'regression_slope' => 0.5,
            'created_by' => $this->user->id
        ]);

        $adjustments = [
            [
                'period_window' => 0,
                'forecast_date' => '2025-01-01',
                'weighted_macro_value' => 1.0,
                'predicted_value' => 0.6,
                'fli_adj' => 0
            ],
            [
                'period_window' => 12,
                'forecast_date' => '2026-01-01',
                'weighted_macro_value' => 1.2,
                'predicted_value' => 0.7,
                'fli_adj' => 0.1667
            ]
        ];

        $data = [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_date' => '2025-01',
            'adjustments' => $adjustments
        ];

        $response = $this->post(route('fli.external.save'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('fli_adj', [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_period' => '2025-01-01',
            'forecast_window_in_months' => 12,
            'fli_adj' => 0.1667
        ]);
    }

    public function test_can_update_loanbook()
    {
        LoanBook::unguard();
        // Setup LoanBook data
        $loan = LoanBook::create([
            'reporting_period' => '202501',
            'contract_id' => 'CTR001',
            'customer_id' => 'CUST001',
            'reporting_year' => 2025,
            'reporting_month' => 1,
            'create_date' => '2024-01-01',
            'due_date' => '2026-01-01',
            'pd_value' => 0.05, // 5%
            'ifrs9stage_post_qualitative' => 1,
            'remaining_tenor' => 24,
            // Add other required fields if any (assuming nullable or defaulted)
        ]);

        // Setup FLI Adjustment for 12 months (Stage 1)
        FliAdj::create([
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_period' => '2025-01-01',
            'forecast_period' => '2026-01-01',
            'forecast_window_in_months' => 12,
            'weighted_macro_data_value' => 1.0,
            'predicted_value' => 0.6,
            'fli_adj' => 0.10, // +10% adjustment
            'created_by' => $this->user->id
        ]);

        $data = [
            'scenario_set_id' => $this->scenarioSet->id,
            'reporting_date' => '2025-01'
        ];

        $response = $this->post(route('fli.external.update-loanbook'), $data);

        $response->assertStatus(200);
        
        // Check if loan was updated
        $loan->refresh();
        
        // Expected: 0.05 * (1 + 0.10) = 0.055
        $this->assertEquals(0.055, $loan->pd_post_fli_adj);
        $this->assertEquals(0.10, $loan->fli_adj);
    }
}
