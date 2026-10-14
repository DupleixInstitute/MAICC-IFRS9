<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\EirRatePeriod;
use App\Models\RateResetEvent;
use App\Models\ReferenceRate;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Phase P5, steps 1 and 2: which loans follow the prime rate, the spread each
 * one is locked at, and every prime-rate change since it was drawn.
 *
 * For each loan in scope this writes the rate periods it has had (period 1
 * from origination, then one per reset) and, for a floating loan, one
 * rate_reset_events row per change. The EIR of each period is left for the
 * next step; a period here is PENDING_EIR.
 *
 *  - Does it follow the prime rate? E-Banker's Interest Policy decides (P
 *    floats, F is fixed). Where MAIIC has not supplied it, the product family
 *    stands in, as the Governance Centre's rate_source_precedence orders it:
 *    FinES loans are fixed at 10 percent and MAIIC Term Loans did not move in
 *    nine months of loan books, while MAIIC Industrial and Agricultural loans
 *    moved with every prime-rate change (spec v3 section 7.3). The source is
 *    recorded so a supplied policy can overrule the guess.
 *
 *  - The spread is locked at origination (Governance key margin_basis). On
 *    LIVE_AT_DRAWDOWN, the default, it is the rate at first disbursement less
 *    the prime rate in force that day: JAT Group, 33.30 less 25.30, is 8.00,
 *    which E-Banker's loan books and EMI chart hold at every change since. On
 *    SUPPLIED or AS_CAPTURED it is the MARKUP column, and a loan without one is
 *    held rather than guessed.
 *
 *  - When a change applies is the Governance key plr_mid_period: from the
 *    Reserve Bank's effective date (the default), the next month-end, or the
 *    next instalment. Two changes that land on the same date are one reset at
 *    the later rate.
 *
 * Re-running is safe. A period or event that has moved past its first state
 * (an EIR solved or locked, a schedule built) is never changed or removed;
 * where the new reading disagrees with it, the result says so.
 */
class ResetDetectionService
{
    public const STATUS_FLOATING = 'FLOATING';
    public const STATUS_FIXED = 'FIXED';
    public const STATUS_BLOCKED = 'BLOCKED';
    public const STATUS_OUT_OF_SCOPE = 'OUT_OF_SCOPE';

    public function __construct(private readonly GovernanceService $governance)
    {
    }

    /**
     * @return array{as_of:string, index_code:string, contracts:int, floating:int, fixed:int, blocked:int,
     *   out_of_scope:int, resets:int, details:array<string,array>}
     */
    public function detect(?string $contractId = null, ?CarbonInterface $asOf = null, string $index = 'PLR', ?int $userId = null): array
    {
        $asOf = CarbonImmutable::parse(($asOf ?? CarbonImmutable::today())->toDateString());
        $index = strtoupper(trim($index));

        $contracts = ContractEir::query()
            ->when($contractId, fn ($q) => $q->where('contract_id', $contractId))
            ->orderBy('contract_id')->get();

        $details = [];
        foreach ($contracts as $contract) {
            $details[$contract->contract_id] = $this->forContract($contract, $asOf, $index, $userId);
        }

        $count = fn (string $status) => count(array_filter($details, fn ($d) => $d['status'] === $status));
        $summary = [
            'as_of' => $asOf->toDateString(),
            'index_code' => $index,
            'contracts' => count($details),
            'floating' => $count(self::STATUS_FLOATING),
            'fixed' => $count(self::STATUS_FIXED),
            'blocked' => $count(self::STATUS_BLOCKED),
            'out_of_scope' => $count(self::STATUS_OUT_OF_SCOPE),
            'resets' => array_sum(array_map(fn ($d) => count($d['resets']), $details)),
        ];

        AuditLoggerService::log(action: 'EIR Reset Detection', entityType: 'ContractEir',
            data: ['meta' => $summary + ['contract_filter' => $contractId, 'user_id' => $userId]]);

        return $summary + ['details' => $details];
    }

