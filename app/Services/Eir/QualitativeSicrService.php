<?php

namespace App\Services\Eir;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\LoanBookBuildService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The qualitative SICR register (IFRS 9 5.5.3, B5.5.17 and Appendix A).
 *
 * A trigger is one item of the SICR item list raised against one contract
 * or against every loan of one customer, with a reason, the evidence, the
 * dates it is in force and two people: the one who raised it and a second
 * person who approves it. The item says the stage it implies: 2 for a
 * significant increase in credit risk, 3 for objective evidence that the
 * loan is credit-impaired. The stage is copied onto the trigger when it is
 * raised, so a later edit of the item does not rewrite a stage already
 * approved.
 *
 * The staging engine reads the approved triggers in force at the month-end
 * it stages (approved or since closed, effective_from on or before the
 * month-end, effective_to empty or on or after it), and lifts the loan to
 * the highest stage they imply. A trigger never moves a loan down. Closing
 * a trigger sets its end date, so a month staged again later still sees the
 * trigger for the months it covered; the governed cure period then decides
 * when the loan may move down.
 */
class QualitativeSicrService
{
    public const SCOPES = ['contract', 'customer'];

    public const STATUSES = ['PROPOSED', 'APPROVED', 'REJECTED', 'CLOSED'];

    /**
     * Raise a trigger. It waits for a second person before it reaches any loan.
     *
     * @param array{item_id:int,scope?:string,contract_id?:?string,customer_id?:?string,reason:string,evidence?:?string,effective_from:string,effective_to?:?string,is_demo?:bool} $data
     */
    public function propose(array $data, int $userId): int
    {
        $item = DB::table('finance_sicr_items')->where('id', (int) ($data['item_id'] ?? 0))->first();
        if ($item === null) {
            throw new RuntimeException('Choose an item from the SICR item list.');
        }
        if (! $item->active) {
            throw new RuntimeException("The item \"{$item->name}\" is switched off; switch it on before raising it.");
        }
        $scope = (string) ($data['scope'] ?? 'contract');
        if (! in_array($scope, self::SCOPES, true)) {
            throw new RuntimeException('A trigger is raised on one contract or on every loan of one customer.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('A trigger needs a reason: what happened to the borrower.');
        }
        $evidence = trim((string) ($data['evidence'] ?? ''));
        if ($item->evidence_required && $evidence === '') {
            throw new RuntimeException("The item \"{$item->name}\" needs evidence: name the document, its date and where it is filed.");
        }
        $from = $this->date($data['effective_from'] ?? null, 'The date the trigger applies from');
        $to = isset($data['effective_to']) && $data['effective_to'] !== '' && $data['effective_to'] !== null ? $this->date($data['effective_to'], 'The end date') : null;
        if ($to !== null && $to < $from) {
            throw new RuntimeException('The end date cannot be before the date the trigger applies from.');
        }
        // no back-dating into a locked month (Staging Module Specification v1, section 8.8, item 4)
        \App\Support\ReportingPeriodLock::assertNotBackDated($from, 'A SICR trigger');

        if ($scope === 'contract') {
            $contract = ltrim(trim((string) ($data['contract_id'] ?? '')), '0');
            $loan = $contract === '' ? null : DB::table('loan_books')->where('contract_id', $contract)->orderByDesc('reporting_period')->first(['contract_id', 'customer_id', 'customer_name']);
            if ($loan === null) {
                throw new RuntimeException("No loan with contract id \"{$contract}\" is in any month's loan book.");
            }
            $account = (string) $loan->contract_id;
            $customerId = $loan->customer_id !== null ? (string) $loan->customer_id : null;
            $customerName = $loan->customer_name;
        } else {
            $customerId = trim((string) ($data['customer_id'] ?? ''));
            $loan = $customerId === '' ? null : DB::table('loan_books')->where('customer_id', $customerId)->orderByDesc('reporting_period')->first(['customer_id', 'customer_name']);
            if ($loan === null) {
                throw new RuntimeException("No customer with id \"{$customerId}\" is in any month's loan book.");
            }
            $account = $customerId;
            $customerName = $loan->customer_name;
        }

        $id = (int) DB::table('finance_sicr_triggers')->insertGetId([
            'group_id' => $item->group_id, 'item_id' => $item->id, 'scope' => $scope, 'account_number' => $account,
            'customer_id' => $customerId, 'customer_name' => $customerName, 'stage_implied' => (int) $item->stage_implied,
            'reason' => $reason, 'evidence' => $evidence !== '' ? $evidence : null,
            'effective_from' => $from, 'effective_to' => $to, 'status' => 'PROPOSED', 'is_demo' => (bool) ($data['is_demo'] ?? false),
            'triggered_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLoggerService::log('SICR Trigger Raised', 'finance_sicr_triggers', $id, ['new_values' => [
            'scope' => $scope, 'contract_id' => $scope === 'contract' ? $account : null, 'customer_id' => $customerId, 'item' => $item->name,
            'stage_implied' => (int) $item->stage_implied, 'effective_from' => $from, 'effective_to' => $to, 'reason' => $reason, 'evidence' => $evidence ?: null,
        ], 'meta' => ['user' => $userId]]);

        return $id;
    }

