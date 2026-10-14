<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Support\ReportingPeriodLock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The governed restructure register (specification v4 sections 3.9 and P7;
 * built 14 October 2026), on the pattern of the write-off register.
 *
 * A candidate becomes a confirmed event only by two people: one proposes its
 * classification with a reason, a different person approves. A proposal can be
 * withdrawn by its maker or rejected by a reviewer, which returns it to the
 * candidates with the reason kept; a confirmed event can be reversed with a
 * reason while its month is open. Nothing is confirmed by the build or the
 * bootstrap (review item R10).
 *
 * What a confirmed classification does:
 *   MODIFICATION       a change of the contract (IFRS 9 5.4.3): the new chart is
 *                      loaded as schedule version N+1 and the revenue run of the
 *                      event's month applies the modification test (3.3.2 and
 *                      B3.3.6 by analogy, modification_threshold, plus the
 *                      qualitative factors recorded here): modification gain or
 *                      loss, or derecognition and version N+1 at a new EIR.
 *   REVISED_ESTIMATE   a revision of the expected cash flows under the existing
 *                      contract (B5.4.6): version N+1 and the catch-up.
 *   CONTRACTUAL_RESET  a change the contract provides for. On a floating-rate
 *                      loan it is the B5.4.5 reset the monthly re-estimation
 *                      already carries (decision D38): nothing more is booked. On
 *                      a fixed-rate loan the revised flows take the B5.4.6 catch-up.
 *   CORRECTION         a correction of E-Banker's record: no accounting.
 *   NOT_A_CHANGE       the evidence did not change the contract: no accounting.
 */
class RestructureRegisterService
{
    public const CLASSIFICATIONS = ['MODIFICATION', 'REVISED_ESTIMATE', 'CONTRACTUAL_RESET', 'CORRECTION', 'NOT_A_CHANGE'];

    /** The qualitative factors that derecognise whatever the 10 percent test says (IFRS 9 3.2.3, B3.3.6 by analogy; O16). */
    public const QUALITATIVE_FACTORS = [
        'counterparty' => 'A different borrower (a change of counterparty)',
        'currency' => 'A change of currency',
        'sppi' => 'A feature that fails the SPPI test (equity conversion, profit share)',
        'instrument' => 'A change of instrument (debt to equity, a new product)',
    ];

    public function __construct(private ?GovernanceService $governance = null)
    {
        $this->governance ??= app(GovernanceService::class);
    }

