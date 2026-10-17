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
 * Every rule below is a governed setting, resolved as at the month-end by
 * StagingPolicy (Staging Module Specification v1, section 3): the staging
 * policy in force (staging_preset) sets them all, or under Custom each
 * setting does. Single-ladder staging, MAIIC's staging policy as at 31
 * December 2025, is the seeded default: one threshold of 181 days for every
 * facility on the bucket ageing, with no missed-instalment trigger, no
 * qualitative register and no probation. What follows describes the rules
 * the directive-aligned policy turns on.
 *
 * Two triggers, both governed:
 *   days past due    the staging count the build wrote (staging_dpd) under the
 *                    staging settings, or derived from the row (section 5)
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
        // the run this month's work belongs to (Staging Module Specification v1, section 8.8): the open month-end
        // or bootstrap run, or one of its own when run from a screen
        if (! $dryRun && ! \App\Support\RunContext::inside($period, 'Staging')) {
            return \App\Support\RunContext::engine($period, 'Staging', $userId, fn () => $this->stage($period, $userId, $dryRun));
        }
        if (! $dryRun) {
            \App\Support\ReportingPeriodLock::assertOpen($period, 'staging');
        }
        // every rule as at the month-end, from the policy in force; a missing setting stops here (D21)
        $policy = StagingPolicy::at($this->governance, CarbonImmutable::parse($period . '-01')->endOfMonth());
        $e = $this->evaluate($period, $policy);
        [$counts, $applied, $notEvaluated] = [$e['counts'], $e['applied'], $e['not_evaluated']];
        $missedTrigger = $policy->missedInstalments();
        if (! $dryRun) {
            DB::transaction(function () use ($e, $period, $counts, $userId, $missedTrigger, $applied, $notEvaluated, $policy) {
                foreach ($e['loans'] as $u) {
                    DB::table('loan_books')->where('id', $u['id'])->update([
                        'ifrs9stage_pre_qualitative' => (string) $u['pre'], 'ifrs9stage_post_qualitative' => (string) $u['stage'],
                        'calculated_ifrs9_stage' => (string) $u['stage'], 'ifrs9_stage' => $u['stage'], 'sicr' => $u['sicr'],
                    ]);
                }
                // the qualitative triggers each loan was staged under, by contract, for the audit trail
                AuditLoggerService::log('Loan Book Staged', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $counts['rows'],
                    'new_values' => $counts + ['staging_policy' => $policy->toArray(), 'missed_instalment_trigger' => $missedTrigger, 'qualitative' => $applied, 'instalment_trigger_not_evaluated' => $notEvaluated], 'meta' => ['user' => $userId]]);
            });
        }

        return $counts + ['staging_preset' => $policy->preset];
    }

    /**
     * The stage of every loan of a month under a set of staging rules, written
     * nowhere: stage() writes it under the rules in force, and the staging
     * comparison (StagingComparisonService) reads it under any other. The cure
     * reads the prior months as booked.
     *
     * @return array{loans:array<string,array{id:int,stage:int,pre:int,dpd:int,reason:?string,sicr:int}>,counts:array,applied:array,not_evaluated:array}
     */
    /** @var array<string, ?StagingThreshold> the rules read in this evaluation */
    private array $thresholdCache = [];

    public function evaluate(string $period, StagingPolicy $policy): array
    {
        $this->thresholdCache = [];
        $monthEnd = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $missedTrigger = $policy->missedInstalments();
        $cureMonths = $policy->cureMonths();
        $cureTest = $policy->cureTest();
        $qualitativeApplied = $policy->qualitativeApplied();
        // the staging day count the build wrote (section 5), where the table has it
        $stagingCount = \Illuminate\Support\Facades\Schema::hasColumn('loan_books', 'staging_dpd');
        $basisInForce = $policy->dpdBasisLabel();
        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->get(array_merge(['id', 'contract_id', 'external_identity_id', 'customer_id', 'product_group', 'product_code', 'tenor', 'overdue_days', 'carrying_amount', 'build_flag', 'ifrs9stage_post_qualitative', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'], $stagingCount ? ['staging_dpd', 'staging_dpd_basis'] : [], \Illuminate\Support\Facades\Schema::hasColumn('loan_books', 'build_basis') ? ['build_basis'] : [], \Illuminate\Support\Facades\Schema::hasColumn('loan_books', 'due_date') ? ['due_date'] : []));
        $asOf = $monthEnd->toDateString();
        $customers = $rows->pluck('customer_id', 'contract_id')->map(fn ($c) => $c === null ? null : (string) $c)->all();
        [$priorStage, $dpdHistory] = $this->priorMonths($period, $cureMonths, $customers, $qualitativeApplied);
        $qualMap = $qualitativeApplied ? $this->qualitative->mapAt($monthEnd->toDateString()) : [];
        $stageOverrides = app(LoanStageOverrideService::class)->active($period);
        $counts = ['period' => $period, 'rows' => 0, 'stage1' => 0, 'stage2' => 0, 'stage3' => 0, 'by_override' => 0, 'by_dpd' => 0, 'by_instalments' => 0, 'by_sicr' => 0, 'by_credit_impaired' => 0, 'by_bucket' => 0, 'held_by_cure' => 0, 'cure_months' => $cureMonths, 'qualitative_triggers' => 0, 'instalments_no_chart' => 0, 'instalments_chart_contradicts_ledger' => 0, 'staging_dpd_derived' => 0];
        $applied = [];
        $notEvaluated = [];
        $loans = [];
        foreach ($rows as $r) {
            // the programme is known by its GL (1050301 to 1050307) as well as by its name:
            // a book built before the GL titles were read named it "GL 1050301" and was
            // staged at 181 days instead of the directive's 91
            $megaFarm = str_contains(strtolower((string) $r->product_group), 'mega') || in_array((string) $r->product_code, \App\Services\MegaFarm\MegaFarmEclService::GLS, true);
            [$s2, $s3] = $this->thresholds($policy, $megaFarm, (int) $r->tenor, $monthEnd);
            // the staging day count (section 5): from the counts the build kept on the row, exact under
            // any rules; else the build's staging_dpd when it was counted on the basis of these rules;
            // otherwise (a row the report importer or the Mega Farm book loaded, or a book built before
            // section 5) derived from the row on the governed basis, the bucket ageing or the build's
            // directive count, and counted
            $kept = is_string($r->build_basis ?? null) ? (json_decode($r->build_basis, true)['dpd_counts'] ?? null) : null;
            if (is_array($kept) && count(array_intersect_key($kept, array_flip(['oldest', 'oldest_recounted', 'bucket', 'bucket_recounted', 'since_maturity']))) === 5) {
                $dpd = $policy->stagingDays(array_map('intval', $kept));
            } elseif ($stagingCount && $r->staging_dpd !== null && $r->staging_dpd_basis === $basisInForce) {
                $dpd = (int) $r->staging_dpd;
            } else {
                $dpd = $policy->bucketBasis() ? self::bucketDays($r) : self::daysPastDueOf($r);
                // a matured loan still owing, counted from its maturity when the rules say so and the row has a due date
                if ($policy->value('dpd_after_maturity') === StagingPolicy::MATURITY_COUNT && ! empty($r->due_date) && (float) ($r->carrying_amount ?? 0) > 0.005 && (string) $r->due_date < $asOf) {
                    $dpd = max($dpd, (int) CarbonImmutable::parse((string) $r->due_date)->diffInDays($monthEnd));
                }
                $counts['staging_dpd_derived']++;
            }
            if ($dpd > 0 && ($policy->bucketBasis() || (int) $r->overdue_days === 0)) { $counts['by_bucket']++; }
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
            $qual = $qualitativeApplied ? QualitativeSicrService::forLoan($qualMap, (string) $r->contract_id, $r->customer_id === null ? null : (string) $r->customer_id) : ['stage' => 0, 'ids' => []];
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
            if ($prior > $post && $this->heldByCure($cureTest, $cureMonths, $r, $dpd, $dpdHistory[$r->contract_id] ?? [], $prior)) {
                $post = $prior;
                $reason = 'cure';
            }
            // an approved stage override for the month is the last word (Client Book Reconciliation Specification v1,
            // section 6a): after the day count, the triggers and the cure rule; the policy's own stage stays beside it
            $policyStage = $post;
            if (isset($stageOverrides[(string) $r->contract_id])) {
                $post = $stageOverrides[(string) $r->contract_id]['stage'];
                $reason = 'override';
                $counts['by_override']++;
            }
            $counts['rows']++;
            $counts['stage' . $post]++;
            if ($reason === 'dpd') { $counts['by_dpd']++; }
            if ($reason === 'instalments') { $counts['by_instalments']++; }
            if ($reason === 'sicr') { $counts['by_sicr']++; }
            if ($reason === 'credit_impaired') { $counts['by_credit_impaired']++; }
            if ($reason === 'cure') { $counts['held_by_cure']++; }
            $loans[(string) $r->contract_id] = ['id' => (int) $r->id, 'stage' => $post, 'pre' => $pre, 'dpd' => $dpd, 'reason' => $reason, 'sicr' => $qual['ids'] !== [] ? 1 : 0, 'policy_stage' => $policyStage,
                'stage_override_id' => $stageOverrides[(string) $r->contract_id]['id'] ?? null];
        }
        $counts['qualitative_triggers'] = count(array_unique(array_merge([], ...array_column($applied, 'triggers'))));

        return ['loans' => $loans, 'counts' => $counts, 'applied' => $applied, 'not_evaluated' => $notEvaluated];
    }

    /**
     * Whether a loan whose measured stage would move it down is held at its
     * prior stage, under the governed cure test (stage_cure_test):
     *   months below the threshold  held until it has been below the threshold
     *                               for the cure months, this month included
     *                               (0 months: it moves down at once)
     *   all arrears cleared         held while anything is overdue (the
     *                               directive, section 13(3))
     *   arrears cleared, then the months  both
     *
     * @param list<?int> $history the prior month-ends' DPD stages, newest first
     */
    private function heldByCure(string $cureTest, int $cureMonths, object $r, int $dpd, array $history, int $prior): bool
    {
        if ($cureTest !== StagingPolicy::CURE_MONTHS && ! self::arrearsCleared($r, $dpd)) {
            return true;
        }
        if ($cureTest === StagingPolicy::CURE_CLEARED || $cureMonths <= 0) {
            return false;
        }
        $below = 1;
        foreach ($history as $monthStage) {
            if ($monthStage === null || $monthStage >= $prior) {
                break;
            }
            $below++;
        }

        return $below < $cureMonths;
    }

    /** Nothing overdue on the row: no days past due and every arrears bucket empty. */
    private static function arrearsCleared(object $r, int $dpd): bool
    {
        return $dpd === 0 && self::bucketDays($r) === 0;
    }

    /**
     * The prior month's post-qualitative stage per contract, and, for the cure
     * count, the DPD (pre-qualitative) stage of the prior month-ends, newest
     * first, with a null for a month the book does not hold (which ends the
     * count: a month that cannot be seen is not a month proved cured).
     *
     * @return array{0:array<string,int>,1:array<string,list<?int>>}
     */
    private function priorMonths(string $period, int $cureMonths, array $customers = [], bool $qualitativeApplied = true): array
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
            $qualByMonth[$m] = $qualitativeApplied ? $this->qualitative->mapAt(CarbonImmutable::parse($m . '-01')->endOfMonth()->toDateString()) : [];
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

    /**
     * The days past due staging reads from a loan-book row: the build's count, or with no count
     * (a row loaded by the report importer, or the Mega Farm book) the lower bound of the highest
     * non-empty arrears bucket. A count the build made on the instalment chart, against the
     * report's ageing, is not overruled by that same report's buckets (verification of 14 October
     * 2026). Public so a comparison (BaselineService, OD_01) reads the days staging reads.
     */
    public static function daysPastDueOf(object $r): int
    {
        $dpd = (int) ($r->overdue_days ?? 0);
        $counted = str_contains((string) ($r->build_flag ?? ''), \App\Services\Ebanker\LoanBookBuildService::SCHEDULE_COUNT);

        return $dpd === 0 && ! $counted ? self::bucketDays($r) : $dpd;
    }

    /** Lower bound of the highest non-empty arrears bucket; 0 = current. Public so a comparison reads the bucket ageing staging reads. */
    public static function bucketDays(object $r): int
    {
        foreach ([271 => 'arrears_271_to_360', 181 => 'arrears_180_to_270', 91 => 'arrears_91_to_180', 31 => 'arrears_30_to_90', 1 => 'arrears_1_to_30'] as $lower => $col) {
            $v = (float) str_replace(',', '', (string) ($r->{$col} ?? '0'));
            if ($v > 0) {
                return $lower;
            }
        }

        return 0;
    }

    /**
     * The Stage 2 and Stage 3 thresholds of a facility under the policy in force:
     * the staging_thresholds row of the profile's class, or by tenor, then the
     * Stage 2 setting for medium and long-term facilities.
     *
     * @return array{0:int,1:int}
     */
    private function thresholds(StagingPolicy $policy, bool $megaFarm, int $tenorMonths, CarbonImmutable $asOf): array
    {
        [$class, $exact] = $policy->thresholdClass($megaFarm);
        // one read per class, tenor and month-end within an evaluation (it read the rule, and the table's columns, once per loan)
        $key = $class . '|' . (int) $exact . '|' . $tenorMonths . '|' . $asOf->toDateString();
        $t = array_key_exists($key, $this->thresholdCache) ? $this->thresholdCache[$key]
            : ($this->thresholdCache[$key] = StagingThreshold::forFacility($class, $tenorMonths, $asOf->toDateString(), $exact));
        if ($t === null) {
            // no silent default (D21): a month without a rule in force is an error to fix, not a 31/181 to assume
            throw new \RuntimeException("No staging threshold is in force for facility class '{$class}' (tenor {$tenorMonths} months)" . ($asOf ? " at {$asOf->toDateString()}" : '') . '. Seed or approve one on the Staging & SICR Rules screen.');
        }

        $s3 = (int) $t->stage3_dpd;

        return [$policy->stage2((int) $t->stage2_dpd, $tenorMonths, $s3), $s3];
    }
}
