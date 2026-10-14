<?php

namespace App\Services\Eir;

use App\Models\StagingThreshold;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\InstalmentChartHistory;
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
 *                    chart in force at the month-end against the cash receipts
 *                    in the ledger to that date allocated in due-date order
 *                    (InstalmentChartHistory). Verification of 14 October 2026:
 *                    the trigger read the live chart of 6 October 2026 for
 *                    every month-end (a schedule E-Banker had not yet written),
 *                    counted every credit as a receipt (write-offs, interest
 *                    credits, and receipts later reversed) and fired on loans
 *                    that owed nothing. It now reads the chart in force, the
 *                    receipts net of reversals with write-offs left out, and
 *                    only a loan with a balance owed; a month-end with no chart
 *                    in force, or whose chart demands more than the ledger has
 *                    ever debited to the loan (a schedule that cannot be the
 *                    loan's), is not evaluated and is counted, never assumed
 *
 * The DPD stage is written to ifrs9stage_pre_qualitative; the stage after the
 * missed-instalment trigger and the qualitative triggers goes to
 * ifrs9stage_post_qualitative. A row never moves down by a trigger.
 *
 * Qualitative triggers (IFRS 9 B5.5.17, Appendix A): the approved entries of
 * the SICR trigger register in force at the month-end (QualitativeSicrService),
 * matched on the contract or, for a customer-wide trigger, on every loan of
 * the customer. The highest stage they imply is applied: 2 for a significant
 * increase in credit risk, 3 for credit-impaired evidence. The engine writes
 * loan_books.sicr = 1 on a row a qualitative trigger reached and 0 otherwise;
 * it no longer reads that flag, which nothing governed or reproducible set
 * (system review of 9 October 2026).
 *
 * The cure period (governed, stage_cure_months; system audit of 9 October
 * 2026, finding M11): the prior month's post-qualitative stage is read, and
 * when the month's measured stage would move a loan DOWN, the loan is held at
 * the prior stage until it has been below the threshold for that many
 * consecutive month-ends, the current one included, counted from the DPD
 * stage stored on the prior months' book rows, raised to the stage of any
 * qualitative trigger in force at that month-end. A move up is immediate. A
 * loan the instalment trigger still holds this month is not cured at all;
 * once the trigger releases, its DPD history governs the probation; a loan
 * a qualitative trigger held cures from the month-end after the trigger ended.
 */
class StagingService
{
    private QualitativeSicrService $qualitative;

    private InstalmentChartHistory $charts;

    public function __construct(private GovernanceService $governance, private LandingZoneReader $zone, ?QualitativeSicrService $qualitative = null, ?InstalmentChartHistory $charts = null)
    {
        $this->qualitative = $qualitative ?? new QualitativeSicrService();
        $this->charts = $charts ?? new InstalmentChartHistory($zone);
    }

    /** @return array{period:string,rows:int,stage1:int,stage2:int,stage3:int,by_dpd:int,by_instalments:int,by_sicr:int,by_credit_impaired:int,by_bucket:int,held_by_cure:int,cure_months:int,qualitative_triggers:int,instalments_no_chart:int,instalments_chart_contradicts_ledger:int} */
    public function stage(string $period, ?int $userId = null, bool $dryRun = false): array
    {
        if (! $dryRun) {
            \App\Support\ReportingPeriodLock::assertOpen($period, 'staging');
        }
        $monthEnd = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $missedTrigger = $this->missedTrigger($monthEnd);
        $cureMonths = $this->cureMonths($monthEnd);
        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->get(['id', 'contract_id', 'external_identity_id', 'customer_id', 'product_group', 'product_code', 'tenor', 'overdue_days', 'carrying_amount', 'build_flag', 'ifrs9stage_post_qualitative', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360']);
        $asOf = $monthEnd->toDateString();
        $customers = $rows->pluck('customer_id', 'contract_id')->map(fn ($c) => $c === null ? null : (string) $c)->all();
        [$priorStage, $dpdHistory] = $this->priorMonths($period, $cureMonths, $customers);
        $qualMap = $this->qualitative->mapAt($monthEnd->toDateString());
        $counts = ['period' => $period, 'rows' => 0, 'stage1' => 0, 'stage2' => 0, 'stage3' => 0, 'by_dpd' => 0, 'by_instalments' => 0, 'by_sicr' => 0, 'by_credit_impaired' => 0, 'by_bucket' => 0, 'held_by_cure' => 0, 'cure_months' => $cureMonths, 'qualitative_triggers' => 0, 'instalments_no_chart' => 0, 'instalments_chart_contradicts_ledger' => 0];
        $applied = [];
        $notEvaluated = [];
        $updates = [];
        foreach ($rows as $r) {
            // the programme is known by its GL (1050301 to 1050307) as well as by its name:
            // a book built before the GL titles were read named it "GL 1050301" and was
            // staged at 181 days instead of the directive's 91
            $class = (str_contains(strtolower((string) $r->product_group), 'mega') || in_array((string) $r->product_code, \App\Services\MegaFarm\MegaFarmEclService::GLS, true)) ? 'MEGA_FARM' : 'DEFAULT';
            [$s2, $s3] = $this->thresholds($class, (int) $r->tenor, $monthEnd);
            // the build counts days from the oldest overdue instalment; a row with no
            // count (loaded by the report importer, or the Mega Farm book) ages by bucket
            $dpd = (int) $r->overdue_days;
            // a count the build made on the instalment chart, against the report's ageing, is not
            // overruled by that same report's buckets (verification of 14 October 2026)
            $counted = str_contains((string) ($r->build_flag ?? ''), \App\Services\Ebanker\LoanBookBuildService::SCHEDULE_COUNT);
            if ($dpd === 0 && ! $counted) {
                $dpd = $this->bucketDays($r);
                if ($dpd > 0) { $counts['by_bucket']++; }
            }
            $pre = $dpd >= $s3 ? 3 : ($dpd >= $s2 ? 2 : 1);
            $post = $pre;
            $reason = $pre > 1 ? 'dpd' : null;
            // the trigger counts unpaid instalments of a balance still owed, on the chart in force at the month-end
            $account = (string) ($r->external_identity_id ?? '');
            if ($missedTrigger > 0 && $post < 3 && $account !== '' && (float) ($r->carrying_amount ?? 0) > 0.005) {
                $chart = $this->charts->at($account, $asOf);
                if ($chart === null || $chart['unreadable']) {
                    if ($chart !== null || $this->charts->hasChart($account)) {
                        $counts['instalments_no_chart']++;
                        $notEvaluated[(string) $r->contract_id] = $chart === null ? 'no instalment chart in force at the month-end' : 'instalment chart in force cannot be dated';
                    }
                } elseif (InstalmentChartHistory::dueTo($chart['instalments'], $asOf) > $this->charts->debitsTo($account, $asOf) + 0.5) {
                    // a schedule that asks for more than the ledger has ever charged the loan is not the loan's schedule
                    $counts['instalments_chart_contradicts_ledger']++;
                    $notEvaluated[(string) $r->contract_id] = 'instalments due on the chart exceed everything the ledger has debited to the loan';
                } elseif (InstalmentChartHistory::missed($chart['instalments'], $this->charts->receiptsTo($account, $asOf), $asOf) >= $missedTrigger) {
                    $post = 3;
                    $reason = 'instalments';
                }
            }
            // the qualitative triggers in force at the month-end (B5.5.17, Appendix A)
            $qual = QualitativeSicrService::forLoan($qualMap, (string) $r->contract_id, $r->customer_id === null ? null : (string) $r->customer_id);
            if ($qual['ids'] !== []) {
                $applied[(string) $r->contract_id] = ['stage' => $qual['stage'], 'triggers' => $qual['ids']];
            }
            if ($qual['stage'] > $post) {
                $post = $qual['stage'];
                $reason = $qual['stage'] >= 3 ? 'credit_impaired' : 'sicr';
            }
            // the cure period (finding M11): a move down waits until the loan has
            // been below the threshold for the governed number of consecutive
            // month-ends; this month counts, the prior months by their DPD stage
            $prior = $priorStage[$r->contract_id] ?? 0;
            if ($cureMonths > 0 && $prior > $post) {
                $below = 1;
                foreach ($dpdHistory[$r->contract_id] ?? [] as $monthStage) {
                    if ($monthStage === null || $monthStage >= $prior) {
                        break;
                    }
                    $below++;
                }
                if ($below < $cureMonths) {
                    $post = $prior;
                    $reason = 'cure';
                }
            }
            $counts['rows']++;
            $counts['stage' . $post]++;
            if ($reason === 'dpd') { $counts['by_dpd']++; }
            if ($reason === 'instalments') { $counts['by_instalments']++; }
            if ($reason === 'sicr') { $counts['by_sicr']++; }
            if ($reason === 'credit_impaired') { $counts['by_credit_impaired']++; }
            if ($reason === 'cure') { $counts['held_by_cure']++; }
            $updates[] = ['id' => $r->id, 'pre' => (string) $pre, 'post' => (string) $post, 'sicr' => $qual['ids'] !== [] ? 1 : 0];
        }
        $counts['qualitative_triggers'] = count(array_unique(array_merge([], ...array_column($applied, 'triggers'))));
        if (! $dryRun) {
            DB::transaction(function () use ($updates, $period, $counts, $userId, $missedTrigger, $applied, $notEvaluated) {
                foreach ($updates as $u) {
                    DB::table('loan_books')->where('id', $u['id'])->update([
                        'ifrs9stage_pre_qualitative' => $u['pre'], 'ifrs9stage_post_qualitative' => $u['post'],
                        'calculated_ifrs9_stage' => $u['post'], 'ifrs9_stage' => (int) $u['post'], 'sicr' => $u['sicr'],
                    ]);
                }
                // the qualitative triggers each loan was staged under, by contract, for the audit trail
                AuditLoggerService::log('Loan Book Staged', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $counts['rows'],
                    'new_values' => $counts + ['missed_instalment_trigger' => $missedTrigger, 'qualitative' => $applied, 'instalment_trigger_not_evaluated' => $notEvaluated], 'meta' => ['user' => $userId]]);
            });
        }

        return $counts;
    }

    /**
     * The prior month's post-qualitative stage per contract, and, for the cure
     * count, the DPD (pre-qualitative) stage of the prior month-ends, newest
     * first, with a null for a month the book does not hold (which ends the
     * count: a month that cannot be seen is not a month proved cured).
     *
     * @return array{0:array<string,int>,1:array<string,list<?int>>}
     */
    private function priorMonths(string $period, int $cureMonths, array $customers = []): array
    {
        $priorPeriod = CarbonImmutable::parse($period . '-01')->subMonth();
        $prior = DB::table('loan_books')->where('reporting_period', $priorPeriod->format('Y-m'))->whereNotNull('ifrs9stage_post_qualitative')
            ->pluck('ifrs9stage_post_qualitative', 'contract_id')->map(fn ($s) => (int) $s)->all();
        if ($cureMonths <= 1 || $prior === []) {
            return [$prior, []];
        }
        // the months whose DPD stage the count reads: the prior month back to cureMonths - 1 months ago
        $months = [];
        for ($i = 1; $i < $cureMonths; $i++) {
            $months[] = $priorPeriod->subMonths($i - 1)->format('Y-m');
        }
        $stored = [];
        foreach (DB::table('loan_books')->whereIn('reporting_period', $months)->whereIn('contract_id', array_keys($prior))->get(['contract_id', 'reporting_period', 'ifrs9stage_pre_qualitative']) as $row) {
            $stored[$row->contract_id][$row->reporting_period] = $row->ifrs9stage_pre_qualitative === null ? null : (int) $row->ifrs9stage_pre_qualitative;
        }
        // a month-end a qualitative trigger covered counts at the stage the trigger implied
        $qualByMonth = [];
        foreach ($months as $m) {
            $qualByMonth[$m] = $this->qualitative->mapAt(CarbonImmutable::parse($m . '-01')->endOfMonth()->toDateString());
        }
        $history = [];
        foreach ($prior as $contract => $stage) {
            foreach ($months as $m) {
                $dpdStage = $stored[$contract][$m] ?? null;
                if ($dpdStage !== null) {
                    $q = QualitativeSicrService::forLoan($qualByMonth[$m], (string) $contract, $customers[$contract] ?? null)['stage'];
                    $dpdStage = max($dpdStage, $q);
                }
                $history[$contract][] = $dpdStage;
            }
        }

        return [$prior, $history];
    }

    private function cureMonths(CarbonImmutable $asOf): int
    {
        // no default in code (D21): the governed value or a named error
        $v = $this->governance->get('stage_cure_months', $asOf);

        return (int) (preg_match('/^(\d+)/', $v, $m) ? $m[1] : 0);
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
    private function thresholds(string $class, int $tenorMonths, CarbonImmutable $asOf): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths, $asOf->toDateString());
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
}
