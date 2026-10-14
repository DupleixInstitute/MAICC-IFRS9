<?php

namespace App\Services\Eir;

use App\Models\ReferenceRate;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use App\Support\ReportingPeriodLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The writer of rate_reset_events and its maker-checker intake (specification
 * v4 phase P5, section 3.2; built 14 October 2026).
 *
 * The rate E-Banker charges is the loan book's month-end rate (section 3.2),
 * and the whole month is charged at it (section 3.1). For every contract of the
 * EIR engine, each month whose loan-book rate differs from the month before is
 * a rate move, recorded with the PLR in force at both month-ends (the reference
 * series, P2_06) and the spread (rate less PLR):
 *   - a floating-rate loan whose spread held (within the 0.15 point tolerance of
 *     the spread derivation, SpreadDerivationService) moved with the PLR: a
 *     PLR_RESET, suggested as an in-contract reset (B5.4.5);
 *   - a floating-rate loan whose spread moved: a SPREAD_CHANGE, MAIIC re-pricing
 *     at its own option or by agreement with the borrower, for a person to
 *     classify (O17);
 *   - a fixed-rate loan whose rate moved is not a B5.4.5 reset at all: it is
 *     written to the restructure register as a candidate (a contractual reset
 *     takes the B5.4.6 catch-up, a negotiated change is a 5.4.3 modification).
 * The Mega Farm programme (MF_07) is outside the EIR engine (decision D30).
 *
 * How the EIR moves for a reset is already built: the monthly re-estimation at
 * the rate charged (decision D38, EirRevenueService::eirInForce), prospective
 * with no catch-up. This register does not repeat it: it is the dated evidence
 * of each move and the control over its classification. One person proposes
 * the classification, a different person approves; a move in a locked period
 * is refused. A move approved as NEGOTIATED becomes a restructure candidate.
 * The governed setting rate_change_classification decides whether NEGOTIATED is
 * open at all ("Every rate change is a reset" closes it).
 */
class RateResetService
{
    public const CLASSIFICATIONS = ['IN_CONTRACT', 'NEGOTIATED'];

    public function __construct(private LandingZoneReader $zone, private ?GovernanceService $governance = null)
    {
        $this->governance ??= app(GovernanceService::class);
    }