    public function propose(int $id, array $data, int $userId): void
    {
        DB::transaction(function () use ($id, $data, $userId) {
            $e = DB::table('restructure_events')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'CANDIDATE') {
                throw new RuntimeException('Only a candidate can be proposed.');
            }
            $class = (string) ($data['classification'] ?? '');
            if (! in_array($class, self::CLASSIFICATIONS, true)) {
                throw new RuntimeException('Choose the classification.');
            }
            if (trim((string) ($data['reason'] ?? '')) === '') {
                throw new RuntimeException('Give the reason for the classification.');
            }
            $this->assertCanAccount($e, $class);
            $factors = array_values(array_intersect(array_keys(self::QUALITATIVE_FACTORS), (array) ($data['qualitative_factors'] ?? [])));
            if ($factors !== [] && $class !== 'MODIFICATION') {
                throw new RuntimeException('Qualitative factors are recorded only on a modification.');
            }
            $fv = isset($data['fair_value']) && $data['fair_value'] !== '' && $data['fair_value'] !== null ? (float) $data['fair_value'] : null;
            if ($fv !== null && ($fv <= 0 || trim((string) ($data['fair_value_basis'] ?? '')) === '')) {
                throw new RuntimeException('A fair value must be more than nil and state its basis.');
            }
            DB::table('restructure_events')->where('id', $id)->update([
                'status' => 'PROPOSED', 'classification' => $class, 'reason' => mb_substr(trim((string) $data['reason']), 0, 1000),
                'qualitative_factors' => $factors === [] ? null : json_encode($factors),
                'board_approval_ref' => ($data['board_approval_ref'] ?? null) ?: null, 'board_approval_date' => ($data['board_approval_date'] ?? null) ?: null,
                'fair_value' => $fv, 'fair_value_basis' => $fv === null ? null : mb_substr(trim((string) $data['fair_value_basis']), 0, 500),
                'modification_fees' => isset($data['modification_fees']) && $data['modification_fees'] !== '' && $data['modification_fees'] !== null ? round((float) $data['modification_fees'], 2) : null,
                'proposed_by' => $userId, 'proposed_at' => now(), 'approved_by' => null, 'approved_at' => null, 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Restructure Classification Proposed', 'restructure_events', $id, ['new_values' => [
                'contract_id' => $e->contract_id, 'event_date' => $e->event_date, 'classification' => $class, 'reason' => $data['reason'],
                'qualitative_factors' => $factors, 'board_approval_ref' => $data['board_approval_ref'] ?? null]]);
        });
    }

    /** A different person approves; an event that changes the accounting gets its version N+1 and its contract_modifications row. */
    public function approve(int $id, int $userId, ?string $label = null): void
    {
        DB::transaction(function () use ($id, $userId, $label) {
            $e = DB::table('restructure_events')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'PROPOSED') {
                throw new RuntimeException('Only a proposed classification can be approved.');
            }
            if ((int) $e->proposed_by === $userId && ! $this->governance->adminOverrideAllowed()) {
                throw new RuntimeException('The person who proposed a classification cannot approve it.');
            }
            $this->assertCanAccount($e, (string) $e->classification);
            $modification = null;
            if ($this->changesAccounting($e)) {
                $modification = $this->recordModification($e, $userId);
            }
            DB::table('restructure_events')->where('id', $id)->update(['status' => 'CONFIRMED', 'approved_by' => $userId, 'approver_label' => $label,
                'approved_at' => now(), 'updated_at' => now()]);
            AuditLoggerService::log('Restructure Event Confirmed', 'restructure_events', $id, ['new_values' => [
                'contract_id' => $e->contract_id, 'event_date' => $e->event_date, 'classification' => $e->classification,
                'contract_modification_id' => $modification, 'label' => $label, 'maker_checker_override' => (int) $e->proposed_by === $userId]]);
        });
    }

    /** Reject (a reviewer), withdraw (the proposer): back to the candidates. Reverse a confirmed event while its month is open. */
    public function close(int $id, int $userId, string $action, string $reason): void
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Give the reason.');
        }
        DB::transaction(function () use ($id, $userId, $action, $reason) {
            $e = DB::table('restructure_events')->where('id', $id)->lockForUpdate()->first();
            if (! $e) {
                throw new RuntimeException('No such event.');
            }
            // the proposer withdraws; a rejection is a second person's act (documentation audit, 14 October 2026)
            if ($action === 'reject' && $e->status === 'PROPOSED' && (int) $e->proposed_by === $userId) {
                throw new RuntimeException('The person who proposed a classification cannot reject it; withdraw it instead.');
            }
            $ok = match ($action) {
                'reject' => $e->status === 'PROPOSED',
                'withdraw' => $e->status === 'PROPOSED' && (int) $e->proposed_by === $userId,
                'reverse' => $e->status === 'CONFIRMED',
                default => false,
            };
            if (! $ok) {
                throw new RuntimeException(match ($action) {
                    'withdraw' => 'Only the person who proposed a classification can withdraw it, and only before it is approved.',
                    'reverse' => 'Only a confirmed event can be reversed.',
                    default => 'Only a proposed classification can be rejected.',
                });
            }
            if ($action === 'reverse') {
                foreach (DB::table('contract_modifications')->where('restructure_event_id', $id)->where('status', '!=', 'REVERSED')->get() as $m) {
                    ReportingPeriodLock::assertOpen($m->reporting_period, 'reversing a restructure');
                    if (DB::table('contract_modifications')->where('contract_id', $m->contract_id)->where('new_schedule_version', '>', $m->new_schedule_version)->where('status', '!=', 'REVERSED')->exists()) {
                        throw new RuntimeException('A later restructure of this loan is confirmed: reverse that one first.');
                    }
                    DB::table('contract_modifications')->where('id', $m->id)->update(['status' => 'REVERSED', 'updated_at' => now()]);
                    DB::table('contract_cashflow_schedule')->where('contract_id', $m->contract_id)->where('schedule_version', $m->new_schedule_version)->delete();
                }
            }
            DB::table('restructure_events')->where('id', $id)->update([
                'status' => 'CANDIDATE', 'classification' => null, 'qualitative_factors' => null, 'fair_value' => null, 'fair_value_basis' => null, 'modification_fees' => null,
                'proposed_by' => null, 'proposed_at' => null, 'approved_by' => null, 'approver_label' => null, 'approved_at' => null,
                'closed_by' => $userId, 'closed_at' => now(), 'close_reason' => mb_substr(ucfirst($action) . 'ed: ' . $reason, 0, 500), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Restructure ' . ucfirst($action) . 'ed', 'restructure_events', $id, ['new_values' => ['contract_id' => $e->contract_id,
                'classification' => $e->classification, 'reason' => $reason]]);
        });
    }

    /**
     * Whether a confirmed event of this class changes the gross carrying amount. An event on
     * or before the take-on of 31 July 2024 never does: the engine's roll-forward starts in
     * August 2024 from the balance taken on, and that balance and the take-on EIR (D41)
     * already carry a restructure made before it (the migration worksheet, CV_10). It is
     * history and disclosure (the RBM return, paragraph 16(1)), not a new gain or loss.
     */
    public function changesAccounting(object $e): bool
    {
        if (self::beforeTakeon($e->event_date ?? null)) {
            return false;
        }

        return match ((string) $e->classification) {
            'MODIFICATION', 'REVISED_ESTIMATE' => true,
            'CONTRACTUAL_RESET' => ! $this->isFloating((string) $e->contract_id),
            default => false,
        };
    }

    /**
     * Fail closed: a classification that changes the accounting needs a date, the
     * old terms and the new schedule, and its month must be open.
     */
    private function assertCanAccount(object $e, string $class): void
    {
        $probe = (object) ['classification' => $class, 'contract_id' => $e->contract_id, 'event_date' => $e->event_date];
        if (! $this->changesAccounting($probe)) {
            return;
        }
        if ($e->event_date === null) {
            throw new RuntimeException('This candidate has no effective date, so it cannot be booked as a ' . strtolower(str_replace('_', ' ', $class)) . ': ' . ($e->incomplete_reason ?? 'no date') . ' Classify it as a correction or not a change, or obtain the date.');
        }
        if ($e->incomplete_reason !== null) {
            throw new RuntimeException('This candidate cannot be booked: ' . $e->incomplete_reason);
        }
        $flows = json_decode((string) $e->new_flows, true) ?: [];
        if ($flows === []) {
            throw new RuntimeException('E-Banker\'s schedule after the event is not on record, so the modified cash flows cannot be read: the event cannot be booked until the chart in force after ' . $e->event_date . ' is landed.');
        }
        ReportingPeriodLock::assertOpen(substr((string) $e->event_date, 0, 7), 'a restructure');
    }

    /** Load the new chart as schedule version N+1 and open the contract_modifications row for the revenue run. */
    private function recordModification(object $e, int $userId): int
    {
        $flows = json_decode((string) $e->new_flows, true) ?: [];
        $old = (int) (DB::table('contract_cashflow_schedule')->where('contract_id', $e->contract_id)->max('schedule_version') ?? 1);
        $new = max($old, 1) + 1;
        $now = now();
        // typed, where the reset pipeline's schedule_type is in place, so a rebuild of the Reset versions never takes it for one
        $typed = \Illuminate\Support\Facades\Schema::hasColumn('contract_cashflow_schedule', 'schedule_type') ? ['schedule_type' => 'RESTRUCTURE'] : [];
        foreach ($flows as $f) {
            DB::table('contract_cashflow_schedule')->insert([
                'contract_id' => $e->contract_id, 'schedule_version' => $new, 'effective_from' => $e->event_date, 'due_date' => $f['due_date'],
                'principal_due' => round((float) $f['principal'], 2), 'interest_due' => round((float) $f['amount'] - (float) $f['principal'], 2), 'fee_due' => 0,
                'schedule_source' => 'IMPORTED', 'created_at' => $now, 'updated_at' => $now,
            ] + $typed);
        }
        $stage = DB::table('loan_books')->where('contract_id', $e->contract_id)->where('reporting_period', substr((string) $e->event_date, 0, 7))
            ->orderByDesc('id')->value('ifrs9stage_post_qualitative');

        return (int) DB::table('contract_modifications')->insertGetId([
            'restructure_event_id' => $e->id, 'contract_id' => $e->contract_id, 'modification_date' => $e->event_date,
            'reporting_period' => substr((string) $e->event_date, 0, 7), 'classification' => $e->classification,
            'old_schedule_version' => max($old, 1), 'new_schedule_version' => $new, 'qualitative_factors' => $e->qualitative_factors,
            'modification_fees' => $e->modification_fees, 'fair_value' => $e->fair_value,
            'lifetime_ecl_before' => $stage === null ? null : (int) $stage >= 2,
            'rate_before' => $e->old_rate, 'rate_after' => $e->new_rate,
            'status' => 'PENDING', 'approved_by' => $userId, 'approved_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public static function beforeTakeon(?string $date): bool
    {
        return $date !== null && $date !== '' && substr($date, 0, 10) <= \App\Services\Ebanker\TakeonLandingService::TAKEON_DATE;
    }

    private function isFloating(string $contractId): bool
    {
        return strtoupper((string) DB::table('contract_eir')->where('contract_id', $contractId)->value('rate_type')) === 'FLOATING';
    }

    /**
     * Restructured facilities for the RBM return (directive para 16(1)): every loan
     * with a confirmed modification on or before the date, with its Board approval.
     *
     * @return list<array{contract_id:string,customer_name:?string,event_date:string,classification:string,board_approval_ref:?string,board_approval_date:?string,treatment:?string}>
     */
    public function restructuredBy(string $date): array
    {
        $rows = DB::table('restructure_events')->where('status', 'CONFIRMED')->where('classification', 'MODIFICATION')
            ->whereNotNull('event_date')->where('event_date', '<=', $date)->orderBy('event_date')->get();
        $treatments = DB::table('contract_modifications')->whereIn('restructure_event_id', $rows->pluck('id')->all() ?: [0])->where('status', '!=', 'REVERSED')
            ->pluck('treatment', 'restructure_event_id');

        return $rows->map(fn ($e) => ['contract_id' => (string) $e->contract_id, 'customer_name' => $e->customer_name, 'event_date' => (string) $e->event_date,
            'classification' => (string) $e->classification, 'board_approval_ref' => $e->board_approval_ref, 'board_approval_date' => $e->board_approval_date,
            'treatment' => $treatments[$e->id] ?? null])->all();
    }

    /**
     * The modification disclosures for a period (IFRS 7.35J and 7.20A), from the
     * applied contract_modifications rows: for the note service to show.
     * $contracts limits them to the loans of a scope (a portfolio); null is the whole book.
     *
     * @param  list<string>|null  $contracts
     * @return array{from:string,to:string,lifetime_ecl_modified:array{count:int,amortised_cost_before:float,net_gain_loss:float},all_modified:array{count:int,amortised_cost_before:float,net_gain_loss:float},derecognised:array{count:int,carrying_amount:float,gain_loss:float},lifetime_modified_now_12_month:array{count:int,gross_carrying_amount:float},catch_up:array{count:int,adjustment:float},pending:int,blocked:int}
     */
    public function ifrs7Disclosures(string $from, string $to, ?array $contracts = null): array
    {
        $scope = fn ($q) => $contracts === null ? $q : $q->whereIn('contract_id', array_map('strval', $contracts) ?: ['-']);
        $rows = $scope(DB::table('contract_modifications')->whereBetween('modification_date', [$from, $to])->where('status', '!=', 'REVERSED'))->get();
        $applied = $rows->where('status', 'APPLIED');
        $mod = $applied->where('treatment', 'MODIFICATION');
        $life = $mod->filter(fn ($m) => (bool) $m->lifetime_ecl_before);
        $der = $applied->where('treatment', 'DERECOGNITION');
        $cu = $applied->where('treatment', 'CATCH_UP');
        // 35J(b): modified at a time the allowance was lifetime ECL, and whose allowance changed to 12-month ECL
        // during the period: Stage 1 at the period end, and on lifetime ECL (Stage 2 or 3) in the period after the
        // modification, at the month-end before the period or a month-end within it, or modified within it
        $ever = $scope(DB::table('contract_modifications')->where('status', 'APPLIED')->where('treatment', 'MODIFICATION')->where('lifetime_ecl_before', true)
            ->where('modification_date', '<=', $to))->get(['contract_id', 'modification_date'])
            ->groupBy(fn ($m) => (string) $m->contract_id)->map(fn ($g) => (string) $g->min('modification_date'));
        $closingMonth = substr($to, 0, 7);
        $openingMonth = CarbonImmutable::parse($from)->subMonthNoOverflow()->format('Y-m');
        $now12m = $ever->isEmpty() ? collect() : DB::table('loan_books')->where('reporting_period', $closingMonth)->whereIn('contract_id', $ever->keys()->all())
            ->where('ifrs9stage_post_qualitative', 1)->get(['contract_id', 'carrying_amount'])
            ->filter(function ($l) use ($ever, $from, $openingMonth, $closingMonth) {
                $modified = $ever[(string) $l->contract_id];
                if ($modified >= $from) {
                    return true; // modified within the period while lifetime ECL, Stage 1 at its end
                }
                $since = max($openingMonth, substr($modified, 0, 7));

                return DB::table('loan_books')->where('contract_id', $l->contract_id)->where('reporting_period', '>=', $since)->where('reporting_period', '<', $closingMonth)
                    ->whereIn('ifrs9stage_post_qualitative', [2, 3])->exists();
            });

        return [
            'from' => $from, 'to' => $to,
            // 35J(a): modified while the allowance was lifetime ECL: amortised cost before, and the net modification gain or loss
            'lifetime_ecl_modified' => ['count' => $life->count(), 'amortised_cost_before' => round((float) $life->sum('carrying_amount_before'), 2), 'net_gain_loss' => round((float) $life->sum('gain_loss'), 2)],
            'all_modified' => ['count' => $mod->count(), 'amortised_cost_before' => round((float) $mod->sum('carrying_amount_before'), 2), 'net_gain_loss' => round((float) $mod->sum('gain_loss'), 2)],
            // 20A: the gain or loss on derecognising amortised-cost assets, here those derecognised by a substantial modification
            'derecognised' => ['count' => $der->count(), 'carrying_amount' => round((float) $der->sum('carrying_amount_before'), 2), 'gain_loss' => round((float) $der->sum('gain_loss'), 2)],
            'lifetime_modified_now_12_month' => ['count' => $now12m->count(), 'gross_carrying_amount' => round((float) $now12m->sum('carrying_amount'), 2)],
            'catch_up' => ['count' => $cu->count(), 'adjustment' => round((float) $cu->sum('gain_loss'), 2)],
            'pending' => $rows->where('status', 'PENDING')->count(), 'blocked' => $rows->where('status', 'BLOCKED')->count(),
        ];
    }

    /**
     * What the register holds that is not (yet) a confirmed event, as at a date: candidates no
     * person has classified, and classifications proposed and awaiting a second person. A
     * candidate with no date counts (its date is unknown). Detection never confirms (R10).
     *
     * @param  list<string>|null  $contracts
     * @return array{candidates:int,proposed:int,confirmed_in_period:int}
     */
    public static function awaitingConfirmation(string $from, string $to, ?array $contracts = null): array
    {
        $q = fn () => DB::table('restructure_events')->when($contracts !== null, fn ($q) => $q->whereIn('contract_id', array_map('strval', $contracts) ?: ['-']));
        $byDate = fn ($b) => $b->where(fn ($w) => $w->whereNull('event_date')->orWhere('event_date', '<=', $to));

        return [
            'candidates' => $byDate($q()->where('status', 'CANDIDATE'))->count(),
            'proposed' => $byDate($q()->where('status', 'PROPOSED'))->count(),
            'confirmed_in_period' => $q()->where('status', 'CONFIRMED')->whereBetween('event_date', [$from, $to])->count(),
        ];
    }

    /** The months a confirmed event of the period has yet to reach the revenue run, for the screens. */
    public static function monthOf(?string $date): ?string
    {
        return $date === null ? null : CarbonImmutable::parse($date)->format('Y-m');
    }
}
