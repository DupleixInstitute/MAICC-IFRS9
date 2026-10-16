<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use App\Support\AccountExclusions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A generated version 1 schedule set against E-Banker's live instalment chart
 * for the loan (release review of 16 October 2026).
 *
 * The bootstrap approves a generated draft under its label, and the one check
 * that stood before it (ScheduleWorkflowService::comparison, against Extract
 * B's remaining cash flows) has no data on a fresh install: every contract was
 * NO_REMAINING_DATA, so nothing guarded the approval. Kadava 104450000041 was
 * locked at 10.4603% on 24 level instalments while E-Banker's chart (P2_10,
 * and IC_01 of pack 11) and its plan (IP_01: plan 1267 of nil from 2 Aug to 30
 * Nov 2022, then 645,184.06) have nothing due in the first months.
 *
 * The chart read is the live one: the rows of P2_10, with the rows pack 11's
 * IC_01 adds where P2_10 holds none (InstalmentChartHistory::LIVE_SUPPLEMENTS),
 * active and not deleted; where two rows carry the same instalment (plan and
 * serial) the later-created stands, as InstalmentChartHistory reads it. Three
 * figures are compared, on the instalments with principal above nil:
 *
 *  - how many there are: equal;
 *  - the date of the first: inside one instalment period of ours (E-Banker
 *    dates its instalments on the first of the month, the generator on the
 *    day of the origination, so a month's instalment falls on two dates);
 *  - the principal in total: within max(1.00, 1%) of the chart's, the band of
 *    PRINCIPAL_NOT_RECONCILED and of the Extract B comparison.
 *
 * A loan with no chart landed is NO_CHART, and the check says nothing about it.
 * The check changes no schedule: it is recorded on contract_eir
 * (schedule_chart_check), refuses the automated approval of a schedule that
 * DIFFERS (ScheduleWorkflowService::approve), and shows on the readiness as
 * SCHEDULE_DIFFERS_FROM_CHART until a person approves the schedule with a reason.
 */
class ScheduleChartCheck
{
    public const AGREES = 'AGREES';
    public const DIFFERS = 'DIFFERS';
    public const NO_CHART = 'NO_CHART';

    public const COLUMN = 'schedule_chart_check';

    public function __construct(private ?LandingZoneReader $zone = null)
    {
    }

    /**
     * E-Banker's live chart instalments with principal above nil, in date order.
     *
     * @return list<array{due:string, principal:float}>|null null when no live chart row is landed for the loan
     */
    public function chart(string $contractId): ?array
    {
        if (! Schema::hasTable('ebanker_raw_rows')) {
            return null;
        }
        $zone = $this->zone ?? new LandingZoneReader();
        $account = AccountExclusions::padded($contractId);
        $rows = [];
        $held = [];
        $any = false;
        foreach (array_merge(['P2_10'], InstalmentChartHistory::LIVE_SUPPLEMENTS) as $query) {
            foreach (array_unique([$account, ltrim($contractId, '0')]) as $form) {
                foreach ($zone->family([$query], null, null, $form) as $row) {
                    $p = $row['payload'];
                    $id = (string) ($p['INST_CHART_DET_ID'] ?? $row['source_key']);
                    if ($query !== 'P2_10' && isset($held[$id])) {
                        continue; // P2_10 holds the row already
                    }
                    $held[$id] = true;
                    if (($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y' || ($p['DELETE_FLAG'] ?? 'N') === 'Y') {
                        continue;
                    }
                    $any = true; // a live row: the loan has a chart
                    $due = InstalmentChartHistory::date((string) ($p['INSTALLMENT_DATE'] ?? ''));
                    if ($due === null) {
                        continue;
                    }
                    $key = ($p['INST_DET_ID'] ?? '') !== '' && (int) ($p['INST_SR_NO'] ?? 0) > 0 ? $p['INST_DET_ID'] . '#' . (int) $p['INST_SR_NO'] : 'row:' . $id;
                    $created = InstalmentChartHistory::date((string) ($p['TRANSACTION_DATE'] ?? '')) ?? '';
                    if (isset($rows[$key]) && $rows[$key]['created'] > $created) {
                        continue;
                    }
                    $rows[$key] = ['due' => $due, 'principal' => self::num($p['PRIN_INSTALLMENT_AMT'] ?? 0), 'created' => $created];
                }
            }
        }
        if (! $any) {
            return null;
        }
        $out = array_values(array_filter(array_map(fn ($r) => ['due' => $r['due'], 'principal' => $r['principal']], $rows), fn ($r) => $r['principal'] > 0.005));
        usort($out, fn ($a, $b) => $a['due'] <=> $b['due']);

        return $out;
    }

    /**
     * The comparison of the contract's version 1 with the chart. Writes nothing.
     *
     * @return array{status:string, reasons:list<string>, ours:array, chart:?array, checked_at:string}
     */
    public function compare(ContractEir $contract): array
    {
        $ours = DB::table('contract_cashflow_schedule')->where('contract_id', $contract->contract_id)->where('schedule_version', 1)
            ->where('principal_due', '>', 0.005)->orderBy('due_date')->get(['due_date', 'principal_due']);
        $figures = fn ($n, $first, $principal) => ['instalments' => $n, 'first_principal_due' => $first, 'principal' => round($principal, 2)];
        $mine = $figures($ours->count(), $ours->isEmpty() ? null : substr((string) $ours->first()->due_date, 0, 10), (float) $ours->sum('principal_due'));
        $chart = $this->chart((string) $contract->contract_id);
        if ($chart === null) {
            return ['status' => self::NO_CHART, 'reasons' => [], 'ours' => $mine, 'chart' => null, 'checked_at' => now()->toDateTimeString()];
        }
        $theirs = $figures(count($chart), $chart[0]['due'] ?? null, array_sum(array_column($chart, 'principal')));
        $reasons = [];
        if ($mine['instalments'] !== $theirs['instalments']) {
            $reasons[] = "{$mine['instalments']} instalments with principal against E-Banker's {$theirs['instalments']}";
        }
        $months = in_array((int) $contract->payments_per_year, [1, 2, 4, 6, 12], true) ? intdiv(12, (int) $contract->payments_per_year) : 1;
        if ($mine['first_principal_due'] !== null && $theirs['first_principal_due'] !== null) {
            $a = CarbonImmutable::parse($mine['first_principal_due']);
            $b = CarbonImmutable::parse($theirs['first_principal_due']);
            if (! ($b->gt($a->subMonthsNoOverflow($months)) && $b->lt($a->addMonthsNoOverflow($months)))) {
                $reasons[] = "the first principal falls due on {$mine['first_principal_due']} against E-Banker's {$theirs['first_principal_due']}";
            }
        } elseif ($mine['first_principal_due'] !== $theirs['first_principal_due']) {
            $reasons[] = 'one of the two schedules repays no principal';
        }
        if (abs($mine['principal'] - $theirs['principal']) > max(1.0, abs($theirs['principal']) * 0.01)) {
            $reasons[] = 'principal ' . number_format($mine['principal'], 2) . " against E-Banker's " . number_format($theirs['principal'], 2);
        }

        return ['status' => $reasons === [] ? self::AGREES : self::DIFFERS, 'reasons' => $reasons, 'ours' => $mine, 'chart' => $theirs, 'checked_at' => now()->toDateTimeString()];
    }

    /** Compare and keep the result on the contract (where the column exists). */
    public function record(ContractEir $contract): array
    {
        $check = $this->compare($contract);
        if (Schema::hasColumn('contract_eir', self::COLUMN)) {
            DB::table('contract_eir')->where('id', $contract->id)->update([self::COLUMN => json_encode($check)]);
            $contract->setRawAttributes(array_merge($contract->getAttributes(), [self::COLUMN => json_encode($check)]), true);
        }

        return $check;
    }

    /** The check held on the contract, or null. */
    public static function held(object $contract): ?array
    {
        $raw = $contract instanceof ContractEir ? $contract->getAttribute(self::COLUMN) : ($contract->{self::COLUMN} ?? null);
        if (is_array($raw)) {
            return $raw;
        }

        return $raw === null || $raw === '' ? null : (json_decode((string) $raw, true) ?: null);
    }

    /** One line for a person: why the schedule is held and the two sets of figures. */
    public static function describe(array $check): string
    {
        $f = fn (?array $x) => $x === null ? 'none' : "{$x['instalments']} instalments with principal, the first on " . ($x['first_principal_due'] ?? '-') . ', principal ' . number_format((float) $x['principal'], 2);

        return 'The generated schedule differs from E-Banker\'s instalment chart: ' . implode('; ', $check['reasons']) . '. Ours: ' . $f($check['ours']) . '. E-Banker: ' . $f($check['chart']) . '.';
    }

    private static function num(mixed $v): float
    {
        $s = str_replace([',', ' '], '', trim((string) $v));

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
