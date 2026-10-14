<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\EirRatePeriod;
use App\Models\RateResetEvent;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Phase P5, step 3: a Reset version of the schedule at each prime-rate change.
 *
 * At a reset the remaining instalments are re-estimated at the new rate (IFRS
 * 9 B5.4.5). Each reset is written as its own schedule version, typed RESET
 * and dated from the reset; version 1 and every earlier version are left as
 * they are, because a past month is never restated.
 *
 * The rule is E-Banker's own, read off JAT Group's EMI chart, where it
 * reproduces two regenerations to the tambala: the principal still owed
 * after the last instalment before the reset is re-amortised at the new rate,
 * over the instalments left to the original maturity, with a period longer
 * than a month taken on the Governance Centre's period_rate_basis. On JAT the
 * 1 Oct 2025 reset gives 28,624,982 (chart 28,624,982) and the 1 Nov 2025
 * reset, from the chart's own balance, 29,239,220 (chart 29,239,220). From
 * April 2026 the chart re-sizes on a simple quarterly rate over a term cut to
 * January 2028; that is not followed here, and the comparison names the gap.
 *
 * Each reset builds on the version before it, so a reset that falls between
 * two instalments of an earlier reset version starts from that version's
 * balance. A version already used by a solved or locked period is kept and
 * chained from; everything else is rebuilt on each run, so it follows version
 * 1 when a draft is regenerated.
 */
class ResetScheduleService
{
    public const TYPE_ORIGINAL = 'ORIGINAL';
    public const TYPE_RESET = 'RESET';

    public function __construct(private readonly ScheduleGeneratorService $generator)
    {
    }

    /** @return array{contracts:int, built:int, kept:int, blocked:int, details:array<string,array>} */
    public function build(?string $contractId = null, ?int $userId = null): array
    {
        $ids = RateResetEvent::query()->when($contractId, fn ($q) => $q->where('contract_id', $contractId))
            ->distinct()->orderBy('contract_id')->pluck('contract_id');

        $details = [];
        foreach ($ids as $id) {
            $contract = ContractEir::where('contract_id', $id)->first();
            $details[$id] = $contract
                ? $this->forContract($contract)
                : ['status' => 'BLOCKED', 'reason' => 'the loan is no longer in the contract master', 'versions' => []];
        }

        $versions = array_merge(...array_values(array_map(fn ($d) => $d['versions'], $details)) ?: [[]]);
        $summary = [
            'contracts' => count($details),
            'built' => count(array_filter($versions, fn ($v) => $v['state'] === 'BUILT')),
            'kept' => count(array_filter($versions, fn ($v) => $v['state'] === 'KEPT')),
            'blocked' => count(array_filter($versions, fn ($v) => $v['state'] === 'BLOCKED')),
        ];
        AuditLoggerService::log(action: 'EIR Reset Schedules', entityType: 'ContractEir',
            data: ['meta' => $summary + ['contract_filter' => $contractId, 'user_id' => $userId]]);

        return $summary + ['details' => $details];
    }

