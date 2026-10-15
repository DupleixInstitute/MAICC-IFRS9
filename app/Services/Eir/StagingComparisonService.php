<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Rbm\RbmReturnService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The staging comparison run (Staging Module Specification v1, section 4):
 * what a month's stages and ECL would be under another set of staging rules,
 * and why.
 *
 * Every loan of the month is staged under the compared rules (a named policy
 * or a set of settings) by the staging engine itself (StagingService::evaluate),
 * beside the stage as booked. Nothing is written but the two comparison tables:
 * not the loan book, the ECL, the revenue or a report; so a locked month can be
 * compared.
 *
 * The reason for a difference is found by moving from the booked rules to the
 * compared ones one setting group at a time, in the specification's order
 * (threshold profile, Mega Farm class, counting basis, maturity count, schedule
 * correction, missed instalments, qualitative trigger, cure), and staging the
 * book again after each step: a loan's reason is every step that changed its
 * stage. The cure reads the prior months as booked.
 *
 * The ECL at the compared stage uses the booked figures of that stage: the
 * month's booked ECL over EAD (coverage) of the loans in that stage of the
 * same portfolio (product group), or of the whole book where the portfolio has
 * no loan in that stage, applied to the loan's own EAD. A loan whose stage does
 * not move keeps its booked ECL. Where no loan of the book is in the compared
 * stage, the ECL at that stage is not known and is left empty, counted.
 *
 * The Stage 3 interest effect is indicative, for one month: interest on the net
 * basis (IFRS 9 5.4.1(b)) earns nothing on the allowance, so a loan entering
 * Stage 3 loses a month's interest at its rate on its ECL at Stage 3, and a loan
 * leaving gains it on its booked ECL.
 */
final class StagingComparisonService
{
    /** The setting groups, in the order the reason is found, with the settings each moves. */
    public const REASONS = [
        'threshold profile' => ['staging_threshold_profile', 'stage2_threshold_medium_long'],
        'Mega Farm class' => ['staging_mega_farm_term'],
        'counting basis' => ['dpd_basis'],
        'maturity count' => ['dpd_after_maturity'],
        'schedule correction' => ['dpd_schedule_correction'],
        'missed instalments' => ['stage3_missed_instalments'],
        'qualitative trigger' => ['sicr_qualitative_register'],
        'cure' => ['stage_cure_months', 'stage_cure_test'],
    ];

    /** A booked stage that a restaging under the booked rules does not give (the book was staged on other data or rules). */
    public const REASON_BOOKED_DIFFERS = 'booked stage differs from a restaging under the booked rules';

    public function __construct(private GovernanceService $governance, private StagingService $staging)
    {
    }

    /** The named policy, or the named policy with settings changed (Custom), to compare a month against. */
    public function rules(string $preset, array $settings = []): StagingPolicy
    {
        return StagingPolicy::named($preset)->with($settings);
    }

    /** The rules in force at the month-end, to change a setting on top of (a single setting tested before it is proposed). */
    public function rulesInForce(string $period): StagingPolicy
    {
        return StagingPolicy::at($this->governance, CarbonImmutable::parse($period . '-01')->endOfMonth());
    }

