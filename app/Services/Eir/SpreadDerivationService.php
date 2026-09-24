<?php

namespace App\Services\Eir;

use App\Models\ReferenceRate;
use App\Services\AuditLoggerService;
use App\Support\ReportingPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Derives the spread added to the prime rate (margin) for every contract
 * from the evidence the loan book already carries (decision D13).
 *
 * For each month a loan appears in the loan book, the spread is the rate the
 * book shows minus the reference rate in force at that month end. Over
 * December 2025 to August 2026 the rate on every PLR-linked MAIIC loan moved
 * by exactly the PLR change in every one of eight months, so a genuine
 * floating loan lands on one clean spread (4.90, 5.00, 6.00, 7.70 points).
 * A loan whose spread wanders by more than 0.15 percentage points is not
 * given a spread: it is flagged for review (validation rule 4), because a
 * wrong spread would misprice every reset from then on.
 *
 * MAIIC's own supplied figure (contract_eir.markup) is a cross-check, never
 * an input: the two are compared and the disagreement is reported. The
 * engine does not depend on a field E-Banker does not hold.
 */
class SpreadDerivationService
{
    /** Spreads this close, in percentage points, are one spread (spec v3 section 5.7 rule 4). */
    public const TOLERANCE_PP = 0.15;

    public const STATUS_DERIVED = 'DERIVED';

    public const STATUS_DRIFT = 'DRIFT';

    public const STATUS_NO_DATA = 'NO_DATA';

    public const STATUS_NO_REFERENCE_RATE = 'NO_REFERENCE_RATE';

    /** Per-contract detail kept in the audit record; the book is a few hundred contracts. */
    private const AUDIT_DETAIL_LIMIT = 1000;

    /**
     * @param  string|null  $asAtPeriod  a reporting period in any loan-book
     *                                    shape; only months up to and including
     *                                    it are read. Null reads every month.
     * @param  string  $index  the reference-rate series, PLR unless told otherwise
     * @return array{
     *   index_code:string, as_at_period:?string, contracts:int, derived:int, drifted:int,
     *   no_data:int, no_reference_rate:int, supplied_agree:int, supplied_disagree:int,
     *   details:array<string,array<string,mixed>>
     * }
     */
    public function derive(?string $asAtPeriod = null, string $index = ReferenceRate::DEFAULT_INDEX, ?int $userId = null): array
    {
        $index = strtoupper(trim($index)) ?: ReferenceRate::DEFAULT_INDEX;

        $asAt = null;
        if ($asAtPeriod !== null && trim($asAtPeriod) !== '') {
            $asAt = ReportingPeriod::normalise($asAtPeriod);
            if ($asAt === null) {
                throw new InvalidArgumentException("'{$asAtPeriod}' is not a reporting period; write it as YYYY-MM.");
            }
        }

        $series = DB::table('reference_rate_series')
            ->where('index_code', $index)
            ->orderBy('effective_date')
            ->get(['effective_date', 'rate'])
            ->map(fn ($row) => ['date' => substr((string) $row->effective_date, 0, 10), 'rate' => (float) $row->rate])
            ->all();
        if ($series === []) {
            throw new RuntimeException("No {$index} reference rates are loaded; import File C before deriving spreads.");
        }

        $contracts = DB::table('contract_eir')
            ->orderBy('contract_id')
            ->get(['contract_id', 'markup', 'reprice_flag'])
            ->keyBy('contract_id');

        $observations = $this->observations($contracts->keys()->all(), $asAt, $series);

        $summary = [
            'index_code' => $index,
            'as_at_period' => $asAt,
            'contracts' => $contracts->count(),
            'derived' => 0,
            'drifted' => 0,
            'no_data' => 0,
            'no_reference_rate' => 0,
            'supplied_agree' => 0,
            'supplied_disagree' => 0,
            'details' => [],
        ];
        $now = now();

        foreach ($contracts as $contractId => $contract) {
            $detail = $this->assess($observations[$contractId] ?? ['spreads' => [], 'months' => 0, 'without_rate' => 0], $contract);

            match ($detail['status']) {
                self::STATUS_DERIVED => $summary['derived']++,
                self::STATUS_DRIFT => $summary['drifted']++,
                self::STATUS_NO_REFERENCE_RATE => $summary['no_reference_rate']++,
                default => $summary['no_data']++,
            };
            if ($detail['supplied_agrees'] === true) {
                $summary['supplied_agree']++;
            } elseif ($detail['supplied_agrees'] === false) {
                $summary['supplied_disagree']++;
            }

            // A contract with nothing to say keeps whatever it had: an earlier
            // run on a fuller loan book is better evidence than none.
            if (in_array($detail['status'], [self::STATUS_DERIVED, self::STATUS_DRIFT], true)) {
                DB::table('contract_eir')->where('contract_id', $contractId)->update([
                    'spread_over_prime' => $detail['spread'],
                    'spread_source' => $detail['status'] === self::STATUS_DERIVED ? self::STATUS_DERIVED : null,
                    'spread_drift_flag' => $detail['status'] === self::STATUS_DRIFT,
                    'updated_at' => $now,
                ]);
            }

            $summary['details'][$contractId] = $detail;
        }

        AuditLoggerService::log(
            action: 'EIR Spread Derivation',
            entityType: 'ContractEir',
            entityId: null,
            data: [
                'reporting_period' => $asAt,
                'meta' => [
                    'user_id' => $userId,
                    'tolerance_pp' => self::TOLERANCE_PP,
                    'result' => array_slice($summary, 0, -1) + [
                        'details' => array_slice($summary['details'], 0, self::AUDIT_DETAIL_LIMIT, true),
                    ],
                ],
            ]
        );

        return $summary;
    }