    /**
     * @return array{floating_moves:int,plr_resets:int,spread_changes:int,fixed_rate_candidates:int,no_reference_rate:int,written:int,kept:int,removed:int}
     */
    public function detect(?int $userId = null): array
    {
        $series = DB::table('reference_rate_series')->where('index_code', ReferenceRate::DEFAULT_INDEX)->orderBy('effective_date')
            ->get(['effective_date', 'rate'])->map(fn ($r) => ['date' => substr((string) $r->effective_date, 0, 10), 'rate' => (float) $r->rate])->all();
        $contracts = DB::table('contract_eir')->get(['contract_id', 'rate_type'])->keyBy(fn ($c) => (string) $c->contract_id);
        $keyed = $this->keyedDates();
        $candidates = new RestructureCandidateService($this->zone);
        $chartRows = $candidates->chart();

        $rates = [];
        foreach (DB::table('loan_books')->whereIn('contract_id', $contracts->keys()->all() ?: ['-'])->whereNotNull('interest_rate')
            ->orderBy('id')->get(['contract_id', 'reporting_period', 'interest_rate', 'external_identity_id']) as $l) {
            $period = substr(str_replace('-', '', (string) $l->reporting_period), 0, 4) . '-' . substr(str_replace('-', '', (string) $l->reporting_period), 4, 2);
            if ((float) $l->interest_rate <= 0) {
                continue; // a nil rate is a closed or unrated month, not a move to nil (as the D38 re-estimation reads it)
            }
            $rates[(string) $l->contract_id][$period] = ['rate' => (float) $l->interest_rate, 'account' => $l->external_identity_id ?: str_pad((string) $l->contract_id, 15, '0', STR_PAD_LEFT)];
        }

        $s = ['floating_moves' => 0, 'fixed_moved_with_plr' => 0, 'plr_resets' => 0, 'spread_changes' => 0, 'fixed_rate_candidates' => 0, 'no_reference_rate' => 0, 'written' => 0, 'kept' => 0, 'removed' => 0];
        $keys = [];
        $rateKeys = [];
        foreach ($rates as $contractId => $months) {
            ksort($months);
            $floating = strtoupper((string) ($contracts[$contractId]->rate_type ?? '')) === 'FLOATING';
            $prev = null;
            foreach ($months as $period => $m) {
                if ($prev !== null && abs($m['rate'] - $prev['rate']) > 1e-6 && self::nextMonth($prev['period']) === $period) {
                    $start = $period . '-01';
                    $prevEnd = CarbonImmutable::parse($prev['period'] . '-01')->endOfMonth()->toDateString();
                    $end = CarbonImmutable::parse($start)->endOfMonth()->toDateString();
                    $plrPrev = self::plrAt($series, $prevEnd);
                    $plrNow = self::plrAt($series, $end);
                    $plrMoved = self::plrMovedIn($series, $prevEnd, $end);
                    $keyedIn = array_values(array_filter($keyed[$m['account']] ?? [], fn ($d) => $d >= $start && $d <= $end));
                    // the date of the move: the PLR's effective date, else the date the account's rate was keyed, else the 1st (section 3.1)
                    $date = $plrMoved ?? ($keyedIn[0] ?? $start);
                    $evidence = sprintf('Loan book rate %s for %s, %s for %s', self::pct($prev['rate']), $prev['period'], self::pct($m['rate']), $period)
                        . ($plrMoved ? "; PLR changed on {$plrMoved}" : '') . ($keyedIn ? '; rate keyed in E-Banker (P1_04) on ' . implode(', ', $keyedIn) : '');
                    // a loan the engine treats as fixed-rate whose rate moved with the PLR, its spread held, is re-priced
                    // like a floating loan (Interest Policy M, manual_policy_handling, review item R14): a reset to record,
                    // not a restructure, with the engine's rate type named on the evidence
                    $movedWithPlr = ! $floating && $plrMoved !== null && $plrPrev !== null && $plrNow !== null
                        && abs(($m['rate'] - $plrNow) - ($prev['rate'] - $plrPrev)) <= SpreadDerivationService::TOLERANCE_PP;
                    if ($movedWithPlr) {
                        $evidence = 'The engine treats this loan as fixed-rate (its EIR does not move with the rate, decision D38 applies to floating loans only), but its rate moved with the PLR: ' . $evidence;
                        $s['fixed_moved_with_plr']++;
                    }
                    if ($floating || $movedWithPlr) {
                        $s['floating_moves'] += $floating ? 1 : 0;
                        if ($plrPrev === null || $plrNow === null) {
                            $s['no_reference_rate']++; // fail closed: a floating move with no PLR in force cannot be split into PLR and spread
                        } else {
                            $spreadPrev = $prev['rate'] - $plrPrev;
                            $spreadNow = $m['rate'] - $plrNow;
                            $type = abs($spreadNow - $spreadPrev) <= SpreadDerivationService::TOLERANCE_PP ? 'PLR_RESET' : 'SPREAD_CHANGE';
                            $s[$type === 'PLR_RESET' ? 'plr_resets' : 'spread_changes']++;
                            $key = "RESET|{$contractId}|{$period}";
                            $keys[] = $key;
                            $written = $this->upsertReset($key, [
                                'contract_id' => $contractId, 'account' => $m['account'], 'reporting_period' => $period, 'reset_date' => $date,
                                'event_type' => $type, 'old_reference_rate' => round($plrPrev, 5), 'new_reference_rate' => round($plrNow, 5),
                                'old_rate' => round($prev['rate'], 5), 'new_rate' => round($m['rate'], 5), 'old_spread' => round($spreadPrev, 5), 'new_spread' => round($spreadNow, 5),
                                'new_schedule_version' => ModificationAccountingService::versionInForce($contractId, $date),
                                'suggested_classification' => $type === 'PLR_RESET' ? 'IN_CONTRACT' : null,
                                'evidence' => mb_substr($evidence . ($type === 'SPREAD_CHANGE' ? sprintf('; spread %s to %s', self::pct($spreadPrev), self::pct($spreadNow)) : ''), 0, 500),
                                'recorded_by' => $userId,
                            ]);
                            $s[$written ? 'written' : 'kept']++;
                        }
                    } else {
                        $remaining = $candidates->remainingAt($m['account'], $date);
                        $candidates->upsert([
                            'detection_key' => "RATE|{$contractId}|{$period}", 'contract_id' => $contractId, 'account' => $m['account'], 'event_date' => $date,
                            'source' => 'RATE_CHANGE', 'source_reference' => $evidence, 'suggested_class' => 'UNCERTAIN',
                            'suggestion_reason' => 'The rate charged on a loan the engine treats as fixed-rate (Interest Policy S or M) moved: a contractual reset if the contract provides for it (the B5.4.6 catch-up for a fixed-rate loan), a modification if negotiated (5.4.3); a manual rate change is not automatically a market reset (review item R14, O17).',
                            'old_rate' => $prev['rate'], 'new_rate' => $m['rate'],
                            'new_terms' => $remaining === [] ? null : $candidates->terms($remaining), 'new_flows' => $remaining === [] ? null : $candidates->flows($remaining),
                            'incomplete_reason' => $remaining === [] && ! isset($chartRows[$m['account']]) ? 'E-Banker\'s instalment chart for the loan is not in the extracts, so the revised cash flows cannot be read.' : null,
                        ]);
                        $s['fixed_rate_candidates']++;
                        $rateKeys[] = "RATE|{$contractId}|{$period}";
                    }
                }
                $prev = $m + ['period' => $period];
            }
        }
        $stale = DB::table('rate_reset_events')->where('status', 'DETECTED')->whereNotNull('detection_key')->whereNotIn('detection_key', $keys === [] ? ['-'] : $keys)->pluck('id');
        $s['removed'] = $stale->count();
        if ($s['removed'] > 0) {
            DB::table('rate_reset_events')->whereIn('id', $stale)->delete();
        }
        // fixed-rate moves no longer in the loan book leave the register while no one has acted on them
        $s['removed'] += DB::table('restructure_events')->where('status', 'CANDIDATE')->where('detection_key', 'like', 'RATE|%')
            ->whereNotIn('detection_key', $rateKeys === [] ? ['-'] : $rateKeys)->delete();
        AuditLoggerService::log('Rate Resets Detected', 'rate_reset_events', null, ['new_values' => $s + ['user_id' => $userId]]);

        return $s;
    }

