<?php

namespace App\Services\Ebanker;

use App\Models\SupportingDocument;
use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceDocumentService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\LoanGrossAmountOverrideService;
use App\Services\Eir\LoanStagingBasisOverrideService;
use App\Services\Eir\StagingPolicy;
use App\Services\Eir\StagingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Reconciles the loan book the system rebuilt from the E-Banker queries and the ledger to a loan
 * book the client gave for the same month-end (Client Book Reconciliation Specification v1,
 * 17 October 2026). The rebuilt book stays the system's book: where the client's book differs, the
 * client either proposes a governed override (a staging day count or a gross amount, approved by a
 * second person with the client's book as its evidence, applied when the month is rebuilt) or keeps
 * the system's figure with a reason. Every step is recorded and audit-logged; nothing here writes
 * to the loan book.
 *
 *  - land(): the file as received, its rows, its hash, and the file itself kept as the evidence.
 *  - compare(): loan by loan, the balance, the arrears bracket and (where the client gives one) the
 *    stage, against the month's book as built and staged under the policy in force; each difference
 *    on the register and in a CSV of the differences beside it. A comparison run again after a
 *    rebuild keeps what was decided and marks a loan the approved override now agrees as reconciled.
 *  - propose(): the override(s) a difference calls for, with the client's book linked as evidence.
 *  - keep(): the system's figure stands, with the reason.
 */
class ClientBookReconciliationService
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PROPOSED = 'OVERRIDE_PROPOSED';
    public const STATUS_RECONCILED = 'RECONCILED';
    public const STATUS_KEPT = 'KEPT';
    public const STATUS_INFO = 'INFO';

    public const CSV_FOLDER = 'client-book-reconciliation';

    public const DOCUMENT_FOLDER = 'approval-documents/client_loan_books';

    private const SUFFIXES = ['limited', 'ltd', 'company', 'co', 'plc', 'the', 'and', 'group'];

    public function __construct(
        private ClientBookReader $reader,
        private StagingService $staging,
        private GovernanceService $governance,
        private LoanStagingBasisOverrideService $stagingOverrides,
        private LoanGrossAmountOverrideService $grossOverrides,
    ) {
    }

    // ----- land --------------------------------------------------------------

    /**
     * @return array{book_id:int, status:string, rows:int}
     */
    public function land(string $path, string $period, string $kind, string $label, ?int $userId, ?string $landedLabel = null, ?string $originalName = null): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new RuntimeException("A client book names its month-end as YYYY-MM; '{$period}' is not one.");
        }
        if (trim($label) === '') {
            throw new RuntimeException('Name the client book (for example "E-Banker loan book report, 31 August 2026").');
        }
        $hash = hash_file('sha256', $path);
        $existing = DB::table('client_loan_books')->where('reporting_period', $period)->where('file_hash', $hash)->first();
        if ($existing !== null) {
            return ['book_id' => (int) $existing->id, 'status' => 'ALREADY_LANDED', 'rows' => (int) $existing->row_count];
        }
        $read = $this->reader->read($path, $kind, $period);
        $name = $originalName ?? basename($path);

        return DB::transaction(function () use ($path, $period, $kind, $label, $userId, $landedLabel, $name, $hash, $read) {
            $id = (int) DB::table('client_loan_books')->insertGetId([
                'reporting_period' => $period, 'kind' => $kind, 'source_label' => mb_substr(trim($label), 0, 200), 'file_name' => mb_substr($name, 0, 255),
                'file_hash' => $hash, 'row_count' => count($read['rows']), 'printed_total' => $read['printed_total'], 'rows_total' => $read['rows_total'],
                'status' => 'LANDED', 'landed_by' => $userId, 'landed_label' => $landedLabel, 'landed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (array_chunk($read['rows'], 500) as $chunk) {
                DB::table('client_loan_book_rows')->insert(array_map(fn ($r) => [
                    'client_book_id' => $id, 'row_no' => $r['row_no'], 'contract_id' => $r['contract_id'], 'customer_name' => $r['customer_name'] !== null ? mb_substr((string) $r['customer_name'], 0, 200) : null,
                    'product_code' => $r['product_code'], 'carrying_amount' => $r['carrying_amount'], 'principal_overdue' => $r['principal_overdue'],
                    'interest_overdue' => $r['interest_overdue'], 'arrears_category' => $r['arrears_category'], 'buckets' => $r['buckets'] !== null ? json_encode($r['buckets']) : null,
                    'days_past_due' => $r['days_past_due'], 'days_basis' => $r['days_basis'] !== null ? mb_substr($r['days_basis'], 0, 200) : null, 'stage' => $r['stage'],
                    'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }
            // the file itself is the evidence every override proposed from it links to
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'csv';
            $stored = self::DOCUMENT_FOLDER . "/{$id}/{$hash}.{$ext}";
            Storage::disk(GovernanceDocumentService::DISK)->put($stored, file_get_contents($path));
            SupportingDocument::create([
                'documentable_type' => 'client_loan_books', 'documentable_id' => $id, 'disk' => GovernanceDocumentService::DISK, 'path' => $stored,
                'original_name' => mb_substr($name, 0, 255), 'mime_type' => $ext === 'csv' ? 'text/csv' : 'application/vnd.ms-excel', 'size' => filesize($path),
                'extension' => $ext, 'document_type' => 'correspondence', 'description' => mb_substr("The client's loan book for {$period}: {$label}", 0, 255), 'hash' => $hash, 'uploaded_by' => $userId,
            ]);
            AuditLoggerService::log('Client Loan Book Landed', 'client_loan_books', $id, [
                'new_values' => ['reporting_period' => $period, 'kind' => $kind, 'label' => $label, 'file' => $name, 'sha256' => $hash, 'rows' => count($read['rows']), 'printed_total' => $read['printed_total']],
                'meta' => ['user' => $userId, 'label' => $landedLabel],
            ]);

            return ['book_id' => $id, 'status' => 'LANDED', 'rows' => count($read['rows'])];
        });
    }

    // ----- compare -----------------------------------------------------------

    /**
     * @return array{book_id:int, period:string, compared:int, same:int, differences:int, by_cause:array<string,int>, csv:string}
     */
    public function compare(int $bookId): array
    {
        $book = DB::table('client_loan_books')->where('id', $bookId)->first() ?? throw new RuntimeException("No client book {$bookId}.");
        if ($book->status === 'WITHDRAWN') {
            throw new RuntimeException("Client book {$bookId} was withdrawn; land the file again to compare it.");
        }
        $period = (string) $book->reporting_period;
        $system = DB::table('loan_books')->where('reporting_period', $period)->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))
            ->get(['contract_id', 'customer_name', 'carrying_amount'])->keyBy(fn ($r) => (string) $r->contract_id);
        if ($system->isEmpty()) {
            throw new RuntimeException("The system has no loan book for {$period}: build the month first, then compare.");
        }
        $policy = StagingPolicy::at($this->governance, CarbonImmutable::parse($period . '-01')->endOfMonth());
        $staged = $this->staging->evaluate($period, $policy)['loans'];
        $client = DB::table('client_loan_book_rows')->where('client_book_id', $bookId)->orderBy('row_no')->get();
        $excluded = DB::table('account_exclusions')->where('status', 'APPROVED')->pluck('id', 'contract_id')->mapWithKeys(fn ($id, $c) => [(string) $c => (int) $id])->all();
        $approved = $this->overridesOf($period, 'APPROVED');
        $proposed = $this->overridesOf($period, 'PROPOSED');

        [$pairs, $clientOnly, $systemOnly] = $this->match($client, $system, $book->kind === 'audited_model');
        $found = [];
        $compared = 0;
        $same = 0;
        foreach ($pairs as [$c, $contract, $matchedOn]) {
            $s = $system[$contract];
            $st = $staged[$contract] ?? null;
            $compared++;
            if (isset($excluded[$contract])) {
                $found[$contract] = $this->diff($book, $c, $s, $st, $matchedOn, "excluded account: not a loan of the book (exclusion #{$excluded[$contract]})", self::STATUS_INFO);
                continue;
            }
            $sysAmount = round((float) $s->carrying_amount, 2);
            $balance = $c->carrying_amount !== null && abs($sysAmount - (float) $c->carrying_amount) > max(1000, 0.005 * abs((float) $c->carrying_amount));
            $bucket = $c->days_past_due !== null && $st !== null && self::bracket((int) $c->days_past_due) !== self::bracket((int) $st['dpd']);
            $stage = $c->stage !== null && $st !== null && (int) $c->stage !== (int) $st['stage'];
            if ($c->days_past_due !== null && $st === null && abs($sysAmount) > 0.005) {
                $found[$contract] = $this->diff($book, $c, $s, null, $matchedOn, 'in the system\'s book but not staged', self::STATUS_OPEN, $balance);
                continue;
            }
            if (! $balance && ! $bucket && ! $stage) {
                $same++;
                $ids = array_filter(['staging override #' . ($approved['staging'][$contract] ?? '') => isset($approved['staging'][$contract]), 'gross amount override #' . ($approved['gross'][$contract] ?? '') => isset($approved['gross'][$contract])]);
                if ($ids !== []) {
                    $found[$contract] = $this->diff($book, $c, $s, $st, $matchedOn, 'agrees after the approved ' . implode(' and ', array_keys($ids)), self::STATUS_RECONCILED);
                }
                continue;
            }
            $cause = match (true) {
                $bucket => 'different arrears bracket',
                $balance => 'different balance',
                default => 'same bracket and balance, different stage',
            };
            $found[$contract] = $this->diff($book, $c, $s, $st, $matchedOn, $cause, self::STATUS_OPEN, $balance, $bucket, $stage);
        }
        foreach ($clientOnly as $c) {
            if (abs((float) ($c->carrying_amount ?? 0)) <= 0.005) {
                continue; // closed in the client's book and absent from the system's: the same
            }
            $key = $c->contract_id !== null ? (string) $c->contract_id : 'row:' . $c->row_no;
            $found[$key] = $this->diff($book, $c, null, null, null, isset($excluded[(string) $c->contract_id]) ? "excluded account: not a loan of the book (exclusion #{$excluded[(string) $c->contract_id]})" : 'in the client\'s book only', isset($excluded[(string) $c->contract_id]) ? self::STATUS_INFO : self::STATUS_OPEN, true);
        }
        // a loan the client's book does not list is a difference only for a book that lists the whole book;
        // the template lists only the loans the client takes a position on
        if ($book->kind !== 'template') {
            foreach ($systemOnly as $contract) {
                $s = $system[$contract];
                if (abs((float) $s->carrying_amount) > 0.005) {
                    $found[$contract] = $this->diff($book, null, $s, $staged[$contract] ?? null, null, 'in the system\'s book only', self::STATUS_OPEN, true);
                }
            }
        }
        foreach ($found as $key => &$d) {
            $d['staging_override_id'] = $proposed['staging'][(string) $d['contract_id']] ?? ($approved['staging'][(string) $d['contract_id']] ?? null);
            $d['gross_override_id'] = $proposed['gross'][(string) $d['contract_id']] ?? ($approved['gross'][(string) $d['contract_id']] ?? null);
            if ($d['status'] === self::STATUS_OPEN && ($d['staging_override_id'] !== null || $d['gross_override_id'] !== null)) {
                $d['status'] = self::STATUS_PROPOSED;
            }
        }
        unset($d);

        $csv = DB::transaction(function () use ($book, $found, $compared, $same) {
            // what was decided stays with the loan; a decided loan that now agrees is reconciled
            $old = DB::table('client_book_differences')->where('client_book_id', $book->id)->get()->keyBy(fn ($r) => $r->contract_id ?? 'row:' . $r->client_row_id);
            $keep = [];
            foreach ($found as $key => $d) {
                $prior = $old[$key] ?? null;
                if ($prior !== null && in_array($prior->status, [self::STATUS_KEPT], true) && $d['status'] === self::STATUS_OPEN) {
                    $d['status'] = self::STATUS_KEPT;
                }
                if ($prior !== null) {
                    $d['decided_by'] = $prior->decided_by;
                    $d['decision_reason'] = $prior->decision_reason;
                    $d['decided_at'] = $prior->decided_at;
                    DB::table('client_book_differences')->where('id', $prior->id)->update($d + ['updated_at' => now()]);
                    $keep[] = (int) $prior->id;
                } else {
                    $keep[] = (int) DB::table('client_book_differences')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach ($old as $key => $prior) {
                if (in_array((int) $prior->id, $keep, true)) {
                    continue;
                }
                if ($prior->decided_by !== null || $prior->staging_override_id !== null || $prior->gross_override_id !== null) {
                    DB::table('client_book_differences')->where('id', $prior->id)->update(['status' => self::STATUS_RECONCILED, 'cause' => 'agrees now', 'updated_at' => now()]);
                } else {
                    DB::table('client_book_differences')->where('id', $prior->id)->delete();
                }
            }
            $byCause = collect($found)->countBy('cause')->sortDesc()->all();
            $csv = $this->writeCsv($book);
            DB::table('client_loan_books')->where('id', $book->id)->update([
                'status' => 'COMPARED', 'compared_at' => now(), 'csv_path' => $csv, 'updated_at' => now(),
                'summary' => json_encode(['compared' => $compared, 'same' => $same, 'differences' => count(array_filter($found, fn ($d) => in_array($d['status'], [self::STATUS_OPEN, self::STATUS_PROPOSED, self::STATUS_KEPT], true))), 'by_cause' => $byCause]),
            ]);

            return $csv;
        });
        $summary = json_decode((string) DB::table('client_loan_books')->where('id', $bookId)->value('summary'), true);
        AuditLoggerService::log('Client Loan Book Compared', 'client_loan_books', $bookId, ['new_values' => ['reporting_period' => $period] + $summary + ['csv' => $csv]]);

        return ['book_id' => $bookId, 'period' => $period, 'compared' => $compared, 'same' => $same, 'differences' => (int) $summary['differences'], 'by_cause' => $summary['by_cause'], 'csv' => $csv];
    }

    /** The differences of a book as a CSV beside the register (storage/app/client-book-reconciliation). */
    public function writeCsv(object $book): string
    {
        $rel = self::CSV_FOLDER . '/' . $book->reporting_period . ' - ' . preg_replace('/[^A-Za-z0-9 ,._-]+/', '', (string) $book->source_label) . " (book {$book->id}) differences.csv";
        $full = storage_path('app/' . $rel);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0775, true);
        }
        $f = fopen($full, 'w');
        fputcsv($f, ['Month-end', 'Contract', 'Customer', 'Matched on', 'Client amount', 'System amount', 'Difference', 'Client days', 'Client bracket', 'System days', 'System bracket', 'Client stage', 'System stage', 'Cause', 'Status', 'Staging override', 'Gross override', 'Decision reason']);
        foreach (DB::table('client_book_differences')->where('client_book_id', $book->id)->orderByRaw("case status when 'OPEN' then 0 when 'OVERRIDE_PROPOSED' then 1 when 'KEPT' then 2 when 'RECONCILED' then 3 else 4 end")->orderBy('contract_id')->get() as $d) {
            fputcsv($f, [$d->reporting_period, $d->contract_id, $d->customer_name, $d->matched_on, $d->client_amount, $d->system_amount,
                $d->client_amount !== null && $d->system_amount !== null ? round((float) $d->client_amount - (float) $d->system_amount, 2) : null,
                $d->client_days, $d->client_bracket, $d->system_days, $d->system_bracket, $d->client_stage, $d->system_stage, $d->cause, $d->status,
                $d->staging_override_id, $d->gross_override_id, $d->decision_reason]);
        }
        fclose($f);

        return $rel;
    }

    // ----- decide ------------------------------------------------------------

    /**
     * Propose the override(s) a difference calls for: a staging day count where the bracket or the
     * stage differs, a gross amount where the balance differs. The days and the amount default to
     * the client's own figures; a person may give a better-evidenced day count (and says why). The
     * client's book is linked to each override as its evidence; a second person approves it.
     *
     * @return array{staging_override_id:?int, gross_override_id:?int}
     */
    public function propose(int $differenceId, int $userId, string $reason, ?int $days = null, ?float $amount = null): array
    {
        $d = DB::table('client_book_differences')->where('id', $differenceId)->first() ?? throw new RuntimeException("No difference {$differenceId}.");
        if (! in_array($d->status, [self::STATUS_OPEN, self::STATUS_KEPT], true)) {
            throw new RuntimeException("Difference {$differenceId} is {$d->status}; only an open difference (or one kept) is overridden.");
        }
        if ($d->contract_id === null) {
            throw new RuntimeException("Difference {$differenceId} matches no loan of the system's book: an override changes a loan the system builds, so this one is answered by the client (or kept), not overridden.");
        }
        if (trim($reason) === '' || mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Give the reason the client\'s figure is the right one (at least 10 characters).');
        }
        if (DB::table('reporting_period_locks')->where('reporting_period', $d->reporting_period)->exists()) {
            throw new RuntimeException("{$d->reporting_period} is locked: a locked period is never rebuilt, so an override could not take effect. Unlock the period first (Period close).");
        }
        $book = DB::table('client_loan_books')->where('id', $d->client_book_id)->first();
        $source = "From the client's book of {$d->reporting_period} ({$book->source_label}, file {$book->file_name}, sha256 " . substr((string) $book->file_hash, 0, 12) . ')';
        $wantsDays = $d->bucket_differs || $d->stage_differs || $days !== null;
        $wantsAmount = $d->balance_differs || $amount !== null;
        if (! $wantsDays && ! $wantsAmount) {
            throw new RuntimeException("Difference {$differenceId} is neither a bracket, a stage nor a balance difference: there is nothing to override.");
        }
        $days ??= $d->client_days !== null ? (int) $d->client_days : null;
        if ($wantsDays && $days === null) {
            throw new RuntimeException("The client's book gives no day count for {$d->contract_id}; enter the day count the evidence supports.");
        }
        $amount ??= $d->client_amount !== null ? (float) $d->client_amount : null;
        if ($wantsAmount && $amount === null) {
            throw new RuntimeException("The client's book gives no amount for {$d->contract_id}; enter the amount the evidence supports.");
        }

        return DB::transaction(function () use ($d, $userId, $reason, $days, $amount, $wantsDays, $wantsAmount, $source, $book) {
            $stagingId = $wantsDays ? $this->stagingOverrides->propose((string) $d->contract_id, (string) $d->reporting_period, trim($reason) . ' ' . $source . '.', $userId, $days, LoanStagingBasisOverrideService::SOURCE_CLIENT_BOOK) : null;
            $grossId = $wantsAmount ? $this->grossOverrides->propose((string) $d->contract_id, (string) $d->reporting_period, trim($reason) . ' ' . $source . '.', $userId, (float) $amount) : null;
            $doc = DB::table('supporting_documents')->where('documentable_type', 'client_loan_books')->where('documentable_id', $book->id)->whereNull('deleted_at')->value('id');
            foreach (array_filter(['loan_staging_basis_overrides' => $stagingId, 'loan_gross_amount_overrides' => $grossId]) as $table => $id) {
                if ($doc !== null) {
                    DB::table('supporting_document_links')->insert(['supporting_document_id' => $doc, 'documentable_type' => $table, 'documentable_id' => $id, 'linked_by' => $userId,
                        'reason' => mb_substr("The client's loan book of {$d->reporting_period} that the override takes its figure from (difference #{$d->id}, contract {$d->contract_id})", 0, 2000), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('client_book_differences')->where('id', $d->id)->update([
                'status' => self::STATUS_PROPOSED, 'staging_override_id' => $stagingId ?? $d->staging_override_id, 'gross_override_id' => $grossId ?? $d->gross_override_id,
                'decided_by' => $userId, 'decision_reason' => mb_substr(trim($reason), 0, 2000), 'decided_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('Client Book Difference Overridden', 'client_book_differences', (int) $d->id, [
                'new_values' => ['contract_id' => $d->contract_id, 'reporting_period' => $d->reporting_period, 'staging_override_id' => $stagingId, 'days' => $wantsDays ? $days : null, 'gross_override_id' => $grossId, 'amount' => $wantsAmount ? $amount : null, 'reason' => $reason],
                'meta' => ['user' => $userId, 'client_book_id' => (int) $d->client_book_id],
            ]);

            return ['staging_override_id' => $stagingId, 'gross_override_id' => $grossId];
        });
    }

    /**
     * Propose the client's figures for many open differences of one book at once, one reason for all.
     *
     * @param  list<int>  $ids
     * @return array{proposed:int, skipped:list<string>}
     */
    public function proposeMany(int $bookId, array $ids, int $userId, string $reason): array
    {
        $proposed = 0;
        $skipped = [];
        foreach (DB::table('client_book_differences')->where('client_book_id', $bookId)->whereIn('id', $ids)->orderBy('id')->get() as $d) {
            try {
                $this->propose((int) $d->id, $userId, $reason);
                $proposed++;
            } catch (\Throwable $e) {
                $skipped[] = ($d->contract_id ?? $d->customer_name) . ': ' . $e->getMessage();
            }
        }

        return ['proposed' => $proposed, 'skipped' => $skipped];
    }

    /** The system's figure stands for this loan, with the reason (the client's book is wrong, or the difference is explained). */
    public function keep(int $differenceId, int $userId, string $reason): void
    {
        $d = DB::table('client_book_differences')->where('id', $differenceId)->first() ?? throw new RuntimeException("No difference {$differenceId}.");
        if ($d->status !== self::STATUS_OPEN) {
            throw new RuntimeException("Difference {$differenceId} is {$d->status}; only an open difference is kept.");
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Give the reason the system\'s figure stands (at least 10 characters).');
        }
        DB::table('client_book_differences')->where('id', $differenceId)->update(['status' => self::STATUS_KEPT, 'decided_by' => $userId, 'decision_reason' => mb_substr(trim($reason), 0, 2000), 'decided_at' => now(), 'updated_at' => now()]);
        AuditLoggerService::log('Client Book Difference Kept', 'client_book_differences', $differenceId, [
            'new_values' => ['contract_id' => $d->contract_id, 'reporting_period' => $d->reporting_period, 'cause' => $d->cause, 'reason' => $reason],
            'meta' => ['user' => $userId, 'client_book_id' => (int) $d->client_book_id],
        ]);
    }

    /** A kept difference opened again, with the reason. */
    public function reopen(int $differenceId, int $userId, string $reason): void
    {
        $d = DB::table('client_book_differences')->where('id', $differenceId)->first() ?? throw new RuntimeException("No difference {$differenceId}.");
        if ($d->status !== self::STATUS_KEPT) {
            throw new RuntimeException("Difference {$differenceId} is {$d->status}; only a kept difference is opened again.");
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Give the reason the difference is opened again (at least 10 characters).');
        }
        DB::table('client_book_differences')->where('id', $differenceId)->update(['status' => self::STATUS_OPEN, 'decided_by' => null, 'decision_reason' => null, 'decided_at' => null, 'updated_at' => now()]);
        AuditLoggerService::log('Client Book Difference Reopened', 'client_book_differences', $differenceId, [
            'old_values' => ['status' => self::STATUS_KEPT, 'reason' => $d->decision_reason], 'new_values' => ['status' => self::STATUS_OPEN, 'reason' => $reason], 'meta' => ['user' => $userId],
        ]);
    }

    /** A client book taken off the register (landed in error); its differences go with it, any override proposed from it stays on its own register. */
    public function withdraw(int $bookId, int $userId, string $reason): void
    {
        $book = DB::table('client_loan_books')->where('id', $bookId)->first() ?? throw new RuntimeException("No client book {$bookId}.");
        if ($book->status === 'WITHDRAWN') {
            throw new RuntimeException("Client book {$bookId} is already withdrawn.");
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new RuntimeException('Give the reason the client book is withdrawn (at least 10 characters).');
        }
        DB::transaction(function () use ($bookId, $userId, $reason, $book) {
            DB::table('client_book_differences')->where('client_book_id', $bookId)->whereIn('status', [self::STATUS_OPEN, self::STATUS_INFO])->delete();
            DB::table('client_loan_books')->where('id', $bookId)->update(['status' => 'WITHDRAWN', 'updated_at' => now()]);
            AuditLoggerService::log('Client Loan Book Withdrawn', 'client_loan_books', $bookId, ['old_values' => ['status' => $book->status], 'new_values' => ['status' => 'WITHDRAWN', 'reason' => $reason], 'meta' => ['user' => $userId]]);
        });
    }

    /**
     * The reconciliation template for a month, backfilled with the system's own figures: the client
     * fills in its carrying amount, day count and stage where it differs and uploads it back.
     *
     * @return list<list<mixed>>
     */
    public function templateRows(string $period): array
    {
        $policy = StagingPolicy::at($this->governance, CarbonImmutable::parse($period . '-01')->endOfMonth());
        $staged = $this->staging->evaluate($period, $policy)['loans'];
        $rows = [ClientBookReader::TEMPLATE_HEADER];
        foreach (DB::table('loan_books')->where('reporting_period', $period)->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))
            ->whereRaw('abs(carrying_amount) > 0.005')->orderBy('contract_id')->get(['contract_id', 'customer_name', 'carrying_amount']) as $r) {
            $st = $staged[(string) $r->contract_id] ?? null;
            $rows[] = [(string) $r->contract_id, $r->customer_name, round((float) $r->carrying_amount, 2), $st['dpd'] ?? null, $st['stage'] ?? null, null, null, null, null];
        }

        return $rows;
    }

    /** The directive's brackets (the December 2025 model's): 0 to 30 days (current included), 31 to 90, 91 to 180, over 180. */
    public static function bracket(?int $days): ?string
    {
        return $days === null ? null : match (true) {
            $days <= 30 => '1-30 Days',
            $days <= 90 => '31-90 Days',
            $days <= 180 => '91-180 Days',
            default => '>181 Days',
        };
    }

    // ----- internals ---------------------------------------------------------

    /** @return array{staging: array<string,int>, gross: array<string,int>} the overrides of one status for a period, by contract */
    private function overridesOf(string $period, string $status): array
    {
        $of = fn (string $t) => DB::table($t)->where('reporting_period', $period)->where('status', $status)->orderBy('id')->pluck('id', 'contract_id')
            ->mapWithKeys(fn ($id, $c) => [(string) $c => (int) $id])->all();

        return ['staging' => $of('loan_staging_basis_overrides'), 'gross' => $of('loan_gross_amount_overrides')];
    }

    /**
     * Pair each client row with a loan of the system's book: by account where the client's book
     * names it; for the audited model, which masks the accounts, by a balance equal to the ngwee
     * and unique on both sides, else by customer name with the nearest balance (the December 2025
     * staging check's rule).
     *
     * @return array{0: list<array{0:object,1:string,2:string}>, 1: list<object>, 2: list<string>}
     */
    private function match($client, $system, bool $byName): array
    {
        $pairs = [];
        $clientOnly = [];
        $used = [];
        if (! $byName) {
            foreach ($client as $c) {
                $id = (string) $c->contract_id;
                if ($id !== '' && isset($system[$id]) && ! isset($used[$id])) {
                    $pairs[] = [$c, $id, 'account'];
                    $used[$id] = true;
                } else {
                    $clientOnly[] = $c;
                }
            }
        } else {
            $cents = fn ($v) => (string) (int) round((float) $v * 100);
            $clientByBalance = $client->groupBy(fn ($c) => $cents($c->carrying_amount));
            $systemByBalance = $system->groupBy(fn ($r) => $cents($r->carrying_amount));
            $names = [];
            foreach ($system as $id => $r) {
                $names[$this->name((string) $r->customer_name)][] = $id;
            }
            $rest = [];
            foreach ($client as $c) {
                $k = $cents($c->carrying_amount);
                if (abs((float) $c->carrying_amount) > 0.005 && $clientByBalance[$k]->count() === 1 && isset($systemByBalance[$k]) && $systemByBalance[$k]->count() === 1) {
                    $id = (string) $systemByBalance[$k]->first()->contract_id;
                    $pairs[] = [$c, $id, $this->name((string) $c->customer_name) === $this->name((string) $system[$id]->customer_name) ? 'name and balance' : 'balance (names differ)'];
                    $used[$id] = true;
                } else {
                    $rest[] = $c;
                }
            }
            foreach ($rest as $c) {
                $candidates = array_values(array_filter($names[$this->name((string) $c->customer_name)] ?? [], fn ($id) => ! isset($used[$id])));
                usort($candidates, fn ($a, $b) => abs((float) $system[$a]->carrying_amount - (float) $c->carrying_amount) <=> abs((float) $system[$b]->carrying_amount - (float) $c->carrying_amount));
                $id = $candidates[0] ?? null;
                if ($id === null) {
                    $clientOnly[] = $c;
                    continue;
                }
                $pairs[] = [$c, (string) $id, 'name'];
                $used[$id] = true;
            }
        }
        $systemOnly = array_values(array_filter(array_map('strval', $system->keys()->all()), fn ($id) => ! isset($used[$id])));

        return [$pairs, $clientOnly, $systemOnly];
    }

    private function diff(object $book, ?object $c, ?object $s, ?array $st, ?string $matchedOn, string $cause, string $status, bool $balance = false, bool $bucket = false, bool $stage = false): array
    {
        return [
            'client_book_id' => (int) $book->id, 'reporting_period' => (string) $book->reporting_period, 'client_row_id' => $c?->id,
            'contract_id' => $s?->contract_id ?? $c?->contract_id, 'customer_name' => mb_substr((string) ($s?->customer_name ?? $c?->customer_name ?? ''), 0, 200) ?: null,
            'matched_on' => $matchedOn, 'client_amount' => $c?->carrying_amount, 'system_amount' => $s !== null ? round((float) $s->carrying_amount, 2) : null,
            'client_days' => $c?->days_past_due, 'client_bracket' => self::bracket($c?->days_past_due !== null ? (int) $c->days_past_due : null),
            'system_days' => $st['dpd'] ?? null, 'system_bracket' => self::bracket(isset($st['dpd']) ? (int) $st['dpd'] : null),
            'client_stage' => $c?->stage, 'system_stage' => isset($st['stage']) ? (int) $st['stage'] : null,
            'bucket_differs' => $bucket, 'balance_differs' => $balance, 'stage_differs' => $stage, 'cause' => mb_substr($cause, 0, 200), 'status' => $status,
            'staging_override_id' => null, 'gross_override_id' => null, 'decided_by' => null, 'decision_reason' => null, 'decided_at' => null,
        ];
    }

    private function name(string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^a-z0-9 ]+/', ' ', strtolower($name))));

        return implode(' ', array_filter($words, fn ($w) => $w !== '' && ! in_array($w, self::SUFFIXES, true)));
    }
}
