<?php

namespace Tests\Feature\Ecl;

use App\Services\Fli\OverlayService;
use App\Services\Reports\EclBuildUpService;
use Mockery;
use Tests\TestCase;

/**
 * The ECL build-up the Loan Book, ECL Calculation and Dashboard show: each
 * loan's lineage on the engine's undiscounted basis (EAD x PD over the
 * horizon x LGD), the book's split into ECL before FLI, forward-looking
 * effect, overlays and booked ECL, and the breakdowns that must tie to the
 * total. No database: the loans are plain rows with no FLI fit, set or
 * overlay ids, so nothing is looked up.
 */
class EclBuildUpServiceTest extends TestCase
{
    protected $seed = false;

    private function loan(array $over = []): object
    {
        return (object) array_merge([
            'id' => 1, 'contract_id' => 'C1', 'customer_name' => 'A', 'loan_portfolio_id' => 1, 'product_group' => 'P1', 'product_code' => '1',
            'industry_type' => '5-5.  Construction and Engineering', 'industry_code' => '4290',
            'ifrs9stage_post_qualitative' => '1', 'calculated_ifrs9_stage' => '1', 'ifrs9stage_pre_qualitative' => '1',
            'overdue_days' => 0, 'tenor' => 36, 'remaining_tenor' => 24,
            'carrying_amount' => 1000.0, 'commitments' => 500.0, 'facility_utilisation_rate' => 0.5,
            'pd_prefli' => 0.10, 'fli_adj' => 0.2, 'pd_post_fli' => 0.12, 'fli_route' => null, 'fli_method' => null,
            'fli_fit_id' => null, 'fli_set_id' => null, 'fli_overlay_ids' => null,
            'lgd_value' => 0.5, 'collection_lgd' => 0.5, 'customer_lgd' => null, 'ecl_value' => null, 'ecl_value_discounted' => null,
            'ecl_discount_status' => 'NOT_REQUESTED',
        ], $over);
    }

    /** The booked ECL the engine would write for a loan, on the PD named. */
    private function booked(object $l, string $pd = 'pd_post_fli'): float
    {
        $x = EclBuildUpService::lineage($l);
        $h = $pd === 'pd_post_fli' ? $x['horizon_pd_post'] : $x['horizon_pd_pre'];

        return round($x['ead'] * $h * $l->lgd_value, 2);
    }

    private function service(): EclBuildUpService
    {
        return new EclBuildUpService(Mockery::mock(OverlayService::class));
    }

    public function test_lineage_follows_the_engine_by_stage(): void
    {
        $s1 = EclBuildUpService::lineage($this->loan());
        $this->assertEqualsWithDelta(1250.0, $s1['ead'], 1e-9); // 1000 + 500 x 0.5
        $this->assertEqualsWithDelta(0.12, $s1['horizon_pd_post'], 1e-12); // twelve months of a 12-month PD
        $this->assertSame('12m', $s1['horizon']);

        $s2 = EclBuildUpService::lineage($this->loan(['ifrs9stage_post_qualitative' => '2']));
        $this->assertEqualsWithDelta(1 - pow(0.88, 2), $s2['horizon_pd_post'], 1e-12); // lifetime over 24 months
        $this->assertSame('lifetime', $s2['horizon']);

        $s3 = EclBuildUpService::lineage($this->loan(['ifrs9stage_post_qualitative' => '3']));
        $this->assertSame(1.0, $s3['horizon_pd_post']);
        $this->assertSame(1.0, $s3['horizon_pd_pre']);

        // no utilisation recorded: the engine takes the whole undrawn amount
        $this->assertEqualsWithDelta(1500.0, EclBuildUpService::lineage($this->loan(['facility_utilisation_rate' => null]))['ead'], 1e-9);
        // no remaining term: twelve months
        $this->assertFalse(EclBuildUpService::lineage($this->loan(['remaining_tenor' => 0]))['tenor_recorded']);
    }

    public function test_build_up_adds_back_to_the_booked_ecl(): void
    {
        $loans = collect([
            $this->loan(['id' => 1]),
            $this->loan(['id' => 2, 'ifrs9stage_post_qualitative' => '2', 'remaining_tenor' => 40]),
            $this->loan(['id' => 3, 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 1.0, 'pd_post_fli' => 1.0, 'fli_adj' => 0]),
        ])->map(function ($l) { $l->ecl_value = $this->booked($l); return $l; });

        $b = $this->service()->buildUp($loans, '2026-08');
        $this->assertTrue($b['available']);
        $this->assertSame('post_fli', $b['basis']);

        $pre = $loans->sum(fn ($l) => $this->booked($l, 'pd_prefli'));
        $this->assertEqualsWithDelta($pre, $b['pre_fli'], 0.05);
        $this->assertEqualsWithDelta($b['final'], $b['pre_fli'] + $b['fli_model'] + $b['overlays'], 0.011);
        $this->assertEqualsWithDelta($loans->sum('ecl_value'), $b['final'], 0.001);
        $this->assertSame(0.0, $b['overlays']);
    }

    public function test_a_run_on_the_pd_before_fli_books_no_forward_looking_effect(): void
    {
        $loans = collect([$this->loan()])->map(function ($l) { $l->ecl_value = $this->booked($l, 'pd_prefli'); return $l; });
        $b = $this->service()->buildUp($loans, '2026-08');
        $this->assertTrue($b['available']);
        $this->assertSame('pre_fli', $b['basis']);
        $this->assertSame(0.0, $b['fli_total']);
        $this->assertSame($b['final'], $b['pre_fli']);
    }

    public function test_a_stale_ecl_is_never_split(): void
    {
        $loans = collect([$this->loan(['ecl_value' => 999.99])]);
        $b = $this->service()->buildUp($loans, '2026-08');
        $this->assertFalse($b['available']);
        $this->assertNull($b['pre_fli']);
        $this->assertStringContainsString('Run the ECL again', $b['reason']);

        $tp = $this->service()->buildUp(collect([$this->loan(['ecl_value' => 999.99, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED'])]), '2026-08');
        $this->assertFalse($tp['available']);
        $this->assertStringContainsString('time-phased', $tp['reason']);
    }

    public function test_breakdowns_keep_six_groups_and_tie_to_the_total(): void
    {
        $loans = collect(range(1, 9))->map(fn ($i) => $this->loan(['id' => $i, 'product_group' => 'P' . $i, 'industry_type' => null,
            'ecl_value' => 100.0 * $i, 'overdue_days' => $i * 50]));
        $svc = $this->service();

        $p = $svc->breakdown($loans, 'product');
        $this->assertCount(7, $p['rows']);
        $this->assertSame('P9', $p['rows'][0]['label']);
        $this->assertSame('Other (3)', $p['rows'][6]['label']);
        $this->assertEqualsWithDelta(4500.0, $p['total']['ecl'], 1e-9);

        $s = $svc->breakdown($loans, 'sector');
        $this->assertSame('Not recorded', $s['rows'][0]['label']);

        $r = $svc->breakdown($loans, 'rbm');
        $this->assertSame(['Pass', 'Special mention', 'Substandard', 'Doubtful', 'Loss'], array_column($r['rows'], 'label'));
        $this->assertEqualsWithDelta(4500.0, $r['total']['ecl'], 1e-9);
        $this->assertSame('Construction and Engineering', EclBuildUpService::sectorLabel('5-5.  Construction and Engineering'));
    }
}
