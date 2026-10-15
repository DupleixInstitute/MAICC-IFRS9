<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 5: the IFRS 9 stage against RBM
 * class report lists every loan whose stage and RBM class disagree on
 * non-performing, with the directive's count the class rests on, the staging
 * count the stage rests on, and the basis of that count.
 */
class StagingRbmDisagreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_loans_whose_stage_and_rbm_class_disagree_are_listed_with_both_counts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $basis = 'bucket ageing, core-system report as given, core-system ageing after maturity';
        $base = ['reporting_period' => '2026-09', 'reporting_year' => 2026, 'reporting_month' => 9, 'customer_id' => '1', 'external_identity_id' => 'TBA', 'carrying_amount' => 1000, 'created_at' => now(), 'updated_at' => now()];
        DB::table('loan_books')->insert([
            // short-term, 120 days on the directive's count: substandard, but Stage 2 on the bucket count of 91 under the single ladder
            ['contract_id' => 'short-120', 'customer_name' => 'Short Ltd', 'tenor' => 12, 'overdue_days' => 120, 'staging_dpd' => 91, 'staging_dpd_basis' => $basis, 'ifrs9stage_post_qualitative' => '2'] + $base,
            // medium-term, 200 days: substandard and Stage 3, they agree
            ['contract_id' => 'long-200', 'customer_name' => 'Long Ltd', 'tenor' => 36, 'overdue_days' => 200, 'staging_dpd' => 181, 'staging_dpd_basis' => $basis, 'ifrs9stage_post_qualitative' => '3'] + $base,
            // current and Stage 1: they agree
            ['contract_id' => 'current', 'customer_name' => 'Current Ltd', 'tenor' => 36, 'overdue_days' => 0, 'staging_dpd' => 0, 'staging_dpd_basis' => $basis, 'ifrs9stage_post_qualitative' => '1'] + $base,
        ]);

        // the reports offer the periods with an ECL run
        DB::table('expected_credit_loss')->insert(['reporting_period' => '2026-09', 'last_reporting_period' => '2026-08']);
        $response = $this->actingAs($user)->get('/ifrs9-reports/rbm-classification?period=2026-09');
        $response->assertOk();
        // the merged report carries every part's sections, the parts listing their titles
        $this->assertContains('IFRS 9 stage against RBM class', array_column($response->viewData('page')['props']['report']['parts'], 'title'));
        $section = collect($response->viewData('page')['props']['report']['sections'])->first(fn ($s) => str_contains($s['heading'] ?? '', 'disagree on non-performing'));
        $this->assertNotNull($section);
        $this->assertCount(1, $section['rows']);
        $this->assertSame(['short-120', 'Short Ltd', '12 months', '120', '91', 'Stage 2', 'Substandard'], array_slice($section['rows'][0], 0, 7));
        $this->assertSame($basis, $section['rows'][0][8]);
    }
}
