<?php

namespace App\Services\Reports;

use App\Services\Reports\EclMovementAttribution as Attribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The IFRS 9 note for the annual financial statements (IFRS 7 paragraphs
 * 35H, 35I and 35M) between an opening month P0 and a closing month P1:
 * the loss allowance and gross carrying amount reconciliations by stage,
 * the position by stage with the comparative, and the basis of measurement
 * written from the system's own settings. One source for the screen, the
 * PDF, the Word note and the Excel workings.
 *
 * Reporting only: it reads the booked allowance (BookedEcl: the ECL
 * discounted at the original EIR, the Mega Farm programme measure, and for a
 * loan with no discounted figure its undiscounted ECL, counted and named),
 * carrying_amount and ifrs9stage_post_qualitative (the stage the ECL was
 * provided on) as stored and never recalculates. Grouped SQL queries, never
 * contract rows in PHP, except the few contracts written off in the period
 * (WriteOffSource: the ledger's write-offs by the WriteOffClassifier and the
 * approved register), which are attributed one by one so the write-off line
 * carries the amount written off (IFRS 7.35H, 35I). The attribution itself is
 * EclMovementAttribution (pure, unit-tested). Booked basis and write-offs
 * built 14 October 2026; until then the note read the undiscounted ecl_value
 * and showed nil written off.
 *
 * Modified financial assets (IFRS 7.35J(a), 35J(b) and 20A, with the IFRS 9
 * B5.4.6 catch-up) come from the confirmed restructure register as the
 * revenue run applied them (RestructureRegisterService::ifrs7Disclosures), for
 * the period from the day after the opening month-end to the closing
 * month-end. A candidate found in E-Banker's reschedule history is never a
 * modification until two people confirm it: the note says how many await
 * that, and discloses none of them (built 14 October 2026).
 */
class Ifrs9NoteService
{
    private const STAGE = 'ifrs9stage_post_qualitative';

    /** Months with a saved ECL run, newest first. */
    public static function eclMonths(): array
    {
        if (! Schema::hasTable('expected_credit_loss')) {
            return [];
        }

        return DB::table('expected_credit_loss')->whereNull('deleted_at')->distinct()
            ->orderByDesc('reporting_period')->pluck('reporting_period')
            ->map(fn ($p) => substr((string) $p, 0, 7))->unique()->values()->all();
    }

    /**
     * Default pair: closing is the latest month with ECL; opening is the
     * December before it when that month has ECL (the annual comparison),
     * else the first month with ECL in the closing month's year, else the
     * latest earlier month with ECL.
     *
     * @return array{0:?string,1:?string}
     */
    public static function defaultPeriods(array $months): array
    {
        rsort($months);
        if (! $months) {
            return [null, null];
        }
        $closing = $months[0];
        $priorDecember = ((int) substr($closing, 0, 4) - 1) . '-12';
        if (in_array($priorDecember, $months, true)) {
            return [$priorDecember, $closing];
        }
        $sameYear = array_values(array_filter($months, fn ($p) => $p < $closing && substr($p, 0, 4) === substr($closing, 0, 4)));
        if ($sameYear) {
            return [end($sameYear), $closing];
        }
        $earlier = array_values(array_filter($months, fn ($p) => $p < $closing));

        return [$earlier[0] ?? null, $closing];
    }

