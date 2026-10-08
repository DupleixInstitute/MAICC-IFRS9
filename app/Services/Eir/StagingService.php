<?php

namespace App\Services\Eir;

use App\Models\StagingThreshold;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\LandingZoneReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stages a built loan book for a period (spec v4 section 3.6, decision D31;
 * engine step 3 of 6.10.1).
 *
 * Two triggers, both governed:
 *   days past due    counted on the governed basis (dpd_basis) by the build
 *                    and compared with the staging_thresholds row for the
 *                    facility class and tenor: the directive's 91 days for
 *                    short-term and Mega Farm facilities, 181 for medium and
 *                    long term, Stage 2 from 31 in every class
 *   missed instalments  the accounting policy's default trigger (note 22.8.1):
 *                    N consecutive instalments unpaid, read from the instalment
 *                    chart (P2_10) against the receipts in the ledger allocated
 *                    in due-date order
 *
 * The DPD stage is written to ifrs9stage_pre_qualitative; the stage after the
 * missed-instalment trigger and any SICR flag already on the row goes to
 * ifrs9stage_post_qualitative. A row never moves down by the trigger alone.
 */
class StagingService
{
    public function __construct(private GovernanceService $governance, private LandingZoneReader $zone)
    {
    }

    /** @return array{period:string,rows:int,stage1:int,stage2:int,stage3:int,by_dpd:int,by_instalments:int,by_sicr:int,by_bucket:int} */
    public function stage(string $period, ?int $userId = null, bool $dryRun = false): array
    {
        if (! $dryRun) {
            \App\Support\ReportingPeriodLock::assertOpen($period, 'staging');
        }
        $monthEnd = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $missedTrigger = $this->missedTrigger($monthEnd);
        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->get(['id', 'contract_id', 'external_identity_id', 'product_group', 'tenor', 'overdue_days', 'sicr', 'ifrs9stage_post_qualitative', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360']);
        $charts = $missedTrigger > 0 ? $this->instalmentsByAccount() : [];
        $receipts = $missedTrigger > 0 ? $this->receiptsByAccount($monthEnd->toDateString()) : [];
        $counts = ['period' => $period, 'rows' => 0, 'stage1' => 0, 'stage2' => 0, 'stage3' => 0, 'by_dpd' => 0, 'by_instalments' => 0, 'by_sicr' => 0, 'by_bucket' => 0];
        $updates = [];
        foreach ($rows as $r) {
            $class = str_contains(strtolower((string) $r->product_group), 'mega') ? 'MEGA_FARM' : 'DEFAULT';
            [$s2, $s3] = $this->thresholds($class, (int) $r->tenor, $monthEnd);
            // the build counts days from the oldest overdue instalment; a row with no
            // count (loaded by the report importer, or the Mega Farm book) ages by bucket
            $dpd = (int) $r->overdue_days;
            if ($dpd === 0) {
                $dpd = $this->bucketDays($r);
                if ($dpd > 0) { $counts['by_bucket']++; }
            }
            $pre = $dpd >= $s3 ? 3 : ($dpd >= $s2 ? 2 : 1);
            $post = $pre;
            $reason = $pre > 1 ? 'dpd' : null;
            if ($missedTrigger > 0 && $post < 3 && isset($charts[$r->external_identity_id])) {
                $missed = $this->consecutiveMissed($charts[$r->external_identity_id], $receipts[$r->external_identity_id] ?? 0.0, $monthEnd->toDateString());
                if ($missed >= $missedTrigger) {
                    $post = 3;
                    $reason = 'instalments';
                }
            }
            if ((int) $r->sicr === 1 && $post < 2) {
                $post = 2;
                $reason = 'sicr';
            }
            $counts['rows']++;
            $counts['stage' . $post]++;
            if ($reason === 'dpd') { $counts['by_dpd']++; }
            if ($reason === 'instalments') { $counts['by_instalments']++; }
            if ($reason === 'sicr') { $counts['by_sicr']++; }
            $updates[] = ['id' => $r->id, 'pre' => (string) $pre, 'post' => (string) $post];
        }
        if (! $dryRun) {
            DB::transaction(function () use ($updates, $period, $counts, $userId, $missedTrigger) {
                foreach ($updates as $u) {
                    DB::table('loan_books')->where('id', $u['id'])->update([
                        'ifrs9stage_pre_qualitative' => $u['pre'], 'ifrs9stage_post_qualitative' => $u['post'],
                        'calculated_ifrs9_stage' => $u['post'], 'ifrs9_stage' => (int) $u['post'],
                    ]);
                }
                AuditLoggerService::log('Loan Book Staged', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $counts['rows'],
                    'new_values' => $counts + ['missed_instalment_trigger' => $missedTrigger], 'meta' => ['user' => $userId]]);
            });
        }

        return $counts;
    }

    /** Lower bound of the highest non-empty arrears bucket; 0 = current. */
    private function bucketDays(object $r): int
    {
        foreach ([271 => 'arrears_271_to_360', 181 => 'arrears_180_to_270', 91 => 'arrears_91_to_180', 31 => 'arrears_30_to_90', 1 => 'arrears_1_to_30'] as $lower => $col) {
            $v = (float) str_replace(',', '', (string) ($r->{$col} ?? '0'));
            if ($v > 0) {
                return $lower;
            }
        }

        return 0;
    }

    /** @return array{0:int,1:int} */
    private function thresholds(string $class, int $tenorMonths, ?CarbonImmutable $asOf = null): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths, $asOf?->toDateString());
        if ($t === null) {
            // no silent default (D21): a month without a rule in force is an error to fix, not a 31/181 to assume
            throw new \RuntimeException("No staging threshold is in force for facility class '{$class}' (tenor {$tenorMonths} months)" . ($asOf ? " at {$asOf->toDateString()}" : '') . '. Seed or approve one on the Staging & SICR Rules screen.');
        }

