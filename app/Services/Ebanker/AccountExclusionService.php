<?php

namespace App\Services\Ebanker;

use App\Services\AuditLoggerService;
use App\Support\AccountExclusions;
use App\Support\ContractId;
use App\Support\MakerChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The account exclusion register (MAIIC's confirmation of 16 October 2026).
 *
 * Some E-Banker accounts are not loans of the book: the vendor's UAT test
 * accounts (closed with status C on 31 December 2025, reason "UAT accounts")
 * and approvals duplicated on a sister account that was the one drawn. Left
 * in, they enter the loan book with nil balances, the contract master with no
 * drawn amount (so they block the EIR coverage), the fee lines and every
 * engine and report downstream.
 *
 * One person proposes an exclusion with the category, the reason and the
 * evidence; a different person approves it, with a supporting document as the
 * approvals of the other registers need (Approvals::assertEvidence, workflow
 * 'account-exclusion'). The bootstrap lands the exclusions MAIIC confirmed as
 * a committed client input (docs/bootstrap/account_exclusions.json) approved
 * under the bootstrap label, never as a MAIIC person.
 *
 * How an approved exclusion takes effect:
 *  - every build honours it at the door: LandingZoneReader leaves the account
 *    out of every family it returns, so the loan book build writes no row for
 *    it, the contract master creates no contract_eir, the fee, drawdown, cash
 *    and interest builds load nothing for it and the take-on population does
 *    not see it; the importers that take a person's file refuse its rows
 *    (AccountExclusions::refusal). The engines read only those tables, so none
 *    of them needs a rule of its own;
 *  - on a database that already holds the account's rows the approval moves
 *    them, never deletes them: each row is copied whole into
 *    account_exclusion_archive with its table, then removed from the table.
 *    The approval is refused when any of the rows sits in a locked reporting
 *    period (a locked period is never restated, spec v4 6.6), naming the
 *    months to unlock first;
 *  - an approved exclusion is withdrawn when one person asks with a reason and
 *    a second person approves the withdrawal: the archived rows go back to
 *    their tables (with their ids where the id is free) and the next build
 *    treats the account like any other.
 *
 * Run records are not moved: a frozen run is what ran (Staging Module
 * Specification v1, section 8.8), and its fingerprint covers its loans. The
 * audit log and the landing zone are never moved either.
 */
class AccountExclusionService
{
    public const CATEGORIES = [
        'UAT_TEST_ACCOUNT' => 'UAT test account',
        'DUPLICATE_APPROVAL' => 'Duplicate approval, never drawn',
    ];

    public const STATUSES = ['PROPOSED', 'APPROVED', 'REJECTED', 'WITHDRAWN'];

    public const CLIENT_INPUT = 'account_exclusions.json';

    /**
     * The tables an approval moves the account's rows out of, in the order they
     * are moved (a withdrawal restores them in the reverse order). A table that
     * is a child of another by an id comes before its parent: the fee
     * classification events and the fee source decisions of the account's fee
     * lines, then the fee lines; the rate periods, then the rate resets they
     * point to.
     *
     * @var list<array{0:string,1?:string,2?:string}> [table] or [table, parent table, column holding the parent's id]
     */
    public const TABLES = [
        ['eir_fee_classification_events', 'contract_fees', 'contract_fee_id'],
        ['fee_source_decisions', 'contract_fees', 'contract_fee_id'],
        ['fee_source_differences'],
        ['fee_gl_candidate_dismissals'],
        ['contract_fees'],
        ['eir_rate_periods'],
        ['rate_reset_events'],
        ['restructure_events'],
        ['loan_write_offs'],
        ['eir_amortisation'],
        ['eir_amortisation_history'],
        ['eir_calculation_history'],
        ['eir_lock_reviews'],
        ['eir_reopen_requests'],
        ['eir_actual_transactions'],
        ['gl_interest_postings'],
        ['contract_disbursements'],
        ['contract_remaining_cashflow_schedule'],
        ['contract_cashflow_schedule'],
        ['contract_modifications'],
        ['contract_takeon'],
        ['contract_eir'],
        ['ecl_scenario_results'],
        ['ecl_cashflow_projections'],
        ['ecl_pd_term_structures'],
        ['ecl_recovery_cashflows'],
        ['discounted_payments'],
        ['lgd_payment_tracking_long'],
        ['collateral_allocations'],
        ['sp_basis_results'],
        ['staging_comparison_rows'],
        ['loan_origination_pds'],
        ['loan_books'],
    ];

    /** Tables holding the account that are evidence of what ran or what was received, and are never moved. */
    public const KEPT = ['run_record_loans', 'reperformance_rows', 'audit_logs', 'ebanker_raw_rows'];

    // ----- the register ----------------------------------------------------

    /**
     * Propose an exclusion. A rejected or withdrawn entry for the account is
     * proposed again on the same row (its history is in the audit log).
     */
    public function propose(array $data, int $userId): int
    {
        $contract = ContractId::normalise($data['contract_id'] ?? null);
        if ($contract === null) {
            throw new RuntimeException('Name the E-Banker account to exclude.');
        }
        $category = (string) ($data['category'] ?? '');
        if (! isset(self::CATEGORIES[$category])) {
            throw new RuntimeException('The category is one of: ' . implode(', ', array_keys(self::CATEGORIES)) . '.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        $evidence = trim((string) ($data['evidence'] ?? ''));
        if (mb_strlen($reason) < 10 || mb_strlen($evidence) < 10) {
            throw new RuntimeException('A specific reason and the evidence (where it is recorded) are required, each at least 10 characters.');
        }
        $sister = ContractId::normalise($data['sister_contract_id'] ?? null);
        if ($category === 'DUPLICATE_APPROVAL' && $sister === null) {
            throw new RuntimeException('A duplicate approval names the sister account the loan runs on.');
        }
        if ($sister !== null && $sister === $contract) {
            throw new RuntimeException('The sister account is a different account.');
        }
        $known = $this->known($contract);
        if ($known === null) {
            throw new RuntimeException("Account {$contract} is not in the E-Banker account master, the loan book or the contract master.");
        }

        return DB::transaction(function () use ($contract, $category, $reason, $evidence, $sister, $known, $userId) {
            $existing = DB::table('account_exclusions')->where('contract_id', $contract)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['PROPOSED', 'APPROVED'], true)) {
                throw new RuntimeException("Account {$contract} already has an exclusion " . strtolower($existing->status) . ' (#' . $existing->id . ').');
            }
            $values = [
                'account' => $known['account'], 'customer_name' => $known['name'], 'category' => $category, 'reason' => $reason, 'evidence' => $evidence,
                'sister_contract_id' => $sister, 'status' => 'PROPOSED', 'source' => 'SCREEN', 'proposed_by' => $userId, 'proposed_at' => now(),
                'approved_by' => null, 'approved_at' => null, 'approver_label' => null, 'applied_at' => null, 'rows_archived' => 0,
                'withdrawal_requested_by' => null, 'withdrawal_requested_at' => null, 'withdrawal_reason' => null,
                'closed_by' => null, 'closed_at' => null, 'close_reason' => null, 'rows_restored' => 0, 'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('account_exclusions')->where('id', $existing->id)->update($values);
                $id = (int) $existing->id;
            } else {
                $id = (int) DB::table('account_exclusions')->insertGetId($values + ['contract_id' => $contract, 'created_at' => now()]);
            }
            AuditLoggerService::log('Account Exclusion Proposed', 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $contract, 'category' => $category, 'reason' => $reason, 'evidence' => $evidence, 'sister_contract_id' => $sister],
                'meta' => ['user' => $userId, 'proposed_again' => $existing !== null],
            ]);

            return $id;
        });
    }

    /**
     * A different person approves (or the bootstrap, under its label), and the
     * account's rows are moved out of the book.
     *
     * @return array{id:int, archived:int, by_table:array<string,int>}
     */
    public function approve(int $id, ?int $userId, ?string $label = null): array
    {
        $bootstrap = $label === LoanBookBuildService::BOOTSTRAP_LABEL;
        if (! $bootstrap && $userId === null) {
            throw new RuntimeException('An exclusion is approved by a named person.');
        }

        $result = DB::transaction(function () use ($id, $userId, $label, $bootstrap) {
            $e = DB::table('account_exclusions')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'PROPOSED') {
                throw new RuntimeException('Only a proposed exclusion can be approved.');
            }
            if (! $bootstrap && $e->proposed_by !== null && (int) $e->proposed_by === $userId
                && ! MakerChecker::selfApprovalAllowed('Account exclusion', 'account_exclusions', (int) $e->id, $userId)) {
                throw new RuntimeException('The person who proposed an exclusion cannot approve it; a second person approves it.');
            }
            // the evidence the workflow requires before approval (Staging Module Specification v1, section 8.6)
            \App\Support\Approvals::assertEvidence('account-exclusion', $id, $label);
            $moved = $this->archive($e);
            DB::table('account_exclusions')->where('id', $id)->update([
                'status' => 'APPROVED', 'approved_by' => $bootstrap ? null : $userId, 'approved_at' => now(), 'approver_label' => $label,
                'applied_at' => now(), 'rows_archived' => $moved['archived'], 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Account Exclusion Approved', 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $e->contract_id, 'category' => $e->category, 'rows_archived' => $moved['archived'], 'by_table' => $moved['by_table'], 'label' => $label],
                'meta' => ['user' => $userId, 'proposed_by' => $e->proposed_by, 'label' => $label],
            ]);

            return ['id' => $id] + $moved;
        });
        AccountExclusions::forget();

        return $result;
    }

    /**
     * Reject (a second person) or withdraw (the person who proposed it) a
     * proposal; on an approved exclusion, 'withdraw' asks for the withdrawal,
     * which a second person then approves (approveWithdrawal).
     */
    public function close(int $id, int $userId, string $action, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('Give the reason, at least 10 characters.');
        }
        $e = DB::table('account_exclusions')->where('id', $id)->first();
        if (! $e) {
            throw new RuntimeException('No such exclusion.');
        }
        if ($e->status === 'APPROVED' && $action === 'withdraw') {
            $this->requestWithdrawal($id, $userId, $reason);

            return;
        }
        DB::transaction(function () use ($id, $userId, $action, $reason) {
            $e = DB::table('account_exclusions')->where('id', $id)->lockForUpdate()->first();
            if ($e->status !== 'PROPOSED') {
                throw new RuntimeException('Only a proposed exclusion can be rejected or withdrawn; an approved one is withdrawn by a request a second person approves.');
            }
            $mine = $e->proposed_by !== null && (int) $e->proposed_by === $userId;
            $status = match (true) {
                $action === 'withdraw' && $mine => 'WITHDRAWN',
                $action === 'reject' && (! $mine || MakerChecker::selfApprovalAllowed('Account exclusion (reject)', 'account_exclusions', (int) $e->id, $userId)) => 'REJECTED',
                default => null,
            };
            if ($status === null) {
                throw new RuntimeException($action === 'reject'
                    ? 'The person who proposed an exclusion cannot reject it; withdraw it instead.'
                    : 'Only the person who proposed an exclusion can withdraw it; a second person rejects it.');
            }
            DB::table('account_exclusions')->where('id', $id)->update(['status' => $status, 'closed_by' => $userId, 'closed_at' => now(), 'close_reason' => $reason, 'updated_at' => now()]);
            AuditLoggerService::log('Account Exclusion ' . ucfirst(strtolower($status)), 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $e->contract_id, 'status' => $status, 'reason' => $reason], 'meta' => ['user' => $userId],
            ]);
        });
    }

    /** Ask for an approved exclusion to be withdrawn: the account comes back into the book once a second person approves. */
    public function requestWithdrawal(int $id, int $userId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('Give the reason the account belongs in the book after all, at least 10 characters.');
        }
        DB::transaction(function () use ($id, $userId, $reason) {
            $e = DB::table('account_exclusions')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'APPROVED') {
                throw new RuntimeException('Only an approved exclusion can be withdrawn.');
            }
            if ($e->withdrawal_requested_by !== null) {
                throw new RuntimeException('A withdrawal of this exclusion is already awaiting approval.');
            }
            DB::table('account_exclusions')->where('id', $id)->update(['withdrawal_requested_by' => $userId, 'withdrawal_requested_at' => now(), 'withdrawal_reason' => $reason, 'updated_at' => now()]);
            AuditLoggerService::log('Account Exclusion Withdrawal Requested', 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $e->contract_id, 'reason' => $reason], 'meta' => ['user' => $userId],
            ]);
        });
    }

    /** A second person approves the withdrawal: the archived rows go back and the account is in the book again. */
    public function approveWithdrawal(int $id, int $userId): array
    {
        $result = DB::transaction(function () use ($id, $userId) {
            $e = DB::table('account_exclusions')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'APPROVED' || $e->withdrawal_requested_by === null) {
                throw new RuntimeException('No withdrawal of this exclusion is awaiting approval.');
            }
            if ((int) $e->withdrawal_requested_by === $userId && ! MakerChecker::selfApprovalAllowed('Account exclusion withdrawal', 'account_exclusions', (int) $e->id, $userId)) {
                throw new RuntimeException('The person who asked for the withdrawal cannot approve it; a second person approves it.');
            }
            $restored = $this->restore($e);
            DB::table('account_exclusions')->where('id', $id)->update(['status' => 'WITHDRAWN', 'closed_by' => $userId, 'closed_at' => now(),
                'close_reason' => $e->withdrawal_reason, 'rows_restored' => $restored['restored'], 'updated_at' => now()]);
            AuditLoggerService::log('Account Exclusion Withdrawn', 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $e->contract_id, 'status' => 'WITHDRAWN', 'reason' => $e->withdrawal_reason, 'rows_restored' => $restored['restored'], 'by_table' => $restored['by_table']],
                'meta' => ['user' => $userId, 'requested_by' => (int) $e->withdrawal_requested_by],
            ]);

            return ['id' => $id] + $restored;
        });
        AccountExclusions::forget();

        return $result;
    }

    /** The requester cancels the withdrawal request, or a second person declines it, with a reason; the exclusion stands. */
    public function dropWithdrawal(int $id, int $userId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new RuntimeException('Give the reason, at least 10 characters.');
        }
        DB::transaction(function () use ($id, $userId, $reason) {
            $e = DB::table('account_exclusions')->where('id', $id)->lockForUpdate()->first();
            if (! $e || $e->status !== 'APPROVED' || $e->withdrawal_requested_by === null) {
                throw new RuntimeException('No withdrawal of this exclusion is awaiting approval.');
            }
            DB::table('account_exclusions')->where('id', $id)->update(['withdrawal_requested_by' => null, 'withdrawal_requested_at' => null, 'withdrawal_reason' => null, 'updated_at' => now()]);
            AuditLoggerService::log('Account Exclusion Withdrawal ' . ((int) $e->withdrawal_requested_by === $userId ? 'Cancelled' : 'Declined'), 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $e->contract_id, 'reason' => $reason, 'request_reason' => $e->withdrawal_reason], 'meta' => ['user' => $userId],
            ]);
        });
    }

    /**
     * Move out whatever rows the approved exclusions' accounts hold now, into
     * the archive of their exclusion: for a door that writes the book without
     * the zone reader (the printed Loan Book Report of method C, through the
     * old importer). Idempotent: an account with no rows moves nothing.
     *
     * @return array<string,int> contract id => rows moved
     */
    public function reapply(): array
    {
        $out = [];
        foreach (DB::table('account_exclusions')->where('status', 'APPROVED')->orderBy('id')->get() as $e) {
            $moved = $this->archive($e);
            if ($moved['archived'] > 0) {
                DB::table('account_exclusions')->where('id', $e->id)->update(['rows_archived' => (int) $e->rows_archived + $moved['archived'], 'updated_at' => now()]);
                AuditLoggerService::log('Account Exclusion Applied Again', 'account_exclusions', (int) $e->id, [
                    'new_values' => ['contract_id' => $e->contract_id, 'rows_archived' => $moved['archived'], 'by_table' => $moved['by_table']],
                ]);
                $out[(string) $e->contract_id] = $moved['archived'];
            }
        }

        return $out;
    }

    // ----- the committed client input ---------------------------------------

    /**
     * The exclusions MAIIC confirmed, committed under docs/bootstrap, landed as
     * APPROVED under the bootstrap label (never as a MAIIC person) before the
     * loan book is built. An account the register already holds, in any
     * status, is left as it is: a person's decision on this database is not
     * overridden by the file.
     *
     * @return array{file:string, sha256:string, accounts:int, landed:int, already:array<string,string>, archived:int}
     */
    public function landClientInput(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("No account exclusions file at {$path}.");
        }
        $raw = (string) file_get_contents($path);
        $doc = json_decode($raw, true);
        if (! is_array($doc) || ! is_array($doc['accounts'] ?? null)) {
            throw new RuntimeException(basename($path) . ' is not an account exclusions file: it needs an "accounts" list.');
        }
        $sha = hash('sha256', $raw);
        $landed = 0; $archived = 0; $already = [];
        foreach ($doc['accounts'] as $i => $a) {
            $contract = ContractId::normalise($a['contract_id'] ?? null);
            $category = (string) ($a['category'] ?? '');
            $reason = trim((string) ($a['reason'] ?? ''));
            $evidence = trim((string) ($a['evidence'] ?? ''));
            if ($contract === null || ! isset(self::CATEGORIES[$category]) || $reason === '' || $evidence === '') {
                throw new RuntimeException(basename($path) . ' entry ' . ($i + 1) . ' needs a contract_id, a known category, a reason and the evidence.');
            }
            $existing = DB::table('account_exclusions')->where('contract_id', $contract)->first();
            if ($existing) {
                $already[$contract] = strtolower($existing->status) . ' (#' . $existing->id . ')';
                continue;
            }
            $id = (int) DB::table('account_exclusions')->insertGetId([
                'contract_id' => $contract, 'account' => $a['account'] ?? AccountExclusions::padded($contract), 'customer_name' => $a['customer_name'] ?? null,
                'category' => $category, 'reason' => $reason, 'evidence' => $evidence . (isset($doc['confirmed']) ? '; ' . $doc['confirmed'] : ''),
                'sister_contract_id' => ContractId::normalise($a['sister_contract_id'] ?? null), 'status' => 'PROPOSED', 'source' => 'CLIENT_INPUT',
                'proposed_by' => null, 'proposed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Account Exclusion Proposed', 'account_exclusions', $id, [
                'new_values' => ['contract_id' => $contract, 'category' => $category, 'reason' => $reason, 'evidence' => $evidence],
                'meta' => ['label' => LoanBookBuildService::BOOTSTRAP_LABEL, 'client_input' => 'docs/bootstrap/' . basename($path), 'sha256' => $sha],
            ]);
            $archived += $this->approve($id, null, LoanBookBuildService::BOOTSTRAP_LABEL)['archived'];
            $landed++;
        }

        return ['file' => basename($path), 'sha256' => $sha, 'accounts' => count($doc['accounts']), 'landed' => $landed, 'already' => $already, 'archived' => $archived];
    }

    // ----- moving the rows ----------------------------------------------------

    /**
     * The locked reporting periods the account has rows in, earliest first: an
     * approval (or a withdrawal) waits until they are unlocked.
     *
     * @return list<string>
     */
    public function lockedMonths(string $contractId): array
    {
        if (! Schema::hasTable('reporting_period_locks')) {
            return [];
        }
        $locked = DB::table('reporting_period_locks')->pluck('reporting_period')->map(fn ($p) => (string) $p)->all();
        if ($locked === []) {
            return [];
        }
        $months = [];
        foreach ($this->tables() as [$table]) {
            if (! Schema::hasColumn($table, 'reporting_period') || ! Schema::hasColumn($table, 'contract_id')) {
                continue;
            }
            foreach (DB::table($table)->whereIn('contract_id', self::forms($contractId))->whereNotNull('reporting_period')->distinct()->pluck('reporting_period') as $p) {
                $p = substr((string) $p, 0, 7);
                if (in_array($p, $locked, true)) {
                    $months[$p] = true;
                }
            }
        }
        $months = array_keys($months);
        sort($months);

        return $months;
    }

    /**
     * What the account holds in each table now, without moving anything.
     *
     * @return array<string,int>
     */
    public function footprint(string $contractId): array
    {
        $out = [];
        foreach ($this->tables() as $t) {
            $n = $this->rowsOf($t, $contractId)->count();
            if ($n > 0) {
                $out[$t[0]] = $n;
            }
        }

        return $out;
    }

    /** @return array{archived:int, by_table:array<string,int>} */
    private function archive(object $e): array
    {
        $locked = $this->lockedMonths((string) $e->contract_id);
        if ($locked !== []) {
            throw new RuntimeException("Account {$e->contract_id} has rows in the locked reporting period(s) " . implode(', ', $locked)
                . '. A locked period is never restated: unlock ' . (count($locked) === 1 ? 'it' : 'them') . ' first (Imports & Feeds, Periods Built; a second person approves the unlock), then approve the exclusion.');
        }
        $byTable = []; $total = 0; $now = now();
        foreach ($this->tables() as $t) {
            $rows = $this->rowsOf($t, (string) $e->contract_id)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            foreach ($rows->chunk(200) as $chunk) {
                DB::table('account_exclusion_archive')->insert($chunk->map(fn ($r) => [
                    'exclusion_id' => $e->id, 'contract_id' => $e->contract_id, 'table_name' => $t[0], 'source_id' => isset($r->id) ? (int) $r->id : null,
                    'row' => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 'archived_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ])->values()->all());
            }
            $removed = isset($rows->first()->id)
                ? DB::table($t[0])->whereIn('id', $rows->pluck('id')->all())->delete()
                : $this->rowsOf($t, (string) $e->contract_id)->delete();
            $byTable[$t[0]] = $removed;
            $total += $removed;
        }

        return ['archived' => $total, 'by_table' => $byTable];
    }

    /** @return array{restored:int, by_table:array<string,int>} */
    private function restore(object $e): array
    {
        $locked = [];
        $lockedAll = Schema::hasTable('reporting_period_locks') ? DB::table('reporting_period_locks')->pluck('reporting_period')->map(fn ($p) => (string) $p)->all() : [];
        $archive = DB::table('account_exclusion_archive')->where('exclusion_id', $e->id)->whereNull('restored_at')->orderBy('id')->get();
        foreach ($archive as $a) {
            $p = substr((string) (json_decode($a->row, true)['reporting_period'] ?? ''), 0, 7);
            if ($p !== '' && in_array($p, $lockedAll, true)) {
                $locked[$p] = true;
            }
        }
        if ($locked !== []) {
            $months = array_keys($locked);
            sort($months);
            throw new RuntimeException('The archived rows of account ' . $e->contract_id . ' include the locked reporting period(s) ' . implode(', ', $months) . ': unlock them first, then approve the withdrawal.');
        }
        $byTable = []; $total = 0; $now = now();
        $order = array_reverse(array_map(fn ($t) => $t[0], self::TABLES));
        $byName = $archive->groupBy('table_name');
        foreach ($order as $table) {
            foreach ($byName->get($table, collect()) as $a) {
                if (! Schema::hasTable($table)) {
                    throw new RuntimeException("The table {$table} the archived row #{$a->id} came from no longer exists; it cannot be restored.");
                }
                $row = json_decode($a->row, true);
                if (isset($row['id']) && DB::table($table)->where('id', $row['id'])->exists()) {
                    unset($row['id']); // the id was taken in the meantime: the row comes back under a new one
                }
                DB::table($table)->insert(array_intersect_key($row, array_flip(Schema::getColumnListing($table))));
                DB::table('account_exclusion_archive')->where('id', $a->id)->update(['restored_at' => $now, 'updated_at' => $now]);
                $byTable[$table] = ($byTable[$table] ?? 0) + 1;
                $total++;
            }
        }

        return ['restored' => $total, 'by_table' => $byTable];
    }

    /** The tables of TABLES that exist on this database. */
    private function tables(): array
    {
        return array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t[0])
            && (isset($t[1]) ? Schema::hasTable($t[1]) && Schema::hasColumn($t[0], $t[2]) : Schema::hasColumn($t[0], 'contract_id'))));
    }

    /** The account's rows of one table: by contract_id, or through the parent's id for a child table. */
    private function rowsOf(array $t, string $contractId): \Illuminate\Database\Query\Builder
    {
        if (isset($t[1])) {
            return DB::table($t[0])->whereIn($t[2], DB::table($t[1])->select('id')->whereIn('contract_id', self::forms($contractId)));
        }

        return DB::table($t[0])->whereIn('contract_id', self::forms($contractId));
    }

    /** The two forms a contract id is stored in: canonical and padded to E-Banker's fifteen characters. */
    private static function forms(string $contractId): array
    {
        return array_values(array_unique([$contractId, AccountExclusions::padded($contractId)]));
    }

    /**
     * The account as the system knows it, from the account master as received
     * (the reader with the exclusions left in), the loan book or the contract
     * master: its padded account number and its name; null when unknown.
     *
     * @return array{account:string, name:?string}|null
     */
    private function known(string $contractId): ?array
    {
        $zone = (new LandingZoneReader())->includingExcluded();
        foreach (self::forms($contractId) as $form) {
            foreach ($zone->family(LandingZoneReader::ACCOUNT_MASTER, null, null, $form) as $row) {
                return ['account' => (string) $row['account'], 'name' => $row['payload']['ACCOUNT_NAME'] ?? null];
            }
        }
        $loan = Schema::hasTable('loan_books') ? DB::table('loan_books')->where('contract_id', $contractId)->orderByDesc('reporting_period')->first(['customer_name', 'external_identity_id']) : null;
        if ($loan) {
            return ['account' => $loan->external_identity_id ?: AccountExclusions::padded($contractId), 'name' => $loan->customer_name];
        }
        $c = Schema::hasTable('contract_eir') ? DB::table('contract_eir')->where('contract_id', $contractId)->first(['customer_name']) : null;

        return $c ? ['account' => AccountExclusions::padded($contractId), 'name' => $c->customer_name] : null;
    }
}
