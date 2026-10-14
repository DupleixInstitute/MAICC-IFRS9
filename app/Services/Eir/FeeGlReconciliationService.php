<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imported fees against the fee income GLs, by month and by year (spec v4
 * sections 3.7 and 7.4).
 *
 * The fee GLs agree to the trial balance every month, but about 44 percent of
 * 2025 fee income never touched a loan account, so the fees loaded by loan
 * (EIR Data Intake, type fees) are proved against the GL rather than against
 * the account ledger. For every year the imported fees cover, every fee held
 * for that year is set beside the trial balance movement of its GL, month by
 * month: a difference means fees the import has not yet carried (or fees on
 * loans outside the engine's schemes), never an adjustment to the GL.
 *
 * Only the fees charged to the borrower count; a PAID line (legal costs MAIIC
 * paid out) belongs on 6752, not on the income GLs, and is reported apart.
 * The check can be switched off in the Governance Centre (fee_gl_reconciliation).
 */
class FeeGlReconciliationService
{
    public const SETTING = 'fee_gl_reconciliation';
    public const ON = 'On: check fee imports against 4871 and 4873';
    public const OFF = 'Off: no check at import';

    /** The fee type a GL code stands for when the row names no fee GL itself. */
    public const GL_BY_TYPE = ['arrangement' => '4873', 'legal' => '4871'];

    /** The fee income GLs of the E-Banker trial balance. */
    public const FEE_GLS = ['4871', '4873', '4874', '4875'];

    public function __construct(
        private TrialBalanceMovementService $movements,
        private GovernanceService $governance,
    ) {
    }

    /** The check runs unless the Governance Centre has switched it off; a missing setting runs it. */
    public function enabled(): bool
    {
        return $this->setting() !== self::OFF;
    }

    public function setting(): ?string
    {
        try {
            return $this->governance->get(self::SETTING);
        } catch (Throwable) {
            return null;
        }
    }

    /** The fee GL a row belongs to: its own fee GL if it names one, else the GL of its fee type. */
    public static function glFor(?string $feeType, ?string $glRef): ?string
    {
        $ref = trim((string) $glRef);
        if (in_array($ref, self::FEE_GLS, true)) {
            return $ref;
        }

        return self::GL_BY_TYPE[strtolower(trim((string) $feeType))] ?? null;
    }

    /**
     * The result the import job and the Fee Classification screen show. When
     * the check is off it says so, so a screen never shows a blank as a pass.
     *
     * @param list<int>|null $years the years to check; null for every year that holds a dated fee
     */
    public function report(?array $years = null): array
    {
        if (! $this->enabled()) {
            return ['enabled' => false, 'setting' => $this->setting() ?? self::OFF];
        }

        return $this->reconcile($years ?? $this->years());
    }

    /** @return list<int> every year that holds a dated fee */
    public function years(): array
    {
        return $this->held()->whereNotNull('transaction_date')->pluck('transaction_date')
            ->map(fn ($d) => (int) substr((string) $d, 0, 4))->filter(fn ($y) => $y > 1900)
            ->unique()->sort()->values()->all();
    }

    /**
     * @param list<int> $years
     * @return array{enabled:bool, setting:?string, tolerance:array, years:array, undated:array}
     */
    public function reconcile(array $years): array
    {
        $tolerance = $this->tolerance();
        $out = [];
        foreach (array_values(array_unique(array_map('intval', $years))) as $year) {
            $out[$year] = $this->year($year, $tolerance);
        }
        ksort($out);

        $undated = $this->held()->whereNull('transaction_date');

        return [
            'enabled' => true,
            'setting' => $this->setting() ?? self::ON,
            'tolerance' => $tolerance,
            'years' => $out,
            'undated' => ['lines' => (clone $undated)->count(), 'amount' => round((float) (clone $undated)->sum('amount'), 2)],
        ];
    }

    /**
     * The fee lines the check counts: a line superseded by a higher source
     * under fee_source_precedence (spec 7.8.2) is the same fee held twice and
     * is left out, so a fee in GL_04 and in the charges table counts once.
     */
    private function held(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('contract_fees');
        if (DB::getSchemaBuilder()->hasColumn('contract_fees', 'superseded_by_source')) {
            $q->whereNull('superseded_by_source');
        }

        return $q;
    }

    private function year(int $year, array $tolerance): array
    {
        $rows = $this->held()
            ->whereBetween('transaction_date', ["{$year}-01-01", "{$year}-12-31 23:59:59"])
            ->get(['fee_type', 'gl_account_ref', 'amount', 'transaction_date', 'cashflow_direction']);

        $imported = [];
        $paid = ['lines' => 0, 'amount' => 0.0];
        $unmapped = ['lines' => 0, 'amount' => 0.0];
        foreach ($rows as $r) {
            if (strtoupper((string) $r->cashflow_direction) === 'PAID') {
                $paid['lines']++;
                $paid['amount'] += (float) $r->amount;
                continue;
            }
            $gl = self::glFor($r->fee_type, $r->gl_account_ref);
            if ($gl === null) {
                $unmapped['lines']++;
                $unmapped['amount'] += (float) $r->amount;
                continue;
            }
            $month = (int) substr((string) $r->transaction_date, 5, 2);
            $imported[$gl][$month] = ($imported[$gl][$month] ?? 0.0) + (float) $r->amount;
        }

        $gls = array_values(array_unique(array_merge(['4871', '4873'], array_keys($imported))));
        sort($gls);

        $byGl = [];
        foreach ($gls as $gl) {
            $months = [];
            $sumImported = $sumGl = 0.0;
            $missing = 0;
            for ($m = 1; $m <= 12; $m++) {
                $period = sprintf('%04d-%02d-01', $year, $m);
                if ($period > now()->startOfMonth()->toDateString()) {
                    break;
                }
                $mv = $this->movements->movement($gl, $period);
                $glAmount = $mv['movement'];
                $imp = round($imported[$gl][$m] ?? 0.0, 2);
                $sumImported += $imp;
                if ($glAmount === null) {
                    $missing++;
                    $status = 'NO_TB';
                } else {
                    $sumGl += $glAmount;
                    $status = $this->within($imp - $glAmount, $glAmount, $tolerance) ? 'AGREES' : 'DIFFERENCE';
                }
                if ($imp == 0.0 && ($glAmount ?? 0.0) == 0.0 && $status !== 'NO_TB') {
                    continue;
                }
                $months[] = [
                    'month' => $m, 'period' => $period, 'imported' => $imp, 'gl' => $glAmount,
                    'difference' => $glAmount === null ? null : round($imp - $glAmount, 2),
                    'status' => $status, 'tb_status' => $mv['status'], 'tb_basis' => $mv['basis'],
                ];
            }
            $difference = round($sumImported - $sumGl, 2);
            $byGl[$gl] = [
                'gl_code' => $gl,
                'imported' => round($sumImported, 2),
                'gl' => round($sumGl, 2),
                'difference' => $difference,
                'months_without_tb' => $missing,
                'status' => $missing > 0 ? 'INCOMPLETE' : ($this->within($difference, $sumGl, $tolerance) ? 'AGREES' : 'DIFFERENCE'),
                'months' => $months,
            ];
        }

        return [
            'year' => $year,
            'gls' => $byGl,
            'paid_excluded' => ['lines' => $paid['lines'], 'amount' => round($paid['amount'], 2)],
            'unmapped' => ['lines' => $unmapped['lines'], 'amount' => round($unmapped['amount'], 2)],
        ];
    }

    /** The reconciliation band in force (recon_tolerance), or to the cent when none is set. */
    private function tolerance(): array
    {
        try {
            return $this->governance->reconciliationTolerance();
        } catch (Throwable) {
            return ['percent' => 0.0, 'floor' => 0.01];
        }
    }

    private function within(float $difference, ?float $base, array $tolerance): bool
    {
        $band = max((float) $tolerance['floor'], abs((float) $base) * (float) $tolerance['percent'] / 100);

        return abs($difference) <= $band + 0.005;
    }
}
