<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\EirAmortisation;
use App\Models\GlInterestPosting;

/**
 * Explains the difference between EIR interest income and what the ledger
 * posted, for one reporting period.
 *
 * A bare variance column invites the reader to assume the engine is wrong. It
 * is not enough to state that the two disagree; the report has to say which
 * of the two moving parts caused it, because only one of them is a
 * misstatement. Both sides accrue interest monthly, so the difference resolves
 * exactly into two terms:
 *
 *   base effect = contractual monthly rate x (amortised opening - drawn amount)
 *   rate effect = (effective monthly rate - contractual monthly rate) x opening
 *
 * The two sum to the variance with no residual. The base effect is the ledger
 * accruing on original principal that has since been repaid. The rate effect
 * is the yield uplift from fees integral to the EIR — the amount this engine
 * exists to recognise — and it is only non-zero once integral fees are
 * classified, because without them the solved EIR de-compounds to exactly the
 * contractual monthly rate.
 *
 * Postings with no calculated counterpart are never folded into the bridge:
 * an absent row is a coverage gap, not a measurement difference, and adding
 * it to a variance would disguise one as the other.
 */
class EirGlReconciliationService
{
    /** A row inside this band of the posted amount is treated as agreeing. */
    public const TOLERANCE_PERCENT = 1.0;

    /** Absolute floor so near-zero postings do not register a false variance. */
    private const TOLERANCE_FLOOR = 1.0;

    /** @return list<string> Periods that have GL postings, newest first. */
    public function availablePeriods(): array
    {
        return GlInterestPosting::query()
            ->selectRaw('period_year, period_month')->distinct()
            ->orderByDesc('period_year')->orderByDesc('period_month')->get()
            ->map(fn ($row) => $this->periodKey((int) $row->period_year, (int) $row->period_month))
            ->all();
    }

    /**
     * @return array{period:?string,rows:list<array>,bridge:array,summary:array}
     */
    public function forPeriod(?string $period = null, ?string $portfolio = null): array
    {
        $period ??= $this->availablePeriods()[0] ?? null;
        if ($period === null) {
            return ['period' => null, 'rows' => [], 'bridge' => $this->emptyBridge(), 'summary' => $this->emptySummary()];
        }

        [$year, $month] = array_map('intval', explode('-', $period));
        $postings = GlInterestPosting::query()->where('period_year', $year)->where('period_month', $month)
            ->orderBy('contract_id')->get();

        $contractIds = $postings->pluck('contract_id')->unique()->all();
        $contracts = ContractEir::whereIn('contract_id', $contractIds)->get()->keyBy('contract_id');
        $accruals = EirAmortisation::whereIn('contract_id', $contractIds)
            ->where('reporting_period', $period)->get()->keyBy('contract_id');

        $rows = [];
        foreach ($postings as $posting) {
            $contract = $contracts->get($posting->contract_id);
            if ($portfolio !== null && $portfolio !== '' && (string) ($contract->portfolio ?? '') !== $portfolio) {
                continue;
            }
            $rows[] = $this->row($posting, $contract, $accruals->get($posting->contract_id), $period);
        }

        return ['period' => $period, 'rows' => $rows, 'bridge' => $this->bridge($rows), 'summary' => $this->summary($rows)];
    }

