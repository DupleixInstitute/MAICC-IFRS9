<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\RateResetEvent;
use App\Models\ReferenceRate;
use App\Services\AuditLoggerService;
use App\Services\Ebanker\InstalmentChartHistory;
use App\Services\Ebanker\LandingZoneReader;
use App\Support\ReportingPeriodLock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The rate-reset register and its maker-checker intake (specification v4 phase
 * P5, sections 3.1, 3.2 and 7.2; built 14 October 2026, reconciled with the
 * reset pipeline the same day).
 *
 * One detector. ResetDetectionService is run first and is the only writer of
 * prime-rate resets: for each loan the contract master states as floating, every
 * change of the PLR series since drawdown on the spread locked at drawdown,
 * dated under plr_mid_period, with its rate periods. This register then sets each
 * reset against the rate E-Banker charged, which is the loan book's month-end
 * rate (section 3.2), the whole month charged at it (section 3.1). For each
 * month whose loan-book rate differs from the month before:
 *   - the prime rate moved in the month: the pipeline's PLR_RESET row of that
 *     month carries the loan book's evidence (rates charged, spreads); where the
 *     pipeline has no reset for it (the loan is held there, or the engine treats
 *     it as fixed-rate while its rate moved with the PLR, review item R14), the
 *     move is still recorded, as a PLR_RESET held from the pipeline (status
 *     BLOCKED, key MOVE|) with the reason, and the pipeline takes it over once it
 *     dates the same reset;
 *   - the spread moved beyond the 0.15 point tolerance of SpreadDerivationService:
 *     a SPREAD_CHANGE, its own event even on the date of a PLR reset (spec v4 P5:
 *     the PLR leg is in-contract by nature, the spread leg is MAIIC re-pricing and
 *     a person classifies it, O17), so the two legs are never one classification;
 *   - a fixed-rate loan whose rate moved, not with the PLR, is not a B5.4.5 reset:
 *     it is written to the restructure register as a candidate (a contractual
 *     reset takes the B5.4.6 catch-up, a negotiated change is a 5.4.3
 *     modification).
 * A pipeline reset the loan book does not show is kept, with that said on its
 * evidence. The Mega Farm programme (MF_07) is outside the EIR engine (D30).
 *
 * The two states on a row: `status` is the pipeline's (DETECTED, SCHEDULED,
 * SOLVED, BLOCKED); `classification_status` is this register's (DETECTED,
 * PROPOSED, APPROVED). One person proposes the classification, a different
 * person approves; a move in a locked period is refused; a move approved as
 * NEGOTIATED becomes a restructure candidate. The governed setting
 * rate_change_classification decides whether NEGOTIATED is open at all ("Every
 * rate change is a reset" closes it).
 *
 * Neither state moves the EIR: it moves by the monthly re-estimation at the rate
 * charged (decision D38, EirRevenueService::eirInForce), prospective with no
 * catch-up, which the ECL reads as the current EIR (currentFloatingEir). The
 * register is the dated evidence of each move and the control over its
 * classification, and does not gate D38.
 */
class RateResetService
{
    public const CLASSIFICATIONS = ['IN_CONTRACT', 'NEGOTIATED'];

    public function __construct(private LandingZoneReader $zone, private ?GovernanceService $governance = null, private ?ResetDetectionService $detector = null)
    {
        $this->governance ??= app(GovernanceService::class);
        $this->detector ??= app(ResetDetectionService::class);
    }

    /**
     * Detect once: the pipeline's resets, then the loan book's evidence and the spread changes.
     *
     * @return array{pipeline:array,floating_moves:int,fixed_moved_with_plr:int,plr_resets:int,plr_resets_held:int,not_in_loan_book:int,spread_changes:int,fixed_rate_candidates:int,no_reference_rate:int,written:int,kept:int,removed:int,details:array}
     */
    public function detect(?int $userId = null, ?string $contractId = null, ?CarbonInterface $asOf = null, string $index = ReferenceRate::DEFAULT_INDEX): array
    {
        $scoped = fn ($q) => $contractId === null ? $q : $q->where('contract_id', $contractId);
        // the register's first version keyed each move RESET|contract|month; one nobody has acted on is detected again below
        $scoped(DB::table('rate_reset_events'))->where('detection_key', 'like', 'RESET|%')
            ->where('classification_status', RateResetEvent::CLASSIFICATION_DETECTED)->where('status', RateResetEvent::STATUS_DETECTED)->delete();

        // 1. the one detector of prime-rate resets, and the rate periods
        $pipeline = $this->detector->detect($contractId, $asOf, $index, $userId);

        // 2. the loan book: the rate E-Banker charged, month by month
        $series = DB::table('reference_rate_series')->where('index_code', $index)->orderBy('effective_date')
            ->get(['effective_date', 'rate'])->map(fn ($r) => ['date' => substr((string) $r->effective_date, 0, 10), 'rate' => (float) $r->rate])->all();
        $contracts = ContractEir::query()->when($contractId, fn ($q) => $q->where('contract_id', $contractId))->get()->keyBy(fn ($c) => (string) $c->contract_id);
        $keyed = $this->keyedDates();
        $candidates = new RestructureCandidateService($this->zone);
        $chartRows = $candidates->chart();

        $rates = [];
        $accounts = [];
        foreach (DB::table('loan_books')->whereIn('contract_id', $contracts->keys()->all() ?: ['-'])->whereNotNull('interest_rate')
            ->orderBy('id')->get(['contract_id', 'reporting_period', 'interest_rate', 'external_identity_id']) as $l) {
            $period = substr(str_replace('-', '', (string) $l->reporting_period), 0, 4) . '-' . substr(str_replace('-', '', (string) $l->reporting_period), 4, 2);
            $account = $l->external_identity_id ?: str_pad((string) $l->contract_id, 15, '0', STR_PAD_LEFT);
            $accounts[(string) $l->contract_id] = $account;
            if ((float) $l->interest_rate <= 0) {
                continue; // a nil rate is a closed or unrated month, not a move to nil (as the D38 re-estimation reads it)
            }
            $rates[(string) $l->contract_id][$period] = ['rate' => (float) $l->interest_rate, 'account' => $account];
        }
        $pipelineRows = $scoped(DB::table('rate_reset_events'))->where('event_type', RateResetEvent::TYPE_PLR_RESET)
            ->where(fn ($q) => $q->whereNull('detection_key')->orWhere('detection_key', 'not like', 'MOVE|%'))->get()->groupBy('contract_id');

        $s = ['pipeline' => array_diff_key($pipeline, ['details' => true]), 'floating_moves' => 0, 'fixed_moved_with_plr' => 0, 'plr_resets' => 0, 'plr_resets_held' => 0,
            'not_in_loan_book' => 0, 'spread_changes' => 0, 'fixed_rate_candidates' => 0, 'no_reference_rate' => 0, 'written' => 0, 'kept' => 0, 'removed' => 0];
        $keys = [];
        $rateKeys = [];
        $matched = [];
        foreach ($rates as $cid => $months) {
            ksort($months);
            $contract = $contracts[$cid];
            $floating = ResetDetectionService::followsPrime($contract)[0] === true;
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
                    if (($floating || $movedWithPlr) && ($plrPrev === null || $plrNow === null)) {
                        $s['no_reference_rate']++; // fail closed: a move with no PLR in force cannot be split into PLR and spread
                    } elseif ($floating || $movedWithPlr) {
                        $s['floating_moves'] += $floating ? 1 : 0;
                        $spreadPrev = $prev['rate'] - $plrPrev;
                        $spreadNow = $m['rate'] - $plrNow;
                        $charged = ['account' => $m['account'], 'old_rate' => round($prev['rate'], 5), 'new_rate' => round($m['rate'], 5),
                            'old_spread' => round($spreadPrev, 5), 'new_spread' => round($spreadNow, 5)];
                        $resetDate = null;
                        if ($plrMoved !== null) {
                            // the prime-rate leg: the pipeline's reset of this month carries the loan book's evidence
                            $row = ($pipelineRows[$cid] ?? collect())->first(fn ($r) => ($d = substr((string) ($r->plr_effective_date ?? $r->reset_date), 0, 10)) > $prevEnd && $d <= $end);
                            $date = $row ? substr((string) $row->reset_date, 0, 10) : ($this->detector->applicationDate(CarbonImmutable::parse($plrMoved), $contract) ?? CarbonImmutable::parse($plrMoved))->toDateString();
                            $row ??= ($pipelineRows[$cid] ?? collect())->first(fn ($r) => substr((string) $r->reset_date, 0, 10) === $date);
                            if ($row) {
                                $matched[$row->id] = true;
                                $pipelineRate = $row->new_contractual_rate !== null ? (float) $row->new_contractual_rate * 100 : null;
                                $note = $pipelineRate !== null && abs($pipelineRate - $m['rate']) > SpreadDerivationService::TOLERANCE_PP
                                    ? '; prime plus the spread locked at drawdown gives ' . self::pct($pipelineRate) : '';
                                $written = $this->enrich($row, $charged + ['evidence' => mb_substr($evidence . $note, 0, 500)]);
                            } else {
                                $why = $movedWithPlr ? 'the engine treats the loan as fixed-rate, so the reset pipeline builds no Reset version for it (manual_policy_handling, R14)'
                                    : 'the reset pipeline has no reset for it: ' . ($pipeline['details'][$cid]['reason'] ?? 'the prime-rate change falls outside the loan\'s dated life');
                                $key = "MOVE|{$cid}|{$period}";
                                $keys[] = $key;
                                $written = $this->upsertMove($key, $charged + [
                                    'contract_id' => $cid, 'reporting_period' => $period, 'reset_date' => $date, 'plr_effective_date' => $plrMoved,
                                    'event_type' => RateResetEvent::TYPE_PLR_RESET, 'old_reference_rate' => round($plrPrev, 5), 'new_reference_rate' => round($plrNow, 5),
                                    'index_code' => $index, 'suggested_classification' => 'IN_CONTRACT', 'evidence' => mb_substr($evidence, 0, 500),
                                    'status' => RateResetEvent::STATUS_BLOCKED, 'note' => mb_substr('Held from the reset pipeline: ' . $why, 0, 1000), 'recorded_by' => $userId,
                                ]);
                                $s['plr_resets_held']++;
                            }
                            $resetDate = $date;
                            $s['plr_resets']++;
                            $s[$written ? 'written' : 'kept']++;
                        }
                        if (abs($spreadNow - $spreadPrev) > SpreadDerivationService::TOLERANCE_PP) {
                            // the spread leg: its own event, for a person to classify (O17)
                            $key = "SPREAD|{$cid}|{$period}";
                            $keys[] = $key;
                            $written = $this->upsertSpread($key, $charged + [
                                'contract_id' => $cid, 'reporting_period' => $period, 'reset_date' => $resetDate ?? ($keyedIn[0] ?? $start), 'plr_effective_date' => $plrMoved,
                                'event_type' => RateResetEvent::TYPE_SPREAD_CHANGE, 'old_reference_rate' => round($plrPrev, 5), 'new_reference_rate' => round($plrNow, 5),
                                'index_code' => $index, 'suggested_classification' => null, 'status' => RateResetEvent::STATUS_DETECTED, 'recorded_by' => $userId,
                                'evidence' => mb_substr($evidence . sprintf('; spread %s to %s', self::pct($spreadPrev), self::pct($spreadNow)), 0, 500),
                            ]);
                            $s['spread_changes']++;
                            $s[$written ? 'written' : 'kept']++;
                        }
                    } else {
                        $date = $plrMoved ?? ($keyedIn[0] ?? $start);
                        $remaining = $candidates->remainingAt($m['account'], $date);
                        $candidates->upsert([
                            'detection_key' => "RATE|{$cid}|{$period}", 'contract_id' => $cid, 'account' => $m['account'], 'event_date' => $date,
                            'source' => 'RATE_CHANGE', 'source_reference' => $evidence, 'suggested_class' => 'UNCERTAIN',
                            'suggestion_reason' => 'The rate charged on a loan the engine treats as fixed-rate (Interest Policy S or M) moved: a contractual reset if the contract provides for it (the B5.4.6 catch-up for a fixed-rate loan), a modification if negotiated (5.4.3); a manual rate change is not automatically a market reset (review item R14, O17).',
                            'old_rate' => $prev['rate'], 'new_rate' => $m['rate'],
                            'new_terms' => $remaining === [] ? null : $candidates->terms($remaining), 'new_flows' => $remaining === [] ? null : $candidates->flows($remaining),
                            'incomplete_reason' => $remaining === [] && ! isset($chartRows[$m['account']]) ? 'E-Banker\'s instalment chart for the loan is not in the extracts, so the revised cash flows cannot be read.' : null,
                        ]);
                        $s['fixed_rate_candidates']++;
                        $rateKeys[] = "RATE|{$cid}|{$period}";
                    }
                }
                $prev = $m + ['period' => $period];
            }
        }

        // 3. a pipeline reset the loan book does not show as a move of the rate charged: kept, and said so
        foreach ($pipelineRows->flatten(1) as $row) {
            if (isset($matched[$row->id])) {
                continue;
            }
            $s['not_in_loan_book']++;
            $month = substr((string) ($row->plr_effective_date ?? $row->reset_date), 0, 7);
            $book = $rates[(string) $row->contract_id][$month]['rate'] ?? null;
            $this->enrich($row, ['account' => $accounts[(string) $row->contract_id] ?? null, 'evidence' => mb_substr(
                'PLR changed on ' . substr((string) ($row->plr_effective_date ?? $row->reset_date), 0, 10) . ' (' . self::pct((float) $row->old_reference_rate) . ' to ' . self::pct((float) $row->new_reference_rate) . '); '
                . ($book === null ? "no loan-book rate for {$month}" : "the loan book shows no move of the rate charged into {$month} (" . self::pct($book) . ')'), 0, 500)]);
        }

        // 4. a move the loan book no longer shows leaves the register while no one has acted on it
        $stale = $scoped(DB::table('rate_reset_events'))->where('classification_status', RateResetEvent::CLASSIFICATION_DETECTED)
            ->where(fn ($q) => $q->where('detection_key', 'like', 'SPREAD|%')->orWhere('detection_key', 'like', 'MOVE|%'))
            ->whereNotIn('detection_key', $keys === [] ? ['-'] : $keys)->pluck('id');
        $s['removed'] = $stale->count();
        if ($s['removed'] > 0) {
            DB::table('rate_reset_events')->whereIn('id', $stale)->delete();
        }
        // fixed-rate moves no longer in the loan book leave the restructure register likewise
        $s['removed'] += $scoped(DB::table('restructure_events'))->where('status', 'CANDIDATE')->where('detection_key', 'like', 'RATE|%')
            ->whereNotIn('detection_key', $rateKeys === [] ? ['-'] : $rateKeys)->delete();
        AuditLoggerService::log('Rate Resets Detected', 'rate_reset_events', null, ['new_values' => array_diff_key($s, ['pipeline' => true]) + ['user_id' => $userId, 'contract_filter' => $contractId]]);

        return $s + ['details' => $pipeline['details']];
    }

    /** The loan book's evidence on a pipeline reset; a classified row keeps the evidence it was classified on. */
    private function enrich(object $row, array $values): bool
    {
        if (($row->classification_status ?? RateResetEvent::CLASSIFICATION_DETECTED) !== RateResetEvent::CLASSIFICATION_DETECTED) {
            return false;
        }
        DB::table('rate_reset_events')->where('id', $row->id)->update(array_filter($values, fn ($v) => $v !== null) + ['updated_at' => now()]);

        return true;
    }

    /** A move held from the pipeline (key MOVE|): written, or refreshed while no one has acted on it. */
    private function upsertMove(string $key, array $row): bool
    {
        $existing = DB::table('rate_reset_events')->where('detection_key', $key)->first(['id', 'classification_status']);
        if ($existing === null) {
            DB::table('rate_reset_events')->insert($row + ['detection_key' => $key, 'classification_status' => RateResetEvent::CLASSIFICATION_DETECTED, 'created_at' => now(), 'updated_at' => now()]);

            return true;
        }
        if ($existing->classification_status === RateResetEvent::CLASSIFICATION_DETECTED) {
            DB::table('rate_reset_events')->where('id', $existing->id)->update($row + ['updated_at' => now()]);

            return true;
        }

        return false;
    }

    /**
     * A spread change: written, or refreshed while no one has acted on it. One the register's
     * first version recorded for the same month (key RESET|) and a person has classified is
     * the same event and stays as it is.
     */
    private function upsertSpread(string $key, array $row): bool
    {
        $existing = DB::table('rate_reset_events')->where('detection_key', $key)->first(['id', 'classification_status'])
            ?? DB::table('rate_reset_events')->where('contract_id', $row['contract_id'])->where('reporting_period', $row['reporting_period'])
                ->where('event_type', RateResetEvent::TYPE_SPREAD_CHANGE)->where('detection_key', 'like', 'RESET|%')->first(['id', 'classification_status']);
        if ($existing === null) {
            DB::table('rate_reset_events')->insert($row + ['detection_key' => $key, 'classification_status' => RateResetEvent::CLASSIFICATION_DETECTED, 'created_at' => now(), 'updated_at' => now()]);

            return true;
        }
        if ($existing->classification_status === RateResetEvent::CLASSIFICATION_DETECTED) {
            DB::table('rate_reset_events')->where('id', $existing->id)->update($row + ['detection_key' => $key, 'updated_at' => now()]);

            return true;
        }

        return false;
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
                if ($r->classification_status !== 'DETECTED') {
                    throw new RuntimeException("Reset #{$r->id} is not awaiting a classification.");
                }
                ReportingPeriodLock::assertOpen((string) $r->reporting_period, 'a rate reset');
                DB::table('rate_reset_events')->where('id', $r->id)->update(['classification_status' => 'PROPOSED', 'classification' => $classification,
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
                if ($r->classification_status !== 'PROPOSED') {
                    throw new RuntimeException("Reset #{$r->id} has no proposed classification to approve.");
                }
                if ((int) $r->proposed_by === $userId && ! \App\Support\MakerChecker::selfApprovalAllowed('Rate reset', 'rate_reset_events', (int) $r->id, $userId)) {
                    throw new RuntimeException("Reset #{$r->id}: the person who proposed a classification cannot approve it.");
                }
                // the evidence the workflow requires before approval (Staging Module Specification v1, section 8.6)
                \App\Support\Approvals::assertEvidence('rate-reset', (int) $r->id, $label);
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
                DB::table('rate_reset_events')->where('id', $r->id)->update(['classification_status' => 'APPROVED', 'approved_by' => $userId, 'approver_label' => $label,
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
        if ($r && $r->classification_status === 'PROPOSED' && $action === 'reject' && (int) $r->proposed_by === $userId) {
            throw new RuntimeException('The person who proposed a classification cannot reject it; withdraw it instead.');
        }
        if (! $r || $r->classification_status !== 'PROPOSED' || ($action === 'withdraw' && (int) $r->proposed_by !== $userId) || ! in_array($action, ['reject', 'withdraw'], true)) {
            throw new RuntimeException($action === 'withdraw' ? 'Only the person who proposed a classification can withdraw it, and only before it is approved.' : 'Only a proposed classification can be rejected.');
        }
        DB::table('rate_reset_events')->where('id', $id)->update(['classification_status' => 'DETECTED', 'classification' => null, 'proposed_by' => null, 'proposed_at' => null,
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