    /** A second person approves; from then on the trigger reaches the loan at every month-end it covers. */
    public function approve(int $triggerId, ?int $approverId, bool $allowMakerCheckerOverride = false, ?string $label = null): void
    {
        $t = $this->find($triggerId);
        if ($t->status !== 'PROPOSED') {
            throw new RuntimeException("Trigger {$triggerId} is " . strtolower($t->status) . ', not waiting for approval.');
        }
        if (! $allowMakerCheckerOverride && $label !== LoanBookBuildService::BOOTSTRAP_LABEL && $approverId !== null && (int) $t->triggered_by === $approverId) {
            throw new RuntimeException('Maker-checker: the person who raised a trigger cannot approve it. A second person approves.');
        }
        // checked again on approval: the month may have been locked since the trigger was raised
        \App\Support\ReportingPeriodLock::assertNotBackDated((string) $t->effective_from, "SICR trigger {$triggerId}");
        DB::table('finance_sicr_triggers')->where('id', $triggerId)->update(['status' => 'APPROVED', 'approved_by' => $approverId, 'approver_label' => $label, 'approved_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('SICR Trigger Approved', 'finance_sicr_triggers', $triggerId, ['new_values' => $this->auditKey($t) + ['stage_implied' => (int) $t->stage_implied, 'effective_from' => $t->effective_from, 'effective_to' => $t->effective_to],
            'meta' => ['approved_by' => $approverId, 'label' => $label]]);
    }