    private function row(GlInterestPosting $posting, ?ContractEir $contract, ?EirAmortisation $accrual, string $period): array
    {
        $posted = (float) $posting->interest_income_posted;
        $base = [
            'contract_id' => $posting->contract_id,
            'reporting_period' => $period,
            'portfolio' => $contract->portfolio ?? null,
            'gl_account_code' => $posting->gl_account_code,
            'gl_posted' => round($posted, 2),
            'drawn_amount' => $contract ? (float) $contract->drawn_amount : null,
            'contractual_rate' => $contract?->contractual_rate !== null ? (float) $contract->contractual_rate : null,
            'eir_effective_annual' => $contract?->eir_effective_annual !== null ? (float) $contract->eir_effective_annual : null,
        ];

        if (! $accrual) {
            // No calculated counterpart: a coverage gap, deliberately left out
            // of the bridge so it cannot read as a measurement difference.
            return $base + [
                'status' => $contract === null ? 'NO_CONTRACT' : 'NOT_CALCULATED',
                'eir_accrued' => null, 'opening_gross' => null, 'interest_basis' => null,
                'variance' => null, 'variance_percent' => null,
                'base_effect' => null, 'rate_effect' => null, 'unexplained' => null,
            ];
        }

        $accrued = (float) $accrual->interest_accrued;
        $opening = (float) $accrual->opening_gross;
        $variance = $accrued - $posted;

        $contractualMonthly = $contract && $contract->contractual_rate !== null ? (float) $contract->contractual_rate / 12 : null;
        $effectiveMonthly = $contract && $contract->eir_effective_annual !== null
            ? pow(1 + (float) $contract->eir_effective_annual, 1 / 12) - 1 : null;
        $drawn = $contract ? (float) $contract->drawn_amount : null;

        $baseEffect = $rateEffect = $unexplained = null;
        if ($contractualMonthly !== null && $effectiveMonthly !== null && $drawn !== null) {
            $baseEffect = $contractualMonthly * ($opening - $drawn);
            $rateEffect = ($effectiveMonthly - $contractualMonthly) * $opening;
            // Whatever the two terms fail to account for — a ledger that did
            // not post flat contractual interest on original principal.
            $unexplained = $variance - $baseEffect - $rateEffect;
        }

        return $base + [
            'status' => $this->withinTolerance($variance, $posted) ? 'WITHIN_TOLERANCE' : 'VARIANCE',
            'eir_accrued' => round($accrued, 2),
            'opening_gross' => round($opening, 2),
            'interest_basis' => $accrual->interest_basis,
            'variance' => round($variance, 2),
            'variance_percent' => $posted != 0.0 ? round($variance / abs($posted) * 100, 2) : null,
            'base_effect' => $baseEffect === null ? null : round($baseEffect, 2),
            'rate_effect' => $rateEffect === null ? null : round($rateEffect, 2),
            'unexplained' => $unexplained === null ? null : round($unexplained, 2),
        ];
    }

    private function withinTolerance(float $variance, float $posted): bool
    {
        return abs($variance) <= max(self::TOLERANCE_FLOOR, abs($posted) * self::TOLERANCE_PERCENT / 100);
    }

    /** The walk from what the ledger posted to what the engine calculated. */
    private function bridge(array $rows): array
    {
        $matched = array_filter($rows, fn ($r) => $r['eir_accrued'] !== null);

        $glTotal = array_sum(array_column($rows, 'gl_posted'));
        $glMatched = array_sum(array_column($matched, 'gl_posted'));

        return [
            'gl_total' => round($glTotal, 2),
            'gl_without_counterpart' => round($glTotal - $glMatched, 2),
            'gl_matched' => round($glMatched, 2),
            'base_effect' => round((float) array_sum(array_column($matched, 'base_effect')), 2),
            'rate_effect' => round((float) array_sum(array_column($matched, 'rate_effect')), 2),
            'unexplained' => round((float) array_sum(array_column($matched, 'unexplained')), 2),
            'eir_total' => round((float) array_sum(array_column($matched, 'eir_accrued')), 2),
            'net_variance' => round((float) array_sum(array_column($matched, 'variance')), 2),
        ];
    }

    private function summary(array $rows): array
    {
        $statuses = array_count_values(array_column($rows, 'status'));

        return [
            'posting_rows' => count($rows),
            'within_tolerance' => $statuses['WITHIN_TOLERANCE'] ?? 0,
            'variance_rows' => $statuses['VARIANCE'] ?? 0,
            'not_calculated' => ($statuses['NOT_CALCULATED'] ?? 0) + ($statuses['NO_CONTRACT'] ?? 0),
            'tolerance_percent' => self::TOLERANCE_PERCENT,
        ];
    }

    private function emptyBridge(): array
    {
        return ['gl_total' => 0.0, 'gl_without_counterpart' => 0.0, 'gl_matched' => 0.0, 'base_effect' => 0.0,
            'rate_effect' => 0.0, 'unexplained' => 0.0, 'eir_total' => 0.0, 'net_variance' => 0.0];
    }

    private function emptySummary(): array
    {
        return ['posting_rows' => 0, 'within_tolerance' => 0, 'variance_rows' => 0, 'not_calculated' => 0,
            'tolerance_percent' => self::TOLERANCE_PERCENT];
    }

    private function periodKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }
}
