<?php

namespace App\Services\Ecl;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Applies a transparent single-horizon discount to already calculated loan-level ECL. */
class EclDiscountingService
{
    public function __construct(private readonly EclDiscountRateService $rates) {}

    /**
     * This is the controlled first step while marginal-PD/cash-shortfall timing
     * is not yet stored. Stage 1 is capped at one year; Stages 2 and 3 use the
     * remaining contractual horizon. Every calculated row is labelled as a
     * horizon proxy so it cannot be mistaken for the eventual time-phased sum.
     *
     * @param Collection<int,object> $loans
     * @return array{calculated:int,unresolved:int,statuses:array<string,int>}
     */
    public function apply(Collection $loans, string $reportingPeriod, string $runId): array
    {
        $pairs = $loans->filter(fn ($loan) => trim((string) ($loan->contract_id ?? '')) !== '')
            ->map(fn ($loan) => ['contract_id' => (string) $loan->contract_id, 'period' => $reportingPeriod])
            ->values()->all();
        $resolution = $this->rates->resolve($pairs, EclDiscountRateService::SOURCE_EIR);
        $statuses = [];
        $calculated = 0;

        foreach ($loans as $loan) {
            $key = (string) ($loan->contract_id ?? '') . '|' . $this->rates->periodKey($reportingPeriod);
            $resolved = $resolution['rates'][$key] ?? null;
            $horizon = $this->horizonYears($loan, $reportingPeriod);
            $status = null;
            $attributes = [
                'ecl_value_discounted' => null,
                'ecl_discounting_effect' => null,
                'ecl_discount_rate' => null,
                'ecl_discount_rate_source' => null,
                'ecl_discount_horizon_years' => $horizon,
                'ecl_calculation_run_id' => $runId,
                'ecl_calculated_at' => now(),
            ];

            if ($resolved === null) {
                $status = 'EIR_UNAVAILABLE';
            } elseif ($horizon === null) {
                $status = 'HORIZON_UNAVAILABLE';
            } else {
                $undiscounted = (float) ($loan->ecl_value ?? 0);
                $rate = (float) $resolved['rate'];
                $discounted = $undiscounted / pow(1 + $rate, $horizon);
                $status = $resolved['applied_source'] === EclDiscountRateService::APPLIED_EIR_FLOATING_PROXY
                    ? 'FLOATING_RATE_PROXY'
                    : 'CALCULATED_HORIZON_PROXY';
                $attributes = array_merge($attributes, [
                    'ecl_value_discounted' => round($discounted, 2),
                    'ecl_discounting_effect' => round($undiscounted - $discounted, 2),
                    'ecl_discount_rate' => $rate,
                    'ecl_discount_rate_source' => $resolved['applied_source'],
                ]);
                $calculated++;
            }

            $attributes['ecl_discount_status'] = $status;
            DB::table('loan_books')->where('id', $loan->id)->update($attributes);
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }

        return [
            'calculated' => $calculated,
            'unresolved' => $loans->count() - $calculated,
            'statuses' => $statuses,
        ];
    }

    private function horizonYears(object $loan, string $reportingPeriod): ?float
    {
        $remaining = (float) ($loan->remaining_tenor ?? 0);
        if ($remaining <= 0 && ! empty($loan->due_date)) {
            $asOf = CarbonImmutable::createFromFormat('Y-m-d', $this->rates->periodKey($reportingPeriod) . '-01')->endOfMonth();
            $due = CarbonImmutable::parse($loan->due_date);
            $remaining = $due->greaterThan($asOf) ? $asOf->diffInDays($due) / 365 : 0.0;
        }
        if ($remaining <= 0) return null;

        $stage = (int) ($loan->calculated_ifrs9_stage
            ?? $loan->ifrs9stage_post_qualitative
            ?? $loan->ifrs9stage_pre_qualitative
            ?? $loan->ifrs9_stage
            ?? 0);

        return round($stage === 1 ? min(1.0, $remaining) : $remaining, 8);
    }
}