    /**
     * Runs the comparison for a month and returns the run id.
     */
    public function run(string $period, StagingPolicy $compared, ?int $userId = null, ?string $note = null): int
    {
        $monthEnd = CarbonImmutable::parse($period . '-01')->endOfMonth();
        $booked = StagingPolicy::at($this->governance, $monthEnd);
        $loans = DB::table('loan_books')->where('reporting_period', $period)
            ->get(['contract_id', 'customer_name', 'product_group', 'tenor', 'overdue_days', 'carrying_amount', 'ead', 'ecl_value', 'interest_rate', 'ifrs9stage_post_qualitative'])
            ->keyBy('contract_id');

        // the book restaged under the booked rules, then one setting group at a time towards the compared rules
        $base = $this->staging->evaluate($period, $booked);
        $reasons = [];
        foreach ($loans as $contract => $l) {
            if ($l->ifrs9stage_post_qualitative !== null && (int) $l->ifrs9stage_post_qualitative !== ($base['loans'][$contract]['stage'] ?? null)) {
                $reasons[$contract][] = self::REASON_BOOKED_DIFFERS;
            }
        }
        $step = $booked;
        $previous = $base;
        foreach (self::REASONS as $reason => $keys) {
            $change = [];
            foreach ($keys as $k) {
                if ($compared->value($k) !== $step->value($k)) {
                    $change[$k] = $compared->value($k);
                }
            }
            if ($change === []) {
                continue;
            }
            $step = $step->with($change);
            $result = $this->staging->evaluate($period, $step);
            foreach ($result['loans'] as $contract => $r) {
                if ($r['stage'] !== ($previous['loans'][$contract]['stage'] ?? null)) {
                    $reasons[$contract][] = $reason;
                }
            }
            $previous = $result;
        }
        $final = $previous;

        $coverage = $this->coverage($loans);
        $rows = [];
        $totals = ['moves' => [], 'ecl_by_reason' => [], 'stage3_interest_effect' => 0.0, 'ecl_not_known' => 0];
        $bookedEcl = 0.0; $comparedEcl = 0.0; $moved = 0;
        foreach ($loans as $contract => $l) {
            $from = (int) ($l->ifrs9stage_post_qualitative ?? $base['loans'][$contract]['stage']);
            $to = $final['loans'][$contract]['stage'];
            $ead = (float) ($l->ead ?? $l->carrying_amount);
            $ecl = (float) ($l->ecl_value ?? 0);
            $at = $from === $to ? $ecl : (isset($coverage[$l->product_group][$to]) ? $ead * $coverage[$l->product_group][$to] : (isset($coverage['*'][$to]) ? $ead * $coverage['*'][$to] : null));
            $rate = (float) ($l->interest_rate ?? 0);
            $rate = $rate > 1 ? $rate / 100 : $rate;
            $interest = 0.0;
            if ($to === 3 && $from !== 3 && $at !== null) {
                $interest = -$rate / 12 * $at;
            } elseif ($from === 3 && $to !== 3) {
                $interest = $rate / 12 * $ecl;
            }
            $reason = $from === $to ? null : implode('; ', $reasons[$contract] ?? ['not attributed']);
            if ($from !== $to) {
                $moved++;
                $key = "{$from}>{$to}";
                $totals['moves'][$key]['loans'] = ($totals['moves'][$key]['loans'] ?? 0) + 1;
                $totals['moves'][$key]['carrying_amount'] = round(($totals['moves'][$key]['carrying_amount'] ?? 0) + (float) $l->carrying_amount, 2);
                if ($at === null) {
                    $totals['ecl_not_known']++;
                } else {
                    $totals['ecl_by_reason'][$reason] = round(($totals['ecl_by_reason'][$reason] ?? 0) + $at - $ecl, 2);
                }
            }
            $totals['stage3_interest_effect'] += $interest;
            $bookedEcl += $ecl;
            $comparedEcl += $at ?? $ecl;
            $rows[] = [
                'contract_id' => (string) $contract, 'customer_name' => $l->customer_name, 'product_group' => $l->product_group, 'tenor' => (int) $l->tenor,
                'booked_stage' => $from, 'compared_stage' => $to,
                'booked_dpd' => $base['loans'][$contract]['dpd'], 'compared_dpd' => $final['loans'][$contract]['dpd'],
                'reason' => $reason === null ? (isset($reasons[$contract]) ? implode('; ', $reasons[$contract]) . ' (no net change)' : null) : $reason,
                'carrying_amount' => round((float) $l->carrying_amount, 2), 'ead' => round($ead, 2),
                'booked_ecl' => round($ecl, 2), 'compared_ecl' => $at === null ? null : round($at, 2), 'ecl_effect' => $at === null ? null : round($at - $ecl, 2),
                'stage3_interest_effect' => round($interest, 2),
                'rbm_class' => RbmReturnService::classify((int) $l->overdue_days, (int) $l->tenor),
            ];
        }
        $totals['stage3_interest_effect'] = round($totals['stage3_interest_effect'], 2);
        ksort($totals['moves']);
        arsort($totals['ecl_by_reason']);

        return DB::transaction(function () use ($period, $booked, $compared, $userId, $note, $loans, $moved, $bookedEcl, $comparedEcl, $totals, $rows) {
            $id = DB::table('staging_comparison_runs')->insertGetId([
                'reporting_period' => $period, 'booked_preset' => $booked->preset, 'compared_preset' => $compared->preset,
                'booked_rules' => json_encode($booked->values), 'compared_rules' => json_encode($compared->values),
                'run_by' => $userId, 'note' => $note === null ? null : mb_substr($note, 0, 500), 'loans' => $loans->count(), 'loans_moved' => $moved,
                'booked_ecl' => round($bookedEcl, 2), 'compared_ecl' => round($comparedEcl, 2), 'totals' => json_encode($totals),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('staging_comparison_rows')->insert(array_map(fn ($r) => $r + ['run_id' => $id, 'created_at' => now(), 'updated_at' => now()], $chunk));
            }
            AuditLoggerService::log('Staging Comparison Run', 'staging_comparison_runs', $id, ['reporting_period' => $period, 'rows_affected' => count($rows),
                'new_values' => ['booked' => $booked->preset, 'compared' => $compared->preset, 'compared_rules' => $compared->values, 'loans_moved' => $moved, 'ecl_effect' => round($comparedEcl - $bookedEcl, 2)],
                'meta' => ['user' => $userId, 'note' => $note]]);

            return $id;
        });
    }

    /**
     * The booked ECL over EAD of each stage, by portfolio and for the whole book ('*').
     *
     * @return array<string,array<int,float>>
     */
    private function coverage($loans): array
    {
        $sum = [];
        foreach ($loans as $l) {
            $stage = (int) $l->ifrs9stage_post_qualitative;
            $ead = (float) ($l->ead ?? $l->carrying_amount);
            foreach ([(string) $l->product_group, '*'] as $g) {
                $sum[$g][$stage]['ecl'] = ($sum[$g][$stage]['ecl'] ?? 0) + (float) ($l->ecl_value ?? 0);
                $sum[$g][$stage]['ead'] = ($sum[$g][$stage]['ead'] ?? 0) + $ead;
            }
        }
        $out = [];
        foreach ($sum as $g => $stages) {
            foreach ($stages as $s => $v) {
                if ($v['ead'] > 0.005) {
                    $out[$g][$s] = $v['ecl'] / $v['ead'];
                }
            }
        }

        return $out;
    }
}