    public static function assertPeriods(string $opening, string $closing): void
    {
        foreach ([$opening, $closing] as $p) {
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p)) {
                throw new \InvalidArgumentException('Periods must be months in the form YYYY-MM.');
            }
        }
        if ($opening >= $closing) {
            throw new \InvalidArgumentException('The closing month must be after the opening month.');
        }
    }

    public static function period(string $p): string
    {
        return date('F Y', strtotime(substr($p, 0, 7) . '-01'));
    }

    public static function shortPeriod(string $p): string
    {
        return date('M Y', strtotime(substr($p, 0, 7) . '-01'));
    }

    /** Line labels in the order of the note. */
    public static function lineLabels(string $what, string $opening, string $closing): array
    {
        return [
            'opening' => 'Opening ' . $what . ' (' . self::shortPeriod($opening) . ')',
            'transfer_1' => 'Transfers to Stage 1',
            'transfer_2' => 'Transfers to Stage 2',
            'transfer_3' => 'Transfers to Stage 3',
            'remeasurement' => $what === 'ECL allowance' ? 'Net remeasurement of ECL' : 'Repayments and other changes in balances',
            'originated' => 'New financial assets originated',
            'derecognised' => 'Financial assets derecognised',
            'written_off' => 'Amounts written off',
            'closing' => 'Closing ' . $what . ' (' . self::shortPeriod($closing) . ')',
        ];
    }

    /**
     * The reconciliation. status: ok, failed (a check failed: the note must
     * not be used) or no_ecl (a month has no saved ECL run).
     */
    public static function build(string $opening, string $closing, ?int $portfolioId = null): array
    {
        self::assertPeriods($opening, $closing);
        $currency = self::currency();
        $portfolio = $portfolioId
            ? DB::table('loan_portfolios')->where('id', $portfolioId)->first(['id', 'name'])
            : null;
        $scope = $portfolio ? $portfolio->name : 'All portfolios';

        $saved = [$opening => self::savedEcl($opening, $portfolioId), $closing => self::savedEcl($closing, $portfolioId)];
        $missing = array_keys(array_filter($saved, fn ($rows) => ! $rows));
        if ($missing) {
            return [
                'status' => 'no_ecl',
                'message' => 'No ECL has been calculated for ' . implode(' and ', array_map([self::class, 'period'], $missing))
                    . ', so there is no loss allowance to reconcile. Choose months with a calculated ECL, or run the ECL calculation for that month first.',
                'opening_period' => $opening, 'closing_period' => $closing,
                'scope' => $scope, 'currency' => $currency, 'checks' => [],
            ];
        }

        $closingMonth = self::monthTotals($closing, $portfolioId);
        $openingMonth = self::monthTotals($opening, $portfolioId);
        $writeOffs = self::writeOffs($opening, $closing, $portfolioId);
        $pairs = self::pairGroups($opening, $closing, $portfolioId, array_keys($writeOffs['groups']));

        // Contracts new in P1 = P1 totals by stage less the P0 contracts matched into that stage,
        // less the contracts written off in the period that are in the P1 book (attributed one by one).
        $matched = [];
        foreach (array_merge($pairs, array_values($writeOffs['groups'])) as $g) {
            if ($g['to'] !== null && ($g['matched'] || isset($g['written_off_gross']))) {
                foreach (['contracts', 'ecl_close', 'gross_close'] as $k) {
                    $matched[$g['to']][$k] = ($matched[$g['to']][$k] ?? 0) + $g[$k];
                }
            }
        }
        $newGroups = [];
        foreach (Attribution::STAGES as $stage) {
            $m = $closingMonth['stages'][$stage];
            $newGroups[] = [
                'to' => $stage,
                'contracts' => $m['contracts'] - ($matched[$stage]['contracts'] ?? 0),
                'ecl_close' => $m['ecl'] - ($matched[$stage]['ecl_close'] ?? 0),
                'gross_close' => $m['gross'] - ($matched[$stage]['gross_close'] ?? 0),
            ];
        }

        $report = [
            'status' => 'ok',
            'message' => null,
            'scope' => $scope,
            'portfolio_id' => $portfolioId,
            'currency' => $currency,
            'opening_period' => $opening,
            'closing_period' => $closing,
            'ecl' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'ecl', $writeOffs['groups'])),
            'gross' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'gross', $writeOffs['groups'])),
            'months' => [$opening => $openingMonth, $closing => $closingMonth],
            'saved' => $saved,
            'groups' => self::workingGroups($pairs, $newGroups, $writeOffs['groups']),
            'basis' => [
                $opening => BookedEcl::summary($opening, $portfolioId ? fn ($q) => $q->where('loan_portfolio_id', $portfolioId) : null),
                $closing => BookedEcl::summary($closing, $portfolioId ? fn ($q) => $q->where('loan_portfolio_id', $portfolioId) : null),
            ],
            'write_offs' => $writeOffs['list'],
            'write_offs_outside' => $writeOffs['outside'],
            'enforcement' => WriteOffSource::enforcementDisclosure(),
            'modifications' => self::modifications($opening, $closing, $portfolioId),
        ];
        $report['checks'] = self::checks($report, $pairs, $newGroups);
        $report['methodology'] = self::methodology($report);
        if (collect($report['checks'])->contains(fn ($c) => $c['level'] === 'fail')) {
            $report['status'] = 'failed';
            $report['message'] = 'A check failed (the reconciliation does not tie, or a loan has no discounted allowance). The note must not be used in the financial statements until the failed checks below are cleared.';
        }
        $report['built_at'] = now()->format('d M Y H:i');

        return $report;
    }

    // -----------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------

    /** Query 1: every P0 contract with its P1 stage and values, grouped; the contracts written off in the period are left to writeOffs(). */
    private static function pairGroups(string $opening, string $closing, ?int $portfolioId, array $exclude = []): array
    {
        $stage = self::STAGE;
        $portfolioJoin = $portfolioId ? ' AND e.loan_portfolio_id = s.loan_portfolio_id' : '';
        $portfolioWhere = $portfolioId ? ' AND s.loan_portfolio_id = ?' : '';
        $excludeWhere = $exclude ? ' AND s.contract_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')' : '';
        $bindings = array_merge([$closing, $opening], $portfolioId ? [$portfolioId] : [], array_map('strval', $exclude));
        $eclS = BookedEcl::sql('s.');
        $eclE = BookedEcl::sql('e.');
        $rows = DB::select(
            "SELECT s.{$stage} AS from_stage, e.{$stage} AS to_stage, (e.id IS NOT NULL) AS matched,
                    COUNT(*) AS contracts,
                    SUM({$eclS}) AS ecl_open, SUM(CASE WHEN e.id IS NULL THEN 0 ELSE {$eclE} END) AS ecl_close,
                    SUM(COALESCE(s.carrying_amount, 0)) AS gross_open, SUM(COALESCE(e.carrying_amount, 0)) AS gross_close
               FROM loan_books AS s
               LEFT JOIN loan_books AS e ON e.contract_id = s.contract_id AND e.reporting_period = ?{$portfolioJoin}
              WHERE s.reporting_period = ?{$portfolioWhere}{$excludeWhere}
              GROUP BY s.{$stage}, e.{$stage}, (e.id IS NOT NULL)",
            $bindings
        );

        return array_map(fn ($r) => [
            'from' => self::stage($r->from_stage),
            'to' => (int) $r->matched ? self::stage($r->to_stage) : null,
            'matched' => (bool) (int) $r->matched,
            'contracts' => (int) $r->contracts,
            'ecl_open' => Attribution::cents($r->ecl_open),
            'ecl_close' => Attribution::cents($r->ecl_close),
            'gross_open' => Attribution::cents($r->gross_open),
            'gross_close' => Attribution::cents($r->gross_close),
        ], $rows);
    }

    /**
     * The contracts written off between the two month-ends (WriteOffSource), each
     * as a group of one with its opening and closing values and the amounts the
     * write-off line takes: the gross written off, and the allowance it used
     * (the smaller of the opening allowance and the amount written off; a loan
     * originated in the period has no opening allowance to use). A write-off on
     * a contract in neither book (or outside the portfolio) is listed, not
     * attributed.
     *
     * @return array{groups:array<string,array>,list:array,outside:array}
     */
    private static function writeOffs(string $opening, string $closing, ?int $portfolioId): array
    {
        $list = WriteOffSource::between(WriteOffSource::monthEnd($opening), WriteOffSource::monthEnd($closing));
        $amounts = [];
        foreach ($list as $w) {
            $amounts[(string) $w['contract_id']] = ($amounts[(string) $w['contract_id']] ?? 0) + (int) round($w['amount'] * 100);
        }
        if (! $amounts) {
            return ['groups' => [], 'list' => [], 'outside' => []];
        }
        $stage = self::STAGE;
        $rows = DB::table('loan_books')->whereIn('reporting_period', [$opening, $closing])->whereIn('contract_id', array_map('strval', array_keys($amounts)))
            ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
            ->selectRaw("contract_id, reporting_period, {$stage} AS stage, " . BookedEcl::sql() . ' AS ecl, COALESCE(carrying_amount, 0) AS gross')
            ->get()->groupBy('contract_id');
        $groups = [];
        $outside = [];
        foreach ($amounts as $cid => $cents) {
            $cid = (string) $cid;
            $mine = $rows->get($cid);
            $s = $mine?->firstWhere('reporting_period', $opening);
            $e = $mine?->firstWhere('reporting_period', $closing);
            if (! $s && ! $e) {
                $outside[$cid] = $cents;
                continue;
            }
            $eclOpen = $s ? Attribution::cents($s->ecl) : 0;
            $groups[$cid] = [
                'contract_id' => $cid,
                'from' => $s ? self::stage($s->stage) : null,
                'to' => $e ? self::stage($e->stage) : null,
                'matched' => $s && $e,
                'contracts' => 1,
                'ecl_open' => $eclOpen, 'ecl_close' => $e ? Attribution::cents($e->ecl) : 0,
                'gross_open' => $s ? Attribution::cents($s->gross) : 0, 'gross_close' => $e ? Attribution::cents($e->gross) : 0,
                'written_off_gross' => $cents,
                'written_off_ecl' => $s ? max(0, min($eclOpen, $cents)) : 0,
            ];
        }

        return ['groups' => $groups,
            'list' => array_values(array_filter($list, fn ($w) => isset($groups[(string) $w['contract_id']]))),
            'outside' => array_values(array_filter($list, fn ($w) => isset($outside[(string) $w['contract_id']])))];
    }

    /**
     * IFRS 7.35J(a), 35J(b) and 20A, and the B5.4.6 catch-up, for the note's period: the
     * day after the opening month-end to the closing month-end, from the modifications the
     * revenue run applied (RestructureRegisterService::ifrs7Disclosures), in the note's
     * scope. Honest when there are none, and about the candidates that await a person.
     */
    private static function modifications(string $opening, string $closing, ?int $portfolioId): array
    {
        $from = \Carbon\CarbonImmutable::parse($opening . '-01')->addMonthNoOverflow()->startOfMonth()->toDateString();
        $to = \Carbon\CarbonImmutable::parse($closing . '-01')->endOfMonth()->toDateString();
        if (! Schema::hasTable('restructure_events') || ! Schema::hasTable('contract_modifications')) {
            return ['installed' => false, 'from' => $from, 'to' => $to, 'none' => true, 'candidates' => 0, 'proposed' => 0, 'pending' => 0, 'blocked' => 0,
                'message' => 'The restructure register is not installed (run the migrations), so no modified financial asset can be disclosed (IFRS 7.35J, 20A).'];
        }
        $contracts = $portfolioId
            ? DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->whereIn('reporting_period', [$opening, $closing])->distinct()->pluck('contract_id')->map(fn ($c) => (string) $c)->all()
            : null;
        $d = (new \App\Services\Eir\RestructureRegisterService())->ifrs7Disclosures($from, $to, $contracts)
            + \App\Services\Eir\RestructureRegisterService::awaitingConfirmation($from, $to, $contracts);
        $d['installed'] = true;
        $d['none'] = $d['all_modified']['count'] === 0 && $d['derecognised']['count'] === 0 && $d['catch_up']['count'] === 0
            && $d['lifetime_modified_now_12_month']['count'] === 0 && $d['pending'] === 0 && $d['blocked'] === 0;
        $range = 'the end of ' . self::period($opening) . ' and the end of ' . self::period($closing);
        $parts = [$d['none']
            ? 'No modification of a financial asset was confirmed and applied between ' . $range . ': there is nothing to disclose under IFRS 7.35J(a), 35J(b) or 20A, and no B5.4.6 catch-up.'
            : 'Modifications confirmed on the restructure register by two people and applied by the revenue run of their month, between ' . $range . '.'];
        if ($d['pending'] > 0) {
            $parts[] = $d['pending'] . ' confirmed modification' . ($d['pending'] === 1 ? ' has' : 's have') . ' not yet been applied by the revenue run of ' . ($d['pending'] === 1 ? 'its' : 'their') . ' month, so ' . ($d['pending'] === 1 ? 'it is' : 'they are') . ' not in the figures.';
        }
        if ($d['blocked'] > 0) {
            $parts[] = $d['blocked'] . ' confirmed modification' . ($d['blocked'] === 1 ? ' was' : 's were') . ' stopped by the revenue run with the reason on the register (EIR Data, Restructures & Resets), so ' . ($d['blocked'] === 1 ? 'it is' : 'they are') . ' not in the figures.';
        }
        $awaiting = $d['candidates'] + $d['proposed'];
        $parts[] = $awaiting > 0
            ? $awaiting . ' candidate restructure' . ($awaiting === 1 ? '' : 's') . ' found in E-Banker\'s reschedule history ' . ($awaiting === 1 ? 'awaits' : 'await') . ' confirmation ('
                . $d['candidates'] . ' not yet classified, ' . $d['proposed'] . ' proposed and awaiting a second person); a candidate is not a modification until confirmed, and none is disclosed here.'
            : 'No candidate restructure awaits confirmation.';
        $d['message'] = implode(' ', $parts);

        return $d;
    }

    /** Queries 2 and 3: one month's totals by stage straight from loan_books. */
    private static function monthTotals(string $period, ?int $portfolioId): array
    {
        $stage = self::STAGE;
        $ead = \App\Services\Ecl\EclExposure::sql(); // the engine's EAD: floored at nil, nil for a loan written off to nil
        $rows = DB::table('loan_books')
            ->selectRaw("{$stage} AS stage, COUNT(*) AS contracts, COUNT(DISTINCT contract_id) AS distinct_contracts,
                SUM(COALESCE(carrying_amount,0)) AS gross, SUM({$ead}) AS ead, SUM(" . BookedEcl::sql() . ") AS ecl,
                SUM(COALESCE(ecl_value,0)) AS ecl_undiscounted, SUM(CASE WHEN ecl_discount_status = '" . BookedEcl::DISCOUNTED_STATUS . "' THEN COALESCE(ecl_value_discounted,0) ELSE 0 END) AS ecl_discounted,
                SUM(ecl_value IS NULL) AS no_ecl,
                MIN(COALESCE(pd_post_fli, pd_prefli)) AS pd_min, MAX(COALESCE(pd_post_fli, pd_prefli)) AS pd_max,
                MIN(lgd_value) AS lgd_min, MAX(lgd_value) AS lgd_max,
                SUM(pd_post_fli IS NOT NULL AND pd_prefli IS NOT NULL AND pd_post_fli <> pd_prefli) AS fli_adjusted")
            ->where('reporting_period', $period)
            ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
            ->groupBy($stage)->get();

        $stages = [];
        $other = ['contracts' => 0, 'ecl' => 0, 'gross' => 0];
        $dupes = 0;
        $noEcl = 0;
        $fli = 0;
        foreach ($rows as $r) {
            $s = self::stage($r->stage);
            $dupes += (int) $r->contracts - (int) $r->distinct_contracts;
            $noEcl += (int) $r->no_ecl;
            $fli += (int) $r->fli_adjusted;
            if ($s === null) {
                $other['contracts'] += (int) $r->contracts;
                $other['ecl'] += Attribution::cents($r->ecl);
                $other['gross'] += Attribution::cents($r->gross);
                continue;
            }
            $stages[$s] = [
                'contracts' => (int) $r->contracts,
                'gross' => Attribution::cents($r->gross),
                'ead' => Attribution::cents($r->ead),
                'ecl' => Attribution::cents($r->ecl),
                'ecl_undiscounted' => Attribution::cents($r->ecl_undiscounted),
                'ecl_discounted' => Attribution::cents($r->ecl_discounted),
                'pd' => [$r->pd_min !== null ? (float) $r->pd_min : null, $r->pd_max !== null ? (float) $r->pd_max : null],
                'lgd' => [$r->lgd_min !== null ? (float) $r->lgd_min : null, $r->lgd_max !== null ? (float) $r->lgd_max : null],
            ];
        }
        foreach (Attribution::STAGES as $s) {
            $stages[$s] ??= ['contracts' => 0, 'gross' => 0, 'ead' => 0, 'ecl' => 0, 'ecl_undiscounted' => 0, 'ecl_discounted' => 0, 'pd' => [null, null], 'lgd' => [null, null]];
        }
        ksort($stages);

        return ['period' => $period, 'stages' => $stages, 'unstaged' => $other, 'duplicates' => $dupes, 'no_ecl' => $noEcl, 'fli_adjusted' => $fli];
    }

    /**
     * The saved ECL run by stage (cents). Portfolio-level rows when the month
     * has them (one portfolio, or all summed), else the rows at whatever
     * level the month was calculated.
     */
    private static function savedEcl(string $period, ?int $portfolioId): array
    {
        $rows = DB::table('expected_credit_loss')->where('reporting_period', $period)->whereNull('deleted_at')->get();
        $portfolioRows = $rows->where('ecl_calculation_level', 'portfolio');
        if ($portfolioRows->isNotEmpty()) {
            $rows = $portfolioId ? $portfolioRows->where('ecl_calculation_id', $portfolioId) : $portfolioRows;
        } elseif ($portfolioId) {
            $rows = collect();
        }
        $out = [];
        foreach ($rows as $row) {
            $s = self::stage($row->ifrs9_stage);
            if ($s === null) {
                continue;
            }
            $out[$s] ??= ['ecl' => 0, 'ecl_discounted' => null, 'ead' => 0, 'loans' => 0, 'pd' => null, 'lgd' => null, 'level' => $row->ecl_calculation_level, 'updated_at' => (string) $row->updated_at];
            $out[$s]['ecl'] += Attribution::cents($row->total_ecl);
            if (isset($row->total_ecl_discounted) && $row->total_ecl_discounted !== null) {
                $out[$s]['ecl_discounted'] = ($out[$s]['ecl_discounted'] ?? 0) + Attribution::cents($row->total_ecl_discounted);
            }
            $out[$s]['ead'] += Attribution::cents($row->total_ead);
            $out[$s]['loans'] += (int) $row->total_loans;
            $out[$s]['pd'] ??= $row->pd_value_used !== null ? (float) $row->pd_value_used : null;
            $out[$s]['lgd'] ??= $row->lgd_value_used !== null ? (float) $row->lgd_value_used : null;
        }
        ksort($out);

        return $out;
    }

    private static function workingGroups(array $pairs, array $newGroups, array $writeOffs = []): array
    {
        $rows = [];
        foreach ($pairs as $g) {
            if ($g['from'] === null || ($g['matched'] && $g['to'] === null)) {
                continue;
            }
            $category = ! $g['matched'] ? 'Derecognised' : ($g['from'] === $g['to'] ? 'Same stage' : 'Transfer');
            $rows[] = ['from' => $g['from'], 'to' => $g['to'], 'category' => $category, 'contracts' => $g['contracts'],
                'ecl_open' => $g['ecl_open'], 'ecl_close' => $g['ecl_close'], 'gross_open' => $g['gross_open'], 'gross_close' => $g['gross_close']];
        }
        foreach ($newGroups as $n) {
            $rows[] = ['from' => null, 'to' => $n['to'], 'category' => 'New', 'contracts' => $n['contracts'],
                'ecl_open' => 0, 'ecl_close' => $n['ecl_close'], 'gross_open' => 0, 'gross_close' => $n['gross_close']];
        }
        $wo = [];
        foreach ($writeOffs as $g) {
            $k = ($g['from'] ?? '-') . '|' . ($g['to'] ?? '-');
            $wo[$k] ??= ['from' => $g['from'], 'to' => $g['to'], 'category' => 'Written off', 'contracts' => 0, 'ecl_open' => 0, 'ecl_close' => 0, 'gross_open' => 0, 'gross_close' => 0];
            foreach (['contracts', 'ecl_open', 'ecl_close', 'gross_open', 'gross_close'] as $f) {
                $wo[$k][$f] += $g[$f];
            }
        }
        $rows = array_merge($rows, array_values($wo));
        usort($rows, fn ($a, $b) => [$a['from'] ?? 9, $a['to'] ?? 9] <=> [$b['from'] ?? 9, $b['to'] ?? 9]);

        return $rows;
    }

    private static function attributionInput(array $pairs, array $newGroups, string $what, array $writeOffs = []): array
    {
        $groups = [];
        foreach ($pairs as $g) {
            if ($g['from'] === null || ($g['matched'] && $g['to'] === null)) {
                continue; // unstaged rows: reported by the checks, not attributed
            }
            $groups[] = ['from' => $g['from'], 'to' => $g['to'], 'contracts' => $g['contracts'],
                'opening' => $g[$what . '_open'], 'closing' => $g[$what . '_close'], 'written_off' => false];
        }
        foreach ($newGroups as $n) {
            $groups[] = ['from' => null, 'to' => $n['to'], 'contracts' => $n['contracts'], 'opening' => 0, 'closing' => $n[$what . '_close']];
        }
        foreach ($writeOffs as $g) {
            if ($g['from'] === null && $g['to'] === null) {
                continue; // unstaged in both books: reported by the checks
            }
            $groups[] = ['from' => $g['from'], 'to' => $g['to'], 'contracts' => 1, 'opening' => $g[$what . '_open'], 'closing' => $g[$what . '_close'],
                'written_off_amount' => $g['written_off_' . $what]];
        }

        return $groups;
    }

    // -----------------------------------------------------------------
    // Checks
    // -----------------------------------------------------------------

    /** Levels: pass, info, warn (explained difference) and fail (do not use the note). */
    private static function checks(array $r, array $pairs, array $newGroups): array
    {
        $checks = [];
        $opening = $r['opening_period'];
        $closing = $r['closing_period'];
        $money = fn (int $c) => self::money($c, $r['currency']);

        foreach (['ecl' => 'Loss allowance', 'gross' => 'Gross carrying amount'] as $key => $label) {
            $c = $r[$key]['check'];
            $checks[] = ['key' => $key . '_ties', 'label' => "{$label}: opening plus movements equals closing, in every column",
                'level' => $c['ok'] ? 'pass' : 'fail',
                'detail' => $c['ok'] ? 'Ties to the cent in Stage 1, Stage 2, Stage 3 and Total. Transfers net to nil.' : implode(' ', $c['messages'])];
        }

        $openMonth = $r['months'][$opening];
        $diffs = [];
        foreach (Attribution::STAGES as $s) {
            foreach (['ecl' => 'ECL', 'gross' => 'gross'] as $k => $kl) {
                $d = $r[$k]['lines']['opening'][$s] - $openMonth['stages'][$s][$k];
                if ($d !== 0) {
                    $diffs[] = "Stage {$s} {$kl} differs by " . $money($d);
                }
            }
            if ($r['ecl']['contracts']['opening'][$s] !== $openMonth['stages'][$s]['contracts']) {
                $diffs[] = "Stage {$s} contracts differ";
            }
        }
        $checks[] = ['key' => 'opening_loan_book', 'label' => 'Opening figures agree with the ' . self::period($opening) . ' loan book',
            'level' => $diffs ? 'fail' : 'pass',
            'detail' => $diffs ? implode('; ', $diffs) . '.' : 'Contracts, gross carrying amount and ECL by stage agree with a direct total of the loan book.'];

        $negative = array_filter($newGroups, fn ($n) => $n['contracts'] < 0);
        $checks[] = ['key' => 'closing_loan_book', 'label' => 'Closing figures agree with the ' . self::period($closing) . ' loan book',
            'level' => $negative ? 'fail' : 'pass',
            'detail' => $negative ? 'More contracts were matched into a stage than the closing book holds: the join is not one to one.'
                : 'Closing figures are the closing book totals by stage; contracts not in the opening book are the new financial assets.'];

        foreach ([$opening => 'opening', $closing => 'closing'] as $period => $side) {
            $saved = $r['saved'][$period];
            $month = $r['months'][$period];
            $d = [];
            foreach (Attribution::STAGES as $s) {
                $row = $saved[$s] ?? null;
                if (! $row) {
                    $d[] = "Stage {$s} has no saved ECL row";
                    continue;
                }
                // the saved run holds the undiscounted ECL (total_ecl) and the discounted ECL of the
                // loans it discounted (total_ecl_discounted); each is compared with its own basis
                $e = $month['stages'][$s]['ecl_undiscounted'] - $row['ecl'];
                $ed = $row['ecl_discounted'] !== null ? $month['stages'][$s]['ecl_discounted'] - $row['ecl_discounted'] : 0;
                $n = $month['stages'][$s]['contracts'] - $row['loans'];
                $x = $month['stages'][$s]['ead'] - $row['ead'];
                if ($e !== 0) { $d[] = "Stage {$s} undiscounted ECL differs by " . $money($e); }
                if ($ed !== 0) { $d[] = "Stage {$s} discounted ECL differs by " . $money($ed); }
                if ($n !== 0) { $d[] = "Stage {$s} loans differ by " . number_format($n); }
                if (abs($x) > 100) { $d[] = "Stage {$s} EAD differs by " . $money($x); }
            }
            $bookRows = array_sum(array_column($month['stages'], 'contracts')) + $month['unstaged']['contracts'];
            if ($bookRows === 0) {
                // A saved run with no loan book behind it: the note would show
                // a nil allowance that is not true. Never issue it.
                $checks[] = ['key' => 'no_book_' . $side, 'label' => self::period($period) . ': no loan book', 'level' => 'fail',
                    'detail' => 'An ECL run is saved for ' . self::period($period) . ' but the loan book for that month is not loaded, so the movement cannot be attributed contract by contract. Load the loan book for the month, or choose another month.'];
                continue;
            }
            $checks[] = $d
                ? ['key' => 'saved_' . $side, 'label' => self::period($period) . ' agrees with the saved ECL run', 'level' => 'warn',
                    'detail' => implode('; ', $d) . '. The loan book changed after the ECL run was saved. The note uses the loan book; run the ECL calculation for this month again so both agree.']
                : ['key' => 'saved_' . $side, 'label' => self::period($period) . ' agrees with the saved ECL run', 'level' => 'pass',
                    'detail' => 'ECL (undiscounted and discounted), EAD and number of loans by stage agree with the saved ECL run.'];

            // the basis of the allowance: the note is issued only when every loan with an
            // ECL carries the booked (discounted) allowance (spec 6.10.1; fail closed)
            $basis = $r['basis'][$period];
            $checks[] = match ($basis['status']) {
                'booked' => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': the allowance is the booked (discounted) allowance', 'level' => 'pass',
                    'detail' => $basis['message']],
                'mixed' => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': loans without a discounted allowance', 'level' => 'fail',
                    'detail' => $basis['message'] . ' The note must not be issued until every loan carries its discounted allowance: lock the EIR of each loan named on the ECL run and run the month-end ECL again.'],
                default => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': no ECL', 'level' => 'fail', 'detail' => (string) $basis['message']],
            };

            if ($month['unstaged']['contracts'] > 0) {
                $checks[] = ['key' => 'unstaged_' . $side, 'label' => self::period($period) . ': loans without a stage', 'level' => 'fail',
                    'detail' => number_format($month['unstaged']['contracts']) . ' loans have no Stage 1, 2 or 3 and are left out of the note (ECL ' . $money($month['unstaged']['ecl']) . ').'];
            }
            if ($month['duplicates'] > 0) {
                $checks[] = ['key' => 'duplicates_' . $side, 'label' => self::period($period) . ': a contract appears more than once', 'level' => 'fail',
                    'detail' => number_format($month['duplicates']) . ' extra rows share a contract number, so the movement cannot be attributed contract by contract.'];
            }
            if ($month['no_ecl'] > 0) {
                $checks[] = ['key' => 'no_ecl_' . $side, 'label' => self::period($period) . ': loans without an ECL value', 'level' => 'warn',
                    'detail' => number_format($month['no_ecl']) . ' loans have no ECL value and count as nil. Run the ECL calculation for this month again.'];
            }
        }
        $unmatched = array_filter($pairs, fn ($g) => $g['matched'] && $g['to'] === null);
        if ($unmatched) {
            $checks[] = ['key' => 'unstaged_pairs', 'label' => 'Opening contracts that are unstaged in the closing month', 'level' => 'fail',
                'detail' => number_format(array_sum(array_column($unmatched, 'contracts'))) . ' contracts are in both books but have no stage at the closing month.'];
        }

        $wo = $r['write_offs'];
        $bySource = [];
        foreach ($wo as $w) {
            $bySource[$w['source']] = ($bySource[$w['source']] ?? 0) + $w['amount'];
        }
        $checks[] = ['key' => 'write_offs', 'label' => 'Amounts written off', 'level' => 'info',
            'detail' => $wo
                ? number_format(count(array_unique(array_column($wo, 'contract_id')))) . ' contracts written off between ' . self::period($opening) . ' and ' . self::period($closing) . ', ' . $money((int) round(array_sum(array_column($wo, 'amount')) * 100))
                    . ' (' . implode('; ', array_map(fn ($k, $v) => ($k === 'ledger' ? 'in the ledger by the write-off rule' : 'approved in the write-off register') . ' ' . $money((int) round($v * 100)), array_keys($bySource), $bySource)) . '). '
                    . 'The gross line takes the amount written off; the allowance line takes the allowance each write-off used (the opening allowance, up to the amount written off).'
                : 'No write-off between ' . self::period($opening) . ' and ' . self::period($closing) . ' in the ledger (by the write-off rule) or approved in the write-off register.'];
        if ($r['write_offs_outside']) {
            $checks[] = ['key' => 'write_offs_outside', 'label' => 'Write-offs on contracts outside the note', 'level' => 'warn',
                'detail' => number_format(count($r['write_offs_outside'])) . ' write-offs (' . $money((int) round(array_sum(array_column($r['write_offs_outside'], 'amount')) * 100)) . ') are on contracts in neither month\'s book for this scope, so they are not in the reconciliation: '
                    . implode(', ', array_unique(array_column($r['write_offs_outside'], 'contract_id'))) . '.'];
        }

        // modified financial assets (IFRS 7.35J, 20A): a confirmed modification the revenue run has not
        // applied (pending) or stopped (blocked) leaves the disclosure incomplete, so the note is not issued
        $mod = $r['modifications'];
        $unapplied = (int) ($mod['pending'] ?? 0) + (int) ($mod['blocked'] ?? 0);
        $checks[] = ['key' => 'modifications', 'label' => 'Modified financial assets (IFRS 7.35J, 20A)',
            'level' => ! $mod['installed'] ? 'warn' : ($unapplied > 0 ? 'fail' : 'info'),
            'detail' => $mod['message'] . ($unapplied > 0 ? ' Run the revenue for the month of each confirmed modification (or clear the reason it was stopped) before the note is used.' : '')];

        return $checks;
    }

    // -----------------------------------------------------------------
    // Basis of measurement, from the system's settings and applied inputs
    // -----------------------------------------------------------------

    /**
     * One sentence per facility class of the staging thresholds in force at the
     * end of the period, most specific row per class and tenor band.
     *
     * @return string[]
     */
    private static function stagingBands(string $period): array
    {
        if (! Schema::hasTable('staging_thresholds')) {
            return [];
        }
        $asOf = \Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString();
        // only approved rows, as the staging engine reads them (StagingThreshold::forFacility; maker-checker
        // since 14 October 2026): a proposed threshold never reaches the financial statements note
        $rows = DB::table('staging_thresholds')
            ->when(Schema::hasColumn('staging_thresholds', 'status'), fn ($q) => $q->where('status', \App\Models\StagingThreshold::STATUS_APPROVED))
            ->whereDate('effective_from', '<=', $asOf)
            ->orderBy('facility_class')->orderBy('min_tenor_months')->orderByDesc('effective_from')->get()
            ->unique(fn ($t) => $t->facility_class . '|' . $t->min_tenor_months);
        // only the classes the staging policy in force reads (Staging Module Specification v1, section 3):
        // the single-ladder and IFRS 9 profile rows are not thresholds of the by-term policy, nor the reverse
        $policy = null;
        $unread = null;
        try {
            if (! Schema::hasTable('governance_settings')) {
                throw \App\Exceptions\GovernanceSettingMissingException::forKey(\App\Services\Eir\StagingPolicy::PRESET, $asOf);
            }
            $policy = \App\Services\Eir\StagingPolicy::at(new \App\Services\Eir\GovernanceService(), \Carbon\CarbonImmutable::parse($asOf));
            $rows = $rows->whereIn('facility_class', $policy->thresholdClasses());
        } catch (\App\Exceptions\GovernanceSettingMissingException $e) {
            // a database without the staging settings lists every approved row, and the note says so; any other
            // error is not hidden (review of 15 October 2026)
            $unread = $e->getMessage();
        }
        $out = $unread === null ? [] : ['the staging policy in force at ' . $asOf . ' could not be read (' . rtrim($unread, '.') . '), so every approved threshold row is listed'];
        foreach ($rows as $t) {
            $s2 = $policy === null ? (int) $t->stage2_dpd : $policy->stage2((int) $t->stage2_dpd, (int) $t->min_tenor_months, (int) $t->stage3_dpd);
            $s2Long = $policy === null || (int) $t->min_tenor_months > 12 ? $s2 : $policy->stage2((int) $t->stage2_dpd, 13, (int) $t->stage3_dpd);
            $s2Text = $s2Long === $s2 ? "{$s2} days past due" : "{$s2} days past due ({$s2Long} for facilities over 12 months)";
            $who = match (true) {
                $t->facility_class === \App\Services\Eir\StagingPolicy::CLASS_SINGLE => 'every facility, whatever its term',
                $t->facility_class === \App\Services\Eir\StagingPolicy::CLASS_IFRS9 => 'every facility',
                $t->facility_class === 'DEFAULT' && (int) $t->min_tenor_months === 0 => 'short-term facilities (repayment within 12 months)',
                $t->facility_class === 'DEFAULT' => 'medium- and long-term facilities (over ' . ((int) $t->min_tenor_months - 1) . ' months)',
                $t->facility_class === 'MEGA_FARM' => 'Mega Farm programme facilities',
                default => strtolower(str_replace('_', ' ', $t->facility_class)) . ' facilities' . ((int) $t->min_tenor_months > 0 ? ' (from ' . (int) $t->min_tenor_months . ' months)' : ''),
            };
            $out[] = "{$who}: Stage 2 from {$s2Text} and Stage 3 from {$t->stage3_dpd} days";
        }

        return $out;
    }

    private static function methodology(array $r): array
    {
        $opening = $r['opening_period'];
        $closing = $r['closing_period'];
        $close = $r['months'][$closing];
        $saved = $r['saved'][$closing];

        // The thresholds the staging engine applies (staging_thresholds, as at the
        // closing month end; future-dated proposals are not in force), not the
        // retired finance_stageing_rules table, which no engine reads.
        $bands = self::stagingBands($closing);

        $p = [];
        $p['staging'] = ($bands
            ? 'Loans are staged each month on days past due under the thresholds in force at ' . $closing . ': ' . implode('; ', $bands) . '. '
            : 'Loans are staged each month on days past due under the staging thresholds in force. ')
            . 'The qualitative significant-increase-in-credit-risk triggers can move a loan to a later stage; the note uses the stage after those triggers, the stage the ECL was provided on. '
            . 'Stage 1 carries a 12-month ECL, Stage 2 a lifetime ECL (not credit-impaired) and Stage 3 a lifetime ECL (credit-impaired).';

        $bits = [];
        foreach (Attribution::STAGES as $s) {
            $pd = $saved[$s]['pd'] ?? $close['stages'][$s]['pd'][0];
            $lgd = $saved[$s]['lgd'] ?? $close['stages'][$s]['lgd'][0];
            $bits[] = "Stage {$s} PD " . self::pct($pd) . ', LGD ' . self::pct($lgd);
        }
        $uniform = collect($close['stages'])->every(fn ($m) => $m['pd'][0] === $m['pd'][1] && $m['lgd'][0] === $m['lgd'][1]);
        $p['parameters'] = 'ECL for each loan is measured at the reporting date as EAD x PD x LGD, where EAD is the carrying amount plus the expected drawn share of undrawn commitments, and is then projected over the loan\'s remaining life and discounted at its effective interest rate, the original EIR or, for a floating-rate loan, the current EIR (IFRS 9 5.5.17, B5.5.44): the discounted amount is the loss allowance. Applied at '
            . self::period($closing) . ': ' . implode('; ', $bits) . '.'
            . ($uniform ? ' Every loan in a stage carries the same PD and LGD.' : ' PD or LGD varies within a stage; the figures above are those saved with the ECL run.')
            . ($close['fli_adjusted'] > 0 ? ' The PD includes the forward-looking (macro-economic) adjustment for ' . number_format($close['fli_adjusted']) . ' loans.' : '')
            // a month whose PD held for want of a model version says so (fli_without_model, D-F1 note of 14 October 2026)
            . (($fwd = \App\Services\Fli\FliWithoutModel::disclosure($closing)) !== null ? ' ' . $fwd : '');

        $rp = DB::table('reporting_periods')->whereDate('period', $closing . '-01')->first();
        $p['sources'] = ($rp && ($rp->pd_id || $rp->lgd_id))
            ? 'The PD applied is ' . ($rp->pd_id ? 'record ' . $rp->pd_id . ' (' . ($rp->pd_calculation_source ?: 'system') . ')' : 'not recorded')
                . ' and the LGD applied is ' . ($rp->lgd_id ? 'record ' . $rp->lgd_id . ' (' . ($rp->lgd_calculation_source ?: 'system') . ')' : 'not recorded') . ' for ' . self::period($closing) . '.'
            : 'The PD and LGD records applied to ' . self::period($closing) . ' are not recorded on the reporting period; the rates above are those saved with the ECL run.';

        $p['movements'] = 'The movement is attributed contract by contract between ' . self::period($opening) . ' and ' . self::period($closing)
            . '. A contract in both books whose stage changed moves its opening balance from the old stage to the new one (transfers, which net to nil in total); the rest of its change is remeasurement in its new stage. '
            . 'Contracts only in the closing book are new financial assets originated. Contracts only in the opening book have been repaid or otherwise derecognised. A contract written off in the period carries the amount written off on the write-off line (gross) and the allowance that write-off used (allowance); the rest of its change follows the same rules. Remeasurement includes the effect of changes in PD, LGD and the discounting between the two months.';

        $basis = $r['basis'][$closing];
        $rc = $basis['reconciliation'];
        $p['discounting'] = 'At ' . self::period($closing) . ' the reporting-date ECL is ' . self::money(Attribution::cents($rc['undiscounted']), $r['currency'])
            . '; the lifetime projection adds ' . self::money(Attribution::cents($rc['projection']), $r['currency'])
            . ' and discounting at the EIR (original; current for a floating-rate loan) deducts ' . self::money(Attribution::cents(-$rc['discounting_effect']), $r['currency'])
            . ', giving the allowance of ' . self::money(Attribution::cents($rc['shown']), $r['currency']) . '. ' . $basis['message'];

        $wo = $r['write_offs'];
        $p['write_offs'] = 'A loan is written off when there is no reasonable expectation of recovering it (IFRS 9 5.4.4). The amounts written off are those E-Banker posted as write-offs (the write-off transaction type, a batch whose other leg debits GL 1064 Term Loans Write Offs, or an ACCOUNT CLOSURE transfer on an account marked H in the account master) and those approved in the write-off register by a person other than the proposer. '
            . ($wo ? 'Between ' . self::period($opening) . ' and ' . self::period($closing) . ', ' . number_format(count(array_unique(array_column($wo, 'contract_id')))) . ' contracts were written off, '
                . self::money((int) round(array_sum(array_column($wo, 'amount')) * 100), $r['currency']) . '. '
                : 'No loan was written off between ' . self::period($opening) . ' and ' . self::period($closing) . '. ')
            . 'Amounts written off that are still subject to enforcement activity (IFRS 7.35L): ' . lcfirst($r['enforcement']);

        return ['paragraphs' => $p, 'thresholds' => $bands];
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public static function currency(): string
    {
        try {
            $id = DB::table('settings')->where('setting_key', 'currency')->value('setting_value');
            $code = $id ? DB::table('currencies')->where('id', $id)->value('code') : null;

            return (string) ($code ?: '');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function stage($stage): ?int
    {
        if ($stage === null || $stage === '' || ! is_numeric($stage) || (float) $stage != (int) $stage) {
            return null;
        }
        $n = (int) $stage;

        return in_array($n, Attribution::STAGES, true) ? $n : null;
    }

    private static function money(int $cents, string $currency): string
    {
        $t = $currency . ' ' . number_format(abs($cents) / 100, 2);

        return $cents < 0 ? '(' . $t . ')' : $t;
    }

    private static function pct(?float $fraction): string
    {
        return $fraction === null ? 'n/a' : rtrim(rtrim(number_format($fraction * 100, 2), '0'), '.') . '%';
    }
}