    private function forContract(ContractEir $contract, CarbonImmutable $asOf, string $index, ?int $userId): array
    {
        $result = ['status' => null, 'reason' => null, 'rate_behaviour_source' => null, 'spread' => null,
            'spread_basis' => null, 'rate_at_origination' => null, 'reference_at_origination' => null,
            'resets' => [], 'periods' => 0, 'kept' => []];

        if (! $contract->isInEirScope()) {
            return ['status' => self::STATUS_OUT_OF_SCOPE, 'reason' => 'Equity-excluded instrument'] + $result;
        }
        if (! $contract->origination_date) {
            return ['status' => self::STATUS_BLOCKED, 'reason' => 'NO_ORIGINATION_DATE: the loan has no first disbursement date, so no period can start'] + $result;
        }
        if ($contract->contractual_rate === null || (float) $contract->contractual_rate <= 0) {
            return ['status' => self::STATUS_BLOCKED, 'reason' => 'NO_RATE: the loan has no rate at first disbursement'] + $result;
        }

        $origination = CarbonImmutable::parse($contract->origination_date->toDateString());
        $rate = (float) $contract->contractual_rate;
        $rate = $rate > 1 ? $rate / 100 : $rate;
        [$floating, $source] = $this->followsPrime($contract);
        $result['rate_behaviour_source'] = $source;
        $result['rate_at_origination'] = round($rate * 100, 5);

        if (! $floating) {
            $result['periods'] = $this->writePeriods($contract, [[
                'from' => $origination, 'reference' => null, 'spread' => null, 'basis' => null, 'rate' => $rate, 'event_id' => null,
            ]], $result['kept']);

            return ['status' => self::STATUS_FIXED] + $result;
        }

        $first = ReferenceRate::inForce($index, $origination);
        if ($first === null) {
            return ['status' => self::STATUS_BLOCKED, 'reason' => "NO_REFERENCE_RATE: the {$index} series starts after this loan's first disbursement on {$origination->toDateString()}"] + $result;
        }
        $result['reference_at_origination'] = round((float) $first->rate, 5);

        $basis = strtoupper(trim($this->governance->get('margin_basis', $origination)));
        $result['spread_basis'] = $basis;
        if ($basis === 'LIVE_AT_DRAWDOWN') {
            $spread = round($rate * 100 - (float) $first->rate, 5);
        } else {
            if ($contract->markup === null || $contract->markup === '') {
                return ['status' => self::STATUS_BLOCKED, 'reason' => "SPREAD_NOT_SUPPLIED: the spread is taken {$basis}, and this loan's contract master has no MARKUP"] + $result;
            }
            $markup = (float) $contract->markup;
            $spread = round($markup > 1 ? $markup : $markup * 100, 5);
        }
        $result['spread'] = $spread;

        // ---- the prime-rate changes since the loan was drawn ---------------
        $until = $contract->maturity_date && $contract->maturity_date->lt($asOf)
            ? CarbonImmutable::parse($contract->maturity_date->toDateString()) : $asOf;
        $timing = $this->governance->get('plr_mid_period', $origination);

        $changes = [];
        $previous = (float) $first->rate;
        $series = ReferenceRate::query()->where('index_code', $index)
            ->whereDate('effective_date', '>', $origination->toDateString())
            ->whereDate('effective_date', '<=', $until->toDateString())
            ->orderBy('effective_date')->get();
        foreach ($series as $row) {
            $new = (float) $row->rate;
            if (abs($new - $previous) < 0.000005) {
                continue;                          // a restatement of the same rate is not a change
            }
            $effective = CarbonImmutable::parse($row->effective_date->toDateString());
            $resetDate = $this->applies($effective, $timing, $contract);
            if ($resetDate === null) {
                $result['kept'][] = "the {$index} change of {$effective->toDateString()} has no version-1 instalment on or after it to take effect from (none generated, or past the last one), so it is not applied";
                $previous = $new;
                continue;
            }
            $key = $resetDate->toDateString();
            $old = isset($changes[$key]) ? $changes[$key]['old_reference'] : $previous;
            $changes[$key] = ['reset_date' => $resetDate, 'plr_effective_date' => $effective, 'old_reference' => $old, 'new_reference' => $new];
            $previous = $new;
        }
        // Two changes that land on one date, and back to where they started, are no reset.
        $changes = array_filter($changes, fn ($c) => abs($c['new_reference'] - $c['old_reference']) >= 0.000005);

        DB::transaction(function () use ($contract, $changes, $spread, $index, $userId, $first, $basis, $origination, $rate, &$result) {
            $eventIds = $this->writeEvents($contract, $changes, $spread, $index, $userId, $result['kept']);

            $periods = [['from' => $origination, 'reference' => (float) $first->rate, 'spread' => $spread,
                'basis' => $basis, 'rate' => $rate, 'event_id' => null]];
            foreach ($changes as $key => $c) {
                $periods[] = ['from' => $c['reset_date'], 'reference' => $c['new_reference'], 'spread' => $spread,
                    'basis' => $basis, 'rate' => round(($c['new_reference'] + $spread) / 100, 7), 'event_id' => $eventIds[$key] ?? null];
            }
            $result['periods'] = $this->writePeriods($contract, $periods, $result['kept']);
        });

        foreach ($changes as $c) {
            $result['resets'][] = [
                'reset_date' => $c['reset_date']->toDateString(),
                'plr_effective_date' => $c['plr_effective_date']->toDateString(),
                'old_reference' => $c['old_reference'],
                'new_reference' => $c['new_reference'],
                'new_rate_pct' => round($c['new_reference'] + $spread, 5),
            ];
        }

        return ['status' => self::STATUS_FLOATING] + $result;
    }

