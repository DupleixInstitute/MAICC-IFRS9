<?php

namespace App\Services\Fli;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The manual-overlay register (spec v4 sections 14.6 and 15.7).
 *
 * An overlay is judgement added to the forward-looking adjustment when no
 * model is approvable or when an event the statistics cannot see has to be
 * reflected now. It is a register entry, not a free field: scope (the book,
 * a product group or one contract), a signed fraction on the PD, a reason,
 * the evidence, an owner, an expiry period, a proposer and a different
 * approver. The register is tied to the scenario set of the period: an
 * overlay needs an approved set when the rule says so, and a set cannot be
 * locked while an overlay against it is still proposed. The ECL shows each
 * overlay as its own line, which is how the auditor and the Board see what
 * judgement added.
 *
 * Built for the system audit of 9 October 2026, finding M3.
 */
class OverlayService
{
    public const SCOPES = ['book', 'product_group', 'contract'];

    public const STATUSES = ['PROPOSED', 'APPROVED', 'REJECTED', 'EXPIRED'];

    public function __construct(private ScenarioSetService $sets)
    {
    }

    // ----- the lifecycle ------------------------------------------------------

    /**
     * A reviewer proposes an overlay; a second person approves it.
     *
     * @param array{reporting_period:string,scope:string,scope_value?:?string,adjustment:float|string,reason:string,evidence?:?string,owner_id?:?int,expiry_period?:?string} $data
     */
    public function propose(array $data, ?int $userId): int
    {
        $period = (string) ($data['reporting_period'] ?? '');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new RuntimeException('An overlay needs a reporting period in the form YYYY-MM.');
        }
        $scope = (string) ($data['scope'] ?? '');
        if (! in_array($scope, self::SCOPES, true)) {
            throw new RuntimeException('The scope must be the book, a product group or a contract.');
        }
        $scopeValue = $scope === 'book' ? null : trim((string) ($data['scope_value'] ?? ''));
        if ($scope !== 'book' && $scopeValue === '') {
            throw new RuntimeException("An overlay on a {$scope} must name which one.");
        }
        if (! is_numeric($data['adjustment'] ?? null)) {
            throw new RuntimeException('The adjustment must be a number: a signed fraction on the PD, 0.15 for the PD times 1.15.');
        }
        $adjustment = (float) $data['adjustment'];
        if ($adjustment <= -1) {
            throw new RuntimeException('An adjustment at or below minus one would take the PD to zero or below.');
        }
        if (trim((string) ($data['reason'] ?? '')) === '') {
            throw new RuntimeException('An overlay needs a reason.');
        }
        $expiry = (string) ($data['expiry_period'] ?? $period);
        if (! preg_match('/^\d{4}-\d{2}$/', $expiry) || $expiry < $period) {
            throw new RuntimeException('The expiry period must be a month no earlier than the reporting period.');
        }
        $set = $this->approvedSet($period);
        if ($set === null && $this->sets->rules($period)['overlay_needs_set']) {
            throw new RuntimeException("An overlay needs an approved scenario set for {$period} (the rule of spec 15.6); approve the set first.");
        }
        $id = (int) DB::table('fli_overlays')->insertGetId([
            'reporting_period' => $period, 'scope' => $scope, 'scope_value' => $scopeValue, 'adjustment' => round($adjustment, 8),
            'reason' => trim((string) $data['reason']), 'evidence' => isset($data['evidence']) && trim((string) $data['evidence']) !== '' ? trim((string) $data['evidence']) : null,
            'owner_id' => $data['owner_id'] ?? $userId, 'expiry_period' => $expiry, 'status' => 'PROPOSED', 'set_id' => $set?->id,
            'proposed_by' => $userId, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('FLI Overlay Proposed', 'fli_overlays', $id, ['reporting_period' => $period, 'new_values' => ['scope' => $scope, 'scope_value' => $scopeValue, 'adjustment' => $adjustment, 'expiry_period' => $expiry, 'set_id' => $set?->id], 'meta' => ['user' => $userId]]);

        return $id;
    }

    public function approve(int $overlayId, ?int $approverId, ?string $label = null): void
    {
        $o = $this->find($overlayId);
        if ($o->status !== 'PROPOSED') {
            throw new RuntimeException("Overlay {$overlayId} is {$o->status}, not proposed.");
        }
        if ($label !== LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $o->proposed_by === $approverId) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the proposer.');
        }
        DB::table('fli_overlays')->where('id', $overlayId)->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('FLI Overlay Approved', 'fli_overlays', $overlayId, ['reporting_period' => $o->reporting_period, 'meta' => ['approved_by' => $approverId, 'label' => $label]]);
    }

