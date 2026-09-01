<?php

namespace App\Services\Eir;

use App\Models\GlAccountScope;
use App\Models\GlTrialBalanceLine;
use Carbon\Carbon;

/**
 * Turns cumulative trial-balance balances into monthly movements (spec §3.4.1).
 *
 * ---------------------------------------------------------------------------
 * THE RULE THIS CLASS EXISTS FOR
 * ---------------------------------------------------------------------------
 * The P&L figures in MAIIC's trial balances are cumulative year-to-date. They
 * accumulate through each financial year and reset every January. Verified
 * across all 19 delivered files — GL 4216 reads 51,993,819 in January 2025 and
 * 1,361,118,063 by November 2025. The second figure is not November's interest;
 * it is eleven months stacked.
 *
 *     movement(month N) = balance(N) - balance(N-1)
 *     movement(January) = balance(January)
 *
 * January is taken whole because the preceding December belongs to a different
 * financial year and the accounts have reset — subtracting across the boundary
 * would net a full year out of one month.
 *
 * Treating a cumulative balance as a month overstates November 2025 interest as
 * 1,361,118,063 against a true 166,170,924: a factor of 8.2. The engine would
 * report a catastrophic variance that does not exist, against a ledger that is
 * in fact correct.
 *
 * Balance-sheet accounts are the opposite case and must NOT be differenced. The
 * loan balance at 30 November IS the balance, not a running total; differencing
 * it produces nonsense in the other direction. So the rule is conditional on
 * what the account is, which is why it needs GlAccountScope and cannot be a
 * blanket transform applied at ingestion.
 *
 * ---------------------------------------------------------------------------
 * WHY MOVEMENTS ARE COMPUTED HERE AND NOT STORED
 * ---------------------------------------------------------------------------
 * Every movement is returned beside the two balances it came from. An auditor
 * who can see `opening`, `closing` and the subtraction has to trust less than
 * one handed a derived number. It also means a re-imported file cannot leave a
 * stale movement behind it.
 */
class TrialBalanceMovementService
{
    /**
     * Monthly movement for one GL code in one period.
     *
     * Returns null when the balance for the period is absent — December 2025's
     * standalone file is post-closing and carries no P&L at all (§3.4.2), and
     * an absent figure is a coverage gap, never a zero movement. Reporting it
     * as zero would show the year's most material month as no activity.
     *
     * @return array{gl_code:string,period:string,movement:?float,closing_balance:?float,
     *               opening_balance:?float,opening_period:?string,is_year_opening:bool,
     *               statement:string,basis:?string,status:string}
     */
    public function movement(string $glCode, string $period, ?string $basis = null): array
    {
        $period = Carbon::parse($period)->startOfMonth();
        $statement = $this->statementFor($glCode);
        $isJanuary = $period->month === 1;

        $closing = $this->line($glCode, $period->toDateString(), $basis);

        $result = [
            'gl_code' => $glCode,
            'period' => $period->toDateString(),
            'movement' => null,
            'closing_balance' => null,
            'opening_balance' => null,
            'opening_period' => null,
            'is_year_opening' => $isJanuary,
            'statement' => $statement,
            'basis' => $closing?->basis,
            'status' => 'NO_BALANCE',
        ];

        if ($closing === null) {
            return $result;
        }

        $normal = $this->normalBalanceFor($glCode);
        $closingBalance = $closing->signedBalance($normal);
        $result['closing_balance'] = round($closingBalance, 2);

        // A balance-sheet account is already the figure anyone wants. Saying so
        // explicitly beats returning a movement nobody should have asked for.
        if ($statement === 'BS') {
            return ['status' => 'POINT_IN_TIME'] + $result;
        }

        if ($isJanuary) {
            return ['status' => 'YEAR_OPENING', 'movement' => round($closingBalance, 2)] + $result;
        }

        $priorPeriod = $period->copy()->subMonth();
        $opening = $this->line($glCode, $priorPeriod->toDateString(), $basis);

        if ($opening === null) {
            // The prior month is missing, so the movement is genuinely unknown.
            // Falling back to the closing balance here would silently report a
            // cumulative figure as a month — precisely the error this class
            // exists to prevent — so it is refused instead.
            return ['status' => 'NO_PRIOR_BALANCE', 'opening_period' => $priorPeriod->toDateString()] + $result;
        }

        $openingBalance = $opening->signedBalance($normal);

        return [
            'status' => 'MOVEMENT',
            'movement' => round($closingBalance - $openingBalance, 2),
            'opening_balance' => round($openingBalance, 2),
            'opening_period' => $priorPeriod->toDateString(),
        ] + $result;
    }

    /**
     * Movements for every in-scope EIR income account in a period, keyed by code.
     *
     * @return array<string,array>
     */
    public function movementsForPeriod(
        string $period,
        ?string $category = null,
        ?string $basis = null,
        string $chart = 'EBANKER'
    ): array {
        // Filtered by chart, or the QuickBooks codes seeded for the AFS bridge
        // (§3.4.4) appear in every E-Banker sweep as permanently absent rows.
        // The trial balances are E-Banker; 4206 is the QuickBooks name for the
        // same interest that E-Banker calls 42019, and reporting both would
        // double-count the moment someone totalled the column.
        $codes = GlAccountScope::query()->inEirScope()->where('chart', $chart)
            ->when($category !== null, fn ($q) => $q->where('category', $category))
            ->orderBy('gl_code')->pluck('gl_title', 'gl_code');

        $out = [];
        foreach ($codes as $code => $title) {
            $out[$code] = ['gl_title' => $title] + $this->movement((string) $code, $period, $basis);
        }

        return $out;
    }

    /**
     * Prefers whichever basis the caller asked for, and otherwise takes the
     * pre-closing row when both exist.
     *
     * December 2025 is delivered twice — post-closing in the monthly file with
     * the P&L already swept to retained earnings, and pre-closing in the AFS
     * workbook with all 21 income accounts intact (§3.4.2). Which one is
     * authoritative is open item #23. Until that is answered in writing, the
     * default is the one that actually carries an income statement, because
     * the alternative silently reports December income as zero.
     */
    private function line(string $glCode, string $period, ?string $basis): ?GlTrialBalanceLine
    {
        // whereDate rather than a plain equality: the `date` cast writes a full
        // datetime, which MySQL truncates into a DATE column but sqlite keeps
        // verbatim. A string comparison would then match in production and miss
        // under test, which is the worst way round for a rule this load-bearing.
        return GlTrialBalanceLine::where('gl_code', $glCode)->whereDate('period', $period)
            ->when($basis !== null, fn ($q) => $q->where('basis', $basis))
            ->orderByRaw("CASE WHEN basis = ? THEN 0 ELSE 1 END", [GlTrialBalanceLine::BASIS_PRECLOSING])
            ->first();
    }

    private function statementFor(string $glCode): string
    {
        $scope = GlAccountScope::where('gl_code', $glCode)->first();

        return $scope->statement ?? (GlAccountScope::isProfitAndLossCode($glCode) ? 'PL' : 'BS');
    }

    private function normalBalanceFor(string $glCode): string
    {
        $scope = GlAccountScope::where('gl_code', $glCode)->first();

        return $scope->normal_balance ?? GlAccountScope::defaultNormalBalance($glCode);
    }
}