    /** @param list<int> $ids */
    public function propose(array $ids, string $classification, string $reason, int $userId): int
    {
        if (! in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new RuntimeException('Choose in-contract or negotiated.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Give the reason for the classification.');
        }
        if ($classification === 'NEGOTIATED' && $this->everyChangeIsAReset()) {
            throw new RuntimeException('The setting in force for rate_change_classification is "Every rate change is a reset": a move cannot be classified as negotiated.');
        }

        return DB::transaction(function () use ($ids, $classification, $reason, $userId) {
            $n = 0;
            foreach (DB::table('rate_reset_events')->whereIn('id', $ids)->lockForUpdate()->get() as $r) {
                if ($r->status !== 'DETECTED') {
                    throw new RuntimeException("Reset #{$r->id} is not awaiting a classification.");
                }
                ReportingPeriodLock::assertOpen((string) $r->reporting_period, 'a rate reset');
                DB::table('rate_reset_events')->where('id', $r->id)->update(['status' => 'PROPOSED', 'classification' => $classification,
                    'reason' => mb_substr(trim($reason), 0, 1000), 'proposed_by' => $userId, 'proposed_at' => now(), 'updated_at' => now()]);
                $n++;
            }
            AuditLoggerService::log('Rate Reset Classification Proposed', 'rate_reset_events', null, ['new_values' => ['ids' => $ids, 'classification' => $classification, 'reason' => $reason]]);

            return $n;
        });
    }

    /** @param list<int> $ids */
    public function approve(array $ids, int $userId, ?string $label = null): int
    {
        return DB::transaction(function () use ($ids, $userId, $label) {
            $n = 0;
            foreach (DB::table('rate_reset_events')->whereIn('id', $ids)->lockForUpdate()->get() as $r) {
                if ($r->status !== 'PROPOSED') {
                    throw new RuntimeException("Reset #{$r->id} has no proposed classification to approve.");
                }
                if ((int) $r->proposed_by === $userId && ! $this->governance->adminOverrideAllowed()) {
                    throw new RuntimeException("Reset #{$r->id}: the person who proposed a classification cannot approve it.");
                }
                ReportingPeriodLock::assertOpen((string) $r->reporting_period, 'a rate reset');
                if ($r->classification === 'NEGOTIATED' && $this->everyChangeIsAReset()) {
                    throw new RuntimeException('The setting in force for rate_change_classification is "Every rate change is a reset": a negotiated classification cannot be approved.');
                }
                $eventId = null;
                if ($r->classification === 'NEGOTIATED') {
                    // a negotiated re-pricing is a change of the contract: it goes to the restructure register as a candidate
                    $svc = new RestructureCandidateService($this->zone);
                    $remaining = $svc->remainingAt((string) $r->account, (string) $r->reset_date);
                    $eventId = $svc->upsert([
                        'detection_key' => 'RESET|' . $r->id, 'contract_id' => (string) $r->contract_id, 'account' => $r->account, 'event_date' => (string) $r->reset_date,
                        'source' => 'RATE_CHANGE', 'source_reference' => "Rate reset #{$r->id} approved as negotiated: " . $r->evidence,
                        'suggested_class' => 'MODIFICATION', 'suggestion_reason' => 'A re-pricing agreed outside the contract (approved on the rate-reset register as negotiated): a modification under 5.4.3 (O17).',
                        'old_rate' => $r->old_rate, 'new_rate' => $r->new_rate,
                        'new_terms' => $remaining === [] ? null : $svc->terms($remaining), 'new_flows' => $remaining === [] ? null : $svc->flows($remaining),
                        'incomplete_reason' => $remaining === [] ? 'E-Banker\'s instalment chart after the re-pricing is not in the extracts, so the modified cash flows cannot be read.' : null,
                    ]);
                }
                DB::table('rate_reset_events')->where('id', $r->id)->update(['status' => 'APPROVED', 'approved_by' => $userId, 'approver_label' => $label,
                    'approved_at' => now(), 'restructure_event_id' => $eventId, 'updated_at' => now()]);
                $n++;
            }
            AuditLoggerService::log('Rate Reset Approved', 'rate_reset_events', null, ['new_values' => ['ids' => $ids, 'label' => $label]]);

            return $n;
        });
    }