    public function reject(int $overlayId, ?int $userId, string $reason): void
    {
        $o = $this->find($overlayId);
        if ($o->status !== 'PROPOSED') {
            throw new RuntimeException("Overlay {$overlayId} is {$o->status}, not proposed.");
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A rejection needs a reason.');
        }
        DB::table('fli_overlays')->where('id', $overlayId)->update(['status' => 'REJECTED', 'rejected_by' => $userId, 'rejected_at' => now(), 'rejected_reason' => trim($reason), 'updated_at' => now()]);
        AuditLoggerService::log('FLI Overlay Rejected', 'fli_overlays', $overlayId, ['reporting_period' => $o->reporting_period, 'meta' => ['user' => $userId, 'reason' => $reason]]);
    }

    /** An approved overlay withdrawn before its expiry period, with the reason. */
    public function expire(int $overlayId, ?int $userId, ?string $reason = null): void
    {
        $o = $this->find($overlayId);
        if ($o->status !== 'APPROVED') {
            throw new RuntimeException("Overlay {$overlayId} is {$o->status}; only an approved overlay expires.");
        }
        DB::table('fli_overlays')->where('id', $overlayId)->update(['status' => 'EXPIRED', 'expired_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('FLI Overlay Expired', 'fli_overlays', $overlayId, ['reporting_period' => $o->reporting_period, 'meta' => ['user' => $userId, 'reason' => $reason]]);
    }

    /** Approved overlays whose expiry period is before the period named are marked expired; the count. */
    public function sweepExpired(string $period): int
    {
        $ids = DB::table('fli_overlays')->where('status', 'APPROVED')->where('expiry_period', '<', $period)->pluck('id')->all();
        foreach ($ids as $id) {
            DB::table('fli_overlays')->where('id', $id)->update(['status' => 'EXPIRED', 'expired_at' => now(), 'updated_at' => now()]);
            AuditLoggerService::log('FLI Overlay Expired', 'fli_overlays', (int) $id, ['reporting_period' => $period, 'meta' => ['reason' => "expiry period passed before {$period}"]]);
        }

        return count($ids);
    }

    // ----- what is in force ----------------------------------------------------

    /** The approved overlays in force for a period: proposed for it or earlier, not yet past their expiry. */
    public function inForce(string $period): Collection
    {
        return DB::table('fli_overlays')->where('status', 'APPROVED')->where('reporting_period', '<=', $period)->where('expiry_period', '>=', $period)->orderBy('id')->get();
    }

    /** Whether an overlay's scope covers a loan row (contract_id, product_group). */
    public function covers(object $overlay, object $loan): bool
    {
        return match ($overlay->scope) {
            'book' => true,
            'product_group' => (string) ($loan->product_group ?? '') === (string) $overlay->scope_value,
            'contract' => (string) ($loan->contract_id ?? '') === (string) $overlay->scope_value,
            default => false,
        };
    }

    /**
     * The overlays of a period for the register screen: every status, newest
     * first, each with its ECL line.
     */
    public function register(string $period): Collection
    {
        $loans = $this->loansOf($period);

        return DB::table('fli_overlays as o')->leftJoin('users as p', 'p.id', '=', 'o.proposed_by')->leftJoin('users as a', 'a.id', '=', 'o.approved_by')->leftJoin('users as w', 'w.id', '=', 'o.owner_id')->leftJoin('users as r', 'r.id', '=', 'o.rejected_by')
            ->where('o.reporting_period', '<=', $period)->where('o.expiry_period', '>=', $period)->orderByDesc('o.id')
            ->get(['o.*', 'p.name as proposer', 'a.name as approver', 'w.name as owner', 'r.name as rejecter'])
            ->map(function ($o) use ($loans, $period) {
                $line = $this->eclLine($o, $loans);
                $o->ecl_line = $line['amount'];
                $o->loans_in_scope = $line['loans'];
                $o->in_force = $o->status === 'APPROVED' && $o->reporting_period <= $period && $o->expiry_period >= $period;

                return $o;
            });
    }

    // ----- the ECL line ---------------------------------------------------------

    /**
     * The loans of a period as the ECL line reads them: the stage the staging
     * engine measured, the pre-FLI PD, the booked PD and ECL, the remaining
     * months, the scope columns. The same selection the sensitivity makes.
     */
    public function loansOf(string $period): Collection
    {
        return DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw('id, contract_id, product_group, coalesce(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) as stage, coalesce(pd_prefli, pd_value, `12m_pd`) as pd_prefli, pd_post_fli, ecl_value, remaining_tenor')
            ->whereRaw('coalesce(pd_prefli, pd_value, `12m_pd`) is not null')->whereNotNull('ecl_value')->get();
    }

    /**
     * What one overlay adds to the ECL, as its own line: over the loans in
     * its scope, the booked ECL scaled by the ratio of stage PDs with and
     * without the overlay, stagePd(pd x (1 + adjustment)) / stagePd(pd) - 1,
     * under the same stage-PD rule as the sensitivity (lifetime for Stage 2,
     * 1 for Stage 3, so a Stage 3 loan adds nothing). The PD the overlay
     * moves is the pre-FLI PD; where the booked ECL already carries a
     * post-FLI PD it is first re-based to the pre-FLI PD by the same ratio,
     * so the line is the overlay's own contribution and not the route's.
     *
     * @return array{amount:float,loans:int}
     */
    public function eclLine(object $overlay, Collection $loans): array
    {
        $adj = (float) $overlay->adjustment;
        $amount = 0.0; $n = 0;
        foreach ($loans as $l) {
            if (! $this->covers($overlay, $l) || $l->ecl_value === null) {
                continue;
            }
            $n++;
            $pre = (float) $l->pd_prefli;
            $booked = $l->pd_post_fli !== null ? (float) $l->pd_post_fli : $pre;
            $bookedStage = ScenarioSetService::stagePd($l, $booked);
            $preStage = ScenarioSetService::stagePd($l, $pre);
            if ($bookedStage <= 0 || $preStage <= 0) {
                continue;
            }
            $eclAtPre = (float) $l->ecl_value * $preStage / $bookedStage;
            $amount += $eclAtPre * (ScenarioSetService::stagePd($l, $pre * (1 + $adj)) / $preStage - 1);
        }

        return ['amount' => round($amount, 2), 'loans' => $n];
    }

    /** The ECL line of every overlay in force for a period, and their total. */
    public function eclLines(string $period): array
    {
        $loans = $this->loansOf($period);
        $lines = [];
        foreach ($this->inForce($period) as $o) {
            $line = $this->eclLine($o, $loans);
            $lines[] = ['overlay_id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment, 'reason' => $o->reason, 'loans' => $line['loans'], 'ecl' => $line['amount']];
        }

        return ['period' => $period, 'lines' => $lines, 'total' => round(array_sum(array_column($lines, 'ecl')), 2)];
    }

    // ----- helpers ---------------------------------------------------------------

    public function find(int $overlayId): object
    {
        return DB::table('fli_overlays')->where('id', $overlayId)->first() ?? throw new RuntimeException("No overlay {$overlayId}.");
    }

    private function approvedSet(string $period): ?object
    {
        return DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
    }
}