    /** Remove every Reset version of a loan, as when version 1 is regenerated. */
    public function discard(string $contractId): void
    {
        DB::transaction(function () use ($contractId) {
            DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)
                ->where('schedule_type', self::TYPE_RESET)->delete();
            RateResetEvent::where('contract_id', $contractId)->where('status', RateResetEvent::STATUS_SCHEDULED)
                ->update(['status' => RateResetEvent::STATUS_DETECTED, 'new_schedule_version' => null, 'note' => null]);
            EirRatePeriod::where('contract_id', $contractId)->where('status', EirRatePeriod::STATUS_PENDING_EIR)
                ->update(['schedule_version' => null]);
        });
    }

    private function forContract(ContractEir $contract): array
    {
        $original = $this->rows($contract->contract_id, 1);
        if ($original === []) {
            return ['status' => 'BLOCKED', 'reason' => 'NO_VERSION_1: generate or import the original schedule first', 'versions' => []];
        }

        $versions = [];
        DB::transaction(function () use ($contract, $original, &$versions) {
            EirRatePeriod::where('contract_id', $contract->contract_id)->where('period_no', 1)
                ->where('status', EirRatePeriod::STATUS_PENDING_EIR)->update(['schedule_version' => 1]);

            $previous = $original;
            $events = RateResetEvent::where('contract_id', $contract->contract_id)->orderBy('reset_date')->get();

            // Every Reset version not used by a solved period is rebuilt and
            // renumbered in date order, so the same resets always get the
            // same numbers and a reset added in between cannot collide.
            $keptVersions = $events->filter(fn ($e) => $e->status === RateResetEvent::STATUS_SOLVED && $e->new_schedule_version)
                ->pluck('new_schedule_version')->map(fn ($v) => (int) $v)->all();
            DB::table('contract_cashflow_schedule')->where('contract_id', $contract->contract_id)
                ->where('schedule_type', self::TYPE_RESET)->whereNotIn('schedule_version', $keptVersions ?: [0])->delete();
            foreach ($events as $e) {
                if (! in_array((int) $e->new_schedule_version, $keptVersions, true)) {
                    $e->new_schedule_version = null;
                }
            }

            foreach ($events as $event) {
                $date = CarbonImmutable::parse($event->reset_date->toDateString());

                if ($event->status === RateResetEvent::STATUS_SOLVED && $event->new_schedule_version) {
                    $previous = $this->rows($contract->contract_id, $event->new_schedule_version) ?: $previous;
                    $keptVersions[] = $event->new_schedule_version;
                    $versions[] = $this->line($event, 'KEPT', 'used by a solved period');
                    continue;
                }

                try {
                    [$rows, $meta] = $this->resetRows($contract, $original, $previous, $date, (float) $event->new_contractual_rate);
                } catch (Throwable $e) {
                    $event->update(['status' => RateResetEvent::STATUS_BLOCKED, 'note' => $e->getMessage()]);
                    $versions[] = $this->line($event, 'BLOCKED', $e->getMessage());
                    continue;
                }

                $version = $this->nextVersion($contract->contract_id, $keptVersions);
                $now = now();
                DB::table('contract_cashflow_schedule')->insert(array_map(fn ($r) => [
                    'contract_id' => $contract->contract_id,
                    'schedule_version' => $version,
                    'schedule_type' => self::TYPE_RESET,
                    'effective_from' => $date->toDateString(),
                    'due_date' => $r['due_date'],
                    'principal_due' => $r['principal_due'],
                    'interest_due' => $r['interest_due'],
                    'fee_due' => 0,
                    'schedule_source' => 'GENERATED',
                    'source_system' => 'EIR_ENGINE',
                    'source_reference' => "Reset of {$date->toDateString()} to " . round($event->new_contractual_rate * 100, 4) . '%',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $rows));

                $event->update(['status' => RateResetEvent::STATUS_SCHEDULED, 'new_schedule_version' => $version,
                    'note' => sprintf('%d instalment(s) of %s from %s on a balance of %s',
                        count($rows), number_format($meta['instalment'], 2), $rows[0]['due_date'], number_format($meta['balance'], 2))]);
                EirRatePeriod::where('contract_id', $contract->contract_id)->where('rate_reset_event_id', $event->id)
                    ->where('status', EirRatePeriod::STATUS_PENDING_EIR)->update(['schedule_version' => $version]);
                $keptVersions[] = $version;
                $versions[] = $this->line($event->fresh(), 'BUILT', null) + $meta;
                $previous = $this->rows($contract->contract_id, $version);
            }
        });

        return ['status' => 'OK', 'reason' => null, 'versions' => $versions];
    }

    /**
     * The remaining instalments re-amortised at the new rate.
     *
     * @return array{0: list<array{due_date:string, principal_due:float, interest_due:float}>, 1: array}
     */
    private function resetRows(ContractEir $contract, array $original, array $previous, CarbonImmutable $date, float $newRate): array
    {
        // A Reset version holds only the instalments after its reset, so the
        // last instalment before this one is read off version 1, whose due
        // dates every later version shares.
        $paid = array_values(array_filter($original, fn ($r) => $r->due_date < $date->toDateString()));
        $left = array_values(array_filter($previous, fn ($r) => $r->due_date >= $date->toDateString()));
        if ($left === []) {
            throw new InvalidArgumentException('NO_INSTALMENTS_LEFT: the reset falls after the last instalment');
        }
        $balance = round(array_sum(array_map(fn ($r) => (float) $r->principal_due, $left)), 2);
        if ($balance <= 0.0) {
            throw new InvalidArgumentException('NOTHING_OWED: no principal is left to re-amortise at the reset');
        }
        if ($paid === [] && (int) $contract->moratorium_months > 0) {
            throw new InvalidArgumentException('RESET_IN_MORATORIUM: a reset before the first instalment of a loan with a moratorium is not built yet');
        }

        $start = $paid === [] ? CarbonImmutable::parse($contract->origination_date->toDateString())
            : CarbonImmutable::parse(end($paid)->due_date);
        $result = $this->generator->generate([
            'principal' => $balance,
            'approved_amount' => $balance,
            'annual_rate' => $newRate,
            'payments_per_year' => (int) $contract->payments_per_year ?: 12,
            'n_payments' => count($left),
            'start_date' => $start->toDateString(),
            'interest_start_date' => $start->toDateString(),
            'first_due_date' => $left[0]->due_date,
            'moratorium_months' => 0,
            'emi_calc_type' => $contract->schedule_emi_calc_type ?: ($contract->emi_calc_type ?: 'E'),
            'interest_calc_base' => 'B',
            'installment_based_on' => 'DISBURSEMENT',
            'as_of' => $date->toDateString(),
        ]);

        $rows = array_map(fn ($r) => ['due_date' => $r['due_date'], 'principal_due' => $r['principal_due'],
            'interest_due' => $r['interest_due']], $result['rows']);

        return [$rows, ['balance' => $balance, 'instalment' => $result['instalment'], 'instalments' => count($rows),
            'from' => $start->toDateString(), 'rate_pct' => round($newRate * 100, 5)]];
    }

    private function rows(string $contractId, int $version): array
    {
        return DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)
            ->where('schedule_version', $version)->orderBy('due_date')->get()
            ->map(function ($r) { $r->due_date = substr((string) $r->due_date, 0, 10); return $r; })->all();
    }

    private function nextVersion(string $contractId, array $taken): int
    {
        $max = (int) DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->max('schedule_version');

        return max([$max, ...$taken, 1]) + 1;
    }

    private function line(RateResetEvent $event, string $state, ?string $reason): array
    {
        return ['reset_date' => $event->reset_date->toDateString(), 'rate_pct' => round((float) $event->new_contractual_rate * 100, 5),
            'version' => $event->new_schedule_version, 'state' => $state, 'reason' => $reason];
    }
}
