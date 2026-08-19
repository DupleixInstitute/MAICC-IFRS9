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
 * moving part caused it, because only some of them are misstatements. Both
 * sides accrue interest monthly, so the difference resolves into three terms
 * that sum to the variance exactly:
 *
 *   implied base      = GL posted / contractual monthly rate
 *   base effect       = contractual monthly rate x (amortised opening - implied base)
 *   rate effect       = (effective monthly rate - contractual monthly rate) x opening
 *   impairment effect = interest accrued - effective monthly rate x opening
 *
 * The BASE EFFECT is the difference between the balance the engine amortises
 * and the balance the ledger accrued on. That base is derived from the posting
 * itself rather than assumed: a ledger posting flat interest on original
 * principal and one accruing on the declining balance are both common, and
 * hardcoding either turns the other's entire base difference into a residual.
 * The implied base is reported per row, because whether a ledger amortises is
 * itself a finding worth seeing.
 *
 * The RATE EFFECT is the yield uplift from fees integral to the EIR — the
 * amount this engine exists to recognise. It is only non-zero once integral
 * fees are classified, because without them the solved EIR de-compounds to
 * exactly the contractual monthly rate.
 *
 * The IMPAIRMENT EFFECT is the shortfall from Stage 3 accruing on the amortised
 * cost net of the loss allowance rather than on gross (IFRS 9 5.4.1). It is
 * zero on every performing row, and it is a correct measurement difference
 * rather than an error.
 *
 * Anything left over is genuinely unexplained — most often a ledger that did
 * not post at the contractual rate at all — and is reported rather than
 * absorbed.
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
                'gl_implied_base' => null, 'base_effect' => null, 'rate_effect' => null,
                'impairment_effect' => null, 'unexplained' => null,
            ];
        }

        $accrued = (float) $accrual->interest_accrued;
        $opening = (float) $accrual->opening_gross;
        $variance = $accrued - $posted;

        $contractualMonthly = $contract && $contract->contractual_rate !== null ? (float) $contract->contractual_rate / 12 : null;
        $effectiveMonthly = $contract && $contract->eir_effective_annual !== null
            ? pow(1 + (float) $contract->eir_effective_annual, 1 / 12) - 1 : null;
        $drawn = $contract ? (float) $contract->drawn_amount : null;

        $baseEffect = $rateEffect = $impairmentEffect = $unexplained = $impliedBase = null;
        if ($contractualMonthly !== null && $contractualMonthly > 0.0 && $effectiveMonthly !== null) {
            // Back out the balance the ledger actually accrued on rather than
            // assuming it. An earlier version hardcoded the drawn amount,
            // which is only right for a ledger that posts flat interest on
            // original principal and never amortises it; a ledger accruing on
            // the current balance then threw its entire base difference into
            // the residual.
            $impliedBase = $posted / $contractualMonthly;
            $baseEffect = $contractualMonthly * ($opening - $impliedBase);
            $rateEffect = ($effectiveMonthly - $contractualMonthly) * $opening;
            // Stage 3 accrues on the amortised cost net of the loss allowance,
            // so the shortfall against a gross accrual is a third real effect
            // and not a residual. It is zero on every GROSS row.
            $impairmentEffect = $accrued - $effectiveMonthly * $opening;
            $unexplained = $variance - $baseEffect - $rateEffect - $impairmentEffect;
        }

        return $base + [
            'status' => $this->withinTolerance($variance, $posted) ? 'WITHIN_TOLERANCE' : 'VARIANCE',
            'eir_accrued' => round($accrued, 2),
            'opening_gross' => round($opening, 2),
            'interest_basis' => $accrual->interest_basis,
            'variance' => round($variance, 2),
            'variance_percent' => $posted != 0.0 ? round($variance / abs($posted) * 100, 2) : null,
            'gl_implied_base' => $impliedBase === null ? null : round($impliedBase, 2),
            'base_effect' => $baseEffect === null ? null : round($baseEffect, 2),
            'rate_effect' => $rateEffect === null ? null : round($rateEffect, 2),
            'impairment_effect' => $impairmentEffect === null ? null : round($impairmentEffect, 2),
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
            'impairment_effect' => round((float) array_sum(array_column($matched, 'impairment_effect')), 2),
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
            'rate_effect' => 0.0, 'impairment_effect' => 0.0, 'unexplained' => 0.0,
            'eir_total' => 0.0, 'net_variance' => 0.0];
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