        return [(int) $t->stage2_dpd, (int) $t->stage3_dpd];
    }

    private function missedTrigger(CarbonImmutable $asOf): int
    {
        // no default in code (D21): the governed value or a named error
        $v = $this->governance->get('stage3_missed_instalments', $asOf);

        return (int) (preg_match('/^(\d+)/', $v, $m) ? $m[1] : 0);
    }

    /** Instalments per account from the current chart: due date and amount, in due-date order. */
    private function instalmentsByAccount(): array
    {
        $by = [];
        foreach ($this->zone->family(['P2_10']) as $row) {
            $p = $row['payload'];
            if (($p['DELETE_FLAG'] ?? 'N') === 'Y' || ($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y') {
                continue;
            }
            $amt = (float) str_replace(',', '', (string) ($p['INSTALLMENT_AMT'] ?? '0'));
            if ($amt <= 0 || $row['row_date'] === null) {
                continue;
            }
            $by[$row['account']][] = [$row['row_date'], $amt];
        }
        foreach ($by as &$list) {
            usort($list, fn ($a, $b) => $a[0] <=> $b[0]);
        }

        return $by;
    }

    /** Receipts (credit postings) per account dated on or before the date. */
    private function receiptsByAccount(string $toDate): array
    {
        $out = [];
        foreach ($this->zone->ledgerByAccount($toDate) as $account => $posts) {
            $sum = 0.0;
            foreach ($posts as $p) {
                $amt = (float) ($p['payload']['TRANSAMT'] ?? 0);
                if ($amt > 0) {
                    $sum += $amt;
                }
            }
            $out[$account] = $sum;
        }

        return $out;
    }

    /** Receipts allocated to instalments in due-date order; the count of unpaid instalments at the end of the run. */
    private function consecutiveMissed(array $instalments, float $receipts, string $asOf): int
    {
        $missed = 0;
        foreach ($instalments as [$due, $amt]) {
            if ($due > $asOf) {
                break;
            }
            if ($receipts + 0.005 >= $amt) {
                $receipts -= $amt;
                $missed = 0;
            } else {
                $receipts = 0.0;
                $missed++;
            }
        }

        return $missed;
    }
}