    /**
     * Monthly spreads per contract: loan-book rate minus the reference rate
     * in force at the month end. The loan book's reporting_period is a free
     * string, so every shape goes through ReportingPeriod once; where one
     * month appears twice under two spellings the later row wins.
     *
     * @param  list<string>  $contractIds
     * @param  list<array{date:string,rate:float}>  $series  ascending
     * @return array<string,array{spreads:array<string,float>,months:int,without_rate:int}>
     */
    private function observations(array $contractIds, ?string $asAt, array $series): array
    {
        $out = [];
        if ($contractIds === []) {
            return $out;
        }

        DB::table('loan_books')
            ->whereIn('contract_id', $contractIds)
            ->orderBy('id')
            ->select(['contract_id', 'reporting_period', 'interest_rate'])
            ->chunk(2000, function ($rows) use (&$out, $asAt, $series) {
                foreach ($rows as $row) {
                    $period = ReportingPeriod::normalise($row->reporting_period);
                    if ($period === null || ($asAt !== null && $period > $asAt)) {
                        continue;
                    }
                    $rate = (float) $row->interest_rate;
                    if ($rate <= 0) {
                        continue; // a blank rate is a gap, not a spread of minus the PLR
                    }

                    $id = (string) $row->contract_id;
                    $out[$id] ??= ['spreads' => [], 'periods' => [], 'without_rate' => 0];
                    $out[$id]['periods'][$period] = true;

                    $reference = $this->inForce($series, ReportingPeriod::monthEnd($period)->toDateString());
                    if ($reference === null) {
                        $out[$id]['without_rate']++;
                        unset($out[$id]['spreads'][$period]);
                        continue;
                    }

                    $out[$id]['spreads'][$period] = round($rate - $reference, 5);
                }
            });

        foreach ($out as $id => $bucket) {
            $out[$id]['months'] = count($bucket['periods']);
            unset($out[$id]['periods']);
        }

        return $out;
    }

    /** The series rate in force on a date, from the ascending in-memory series. */
    private function inForce(array $series, string $date): ?float
    {
        $rate = null;
        foreach ($series as $row) {
            if ($row['date'] > $date) {
                break;
            }
            $rate = $row['rate'];
        }

        return $rate;
    }