    /** @return array{0: bool, 1: string} whether the loan floats, and what said so */
    private function followsPrime(ContractEir $contract): array
    {
        $policy = strtoupper(trim((string) $contract->interest_policy));
        if ($policy !== '') {
            return [$policy === 'P', 'INTEREST_POLICY'];
        }
        if ($contract->reprice_flag !== null) {
            return [(bool) $contract->reprice_flag, 'REPRICE_FLAG'];
        }

        $family = strtoupper((string) $contract->portfolio . ' ' . (string) $contract->product_type);
        if (str_contains($family, 'FINES') || str_contains($family, 'TERM LOAN')) {
            return [false, 'PRODUCT_FAMILY'];
        }

        return [str_contains($family, 'MAIIC'), 'PRODUCT_FAMILY'];
    }

    /** The date a prime-rate change takes effect for this loan, under plr_mid_period. */
    private function applies(CarbonImmutable $effective, string $timing, ContractEir $contract): ?CarbonImmutable
    {
        $text = strtoupper($timing);
        if (str_contains($text, 'MONTH-END') || str_contains($text, 'MONTH END')) {
            return $effective->endOfMonth()->startOfDay();
        }
        if (str_contains($text, 'INSTALMENT') || str_contains($text, 'INSTALLMENT')) {
            $next = DB::table('contract_cashflow_schedule')->where('contract_id', $contract->contract_id)
                ->where('schedule_version', 1)->whereDate('due_date', '>=', $effective->toDateString())->min('due_date');

            return $next ? CarbonImmutable::parse(substr((string) $next, 0, 10)) : null;
        }

        return $effective;
    }

    /** @return array<string,int> reset date => event id */
    private function writeEvents(ContractEir $contract, array $changes, float $spread, string $index, ?int $userId, array &$kept): array
    {
        $existing = RateResetEvent::where('contract_id', $contract->contract_id)->get()
            ->keyBy(fn ($e) => $e->reset_date->toDateString());
        $ids = [];
        foreach ($changes as $key => $c) {
            $values = [
                'plr_effective_date' => $c['plr_effective_date']->toDateString(),
                'index_code' => $index,
                'old_reference_rate' => $c['old_reference'],
                'new_reference_rate' => $c['new_reference'],
                'spread_over_prime' => $spread,
                'old_contractual_rate' => round(($c['old_reference'] + $spread) / 100, 7),
                'new_contractual_rate' => round(($c['new_reference'] + $spread) / 100, 7),
            ];
            $event = $existing->get($key);
            if ($event && $event->status !== RateResetEvent::STATUS_DETECTED) {
                if (abs((float) $event->new_reference_rate - $c['new_reference']) >= 0.000005) {
                    $kept[] = "the reset of {$key} is already {$event->status} at {$event->new_reference_rate}; the series now reads {$c['new_reference']}";
                }
                $ids[$key] = $event->id;
                continue;
            }
            $event ??= new RateResetEvent(['contract_id' => $contract->contract_id, 'reset_date' => $key]);
            $event->fill($values + ['status' => RateResetEvent::STATUS_DETECTED, 'recorded_by' => $userId])->save();
            $ids[$key] = $event->id;
        }

        // A detected reset the series no longer supports goes; a processed one stays.
        RateResetEvent::where('contract_id', $contract->contract_id)->where('status', RateResetEvent::STATUS_DETECTED)
            ->get()->reject(fn ($e) => isset($changes[$e->reset_date->toDateString()]))->each->delete();

        return $ids;
    }

    /** @return int periods the loan now has */
    private function writePeriods(ContractEir $contract, array $periods, array &$kept): int
    {
        usort($periods, fn ($a, $b) => $a['from'] <=> $b['from']);
        $existing = EirRatePeriod::where('contract_id', $contract->contract_id)->get()
            ->keyBy(fn ($p) => $p->effective_from->toDateString());
        $wanted = [];

        foreach ($periods as $i => $p) {
            $key = $p['from']->toDateString();
            $wanted[$key] = true;
            $values = [
                'sub_account_no' => $contract->sub_account_no,
                'period_no' => $i + 1,
                'effective_to' => isset($periods[$i + 1]) ? $periods[$i + 1]['from']->subDay()->toDateString() : null,
                'reference_rate' => $p['reference'],
                'spread_over_prime' => $p['spread'],
                'spread_basis' => $p['basis'],
                'contractual_rate' => $p['rate'],
                'rate_reset_event_id' => $p['event_id'],
            ];
            $period = $existing->get($key);
            if ($period && $period->status !== EirRatePeriod::STATUS_PENDING_EIR) {
                if (abs((float) $period->contractual_rate - $p['rate']) >= 0.0000005) {
                    $kept[] = "period from {$key} is {$period->status} at " . round($period->contractual_rate * 100, 4) . '%; the series now gives ' . round($p['rate'] * 100, 4) . '%';
                }
                continue;
            }
            $period ??= new EirRatePeriod(['contract_id' => $contract->contract_id, 'effective_from' => $key]);
            $period->fill($values + ['status' => EirRatePeriod::STATUS_PENDING_EIR])->save();
        }

        EirRatePeriod::where('contract_id', $contract->contract_id)->where('status', EirRatePeriod::STATUS_PENDING_EIR)
            ->get()->reject(fn ($p) => isset($wanted[$p->effective_from->toDateString()]))->each->delete();

        return count($wanted);
    }
}