    /** Reject (a reviewer) or withdraw (the proposer): back to DETECTED with the reason kept. */
    public function close(int $id, int $userId, string $action, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give the reason.');
        }
        $r = DB::table('rate_reset_events')->where('id', $id)->first();
        if (! $r || $r->status !== 'PROPOSED' || ($action === 'withdraw' && (int) $r->proposed_by !== $userId) || ! in_array($action, ['reject', 'withdraw'], true)) {
            throw new RuntimeException($action === 'withdraw' ? 'Only the person who proposed a classification can withdraw it, and only before it is approved.' : 'Only a proposed classification can be rejected.');
        }
        DB::table('rate_reset_events')->where('id', $id)->update(['status' => 'DETECTED', 'classification' => null, 'proposed_by' => null, 'proposed_at' => null,
            'close_reason' => mb_substr(ucfirst($action) . 'ed: ' . $reason, 0, 500), 'updated_at' => now()]);
        AuditLoggerService::log('Rate Reset ' . ucfirst($action) . 'ed', 'rate_reset_events', $id, ['new_values' => ['reason' => $reason]]);
    }

    private function everyChangeIsAReset(): bool
    {
        try {
            return $this->governance->get('rate_change_classification') === 'Every rate change is a reset';
        } catch (\Throwable $e) {
            throw new RuntimeException('The reset-or-modification rule (rate_change_classification, O17) has no approved value: ' . $e->getMessage());
        }
    }

    private function upsertReset(string $key, array $row): bool
    {
        $existing = DB::table('rate_reset_events')->where('detection_key', $key)->first(['id', 'status']);
        if ($existing === null) {
            DB::table('rate_reset_events')->insert($row + ['detection_key' => $key, 'status' => 'DETECTED', 'created_at' => now(), 'updated_at' => now()]);

            return true;
        }
        if ($existing->status === 'DETECTED') {
            DB::table('rate_reset_events')->where('id', $existing->id)->update($row + ['updated_at' => now()]);

            return true;
        }

        return false;
    }

    /** @return array<string, list<string>> the dates each account's rate was keyed (P1_04 APPLICABLE_FROM_DATE) */
    private function keyedDates(): array
    {
        $out = [];
        foreach ($this->zone->rateSetupByAccount() as $account => $rows) {
            foreach ($rows as $row) {
                if ($d = InstalmentChartHistory::date((string) ($row['payload']['APPLICABLE_FROM_DATE'] ?? ''))) {
                    $out[$account][] = $d;
                }
            }
            sort($out[$account]);
        }

        return $out;
    }

    public static function plrAt(array $series, string $date): ?float
    {
        $rate = null;
        foreach ($series as $p) {
            if ($p['date'] > $date) {
                break;
            }
            $rate = $p['rate'];
        }

        return $rate;
    }

    /** The PLR's effective date inside (after, upTo], when its rate changed then. */
    public static function plrMovedIn(array $series, string $after, string $upTo): ?string
    {
        $before = self::plrAt($series, $after);
        foreach ($series as $p) {
            if ($p['date'] > $after && $p['date'] <= $upTo && ($before === null || abs($p['rate'] - $before) > 1e-9)) {
                return $p['date'];
            }
        }

        return null;
    }

    private static function nextMonth(string $period): string
    {
        return CarbonImmutable::parse($period . '-01')->addMonth()->format('Y-m');
    }

    private static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') . '%';
    }
}