    public function reject(int $triggerId, ?int $userId, string $reason): void
    {
        $t = $this->find($triggerId);
        if ($t->status !== 'PROPOSED') {
            throw new RuntimeException("Trigger {$triggerId} is " . strtolower($t->status) . ', not waiting for approval.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('A rejection needs a reason.');
        }
        DB::table('finance_sicr_triggers')->where('id', $triggerId)->update(['status' => 'REJECTED', 'rejected_by' => $userId, 'rejected_at' => now(), 'rejected_reason' => trim($reason), 'updated_at' => now()]);
        AuditLoggerService::log('SICR Trigger Rejected', 'finance_sicr_triggers', $triggerId, ['new_values' => $this->auditKey($t), 'meta' => ['user' => $userId, 'reason' => trim($reason)]]);
    }

    /**
     * The condition has passed: the trigger stops on the date given (it still
     * applies to every month-end up to and including that date).
     */
    public function close(int $triggerId, ?int $userId, string $reason, ?string $onDate = null): void
    {
        $t = $this->find($triggerId);
        if ($t->status !== 'APPROVED') {
            throw new RuntimeException("Trigger {$triggerId} is " . strtolower($t->status) . '; only an approved trigger is closed.');
        }
        if (trim($reason) === '') {
            throw new RuntimeException('Closing a trigger needs a reason: what changed.');
        }
        $on = $this->date($onDate ?? now()->toDateString(), 'The closing date');
        if ($on < (string) $t->effective_from) {
            throw new RuntimeException("The closing date cannot be before the trigger's start date ({$t->effective_from}).");
        }
        $to = $t->effective_to !== null && (string) $t->effective_to < $on ? (string) $t->effective_to : $on;
        // an earlier end no longer covers the month-ends after it: none of them may be a locked month
        // (section 8.8, item 4); a month-end after the old end date was never covered, so it does not move
        if ($t->effective_to === null || (string) $t->effective_to > $to) {
            $after = \Carbon\CarbonImmutable::parse($to)->addDay()->toDateString();
            $affected = array_values(array_filter(\App\Support\ReportingPeriodLock::lockedFrom($after),
                fn ($p) => $t->effective_to === null || \Carbon\CarbonImmutable::parse($p . '-01')->endOfMonth()->toDateString() <= (string) $t->effective_to));
            if ($affected !== []) {
                throw new \App\Support\BackDatedChangeException("Closing SICR trigger {$triggerId} on {$to}", $after, $affected);
            }
        }
        DB::table('finance_sicr_triggers')->where('id', $triggerId)->update(['status' => 'CLOSED', 'effective_to' => $to, 'closed_by' => $userId, 'closed_at' => now(), 'closed_reason' => trim($reason), 'updated_at' => now()]);
        AuditLoggerService::log('SICR Trigger Closed', 'finance_sicr_triggers', $triggerId, ['new_values' => $this->auditKey($t) + ['effective_to' => $to], 'meta' => ['user' => $userId, 'reason' => trim($reason)]]);
    }

    // ----- what the staging engine reads -------------------------------------

    /** Approved (or since closed) triggers in force on a date. */
    public function inForce(string $date): Collection
    {
        return DB::table('finance_sicr_triggers')->whereIn('status', ['APPROVED', 'CLOSED'])
            ->whereNotNull('effective_from')->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderBy('id')->get(['id', 'scope', 'account_number', 'customer_id', 'stage_implied']);
    }

    /**
     * The triggers in force on a date, keyed for a fast lookup per loan row.
     *
     * @return array{contract:array<string,list<array{0:int,1:int}>>,customer:array<string,list<array{0:int,1:int}>>}
     */
    public function mapAt(string $date): array
    {
        $map = ['contract' => [], 'customer' => []];
        foreach ($this->inForce($date) as $t) {
            $key = $t->scope === 'customer' ? (string) $t->customer_id : (string) $t->account_number;
            $map[$t->scope === 'customer' ? 'customer' : 'contract'][$key][] = [(int) $t->id, (int) $t->stage_implied];
        }

        return $map;
    }

    /**
     * The highest stage the triggers in force imply for one loan, and their ids.
     *
     * @return array{stage:int,ids:list<int>}
     */
    public static function forLoan(array $map, ?string $contractId, ?string $customerId): array
    {
        $hits = array_merge($map['contract'][(string) $contractId] ?? [], $customerId !== null && $customerId !== '' ? ($map['customer'][$customerId] ?? []) : []);
        $stage = 0;
        $ids = [];
        foreach ($hits as [$id, $s]) {
            $stage = max($stage, $s);
            $ids[] = $id;
        }

        return ['stage' => $stage, 'ids' => $ids];
    }

    public function find(int $triggerId): object
    {
        return DB::table('finance_sicr_triggers')->where('id', $triggerId)->first() ?? throw new RuntimeException("No SICR trigger {$triggerId}.");
    }

    private function auditKey(object $t): array
    {
        return ['scope' => $t->scope, 'contract_id' => $t->scope === 'contract' ? $t->account_number : null, 'customer_id' => $t->customer_id];
    }

    private function date($value, string $what): string
    {
        try {
            if ($value === null || trim((string) $value) === '') {
                throw new RuntimeException('empty');
            }

            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException("{$what} must be a date.");
        }
    }
}
