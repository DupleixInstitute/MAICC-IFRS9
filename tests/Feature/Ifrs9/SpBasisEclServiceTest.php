<?php

namespace Tests\Feature\Ifrs9;

use App\Models\GovernanceSetting;
use App\Models\InternalGradeProfile;
use App\Services\Ifrs9\SpBasisEclService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * The S&P basis (spec v4 section 17): the December 2025 model's PD method beside the system's ECL, for
 * comparison only. It must reproduce the model's arithmetic and never write a booked figure.
 */
class SpBasisEclServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PDS = [
        '3' => [0.0003, 0.0003, 0.0003],
        '6' => [0.0298, 0.0609, 0.0846],
        '8' => [0.1758, 0.2281, 0.2584],
        '10' => [1.0, 1.0, 1.0],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('loan_books')->delete();
        $profile = InternalGradeProfile::create(['name' => 'S&P test', 'description' => 'test', 'max_tenor_years' => 3, 'is_active' => true]);
        foreach (self::PDS as $code => $pds) {
            $m = $profile->mappings()->create(['grade_code' => $code, 'grade_name' => "Grade {$code}", 'sp_rating' => ['3' => 'A', '6' => 'B', '8' => 'CCC', '10' => 'D'][$code]]);
            foreach ($pds as $i => $pd) {
                DB::table('grade_tenor_pd')->insert(['grade_mapping_id' => $m->id, 'tenor_years' => $i + 1, 'pd_probability' => $pd, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $loan = fn (string $id, int $dpd, int $stage, ?float $lgd, float $ead, string $due, string $group = 'MAIIC Term Loans') => DB::table('loan_books')->insert([
            'contract_id' => $id, 'customer_id' => $id, 'customer_name' => "Borrower {$id}", 'reporting_period' => '2025-12', 'reporting_year' => 2025, 'reporting_month' => 12,
            'loan_portfolio_id' => 1, 'commitments' => 0, 'facility_utilisation_rate' => 1, 'product_group' => $group, 'overdue_days' => $dpd,
            'ifrs9stage_post_qualitative' => $stage, 'interest_rate' => 30.0, 'lgd_value' => $lgd, 'ead' => $ead, 'pd_post_fli' => 0.15, 'ecl_value' => 1234.56,
            'due_date' => $due, 'created_at' => now(), 'updated_at' => now()]);
        $loan('900000000001', 10, 1, 0.5, 1_000_000, '2028-12-31');   // Stage 1, grade 3
        $loan('900000000002', 60, 2, 0.5, 1_000_000, '2028-06-30');   // Stage 2, grade 6, 30 months: 3 years
        $loan('900000000003', 200, 3, 0.5, 1_000_000, '2027-12-31');  // Stage 3, grade 10
        $loan('900000000004', 0, 1, 0.5, 1_000_000, '2027-12-31', 'Mega Farm Seed Loans'); // the programme: outside
        $loan('900000000005', 0, 1, null, 1_000_000, '2027-12-31');   // no LGD: not measured
    }

    private function set(string $key, string $value): void
    {
        GovernanceSetting::where('key', $key)->update(['value' => $value]);
    }

    public function test_it_reproduces_the_model_arithmetic_and_writes_no_booked_figure(): void
    {
        $r = app(SpBasisEclService::class)->run('2025-12', 1);
        $this->assertSame(3, $r['loans'], 'the Mega Farm loan and the loan with no LGD are outside');

        $rows = DB::table('sp_basis_results')->where('run_id', $r['run_id'])->get()->keyBy('contract_id');
        $recovered = fn (float $ead) => $ead - 0.4698 * $ead / pow(1.30, 360 / 365);
        // Stage 1: the one-year rate of grade 3, times one plus 15.75 percent
        $this->assertSame([1, '3', 1], [(int) $rows['900000000001']->stage, $rows['900000000001']->grade_code, (int) $rows['900000000001']->horizon_years]);
        $this->assertEqualsWithDelta(0.0003 * 1.1575 * 0.5 * $recovered(1_000_000), (float) $rows['900000000001']->ecl, 0.01);
        // Stage 2: grade 6 to maturity (30 months, so three years)
        $this->assertSame([2, '6', 3], [(int) $rows['900000000002']->stage, $rows['900000000002']->grade_code, (int) $rows['900000000002']->horizon_years]);
        $this->assertEqualsWithDelta(0.0846 * 1.1575 * 0.5 * $recovered(1_000_000), (float) $rows['900000000002']->ecl, 0.01);
        // Stage 3: 100 percent, no factor
        $this->assertSame(1.0, (float) $rows['900000000003']->pd);
        $this->assertEqualsWithDelta(0.5 * $recovered(1_000_000), (float) $rows['900000000003']->ecl, 0.01);

        // the system's figures are carried beside it and left as they were
        $this->assertSame(1234.56, (float) $rows['900000000001']->system_ecl);
        $this->assertSame(5, DB::table('loan_books')->where('ecl_value', 1234.56)->count());
    }

    public function test_the_governed_options_change_only_the_s_and_p_basis(): void
    {
        $this->set('sp_basis_macro_factor', SpBasisEclService::MACRO_NONE);
        $this->set('sp_basis_recovery', SpBasisEclService::RECOVERY_NONE);
        $this->set('sp_basis_staging', SpBasisEclService::STAGING_SYSTEM);

        $r = app(SpBasisEclService::class)->run('2025-12', 1);
        $rows = DB::table('sp_basis_results')->where('run_id', $r['run_id'])->get()->keyBy('contract_id');
        $this->assertEqualsWithDelta(0.0003 * 0.5 * 1_000_000, (float) $rows['900000000001']->ecl, 0.01);
        $this->assertSame(0.0, (float) $rows['900000000001']->recovery_pv);
        $this->assertSame(2, (int) $rows['900000000002']->stage, 'the system\'s own stage');
    }

    public function test_it_refuses_without_an_active_profile_or_a_setting(): void
    {
        InternalGradeProfile::query()->update(['is_active' => false]);
        try {
            app(SpBasisEclService::class)->run('2025-12', 1);
            $this->fail('ran with no active profile');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No internal grading profile is active', $e->getMessage());
        }
        $this->assertSame(0, DB::table('sp_basis_runs')->count());

        InternalGradeProfile::query()->update(['is_active' => true]);
        GovernanceSetting::where('key', 'sp_basis_staging')->delete();
        $this->expectExceptionMessage('sp_basis_staging');
        app(SpBasisEclService::class)->run('2025-12', 1);
    }

    public function test_an_active_profile_does_not_write_pds_to_the_loan_book_while_the_feed_is_off(): void
    {
        $admin = \App\Models\User::first();
        $profile = InternalGradeProfile::where('is_active', true)->first();
        $this->actingAs($admin)->post(route('internal-grading.loanbook.updateWithPD'), ['reporting_period' => '2025-12', 'profile_id' => $profile->id, 'scope' => 'portfolio', 'portfolio_id' => 1])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('loan_books')->whereNotNull('pd_prefli')->count(), 'the engine PD is left as it was');
        $this->assertFalse(app(SpBasisEclService::class)->feedOn());
    }

    public function test_the_page_renders_and_a_run_needs_the_settings_permission(): void
    {
        $admin = \App\Models\User::first();
        $this->actingAs($admin)->get(route('internal-grading.sp-basis', ['period' => '2025-12']))->assertOk();
        $this->actingAs($admin)->post(route('internal-grading.sp-basis.run'), ['period' => '2025-12'])->assertRedirect();
        $this->assertSame(1, DB::table('sp_basis_runs')->count());
    }
}
