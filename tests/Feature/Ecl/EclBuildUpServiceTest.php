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

    /**
     * A loan in credit (the customer has paid more than is owed) is a liability
     * to the customer, not a negative exposure (spec 3.8): its EAD is nil and a
     * booked ECL of nil ties. The engine used to book a negative ECL on it.
     */
    public function test_a_credit_balance_has_no_exposure_and_no_negative_ecl(): void
    {
        $l = $this->loan(['carrying_amount' => -29678.33, 'commitments' => 0.0, 'ecl_value' => 0.0]);
        $x = EclBuildUpService::lineage($l);
        $this->assertSame(0.0, $x['ead']);
        $this->assertTrue($x['ties']);
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

        // a stale ECL stays stale whether or not it was also discounted
        $tp = $this->service()->buildUp(collect([$this->loan(['ecl_value' => 999.99, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED'])]), '2026-08');
        $this->assertFalse($tp['available']);
        $this->assertStringContainsString('Run the ECL again', $tp['reason']);
    }

    /**
     * The time-phased engine leaves ecl_value as the engine measure and
     * writes the discounted allowance beside it, so a discounted month still
     * splits before and after FLI (UI end-to-end audit of 14 October 2026:
     * the month record refused every discounted month), and discounted()
     * reconciles the engine measure to the discounted allowance.
     */
    public function test_a_discounted_month_still_builds_up_and_reconciles_to_the_discounted_allowance(): void
    {
        $a = $this->loan(['id' => 1, 'contract_id' => 'A']);
        $a->ecl_value = $this->booked($a);
        $a->ecl_discount_status = 'CALCULATED_TIME_PHASED';
        $a->ecl_value_discounted = round($a->ecl_value * 0.9, 2);
        $a->ecl_discounting_effect = round($a->ecl_value * 0.05, 2); // time-phased undiscounted is 0.95 of the engine measure
        $b = $this->loan(['id' => 2, 'contract_id' => 'B']);
        $b->ecl_value = $this->booked($b);
        $b->ecl_discount_status = 'TIME_PHASED_UNRESOLVED';

        $up = $this->service()->buildUp(collect([$a, $b]), '2026-08');
        $this->assertTrue($up['available'], (string) $up['reason']);
        $this->assertSame('post_fli', $up['basis']);
        $this->assertEqualsWithDelta($up['final'], $up['pre_fli'] + $up['fli_total'], 0.011);

        $d = EclBuildUpService::discounted(collect([$a, $b]));
        $this->assertSame(1, $d['discounted_loans']);
        $this->assertSame(1, $d['unresolved_loans']);
        $this->assertFalse($d['complete']);
        $this->assertEqualsWithDelta($b->ecl_value, $d['unresolved_ecl'], 0.005);
        // engine measure + projection effect + discounting effect = discounted allowance
        $this->assertEqualsWithDelta($d['discounted'], $d['engine_ecl'] + $d['projection_effect'] + $d['discounting_effect'], 0.011);
        $this->assertEqualsWithDelta($a->ecl_value_discounted, $d['discounted'], 0.005);
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