    /**
     * One contract's verdict from its monthly spreads.
     *
     * @param  array{spreads:array<string,float>,months:int,without_rate:int}  $obs
     * @return array<string,mixed>
     */
    private function assess(array $obs, object $contract): array
    {
        $spreads = $obs['spreads'];
        ksort($spreads);
        $values = array_values($spreads);
        $count = count($values);

        $detail = [
            'status' => self::STATUS_NO_DATA,
            'observations' => $count,
            'first_period' => $count ? array_key_first($spreads) : null,
            'last_period' => $count ? array_key_last($spreads) : null,
            'spread' => null,
            'min' => null,
            'max' => null,
            'supplied_pp' => $this->suppliedPoints($contract->markup ?? null),
            'supplied_agrees' => null,
            'note' => null,
        ];

        if ($count === 0) {
            if ($obs['months'] > 0 && $obs['without_rate'] > 0) {
                $detail['status'] = self::STATUS_NO_REFERENCE_RATE;
                $detail['note'] = "{$obs['without_rate']} loan-book month(s) fall before the first reference rate; nothing to subtract";
            } else {
                $detail['note'] = 'no loan-book month carries a rate for this contract';
            }

            return $detail;
        }

        $min = min($values);
        $max = max($values);
        $detail['min'] = $min;
        $detail['max'] = $max;

        if (($max - $min) > self::TOLERANCE_PP + 1e-9) {
            $detail['status'] = self::STATUS_DRIFT;
            $detail['note'] = sprintf(
                'spread moves from %s to %s points over %d month(s), more than the %s allowed; held for review',
                number_format($min, 2), number_format($max, 2), $count, number_format(self::TOLERANCE_PP, 2)
            );
            $reprice = $contract->reprice_flag ?? null;
            if ($reprice !== null && ! filter_var($reprice, FILTER_VALIDATE_BOOLEAN)) {
                $detail['note'] .= '; interest policy F says the loan never reprices, so a moving spread is expected';
            }
        } else {
            $detail['status'] = self::STATUS_DERIVED;
            $detail['spread'] = $this->median($values);
            $detail['note'] = sprintf('constant at %s points over %d month(s)', number_format($detail['spread'], 2), $count);
            if ($obs['without_rate'] > 0) {
                $detail['note'] .= "; {$obs['without_rate']} earlier month(s) had no reference rate and were ignored";
            }
        }

        if ($detail['supplied_pp'] !== null) {
            if ($detail['status'] === self::STATUS_DERIVED) {
                $detail['supplied_agrees'] = abs($detail['supplied_pp'] - $detail['spread']) <= self::TOLERANCE_PP + 1e-9;
                $detail['note'] .= sprintf(
                    '; MAIIC supplied %s points, which %s',
                    number_format($detail['supplied_pp'], 2),
                    $detail['supplied_agrees'] ? 'agrees' : 'does not agree within ' . number_format(self::TOLERANCE_PP, 2)
                );
            } else {
                $detail['note'] .= sprintf('; MAIIC supplied %s points, not comparable while the derived spread drifts', number_format($detail['supplied_pp'], 2));
            }
        }

        return $detail;
    }

    /**
     * contract_eir.markup is stored the way ContractMasterImportService
     * stores every rate: as a decimal fraction (0.05 for five points). It is
     * turned back into percentage points here so the comparison is like for
     * like. A value above 1 was never divided and is already in points.
     */
    private function suppliedPoints($markup): ?float
    {
        if ($markup === null || $markup === '') {
            return null;
        }
        $value = (float) $markup;
        if ($value == 0.0) {
            return null;
        }

        return round($value <= 1 ? $value * 100 : $value, 5);
    }

    /** @param  list<float>  $values */
    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        $median = $n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;

        return round($median, 5);
    }
}
