<?php

namespace App\Services\Eir;

use App\Models\StagingThreshold;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Maker-checker on the days-past-due thresholds (system audit of 9 October
 * 2026, finding H11). A threshold row is proposed by one person and takes no
 * effect until a different person approves it, the same rule the Governance
 * Centre applies to every setting. A change applies forward only: its
 * effective date must be later than the last approved row for the same class
 * and tenor, and after the last locked reporting period, so no governed or
 * locked month is restated. Staging reads only approved rows
 * (StagingThreshold::forFacility).
 */
class StagingThresholdService
{
    /** @param array{facility_class:string,min_tenor_months:int,stage2_dpd:int,stage3_dpd:int,effective_from:string,reason:string,rebuttal_basis?:?string} $data */
    public function propose(array $data, ?int $userId): StagingThreshold
    {
        if ($userId === null) {
            throw new InvalidArgumentException('A threshold is proposed by a named person.');
        }
        $class = strtoupper(trim((string) ($data['facility_class'] ?? '')));
        if (preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $class) !== 1) {
            throw new InvalidArgumentException('The facility class is a code of capital letters, digits and underscores (DEFAULT, LONG_TERM, MEGA_FARM).');
        }
        $tenor = (int) ($data['min_tenor_months'] ?? -1);
        $s2 = (int) ($data['stage2_dpd'] ?? 0);
        $s3 = (int) ($data['stage3_dpd'] ?? 0);
        if ($tenor < 0) {
            throw new InvalidArgumentException('The minimum tenor is zero or more months.');
        }
        if ($s2 < 1 || $s3 <= $s2) {
            throw new InvalidArgumentException('Stage 2 starts at one day or more and Stage 3 starts later than Stage 2.');
        }
        $rebuttal = trim((string) ($data['rebuttal_basis'] ?? ''));
        if ($s2 > 31 && $rebuttal === '') {
            // IFRS 9 5.5.11 and B5.5.37: more than 30 days past due (Stage 2 from day 31) is presumed
            // significant; a later Stage 2 trigger rebuts that presumption and needs its documented evidence
            throw new InvalidArgumentException('Stage 2 from later than 31 days past due rebuts the IFRS 9 presumption (B5.5.37): state the evidence in the rebuttal basis.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A change needs a specific reason of at least 10 characters.');
        }
        $effective = $this->date((string) ($data['effective_from'] ?? ''));
        $this->assertForwardOnly($class, $tenor, $effective, null);

        return DB::transaction(function () use ($class, $tenor, $s2, $s3, $rebuttal, $effective, $reason, $userId) {
            $row = StagingThreshold::create([
                'facility_class' => $class, 'min_tenor_months' => $tenor, 'stage2_dpd' => $s2, 'stage3_dpd' => $s3,
                'rebuttal_basis' => $rebuttal === '' ? null : $rebuttal, 'effective_from' => $effective->toDateString(),
                'status' => StagingThreshold::STATUS_PROPOSED, 'proposed_by' => $userId, 'reason' => mb_substr($reason, 0, 500),
            ]);
            AuditLoggerService::log('Staging Threshold Proposed', 'staging_thresholds', $row->id, ['new_values' => $row->only(['facility_class', 'min_tenor_months', 'stage2_dpd', 'stage3_dpd', 'effective_from', 'rebuttal_basis', 'reason']), 'meta' => ['proposed_by' => $userId]]);

            return $row;
        });
    }

    public function approve(int $id, ?int $approverId): StagingThreshold
    {
        return DB::transaction(function () use ($id, $approverId) {
            $row = StagingThreshold::query()->lockForUpdate()->findOrFail($id);
            if ($row->status !== StagingThreshold::STATUS_PROPOSED) {
                throw new LogicException('Only a proposed threshold can be approved.');
            }
            if ($approverId === null || $row->proposed_by === null || (int) $row->proposed_by === $approverId) {
                throw new LogicException('Maker-checker: the approver must be a different person from the proposer, and both must be named.');
            }
            $effective = CarbonImmutable::parse($row->effective_from->toDateString());
            $this->assertForwardOnly($row->facility_class, (int) $row->min_tenor_months, $effective, $row->id);
            $row->update(['status' => StagingThreshold::STATUS_APPROVED, 'approved_by' => $approverId, 'approved_at' => now()]);
            AuditLoggerService::log('Staging Threshold Approved', 'staging_thresholds', $row->id, ['new_values' => $row->only(['facility_class', 'min_tenor_months', 'stage2_dpd', 'stage3_dpd', 'effective_from']), 'meta' => ['proposed_by' => $row->proposed_by, 'approved_by' => $approverId]]);

            return $row->fresh();
        });
    }

    public function reject(int $id, ?int $userId, string $reason): StagingThreshold
    {
        $reason = trim($reason);
        if ($userId === null || mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A rejection is made by a named person with a reason of at least 10 characters.');
        }
        $row = StagingThreshold::query()->findOrFail($id);
        if ($row->status !== StagingThreshold::STATUS_PROPOSED) {
            throw new LogicException('Only a proposed threshold can be rejected.');
        }
        $row->update(['status' => StagingThreshold::STATUS_REJECTED, 'approved_by' => $userId, 'approved_at' => now(), 'reason' => mb_substr($row->reason . ' | rejected: ' . $reason, 0, 500)]);
        AuditLoggerService::log('Staging Threshold Rejected', 'staging_thresholds', $row->id, ['meta' => ['rejected_by' => $userId, 'reason' => $reason]]);

        return $row->fresh();
    }

    /**
     * Forward only: later than the last approved row for the class and tenor
     * (a governed month keeps its rule) and after the last locked period.
     */
    private function assertForwardOnly(string $class, int $tenor, CarbonImmutable $effective, ?int $ignoreId): void
    {
        $latest = StagingThreshold::query()->where('facility_class', $class)->where('min_tenor_months', $tenor)
            ->where('status', StagingThreshold::STATUS_APPROVED)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->max('effective_from');
        if ($latest !== null && $effective->toDateString() <= CarbonImmutable::parse($latest)->toDateString()) {
            throw new LogicException("The effective date must be later than {$latest}, the last approved threshold for {$class} from {$tenor} months. A change never restates a governed month.");
        }
        if (DB::getSchemaBuilder()->hasTable('reporting_period_locks')) {
            $locked = DB::table('reporting_period_locks')->max('reporting_period');
            if ($locked !== null && $effective->format('Y-m') <= $locked) {
                throw new LogicException("Reporting period {$locked} is locked; a threshold change takes effect after it.");
            }
        }
    }

    private function date(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new InvalidArgumentException('The effective date must be written as yyyy-mm-dd.');
        }

        return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
    }
}
