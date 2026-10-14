<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IFRS 9 accounting for a confirmed restructure event, applied by the revenue
 * run in the event's month (specification v4 phases P6 and P7; built
 * 14 October 2026). Only an event a person classified and a different person
 * approved reaches it (RestructureRegisterService); a candidate never does.
 *
 * The roll-forward is month-end (decision D2): the event is applied to the
 * month's closing. The gross carrying amount before the event is the closing
 * the month would have had; the modified cash flows are schedule version N+1
 * (E-Banker's chart after the event) due after the month-end, discounted at the
 * EIR the month accrued at: the original EIR, as re-estimated under B5.4.5 for
 * a floating-rate loan (decision D38).
 *
 *  MODIFICATION (5.4.3). The modification test (3.3.2 and B3.3.6 by analogy,
 *    the threshold in force for modification_threshold, O16; review item R14
 *    records that the 10 percent test is written for liabilities, so it is
 *    MAIIC's asset policy, governed): the present value of the modified flows
 *    at the original EIR against the carrying amount before. Below the
 *    threshold and with no qualitative factor recorded, the loan is modified:
 *    the gross carrying amount becomes that present value and the difference
 *    is the modification gain or loss. Fees received (or costs incurred) on the
 *    modification adjust the carrying amount and are amortised over the
 *    remaining term through a revised EIR. At or above the threshold, or with
 *    a qualitative factor, the loan is derecognised (3.2.3) and version N+1 is
 *    recognised at its fair value (5.1.1), which the register must record,
 *    with a new EIR solved on the new flows; the difference is the
 *    derecognition gain or loss (IFRS 7.20A). A new asset that is credit-impaired
 *    when recognised is purchased or originated credit-impaired (5.5.13,
 *    B5.4.7) and needs a credit-adjusted EIR on its expected cash flows, which
 *    the engine does not hold: it fails closed.
 *  REVISED_ESTIMATE and a CONTRACTUAL_RESET of a fixed-rate loan (B5.4.6). The
 *    gross carrying amount becomes the present value of the revised flows at
 *    the original EIR; the difference is the catch-up, to profit or loss.
 *
 * Nothing is estimated: an event that cannot be applied stops the month with
 * the reason (ModificationBlockedException) and the row is marked BLOCKED.
 */
class ModificationAccountingService
{
    public function __construct(private ?GovernanceService $governance = null)
    {
        $this->governance ??= app(GovernanceService::class);
    }

    /** Whether the register's tables exist (a test schema without them never reaches this service). */
    public static function available(): bool
    {
        return Schema::hasTable('contract_modifications');
    }

    /**
     * Apply the confirmed events of a month to its closing.
     *
     * @return array{adjustment:float,closing:float,kind:string,version:int}|null
     * @throws ModificationBlockedException
     */
    public function apply(object $contract, string $period, float $closingBefore, float $eirAnnual, int $stage, string $dayCount = 'ACT/365'): ?array
    {
        if (! self::available()) {
            return null;
        }
        $rows = DB::table('contract_modifications')->where('contract_id', $contract->contract_id)->where('reporting_period', $period)
            ->where('status', '!=', 'REVERSED')->orderBy('new_schedule_version')->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $end = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->endOfMonth()->startOfDay();
        $closing = $closingBefore;
        $kinds = [];
        $version = null;
        foreach ($rows as $m) {
            $r = $this->one($m, $contract, $end, $closing, $eirAnnual, $stage, $dayCount);
            $closing = $r['closing'];
            $kinds[] = $r['kind'];
            $version = (int) $m->new_schedule_version;
        }

        return ['adjustment' => round($closing - $closingBefore, 2), 'closing' => $closing, 'kind' => implode('+', array_unique($kinds)), 'version' => $version];
    }

    /** @return array{closing:float,kind:string} */
    private function one(object $m, object $contract, CarbonImmutable $end, float $before, float $eir, int $stage, string $dayCount): array
    {
        $flows = DB::table('contract_cashflow_schedule')->where('contract_id', $m->contract_id)->where('schedule_version', $m->new_schedule_version)
            ->where('due_date', '>', $end->toDateString())->orderBy('due_date')->get(['due_date', 'principal_due', 'interest_due', 'fee_due'])
            ->map(fn ($f) => ['due_date' => substr((string) $f->due_date, 0, 10), 'amount' => (float) $f->principal_due + (float) $f->interest_due + (float) $f->fee_due])->all();
        if ($flows === []) {
            $this->block($m, "Schedule version {$m->new_schedule_version} has no cash flow due after {$end->toDateString()}: the modified flows cannot be valued.");
        }
        $pv = self::presentValue($flows, $end, $eir, $dayCount);
        $rateAfter = $m->rate_after ?? DB::table('loan_books')->where('contract_id', $m->contract_id)->where('reporting_period', $end->format('Y-m'))->orderByDesc('id')->value('interest_rate');
        $set = ['carrying_amount_before' => round($before, 2), 'pv_new_flows_at_original_eir' => round($pv, 2), 'eir_used' => round($eir, 8),
            'rate_after' => $rateAfter, 'poci' => false, 'blocked_reason' => null];

        if ($m->classification === 'MODIFICATION') {
            try {
                $threshold = self::thresholdOf($this->governance->get('modification_threshold', $end));
            } catch (\Throwable $e) {
                $this->block($m, 'The modification test needs the governed threshold (modification_threshold, O16): ' . $e->getMessage());
            }
            if ($threshold === null) {
                $this->block($m, 'The value in force for modification_threshold is not a percentage.');
            }
            $change = $before > 0 ? abs($pv - $before) / $before : null;
            $factors = json_decode((string) $m->qualitative_factors, true) ?: [];
            $set += ['change_pct' => $change === null ? null : round($change * 100, 6), 'threshold_pct' => round($threshold * 100, 4)];
            $derecognise = $change === null || $change >= $threshold || $factors !== [];
            if (! $derecognise) {
                $fees = (float) ($m->modification_fees ?? 0);
                $after = $pv - $fees;
                $revised = abs($fees) > 0.005 ? self::solve($flows, $end, $after, $dayCount) : null;
                if (abs($fees) > 0.005 && $revised === null) {
                    $this->block($m, 'The revised EIR after the modification fees could not be solved on the modified flows.');
                }

                return $this->done($m, $set + ['treatment' => 'MODIFICATION', 'gain_loss' => round($pv - $before, 2), 'carrying_amount_after' => round($after, 2), 'revised_eir' => $revised], $after, 'MODIFICATION');
            }
            $why = $change === null ? 'no carrying amount before the event' : sprintf('the present value moved %.2f percent against a threshold of %.2f percent', $change * 100, $threshold * 100);
            if ($factors !== []) {
                $why .= '; qualitative: ' . implode(', ', $factors);
            }
            if ($stage === 3) {
                DB::table('contract_modifications')->where('id', $m->id)->update(['poci' => true]);
                $this->block($m, "Derecognised ({$why}) while credit-impaired: the new asset is purchased or originated credit-impaired (IFRS 9 5.5.13, B5.4.7) and needs a credit-adjusted EIR on its expected cash flows, which the engine does not hold. Supply the expected cash flows, or reclassify the event.");
            }
            $fv = $m->fair_value !== null ? (float) $m->fair_value : null;
            if ($fv === null || $fv <= 0) {
                $this->block($m, "Derecognised ({$why}): the new asset (version {$m->new_schedule_version}) is recognised at its fair value (IFRS 9 5.1.1), and none is recorded on the register. Record the fair value with its basis.");
            }
            $newEir = self::solve($flows, $end, $fv, $dayCount);
            if ($newEir === null) {
                $this->block($m, "Derecognised ({$why}): no EIR discounts the new flows to the recorded fair value of " . number_format($fv, 2) . '.');
            }

            return $this->done($m, $set + ['treatment' => 'DERECOGNITION', 'gain_loss' => round($fv - $before, 2), 'carrying_amount_after' => round($fv, 2), 'revised_eir' => round($newEir, 8)], $fv, 'DERECOGNITION');
        }

        // B5.4.6: the revised estimated contractual flows at the original EIR; the difference is the catch-up
        return $this->done($m, $set + ['treatment' => 'CATCH_UP', 'gain_loss' => round($pv - $before, 2), 'carrying_amount_after' => round($pv, 2), 'revised_eir' => null, 'change_pct' => null], $pv, 'CATCH_UP');
    }

    /** @return array{closing:float,kind:string} */
    private function done(object $m, array $set, float $closing, string $kind): array
    {
        DB::table('contract_modifications')->where('id', $m->id)->update($set + ['status' => 'APPLIED', 'applied_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Restructure Applied', 'contract_modifications', (int) $m->id, ['reporting_period' => $m->reporting_period, 'new_values' => [
            'contract_id' => $m->contract_id, 'treatment' => $kind, 'carrying_amount_before' => $set['carrying_amount_before'], 'pv_new_flows' => $set['pv_new_flows_at_original_eir'],
            'gain_loss' => $set['gain_loss'], 'carrying_amount_after' => $set['carrying_amount_after'], 'eir_used' => $set['eir_used'], 'revised_eir' => $set['revised_eir'] ?? null,
            'change_pct' => $set['change_pct'] ?? null, 'threshold_pct' => $set['threshold_pct'] ?? null]]);

        return ['closing' => $closing, 'kind' => $kind];
    }

    /** @throws ModificationBlockedException */
    private function block(object $m, string $reason): never
    {
        throw new ModificationBlockedException((int) $m->id, "Restructure of {$m->modification_date} (schedule version {$m->new_schedule_version}): {$reason}");
    }

    /**
     * A month after a confirmed event that the revenue run has not booked would open on a
     * closing that ignores it: refused until the event's month is run (fail closed). An event
     * dated before the loan's first roll-forward month is outside the engine's history and
     * does not hold the later months.
     *
     * @throws \RuntimeException
     */
    public function assertBookedBefore(string $contractId, string $period): void
    {
        if (! self::available()) {
            return;
        }
        $first = DB::table('eir_amortisation')->where('contract_id', $contractId)->min('reporting_period');
        $q = DB::table('contract_modifications')->where('contract_id', $contractId)->whereIn('status', ['PENDING', 'BLOCKED'])->where('reporting_period', '<', $period);
        if ($first !== null) {
            $q->where('reporting_period', '>=', $first);
        }
        $m = $q->orderBy('reporting_period')->first();
        if ($m !== null) {
            throw new \RuntimeException("A confirmed restructure of {$m->reporting_period} (schedule version {$m->new_schedule_version}) is not booked yet"
                . ($m->status === 'BLOCKED' ? ' (blocked: ' . $m->blocked_reason . ')' : '') . ": re-run the EIR revenue from {$m->reporting_period}, with a reason, before {$period}.");
        }
    }

    /** Record a block outside the run's transaction, which rolls back. */
    public static function recordBlocked(ModificationBlockedException $e): void
    {
        if (self::available()) {
            DB::table('contract_modifications')->where('id', $e->modificationId)->where('status', '!=', 'REVERSED')
                ->update(['status' => 'BLOCKED', 'blocked_reason' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);
        }
    }

    /**
     * The EIR basis after the events applied before a month (decision D38 continues on it):
     * the EIR to accrue at when a derecognition or the fees of a modification set a new one,
     * the rate that EIR was set at, and the change in the contractual rate that a modification
     * kept inside the original EIR (so the monthly re-estimation does not move the EIR for it).
     *
     * @return array{eir:?float,built:?float,shift:float}|null
     */
    public function basisBefore(string $contractId, string $period): ?array
    {
        if (! self::available()) {
            return null;
        }
        $rows = DB::table('contract_modifications')->where('contract_id', $contractId)->where('status', 'APPLIED')->where('reporting_period', '<', $period)
            ->orderBy('new_schedule_version')->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $eir = null; $built = null; $shift = 0.0;
        foreach ($rows as $m) {
            if ($m->revised_eir !== null && in_array($m->treatment, ['DERECOGNITION', 'MODIFICATION'], true)) {
                $eir = (float) $m->revised_eir;
                $built = $m->rate_after !== null ? (float) $m->rate_after / 100 : $built;
                $shift = 0.0;
            } elseif ($m->treatment === 'MODIFICATION' && $m->rate_before !== null && $m->rate_after !== null) {
                $shift += ((float) $m->rate_after - (float) $m->rate_before) / 100;
            }
        }

        return ['eir' => $eir, 'built' => $built, 'shift' => $shift];
    }

    /** The schedule version in force on a date: the latest confirmed version effective by then, else version 1. */
    public static function versionInForce(string $contractId, string $date): int
    {
        if (! self::available() || ! Schema::hasColumn('contract_cashflow_schedule', 'effective_from')) {
            return 1;
        }
        $v = DB::table('contract_modifications')->where('contract_id', $contractId)->where('status', '!=', 'REVERSED')
            ->where('modification_date', '<=', $date)->max('new_schedule_version');

        return $v === null ? 1 : (int) $v;
    }

    /** '10 percent' and its alternatives, as a fraction. */
    public static function thresholdOf(string $value): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*percent/i', $value, $m) === 1 ? (float) $m[1] / 100 : null;
    }

    /** The present value at a date of dated flows due after it, at an effective annual rate. */
    public static function presentValue(array $flows, CarbonImmutable $asOf, float $annual, string $dayCount = 'ACT/365'): float
    {
        $pv = 0.0;
        foreach ($flows as $f) {
            $due = CarbonImmutable::parse($f['due_date']);
            if ($due->lessThanOrEqualTo($asOf)) {
                continue;
            }
            $pv += (float) $f['amount'] / pow(1 + $annual, self::years($asOf, $due, $dayCount));
        }

        return $pv;
    }

    /** The effective annual rate that discounts the flows to the amount, or null (bisection; present value falls as the rate rises). */
    public static function solve(array $flows, CarbonImmutable $asOf, float $amount, string $dayCount = 'ACT/365'): ?float
    {
        if ($amount <= 0 || $flows === []) {
            return null;
        }
        $lo = -0.99; $hi = 10.0;
        $f = fn ($r) => self::presentValue($flows, $asOf, $r, $dayCount) - $amount;
        if ($f($lo) < 0 || $f($hi) > 0) {
            return null;
        }
        for ($i = 0; $i < 200; $i++) {
            $mid = ($lo + $hi) / 2;
            if ($f($mid) > 0) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
            if ($hi - $lo < 1e-12) {
                break;
            }
        }

        return ($lo + $hi) / 2;
    }

    private static function years(CarbonImmutable $from, CarbonImmutable $to, string $basis): float
    {
        $basis = strtoupper(str_replace([' ', '_'], ['', '/'], $basis));
        if (in_array($basis, ['30/360', '30E/360'], true)) {
            $days = (($to->year - $from->year) * 360) + (($to->month - $from->month) * 30) + (min(30, $to->day) - min(30, $from->day));

            return max(0, $days / 360);
        }

        return max(0, $from->diffInDays($to) / 365);
    }
}
