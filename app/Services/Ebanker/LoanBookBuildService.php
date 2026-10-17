<?php

namespace App\Services\Ebanker;

use App\Jobs\ProcessLoanImportJob;
use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Builds loan_books for a range of months from the landing zone by one of the
 * three methods of spec v4 section 6.2, under the rules of section 6.6:
 *
 *   A  bootstrap: the stored Loan Book Report's latest run for the account and
 *      month-end, column for column; months before the stored report begins
 *      (December 2024) are built as under B and marked derived
 *   B  derivation: carrying amount, disbursed and interest from the ledger's
 *      postings by type; the facility from the masters; the rate charged;
 *      arrears, overdue date, status and segment from the stored run where one
 *      exists. The stored carrying amount is kept beside the derived one and a
 *      difference is a flagged row, never a silent choice
 *   C  the printed report: the CSV MAIIC produces today, through the importer
 *      that already exists, after the date gate and after the rows are landed
 *      verbatim so the month traces to its file like any other
 *
 * Idempotent on account and reporting period. Rebuilding a month prints every
 * difference first. A locked period is never restated. The ECL columns (stage,
 * PD, LGD, FLI, ECL) are not touched by any method. Every row records its
 * method, the load it came from and the inputs it was built from. An account
 * an approved exclusion takes out of the book (the account exclusion
 * register, AccountExclusionService) gets no row: methods A and B read the
 * zone through LandingZoneReader, which leaves it out, and method C moves the
 * report's rows for it to the exclusion's archive.
 */
class LoanBookBuildService
{
    public const METHODS = ['A' => 'bootstrap', 'B' => 'derive', 'C' => 'report'];
    public const BOOTSTRAP_LABEL = 'System Bootstrap (automated data-readiness, not a MAIIC approval)';

    /** Ledger transaction types, from DD_12 and the GL audit (spec v4 sections 3.4 and 7.1). */
    public const TYPE_DISBURSEMENT = ['301', '400'];
    public const TYPE_INTEREST = ['303', '120'];
    public const TYPE_RECEIPT = ['305', '306', '343', '900', '901'];
    /** 300: a write-off. It reduces the gross through the allowance; it is not cash received (spec 3.3; audit H1). */
    public const TYPE_WRITEOFF = ['300'];
    /** 401: the Nascomex loan settled by redemption of its preference shares, a settlement in kind, not cash (spec 3.3 and O13; audit H1). */
    public const TYPE_SETTLEMENT_IN_KIND = ['401'];

    /**
     * The Mega Farm interest split as E-Banker posts it (MF_03, pack 4; review of 14 October 2026).
     * Each month's 15 percent is posted per loan in one INTP batch: 33 percent (type 303, "BANK_INT
     * 33%") debited to the loan's own interest-receivable GL, and 67 percent (type 400, "GOVT_INT 67%
     * Int Ac" or "GOVT_INT_PORTION 67%") debited to the programme's government-portion receivable GL,
     * with its contra (type 401, "GOVT_INT 67%") credited to the fund. Every leg carries the loan
     * account's AC_GLCODE, so the reader returns all three; the contra is the fund's side of the
     * entry, not the loan's, and is left out of the carrying amount. The 67 percent leg is interest
     * (the government's portion), never a drawdown, although type 400 is a disbursement type on
     * MAIIC's own book. The legs are known by their type
     * and narration on the account, and the GLs they were booked to are read from the legs themselves
     * (build_basis.mega_farm), never from a list in code.
     */
    public const TYPE_GOVERNMENT_PORTION = '400';
    public const TYPE_GOVERNMENT_PORTION_CONTRA = '401';
    public const GOVERNMENT_PORTION_NARRATION = 'GOVT_INT';

    /** Type 900 debited to a loan at the migration with this narration is the balance taken on: drawn, not a receipt (isTakeOnDrawdown). */
    public const TYPE_TAKEON_OPENING = '900';
    public const TAKEON_OPENING_NARRATION = 'OPENING ACCOUNT BALANCE';

    /** The flag prefix of every Mega Farm build flag. */
    public const MEGA_FARM_FLAG = 'Mega Farm:';
    /** A Mega Farm account-month whose ledger still does not rebuild the report: the report's figure stands (BaselineService counts it). */
    public const MEGA_FARM_REPORT_STANDS = 'Mega Farm: carrying amount from the Loan Book Report';
    /** A Mega Farm account-month whose report prints the ledger's credit balance as a debit: the ledger stands (BaselineService counts it). */
    public const MEGA_FARM_SIGN_FLAG = 'Mega Farm: the Loan Book Report prints the credit balance as a debit; the ledger stands';

    /** The loan-book columns a build writes; nothing else on the row is touched. */
    private const COLUMNS = [
        'contract_id', 'customer_id', 'customer_name', 'external_identity_id', 'product_group', 'product_code', 'funding_source',
        'loan_portfolio_id', 'reporting_year', 'reporting_month', 'reporting_period', 'create_date', 'due_date', 'tenor', 'remaining_tenor',
        'interest_rate', 'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount', 'interest_to_date',
        'stored_carrying_amount', 'commitments', 'facility_utilisation_rate', 'ead', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180',
        'arrears_180_to_270', 'arrears_271_to_360', 'overdue_days', 'overdue_principal_date', 'overdue_status', 'contract_status',
        'staging_dpd', 'staging_dpd_basis',
        'industry_code', 'industry_type', 'is_month_end', 'build_method', 'build_load_id', 'build_source_key', 'build_flag', 'build_basis',
        'created_at', 'updated_at',
    ];

    public function __construct(private LandingZoneReader $zone, private GovernanceService $governance, private ?\App\Services\Eir\LoanStagingBasisOverrideService $stagingOverrides = null)
    {
        $this->stagingOverrides ??= new \App\Services\Eir\LoanStagingBasisOverrideService();
    }

    /**
     * The method in force on a month-end: the Governance Centre setting's
     * letter (section 6.2). No method is assumed: with no approved value the
     * build stops (GovernanceSettingMissingException), and a value the build
     * does not know is refused with its name (it used to fall back to B).
     */
    public function methodInForce(string $monthEnd): string
    {
        $value = $this->governance->get('loan_book_build_method', CarbonImmutable::parse($monthEnd));
        $letter = strtoupper(substr(trim($value), 0, 1));
        if (! isset(self::METHODS[$letter])) {
            throw new RuntimeException("The governed loan book build method '{$value}' in force at {$monthEnd} is not one the build knows (A, B or C).");
        }

        return $letter;
    }

    /**
     * Request a build. With an approver (a second person, or the bootstrap
     * label) the build runs at once; without one it is recorded as PROPOSED
     * with its preview and nothing is written. A dry run writes nothing at all.
     *
     * @return array{build_id:int,status:string,periods:array<string,array>,loads:array}
     */
    public function build(string $from, string $to, ?string $method, ?int $requestedBy, ?int $approvedBy = null, ?string $approverLabel = null, bool $dryRun = false, bool $retireStale = false): array
    {
        if ($method !== null && ! isset(self::METHODS[strtoupper($method)]) && ! in_array(strtolower($method), self::METHODS, true)) {
            throw new RuntimeException("Unknown build method '{$method}'; use A|bootstrap, B|derive or C|report.");
        }
        if ($method !== null) {
            $method = isset(self::METHODS[strtoupper($method)]) ? strtoupper($method) : array_search(strtolower($method), self::METHODS, true);
        }
        if ($method === 'C') {
            throw new RuntimeException('Method C takes the report file: use buildFromReport().');
        }
        $periods = $this->periods($from, $to);
        $locked = $this->lockedAmong($periods);
        if ($locked !== []) {
            throw new RuntimeException('Locked period(s) are never restated: ' . implode(', ', $locked));
        }
        $last = $this->zone->lastLedgerDate();
        $lastEnd = CarbonImmutable::parse(end($periods) . '-01')->endOfMonth()->toDateString();
        if ($last === null || $lastEnd > $last) {
            throw new RuntimeException('The landing zone ends at ' . ($last ?? 'nothing loaded') . "; a build for {$lastEnd} is refused, never estimated.");
        }
        if ($requestedBy !== null && $approvedBy !== null && $requestedBy === $approvedBy && ! \App\Support\MakerChecker::selfApprovalAllowed('Loan book build', 'loan_book_builds', null, $approvedBy)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the requester.');
        }

        $loads = $this->zone->loadsOf(array_merge(LandingZoneReader::LEDGER, LandingZoneReader::LOAN_BOOK_RUNS, LandingZoneReader::ACCOUNT_MASTER, LandingZoneReader::LOAN_MASTER));
        $result = [];
        $built = [];
        // the ledger to the last month is read once and sliced per month (method B), instead of
        // re-reading some 300,000 postings for every month of the range
        $this->ledgerTo = $lastEnd;
        $this->ledgerCache = null;
        $this->masterCache = [];
        foreach ($periods as $period) {
            $m = $method ?? $this->methodInForce($this->monthEnd($period));
            $rows = $this->rowsFor($period, $m);
            $built[$period] = $rows;
            $result[$period] = ['method' => $m, 'rows' => count($rows), 'flagged' => count(array_filter($rows, fn ($r) => $r['build_flag'] !== null))] + $this->compare($period, $rows);
            $result[$period]['stale'] = $retireStale ? $this->stale($period, $rows) : [];
            $result[$period]['retired'] = 0;
        }
        $approved = $approvedBy !== null || $approverLabel !== null;
        $status = $dryRun ? 'DRY_RUN' : ($approved ? 'BUILT' : 'PROPOSED');
        if ($dryRun) {
            return ['build_id' => 0, 'status' => $status, 'periods' => $result, 'loads' => $loads];
        }

        return DB::transaction(function () use ($from, $to, $method, $requestedBy, $approvedBy, $approverLabel, $loads, &$result, $built, $approved, $status, $retireStale) {
            $buildId = (int) DB::table('loan_book_builds')->insertGetId([
                'method' => $method ?? '?', 'period_from' => $from, 'period_to' => $to, 'status' => $approved ? 'APPROVED' : 'PROPOSED',
                'requested_by' => $requestedBy, 'approved_by' => $approvedBy, 'approver_label' => $approverLabel, 'approved_at' => $approved ? now() : null,
                'pack_loads' => json_encode($loads), 'result' => json_encode($result), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $approved) {
                AuditLoggerService::log('Loan Book Build Proposed', 'loan_book_builds', $buildId, ['new_values' => ['from' => $from, 'to' => $to, 'method' => $method], 'meta' => ['requested_by' => $requestedBy]]);

                return ['build_id' => $buildId, 'status' => 'PROPOSED', 'periods' => $result, 'loads' => $loads];
            }
            foreach ($built as $period => $rows) {
                $this->write($rows);
                if ($retireStale && $result[$period]['stale'] !== []) {
                    $result[$period]['retired'] = DB::table('loan_books')->where('reporting_period', $period)->whereIn('contract_id', $result[$period]['stale'])->whereNull('build_method')->delete();
                }
            }
            DB::table('loan_book_builds')->where('id', $buildId)->update(['result' => json_encode($result)]);
            DB::table('loan_book_builds')->where('id', $buildId)->update(['status' => 'BUILT', 'built_at' => now(), 'updated_at' => now()]);
            AuditLoggerService::log('Loan Book Built', 'loan_book_builds', $buildId, [
                'new_values' => ['from' => $from, 'to' => $to, 'periods' => array_map(fn ($p) => ['method' => $p['method'], 'rows' => $p['rows'], 'changed' => $p['changed'], 'new' => $p['new'], 'retired' => $p['retired']], $result)],
                'meta' => ['requested_by' => $requestedBy, 'approved_by' => $approvedBy, 'approver_label' => $approverLabel, 'pack_loads' => $loads],
            ]);

            return ['build_id' => $buildId, 'status' => 'BUILT', 'periods' => $result, 'loads' => $loads];
        });
    }

    /** Rebuild a PROPOSED build after a second person approves it. */
    public function approve(int $buildId, int $approvedBy): array
    {
        $b = DB::table('loan_book_builds')->where('id', $buildId)->first();
        if ($b === null || $b->status !== 'PROPOSED') {
            throw new RuntimeException("Build {$buildId} is not awaiting approval.");
        }
        // decided here without recording: build() checks the same two people again and records the one use
        if ((int) $b->requested_by === $approvedBy && ! \App\Support\MakerChecker::allowedFor(\App\Models\User::find($approvedBy))) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the requester.');
        }
        DB::table('loan_book_builds')->where('id', $buildId)->update(['status' => 'REJECTED', 'note' => 'Superseded by the approved build', 'updated_at' => now()]);

        return $this->build($b->period_from, $b->period_to, $b->method === '?' ? null : $b->method, (int) $b->requested_by, $approvedBy);
    }

    /**
     * Method C: the printed Loan Book Report as a CSV. The file passes the date
     * gate (every date in the report's d/m/Y form, the row named on failure), is
     * landed verbatim against a load of its own, then read by the importer that
     * already exists; the rows it wrote are stamped with the method and the load.
     */
    public function buildFromReport(string $path, string $period, ?int $requestedBy, ?int $approvedBy = null, ?string $approverLabel = null, bool $dryRun = false): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Not a file: {$path}");
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new RuntimeException("Period must be YYYY-MM, got '{$period}'.");
        }
        if ($this->lockedAmong([$period]) !== []) {
            throw new RuntimeException("Locked period {$period} is never restated.");
        }
        if ($requestedBy !== null && $approvedBy !== null && $requestedBy === $approvedBy && ! \App\Support\MakerChecker::selfApprovalAllowed('Loan book build', 'loan_book_builds', null, $approvedBy)) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the requester.');
        }
        $raw = file_get_contents($path);
        $sha = hash('sha256', $raw);
        $fh = fopen($path, 'r');
        $rows = [];
        $failures = [];
        $i = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $i++;
            if ($i === 1) {
                continue;
            }
            if (count($row) < 7) {
                continue;
            }
            $dateCells = trim((string) ($row[0] ?? '')) === '' ? [$row[6] ?? '', $row[7] ?? ''] : [$row[5] ?? '', $row[6] ?? ''];
            foreach ($dateCells as $v) {
                $v = trim((string) $v);
                if ($v === '' || $v === '-') {
                    continue;
                }
                if ($this->strictDate($v, 'd/m/Y') === null) {
                    $failures[] = "row {$i}: '{$v}' is not an unambiguous date in the report's d/m/Y form";
                    break 2;
                }
            }
            $rows[] = $row;
        }
        fclose($fh);
        $gates = ['file' => basename($path), 'sha256' => $sha, 'rows' => count($rows), 'failures' => $failures];
        if ($failures !== [] || $dryRun) {
            return ['build_id' => 0, 'status' => $failures !== [] ? 'QUARANTINED' : 'DRY_RUN', 'gates' => $gates, 'periods' => []];
        }
        $approved = $approvedBy !== null || $approverLabel !== null;

        return DB::transaction(function () use ($path, $raw, $sha, $rows, $period, $requestedBy, $approvedBy, $approverLabel, $approved, $gates) {
            $existing = DB::table('ebanker_loads')->where('pack_hash', $sha)->first();
            $loadId = $existing ? (int) $existing->id : (int) DB::table('ebanker_loads')->insertGetId([
                'pack_hash' => $sha, 'pack_name' => 'Loan Book Report ' . basename($path), 'route' => 'METHOD_C_REPORT', 'period' => $period,
                'date_format' => 'd/m/Y', 'manifest' => json_encode(['file' => basename($path), 'sha256' => $sha, 'rows' => count($rows), 'period' => $period]),
                'gates' => json_encode($gates), 'status' => 'LANDED', 'loaded_by' => $requestedBy, 'loaded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $existing) {
                $monthEnd = $this->monthEnd($period);
                $batch = [];
                foreach ($rows as $n => $row) {
                    $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
                    $batch[] = ['load_id' => $loadId, 'query_id' => 'LBR', 'source_key' => $sha . '#' . ($n + 2), 'account' => null, 'row_date' => $monthEnd,
                        'payload' => $payload, 'row_hash' => hash('sha256', $payload), 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
                    if (count($batch) >= 500) {
                        DB::table('ebanker_raw_rows')->insert($batch); $batch = [];
                    }
                }
                if ($batch !== []) {
                    DB::table('ebanker_raw_rows')->insert($batch);
                }
            }
            $buildId = (int) DB::table('loan_book_builds')->insertGetId([
                'method' => 'C', 'period_from' => $period, 'period_to' => $period, 'status' => $approved ? 'APPROVED' : 'PROPOSED',
                'requested_by' => $requestedBy, 'approved_by' => $approvedBy, 'approver_label' => $approverLabel, 'approved_at' => $approved ? now() : null,
                'pack_loads' => json_encode([['load_id' => $loadId, 'pack_hash' => $sha, 'pack' => basename($path)]]), 'result' => json_encode(['gates' => $gates]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $approved) {
                return ['build_id' => $buildId, 'status' => 'PROPOSED', 'gates' => $gates, 'periods' => []];
            }
            // the importer reads from storage/app; hand it a copy there
            $rel = 'imports/method-c/' . $sha . '.csv';
            $full = storage_path('app/' . $rel);
            if (! is_dir(dirname($full))) {
                mkdir(dirname($full), 0775, true);
            }
            file_put_contents($full, $raw);
            $portfolioId = (int) (DB::table('loan_portfolios')->orderBy('id')->value('id') ?? 1);
            $started = now();
            (new ProcessLoanImportJob($rel, basename($path), $period, $portfolioId))->handle();
            $stamped = DB::table('loan_books')->where('reporting_period', $period)->where('updated_at', '>=', $started)
                ->update(['build_method' => 'C', 'build_load_id' => $loadId, 'build_source_key' => null, 'build_flag' => null,
                    'build_basis' => json_encode(['file' => basename($path), 'sha256' => $sha]), 'is_month_end' => 1]);
            // the printed report carries every account E-Banker holds: the rows of an account the exclusion
            // register takes out of the book go to its archive, as the other doors leave them out (AccountExclusionService)
            $stamped -= array_sum(app(AccountExclusionService::class)->reapply());
            $result = [$period => ['method' => 'C', 'rows' => $stamped, 'flagged' => 0, 'gates' => $gates]];
            DB::table('loan_book_builds')->where('id', $buildId)->update(['status' => 'BUILT', 'built_at' => now(), 'result' => json_encode($result), 'updated_at' => now()]);
            AuditLoggerService::log('Loan Book Built', 'loan_book_builds', $buildId, ['new_values' => ['period' => $period, 'method' => 'C', 'rows' => $stamped, 'file' => basename($path), 'sha256' => $sha], 'meta' => ['requested_by' => $requestedBy, 'approved_by' => $approvedBy, 'approver_label' => $approverLabel]]);

            return ['build_id' => $buildId, 'status' => 'BUILT', 'gates' => $gates, 'periods' => $result];
        });
    }

    /** The rows a method produces for a period, without writing them. */
    public function rowsFor(string $period, string $method): array
    {
        $monthEnd = $this->monthEnd($period);

        return $method === 'A' ? $this->rowsA($monthEnd) : $this->rowsB($monthEnd);
    }

    /**
     * Every difference between what is in loan_books for the period and what
     * the build would write: new accounts, changed carrying amounts, rows the
     * build does not produce. Printed before any overwrite (section 6.6).
     *
     * @return array{new:int,changed:int,unchanged:int,not_in_build:int,differences:list<array>}
     */
    public function compare(string $period, array $rows): array
    {
        $existing = DB::table('loan_books')->where('reporting_period', $period)->get(['contract_id', 'carrying_amount', 'build_method'])->keyBy('contract_id');
        $new = $changed = $unchanged = 0;
        $diffs = [];
        $seen = [];
        foreach ($rows as $r) {
            $seen[$r['contract_id']] = true;
            $e = $existing->get($r['contract_id']);
            if ($e === null) {
                $new++;
                continue;
            }
            if (abs((float) $e->carrying_amount - (float) $r['carrying_amount']) > 0.005) {
                $changed++;
                if (count($diffs) < 200) {
                    $diffs[] = ['contract_id' => $r['contract_id'], 'was' => round((float) $e->carrying_amount, 2), 'now' => round((float) $r['carrying_amount'], 2), 'was_method' => $e->build_method, 'now_method' => $r['build_method']];
                }
            } else {
                $unchanged++;
            }
        }
        $notInBuild = 0;
        foreach ($existing as $cid => $e) {
            if (! isset($seen[$cid])) {
                $notInBuild++;
            }
        }

        return ['new' => $new, 'changed' => $changed, 'unchanged' => $unchanged, 'not_in_build' => $notInBuild, 'differences' => $diffs];
    }

    /**
     * Rows of the period the build does not produce and no build ever wrote,
     * on a GL the build covers or on no GL at all (the old test imports):
     * retired only when asked, never a Mega Farm or other out-of-scope row.
     *
     * @return list<string> contract ids
     */
    public function stale(string $period, array $rows): array
    {
        $produced = array_flip(array_column($rows, 'contract_id'));
        $out = [];
        $q = DB::table('loan_books')->where('reporting_period', $period)->whereNull('build_method')
            ->where(fn ($w) => $w->whereNull('product_code')->orWhereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS)));
        foreach ($q->pluck('contract_id') as $cid) {
            if (! isset($produced[$cid])) {
                $out[] = $cid;
            }
        }

        return $out;
    }

    // ----- method A -------------------------------------------------------

    private function rowsA(string $monthEnd): array
    {
        $runs = $this->zone->latestRunsOn($monthEnd);
        if ($runs === []) {
            // before the stored report begins: built from the ledger and the balance history, marked derived
            $rows = $this->rowsB($monthEnd);
            foreach ($rows as &$r) {
                $r['build_method'] = 'A';
                $r['build_flag'] = trim('derived: no stored run for this month-end; ' . ($r['build_flag'] ?? ''), '; ');
            }

            return $rows;
        }
        $master = $this->zone->accountMaster();
        $priorOverdueDates = $this->priorOverdueDates($monthEnd);
        $overrides = $this->stagingOverrides->active();
        $rows = [];
        foreach ($runs as $account => $run) {
            $p = $run['payload'];
            $gl = trim((string) ($p['GLCODE'] ?? ''));
            [$group, $fund] = $this->groupOf($gl);
            $name = $master[$account]['ACCOUNT_NAME'] ?? null;
            $arrears = $this->arrears($p);
            [$dpd, $odDate, $dpdFlag, $stagingDpd, $stagingBasis, $dpdCounts] = $this->daysPastDue($p, $monthEnd, $arrears, $this->date($p['MATURITY_DATE'] ?? ''), $this->num($p['CARRYING_AMOUNT'] ?? 0), $account);
            $pickFlag = $run['pick_conflict'] ?? null;
            $contractId = ltrim($account, '0');
            $createDate = $this->date($p['VALUE_DATE'] ?? '');
            $jumpFlag = $this->overdueDateJumpFlag($contractId, $odDate, $createDate, $priorOverdueDates);
            [$stagingDpd, $stagingBasis, $overrideFlag, $dpdCounts] = $this->applyStagingOverride($contractId, $dpd, $stagingDpd, $stagingBasis, $overrides, $dpdCounts);
            $rows[] = $this->row([
                'contract_id' => $contractId, 'customer_id' => trim((string) ($p['CUSTOMER_ID'] ?? '')), 'customer_name' => $name,
                'external_identity_id' => $account, 'product_group' => $group, 'product_code' => $gl, 'funding_source' => $fund,
                'create_date' => $createDate, 'due_date' => $this->date($p['MATURITY_DATE'] ?? ''),
                'tenor' => (int) round($this->num($p['TENOR_YRS'] ?? 0) * 12), 'interest_rate' => $this->num($p['INTEREST_RATE'] ?? 0),
                'principal_balance' => $this->num($p['PRINCIPAL'] ?? 0), 'approved_amount' => $this->num($p['APPROVED'] ?? 0),
                'disbursed' => $this->num($p['DISBURSD'] ?? 0), 'repayments' => $this->num($p['REPAYMENT'] ?? 0),
                'carrying_amount' => $this->num($p['CARRYING_AMOUNT'] ?? 0), 'interest_to_date' => $this->num($p['INTEREST_TO_DATE'] ?? 0),
                'stored_carrying_amount' => $this->num($p['CARRYING_AMOUNT'] ?? 0), 'commitments' => $this->num($p['NOT_YET_DISBURS'] ?? 0),
                'overdue_days' => $dpd, 'overdue_principal_date' => $odDate, 'contract_status' => $master[$account]['STATUS_CODE'] ?? null,
                'staging_dpd' => $stagingDpd, 'staging_dpd_basis' => $stagingBasis,
                'industry_code' => $this->str($p['INDUSTRY_CODE'] ?? null), 'industry_type' => $this->str($p['IND_DESCR'] ?? null, 100),
                'build_method' => 'A', 'build_load_id' => $run['load_id'], 'build_source_key' => $run['source_key'],
                'build_flag' => implode('; ', array_filter([$pickFlag, $dpdFlag, $jumpFlag, $overrideFlag])) ?: null,
                'build_basis' => ['run' => $run['query_id'] . ':' . $run['source_key'], 'run_date' => $this->date($p['TRANSACTION_DATE'] ?? ''), 'segment' => $p['SEG_MENT'] ?? null, 'dpd_counts' => $dpdCounts],
            ] + $arrears, $monthEnd);
        }

        return $rows;
    }

    /**
     * The overdue-principal date last month's build booked, by contract id (section 6.4): a batch
     * read, once per build, so the cross-period check below costs no extra query per loan.
     */
    private function priorOverdueDates(string $monthEnd): array
    {
        $prior = CarbonImmutable::parse($monthEnd)->subMonthNoOverflow()->format('Y-m');

        return DB::table('loan_books')->where('reporting_period', $prior)->whereNotNull('overdue_principal_date')
            ->pluck('overdue_principal_date', 'contract_id')->all();
    }

    /**
     * A jump in the reported overdue-principal date that last month's own booked figure, or the
     * loan's own value date, says is not ageing drift: a date before the loan was even disbursed,
     * or a move of more than 150 days since last month (ordinary bi-monthly and quarterly
     * instalment schedules were found to move the date by up to 141 days between two consecutive
     * month-ends; nothing legitimate moved it further). A third, distinct E-Banker defect from the
     * re-pull drift section 6 corrects and the broken-bucket defect section 6.1 catches, found on 6
     * of the 36 facility mismatches against MAIIC's December 2025 workbook (verification of 17
     * October 2026). Never resolved silently: the reported date stands, flagged for a person.
     */
    private function overdueDateJumpFlag(string $contractId, ?string $odDate, ?string $createDate, array $priorOverdueDates): ?string
    {
        if ($odDate === null) {
            return null;
        }
        if ($createDate !== null && $odDate < $createDate) {
            return sprintf(
                "the reported overdue-principal date (%s) is before the loan's own value date (%s): kept as reported, flagged for review (Loan Book Report Point-in-Time Specification v1, section 6.4)",
                $odDate, $createDate
            );
        }
        $prior = $priorOverdueDates[$contractId] ?? null;
        if ($prior === null) {
            return null;
        }
        $diff = abs(CarbonImmutable::parse($odDate)->diffInDays(CarbonImmutable::parse($prior)));
        if ($diff > 150) {
            return sprintf(
                'the reported overdue-principal date moved from %s to %s since last month (%d days): kept as reported, flagged for review (Loan Book Report Point-in-Time Specification v1, section 6.4)',
                $prior, $odDate, $diff
            );
        }

        return null;
    }

    /**
     * A governed, per-loan exception from the staging policy's own basis (December 2025 Loan
     * Book Investigation Findings v1, section 10, and the discussion that followed it on 17
     * October 2026, decided without building a general policy change: the directive's own
     * schedule-aware count, already computed for every loan as `overdue_days`, matched MAIIC's
     * workbook on JAT and BenCal; the staging policy in force was still reading the raw report
     * bucket for them). Where an approved override exists for this loan, staging_dpd becomes
     * that already-computed directive count instead of whatever the governed policy would have
     * read; every other loan is staged exactly as the policy says, unchanged. Never a typed-in
     * number — there is nothing here for a person to get wrong beyond the decision itself.
     *
     * @return array{0:int,1:string,2:?string}
     */
    /**
     * @return array{0:int,1:string,2:?string,3:array<string,int>}
     */
    private function applyStagingOverride(string $contractId, int $directiveDpd, int $stagingDpd, string $stagingBasis, array $overrides, array $dpdCounts): array
    {
        $override = $overrides[$contractId] ?? null;
        if ($override === null) {
            return [$stagingDpd, $stagingBasis, null, $dpdCounts];
        }
        $isAudited = ($override['source'] ?? null) === \App\Services\Eir\LoanStagingBasisOverrideService::SOURCE_AUDITED_WORKBOOK;
        $targetDpd = $isAudited ? ($override['override_days'] ?? $directiveDpd) : $directiveDpd;
        if ($stagingDpd === $targetDpd) {
            return [$stagingDpd, $stagingBasis, null, $dpdCounts];
        }
        $sourceLabel = $isAudited ? sprintf('an audited figure (%d days)', $targetDpd) : "the directive's own already-computed count ({$targetDpd} days)";
        $flag = sprintf(
            "staging basis overridden by governed decision #%d: uses %s instead of the policy's %s (%d days); reason: %s",
            $override['id'], $sourceLabel, $stagingBasis, $stagingDpd, $override['reason']
        );
        // StagingService::evaluate() recomputes the day count from these counts under whichever
        // policy is in force at the time it is read, bypassing staging_dpd entirely whenever they
        // are present — which is always. Forcing every one of them to the override's own figure
        // is what makes the override survive that recompute, under any governed policy, not just
        // the one in force when this build ran.
        $overriddenCounts = $dpdCounts;
        foreach (['oldest', 'oldest_recounted', 'bucket', 'bucket_recounted', 'since_maturity'] as $key) {
            $overriddenCounts[$key] = $targetDpd;
        }

        return [$targetDpd, sprintf('overridden to %s (policy basis was: %s)', $sourceLabel, $stagingBasis), $flag, $overriddenCounts];
    }

    // ----- method B -------------------------------------------------------

    /** The last month-end of the build in progress, and the ledger read once to it. */
    private ?string $ledgerTo = null;

    private ?array $ledgerCache = null;

    /** The masters, read once per build: they do not depend on the month. */
    private array $masterCache = [];

    /**
     * The ledger dated on or before the month-end, with the Mega Farm postings. Within a build it is
     * a slice of the ledger read once to the build's last month-end: each account's postings are in
     * date order, so the slice is the same rows a read to this month-end returns.
     */
    private function ledgerOn(string $monthEnd): array
    {
        if ($this->ledgerTo === null || $monthEnd > $this->ledgerTo) {
            return $this->zone->ledgerByAccount($monthEnd, null, true);
        }
        $this->ledgerCache ??= $this->zone->ledgerByAccount($this->ledgerTo, null, true);
        if ($monthEnd === $this->ledgerTo) {
            return $this->ledgerCache;
        }
        $out = [];
        foreach ($this->ledgerCache as $account => $rows) {
            $kept = [];
            foreach ($rows as $row) {
                if ($row['row_date'] === null || $row['row_date'] > $monthEnd) {
                    break;
                }
                $kept[] = $row;
            }
            if ($kept !== []) {
                $out[$account] = $kept;
            }
        }

        return $out;
    }

    private function rowsB(string $monthEnd): array
    {
        // the Mega Farm accounts are in the Loan Book Report too, so their own ledger is read for them
        $ledger = $this->ledgerOn($monthEnd);
        $runs = $this->zone->latestRunsOn($monthEnd);
        $priorOverdueDates = $this->priorOverdueDates($monthEnd);
        $overrides = $this->stagingOverrides->active();
        $master = $this->masterCache['account'] ??= $this->zone->accountMaster();
        $loans = $this->masterCache['loan'] ??= $this->zone->loanMaster();
        $rates = $this->masterCache['rates'] ??= $this->zone->rateSetupByAccount();
        $statuses = $this->masterCache['status'] ??= $this->zone->statusHistoryByAccount();
        // a write-off posted as a repayment (spec 3.8) is a write-off here too
        $writeOffs = new WriteOffClassifier($this->zone);
        $accounts = array_unique(array_merge(array_keys($ledger), array_keys($runs)));
        sort($accounts);
        $rows = [];
        $monthStart = substr($monthEnd, 0, 8) . '01';
        foreach ($accounts as $account) {
            $run = $runs[$account] ?? null;
            $p = $run['payload'] ?? [];
            $am = $master[$account] ?? [];
            $gl = trim((string) ($am['GLCODE'] ?? ($p['GLCODE'] ?? '')));
            $megaFarm = ! isset(LandingZoneReader::LOAN_GLS[$gl]) && in_array($gl, \App\Services\MegaFarm\MegaFarmEclService::GLS, true);
            $sum = ['all' => 0.0, 'disb' => 0.0, 'int' => 0.0, 'rec' => 0.0, 'woff' => 0.0, 'kind' => 0.0, 'other' => 0.0, 'takeon' => 0.0, 'n' => 0, 'first' => null, 'last' => null, 'load' => null];
            $mf = ['own' => 0.0, 'own_month' => 0.0, 'gov' => 0.0, 'gov_month' => 0.0, 'contra' => 0.0, 'gov_gls' => [], 'contra_gls' => [], 'batches' => []];
            foreach ($ledger[$account] ?? [] as $post) {
                $amt = $this->num($post['payload']['TRANSAMT'] ?? 0);
                $type = (string) ($post['payload']['TRANTYPE'] ?? '');
                $inMonth = $post['row_date'] !== null && $post['row_date'] >= $monthStart;
                if ($megaFarm && $this->isGovernmentPortion($post['payload'], self::TYPE_GOVERNMENT_PORTION_CONTRA)) {
                    // the fund's side of the government-portion entry: not part of the loan's carrying amount
                    $mf['contra'] += $amt;
                    $mf['contra_gls'][trim((string) ($post['payload']['CB_GLCODE'] ?? ''))] = true;
                    $key = WriteOffClassifier::batchKey($post['row_date'], $post['payload']) ?? ('row ' . $post['source_key']);
                    $mf['batches'][$key] = ($mf['batches'][$key] ?? 0.0) + $amt;
                    continue;
                }
                if ($megaFarm && $this->isGovernmentPortion($post['payload'], self::TYPE_GOVERNMENT_PORTION)) {
                    $mf['gov'] -= $amt;
                    $mf['gov_month'] -= $inMonth ? $amt : 0.0;
                    $mf['gov_gls'][trim((string) ($post['payload']['CB_GLCODE'] ?? ''))] = true;
                    $key = WriteOffClassifier::batchKey($post['row_date'], $post['payload']) ?? ('row ' . $post['source_key']);
                    $mf['batches'][$key] = ($mf['batches'][$key] ?? 0.0) + $amt;
                    // the government's 67 percent is interest charged, not a drawdown: counted as interest so that
                    // neither the disbursed amount nor the undrawn commitment moves with the interest posted
                    // (type 400 stays a disbursement type on MAIIC's own book, TYPE_DISBURSEMENT)
                    $sum['int'] += $amt;
                    $sum['all'] += $amt; $sum['n']++;
                    $sum['first'] ??= $post['row_date']; $sum['last'] = $post['row_date']; $sum['load'] = $post['load_id'];
                    continue;
                }
                if ($megaFarm && in_array($type, self::TYPE_INTEREST, true)) {
                    $mf['own'] -= $amt;
                    $mf['own_month'] -= $inMonth ? $amt : 0.0;
                }
                $sum['all'] += $amt; $sum['n']++;
                $sum['first'] ??= $post['row_date']; $sum['last'] = $post['row_date']; $sum['load'] = $post['load_id'];
                if ($writeOffs->isWriteOff($post['payload'], $post['row_date'], $account)) {
                    $sum['woff'] += $amt;     // in the balance, never in repayments (type 300, a 1064 batch, an H closure)
                } elseif (in_array($type, self::TYPE_DISBURSEMENT, true)) {
                    $sum['disb'] += $amt;
                } elseif ($this->isTakeOnDrawdown($post)) {
                    $sum['disb'] += $amt;     // the balance taken on at the migration was drawn (see TYPE_TAKEON_OPENING)
                    $sum['takeon'] += $amt;
                } elseif (in_array($type, self::TYPE_INTEREST, true)) {
                    $sum['int'] += $amt;
                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;
                } elseif (in_array($type, self::TYPE_SETTLEMENT_IN_KIND, true)) {
                    $sum['kind'] += $amt;     // in the balance, never in repayments
                } else {
                    $sum['other'] += $amt;
                }
            }
            $carrying = round(-$sum['all'], 2);
            if (abs($carrying) < 0.005 && $run === null) {
                continue; // closed and not in the report for the month
            }
            $lm = $loans[$account] ?? [];
            [$group, $fund] = $this->groupOf($gl);
            $flags = [];
            if (($run['pick_conflict'] ?? null) !== null) {
                $flags[] = $run['pick_conflict'];
            }
            $stored = $run !== null ? $this->num($p['CARRYING_AMOUNT'] ?? 0) : null;
            $derived = $carrying;
            $signError = null;
            if ($megaFarm) {
                // a Mega Farm account (outside the EIR, decision D30): its MF_03 ledger, with the government's
                // 67 percent interest legs, rebuilds the balance E-Banker reports; where it still does not,
                // the Loan Book Report's carrying amount stands and the difference is kept on the row
                $unpaired = array_filter($mf['batches'], fn ($v) => abs($v) > 0.005);
                if ($unpaired !== []) {
                    $flags[] = sprintf('%s %d government-portion batch(es) whose 67%% leg and its contra on the fund do not net to nil (first %s)', self::MEGA_FARM_FLAG, count($unpaired), array_key_first($unpaired));
                }
                if ($stored !== null && abs($stored - $derived) > 0.005) {
                    if ($derived < -0.005 && abs($derived + $stored) <= 0.005) {
                        // the report prints an overpaid account's credit balance as a debit (60 accounts in its May, June
                        // and July 2026 runs; the April and August runs agree with the ledger): a sign error in the
                        // report, so the ledger's credit stands and the ECL floors the exposure at nil (EclExposure)
                        $flags[] = self::MEGA_FARM_SIGN_FLAG;
                        $signError = ['report_carrying' => round($stored, 2), 'ledger_carrying' => round($derived, 2)];
                    } else {
                        $carrying = $stored;
                        $flags[] = sprintf('%s; the MF_03 ledger with its government-portion legs derives %s (difference %s)', self::MEGA_FARM_REPORT_STANDS, number_format($derived, 2), number_format($derived - $stored, 2));
                    }
                }
            } elseif ($stored !== null && abs($stored - $carrying) > 0.005) {
                $flags[] = sprintf('carrying amount differs from the stored run by %s', number_format($carrying - $stored, 2));
            }
            if ($am === []) {
                $flags[] = 'account not in the account master';
            }
            $disbursed = round(-$sum['disb'], 2);
            $interest = round(-$sum['int'], 2);
            $receipts = round($sum['rec'], 2);
            // E-Banker's own principal/interest split is kept where a run exists; the ledger cannot
            // say how much of a receipt settled interest, so without a run the whole balance is principal
            $principal = $run !== null ? $this->num($p['PRINCIPAL'] ?? 0) : $carrying;
            $approved = $this->num($lm['SANCTION_AMOUNT'] ?? ($p['APPROVED'] ?? 0));
            // the facility's end: the run's maturity date, or where the run leaves it empty the loan master's expiry
            // (an empty MATURITY_DATE used to leave the row with no due date, so a facility that had expired kept its
            // undrawn sanction as a commitment; review of 14 October 2026)
            [$due, $dueSource] = $this->dueDate($p, $lm);
            $rate = $this->rateOn($account, $monthEnd, $p, $rates, $am);
            $arrears = $this->arrears($p);
            [$dpd, $odDate, $dpdFlag, $stagingDpd, $stagingBasis, $dpdCounts] = $this->daysPastDue($p, $monthEnd, $arrears, $due, $carrying, $account);
            if ($dpdFlag !== null) {
                $flags[] = $dpdFlag;
            }
            $tenor = (int) ($this->num($lm['PERIOD_YEARS'] ?? 0) * 12 + $this->num($lm['PERIOD_MONTHS'] ?? 0));
            if ($tenor === 0 && isset($p['TENOR_YRS'])) {
                $tenor = (int) round($this->num($p['TENOR_YRS']) * 12);
            }
            $contractId = ltrim($account, '0');
            $createDate = $this->date($p['VALUE_DATE'] ?? ($am['ACCOUNT_OPEN_DATE'] ?? '')) ?? $sum['first'];
            $jumpFlag = $this->overdueDateJumpFlag($contractId, $odDate, $createDate, $priorOverdueDates);
            if ($jumpFlag !== null) {
                $flags[] = $jumpFlag;
            }
            [$stagingDpd, $stagingBasis, $overrideFlag, $dpdCounts] = $this->applyStagingOverride($contractId, $dpd, $stagingDpd, $stagingBasis, $overrides, $dpdCounts);
            if ($overrideFlag !== null) {
                $flags[] = $overrideFlag;
            }
            $rows[] = $this->row([
                'contract_id' => $contractId, 'customer_id' => trim((string) ($am['CUSTOMER_ID'] ?? ($p['CUSTOMER_ID'] ?? ''))),
                'customer_name' => $am['ACCOUNT_NAME'] ?? null, 'external_identity_id' => $account, 'product_group' => $group, 'product_code' => $gl,
                'funding_source' => $fund,
                'create_date' => $createDate,
                'due_date' => $due,
                'tenor' => $tenor, 'interest_rate' => $rate, 'principal_balance' => $principal, 'approved_amount' => $approved,
                'disbursed' => $disbursed, 'repayments' => $receipts, 'carrying_amount' => $carrying, 'interest_to_date' => $interest,
                'stored_carrying_amount' => $stored, 'commitments' => max(0.0, round($approved - $disbursed, 2)),
                'overdue_days' => $dpd, 'overdue_principal_date' => $odDate, 'contract_status' => $this->statusOn($account, $monthEnd, $statuses, $am),
                'staging_dpd' => $stagingDpd, 'staging_dpd_basis' => $stagingBasis,
                'industry_code' => $this->str($p['INDUSTRY_CODE'] ?? ($lm['INDUSTRY_CODE'] ?? null)), 'industry_type' => $this->str($p['IND_DESCR'] ?? null, 100),
                'build_method' => 'B', 'build_load_id' => $sum['load'] ?? ($run['load_id'] ?? null), 'build_source_key' => $run['source_key'] ?? null,
                'build_flag' => $flags === [] ? null : implode('; ', $flags),
                'build_basis' => ['postings' => $sum['n'], 'first_posting' => $sum['first'], 'last_posting' => $sum['last'],
                    'ledger' => ['disbursed' => $disbursed, 'interest' => $interest, 'receipts' => $receipts, 'written_off' => round($sum['woff'], 2), 'settled_in_kind' => round($sum['kind'], 2), 'other' => round($sum['other'], 2)],
                    'stored_run' => $run !== null ? $run['query_id'] . ':' . $run['source_key'] : null, 'rate_source' => $this->rateSource,
                    'ledger_carrying' => round($derived, 2), 'dpd_counts' => $dpdCounts]
                    + ($dueSource !== null ? ['due_date_source' => $dueSource] : [])
                    + (abs($sum['takeon']) > 0.005 ? ['takeon_drawn' => round(-$sum['takeon'], 2)] : [])
                    + ($megaFarm ? ['mega_farm' => $this->megaFarmSplit($mf) + ($signError !== null ? ['report_sign_error' => $signError] : [])] : []),
            ] + $arrears, $monthEnd);
        }

        return $rows;
    }

    /**
     * The balance a loan was taken on with at the migration (MF_03, pack 4): a type 900 debit to the
     * loan, narrated "Opening Account Balance", dated on or before the take-on date
     * (TakeonLandingService::TAKEON_DATE, 31 July 2024). The Mega Farm loans of the 2024 season came
     * into E-Banker this way (413 postings, 8,179.3m, all on 31 July 2024), so the amount was drawn
     * before the migration; read as a receipt (TYPE_RECEIPT) it left the whole sanction looking
     * undrawn. Every other type 900 posting keeps its place among the receipts. MAIIC's own book was
     * taken on by type 301 ("Opening Account Balance Disbursement amount") and holds no such debit.
     */
    private function isTakeOnDrawdown(array $post): bool
    {
        $p = $post['payload'];

        return (string) ($p['TRANTYPE'] ?? '') === self::TYPE_TAKEON_OPENING
            && $this->num($p['TRANSAMT'] ?? 0) < 0
            && $post['row_date'] !== null && $post['row_date'] <= TakeonLandingService::TAKEON_DATE
            && str_contains(strtoupper((string) ($p['PARTICULARS'] ?? '')), self::TAKEON_OPENING_NARRATION);
    }

    /**
     * The due date of the facility and, when it is not the run's, where it came from: the run's
     * MATURITY_DATE, else the loan master's EXPIRY_DATE. An empty cell is no date.
     *
     * @return array{0:?string,1:?string}
     */
    private function dueDate(array $run, array $loanMaster): array
    {
        $maturity = $this->date((string) ($run['MATURITY_DATE'] ?? ''));
        if ($maturity !== null || $run === []) {
            return $run === [] ? [$this->date((string) ($loanMaster['EXPIRY_DATE'] ?? '')), null] : [$maturity, null];
        }
        $expiry = $this->date((string) ($loanMaster['EXPIRY_DATE'] ?? ''));

        return [$expiry, $expiry !== null ? "loan master EXPIRY_DATE: the report's MATURITY_DATE is empty" : null];
    }

    /** A government-portion leg of the Mega Farm interest batch: its type and the GOVT_INT narration, on the account. */
    private function isGovernmentPortion(array $payload, string $type): bool
    {
        return (string) ($payload['TRANTYPE'] ?? '') === $type
            && str_contains(strtoupper((string) ($payload['PARTICULARS'] ?? '')), self::GOVERNMENT_PORTION_NARRATION);
    }

    /**
     * The interest split of a Mega Farm account-month, for decision D30 (Dr Thom: the 33/67 split):
     * the interest charged to date and in the month on the loan's own interest-receivable GL (types
     * 303 and 120, MAIIC's 33 percent) and on the government-portion GL (type 400, the fund's 67
     * percent), their sum, the contra left out of the carrying amount, and the GLs each was booked
     * to as the legs name them. The 67 percent legs are interest: they are in the row's
     * interest_to_date, not in its disbursed amount, so the undrawn commitment (approved less
     * disbursed) is not reduced by interest posted.
     */
    private function megaFarmSplit(array $mf): array
    {
        // GL codes as array keys come back as integers: read back as the strings the ledger carries
        $gls = function (array $set): array {
            $l = array_values(array_filter(array_map('strval', array_keys($set)), fn ($g) => $g !== ''));
            sort($l);

            return $l;
        };

        return [
            'interest_own_gl' => round($mf['own'], 2), 'interest_government_portion' => round($mf['gov'], 2), 'interest_total' => round($mf['own'] + $mf['gov'], 2),
            'month' => ['interest_own_gl' => round($mf['own_month'], 2), 'interest_government_portion' => round($mf['gov_month'], 2), 'interest_total' => round($mf['own_month'] + $mf['gov_month'], 2)],
            'government_portion_contra_excluded' => round($mf['contra'], 2),
            'government_portion_gls' => $gls($mf['gov_gls']), 'contra_gls' => $gls($mf['contra_gls']),
        ];
    }

    private string $rateSource = '';

    /** The rate charged: the stored run's rate; else the rate set-up in force on the date; else the account master. */
    private function rateOn(string $account, string $monthEnd, array $run, array $rates, array $am): float
    {
        if (isset($run['INTEREST_RATE']) && trim((string) $run['INTEREST_RATE']) !== '') {
            $this->rateSource = 'stored run';

            return $this->num($run['INTEREST_RATE']);
        }
        $rate = null;
        foreach ($rates[$account] ?? [] as $r) {
            if ($r['row_date'] !== null && $r['row_date'] <= $monthEnd) {
                $rate = $this->num($r['payload']['INTEREST_RATE'] ?? 0);
            }
        }
        if ($rate !== null) {
            $this->rateSource = 'rate set-up (P1_04)';

            return $rate;
        }
        $this->rateSource = 'account master';

        return $this->num($am['INTEREST_RATE'] ?? 0);
    }

    private function statusOn(string $account, string $monthEnd, array $statuses, array $am): ?string
    {
        $status = null;
        foreach ($statuses[$account] ?? [] as $s) {
            if ($s['row_date'] !== null && $s['row_date'] <= $monthEnd) {
                $status = $s['payload']['STATUS_CODE'] ?? $status;
            }
        }

        return $status ?? ($am['STATUS_CODE'] ?? null);
    }

    // ----- shared ---------------------------------------------------------

    /** The arrears buckets of the stored run, under the names the staging classifier reads. */
    private function arrears(array $p): array
    {
        return [
            'arrears_1_to_30' => $this->num($p['DAY_1_30'] ?? 0), 'arrears_30_to_90' => $this->num($p['DAY_31_91'] ?? 0),
            'arrears_91_to_180' => $this->num($p['DAY_91_180'] ?? 0), 'arrears_180_to_270' => $this->num($p['DAY_181_270'] ?? 0),
            'arrears_271_to_360' => $this->num($p['DAY_271_360'] ?? 0),
        ];
    }

    /**
     * Two day counts (Staging Module Specification v1, section 5).
     *
     * The directive's count, into overdue_days, whatever the staging policy:
     * from the oldest overdue instalment (OVERDUE_PRINCI_DATE), read against
     * the instalment chart in force (againstTheChart), and from maturity when
     * that is longer. A loan past its maturity date that still owes a balance
     * has its whole balance overdue from maturity: the last instalment is
     * unpaid, which is what the directive counts (spec 3.6). E-Banker's report
     * leaves the arrears buckets and the overdue date empty on many such loans
     * (the Mega Farm bullet loans, due once after harvest, read 0 days at
     * December 2025 on 47 billion kwacha that matured in 2025). Results review,
     * 13 October 2026. The RBM return and the RBM classification read only
     * this count, so no staging setting can change them.
     *
     * The staging count, into staging_dpd with its basis in words, under the
     * staging policy in force at the month-end (StagingPolicy): the bucket
     * ageing or the oldest overdue instalment (dpd_basis), with or without the
     * schedule correction (dpd_schedule_correction) and the count from
     * maturity (dpd_after_maturity).
     *
     * @return array{0:int,1:?string,2:?string,3:int,4:string,5:array<string,int>}
     */
    private function daysPastDue(array $p, string $monthEnd, array $arrears, ?string $maturity = null, float $balance = 0.0, ?string $account = null): array
    {
        [$reported, $odDate] = $this->daysPastDueReported($p, $monthEnd, $arrears, true);
        [$dpd, $odDate, $flag] = $this->againstTheChart($account, $monthEnd, $reported, $odDate, $balance, array_sum($arrears) > 0);
        $recounted = $dpd;
        $sinceMaturity = $maturity !== null && $balance > 0.005 && $maturity < $monthEnd
            ? (int) CarbonImmutable::parse($maturity)->diffInDays(CarbonImmutable::parse($monthEnd), false) : 0;
        if ($sinceMaturity > $dpd) {
            [$dpd, $odDate] = [$sinceMaturity, $odDate === null || $odDate > $maturity ? $maturity : $odDate];
        }

        // every count the staging settings choose between, kept on the row so that staging, and the
        // staging comparison, can count under any policy exactly (StagingPolicy::stagingDays)
        [$bucket] = $this->daysPastDueReported($p, $monthEnd, $arrears, false);
        $counts = [
            'oldest' => $reported, 'oldest_recounted' => $recounted,
            'bucket' => $bucket, 'bucket_recounted' => $this->againstTheChart($account, $monthEnd, $bucket, null, $balance, array_sum($arrears) > 0)[0],
            'since_maturity' => max(0, $sinceMaturity),
        ];
        $policy = $this->stagingPolicy($monthEnd);

        return [$dpd, $odDate, $flag, $policy->stagingDays($counts), $policy->dpdBasisLabel(), $counts];
    }

    /** @var array<string,StagingPolicy> the staging policy in force, by month-end */
    private array $stagingPolicies = [];

    private function stagingPolicy(string $monthEnd): StagingPolicy
    {
        return $this->stagingPolicies[$monthEnd] ??= StagingPolicy::at($this->governance, CarbonImmutable::parse($monthEnd));
    }

    private ?InstalmentChartHistory $charts = null;

    /** The flag a row carries when its days past due were counted on the instalment chart, not taken from the report (StagingService reads it). */
    public const SCHEDULE_COUNT = 'days past due counted on the instalment chart in force';

    /**
     * The reported ageing read against the instalment chart in force at the
     * month-end (verification of the PD, LGD and staging inputs, 14 October
     * 2026). Two E-Banker defects are corrected, each named on the row; any
     * other reported ageing stands.
     *
     *   Nothing due yet. The report ages arrears from a date on which the
     *   chart in force schedules no instalment: on seven loans with a
     *   moratorium or half-yearly instalments in 2025 (Namalowe Eco Farm,
     *   Mchinji Civil Servants SACCO, Microloan Foundation, Chikomanya Farms,
     *   Mthirakuwiri Farm, Barnet, Chigra) the as-at reports re-run in
     *   September 2026 aged monthly instalments from definitions E-Banker had
     *   deleted, while the chart in force had nothing due until months later. No
     *   contractual payment had fallen due, so none was past due.
     *
     *   A collapsed chart. The chart dates several instalments of one monthly
     *   definition on one day (InstalmentChartHistory), so from that day the
     *   report shows the whole balance overdue from the value date (Udon
     *   Business from December 2025) although the ledger shows the borrower
     *   paid ahead. The days are counted on the chart dated by its monthly
     *   definition against the ledger receipts to the month-end.
     *
     * @return array{0:int,1:?string,2:?string}
     */
    private function againstTheChart(?string $account, string $monthEnd, int $dpd, ?string $odDate, float $balance, bool $bucketsAged = false): array
    {
        if ($account === null || $account === '' || $balance <= 0.005) {
            return [$dpd, $odDate, null];
        }
        $this->charts ??= new InstalmentChartHistory($this->zone);
        $chart = $this->charts->at($account, $monthEnd);
        if ($chart === null) {
            return [$dpd, $odDate, null];
        }
        if ($chart['collapsed']) {
            if ($chart['unreadable']) {
                return [$dpd, $odDate, 'instalment chart dates several instalments on one day and states no monthly definition to date them by: the reported ageing is kept'];
            }
            [$own, $oldest] = InstalmentChartHistory::daysPastDue($chart['instalments'], $this->charts->receiptsTo($account, $monthEnd), $monthEnd);

            return [$own, $oldest, sprintf('%s: it dates several instalments on one day (E-Banker defect) and is dated by its monthly definition, against the ledger receipts (%d; the report says %d)', self::SCHEDULE_COUNT, $own, $dpd)];
        }
        // the report's ageing, by its overdue date or by its buckets (which staging reads when the count is nil)
        if (($dpd > 0 || $bucketsAged) && $chart['first_due'] !== null && $chart['first_due'] > $monthEnd) {
            return [0, null, sprintf('%s: the reported %d days are not accepted, no instalment falls due until %s', self::SCHEDULE_COUNT, $dpd, $chart['first_due'])];
        }

        return [$dpd, $odDate, null];
    }

    /**
     * The reported ageing: from the oldest overdue instalment (OVERDUE_PRINCI_DATE),
     * or, on the bucket basis or with no overdue date, the lower bound of the
     * highest non-empty arrears bucket.
     *
     * @return array{0:int,1:?string}
     */
    private function daysPastDueReported(array $p, string $monthEnd, array $arrears, bool $oldest): array
    {
        $odDate = $this->date($p['OVERDUE_PRINCI_DATE'] ?? '');
        $inArrears = array_sum($arrears) > 0 || $this->num($p['ARREAS_TOTAL'] ?? 0) > 0;
        if ($oldest && $odDate !== null && $inArrears) {
            return [max(0, CarbonImmutable::parse($odDate)->diffInDays(CarbonImmutable::parse($monthEnd), false)), $odDate];
        }
        foreach ([271 => 'arrears_271_to_360', 181 => 'arrears_180_to_270', 91 => 'arrears_91_to_180', 31 => 'arrears_30_to_90', 1 => 'arrears_1_to_30'] as $lower => $col) {
            if ($arrears[$col] > 0) {
                return [$lower, $odDate];
            }
        }

        return [0, $odDate];
    }

    /** @var array<string,string>|null the GL titles of the trial balance, by GL code */
    private ?array $glTitles = null;

    /**
     * The product group and funding source of a loan GL. MAIIC's own GLs are
     * the reader's map; any other loan GL (the Mega Farm programme's 1050301
     * to 1050307) takes the title the trial balance gives it, so the build
     * names the programme as E-Banker does ("Mega Farm Seed Loans") and the
     * staging class (91 days, spec 3.6, D31), the programme portfolio and the
     * Mega Farm module can recognise it. A GL with no title on any trial
     * balance keeps its code as its name, never a guessed one.
     *
     * @return array{0:string,1:string}
     */
    private function groupOf(string $gl): array
    {
        if (isset(LandingZoneReader::LOAN_GLS[$gl])) {
            return LandingZoneReader::LOAN_GLS[$gl];
        }
        if ($this->glTitles === null) {
            $this->glTitles = DB::getSchemaBuilder()->hasTable('gl_trial_balance_lines')
                ? DB::table('gl_trial_balance_lines')->whereNotNull('gl_title')->where('gl_title', '<>', '')->orderBy('period')->pluck('gl_title', 'gl_code')->map(fn ($t) => trim((string) $t))->all()
                : [];
        }
        $megaFarm = in_array($gl, \App\Services\MegaFarm\MegaFarmEclService::GLS, true);

        return [$this->glTitles[$gl] ?? "GL {$gl}", $megaFarm ? 'Mega Farm (Government fund)' : 'MAIIC'];
    }

    private function row(array $v, string $monthEnd): array
    {
        $end = CarbonImmutable::parse($monthEnd);
        $due = $v['due_date'] ? CarbonImmutable::parse($v['due_date']) : null;
        // loan_books declares the numerics NOT NULL with a zero default; a loan with no due date carries 0
        $remaining = $due ? max(0, round($end->diffInMonths($due, false), 2)) : 0;
        $carrying = (float) $v['carrying_amount'];
        // a facility past its maturity can no longer be drawn: its undrawn amount is no exposure
        // (Mphanga Holdings matured May 2025 with 65m undrawn carried into the EAD of December;
        // verification of 14 October 2026). The undrawn amount is kept on the build basis.
        if ($due !== null && $due->lt($end) && (float) $v['commitments'] > 0) {
            $basis = $v['build_basis'] ?? [];
            $basis['undrawn_after_maturity'] = round((float) $v['commitments'], 2);
            $v['build_basis'] = $basis;
            $v['commitments'] = 0.0;
        }
        // a loan written off to a nil (or credit) balance is closed: the write-off ends the facility, so
        // what was left undrawn on it can no longer be drawn and is no exposure (104420000007, written off
        // in June 2026, carried 162.6m undrawn into the EAD and the ECL of every later month; review of
        // 14 October 2026). The ECL engines apply the same rule (EclExposure); the undrawn amount is kept
        // on the build basis.
        if ($carrying <= 0 && (float) ($v['build_basis']['ledger']['written_off'] ?? 0) > 0 && (float) $v['commitments'] > 0) {
            $basis = $v['build_basis'];
            $basis['undrawn_after_write_off'] = round((float) $v['commitments'], 2);
            $v['build_basis'] = $basis;
            $v['commitments'] = 0.0;
        }
        $v += [
            'loan_portfolio_id' => $this->portfolioId(), 'reporting_year' => $end->year, 'reporting_month' => $end->month, 'reporting_period' => $end->format('Y-m'),
            'remaining_tenor' => $remaining, // the ECL engines read this as the credit-conversion fraction on the undrawn commitment (null = 1), so it is a fraction, not a percentage
            // with no approved amount (the Mega Farm loan-book runs carry none) the facility counts as fully drawn:
            // the column is NOT NULL with a default of 1, and the ECL engines read a missing rate as 1
            'facility_utilisation_rate' => $v['approved_amount'] > 0 ? round(min(1.0, max(0.0, $v['disbursed'] / $v['approved_amount'])), 2) : 1.0,
            'ead' => round($carrying + (float) $v['commitments'], 2),
            'overdue_status' => $v['overdue_days'] > 0 ? 'OVERDUE' : 'CURRENT', 'is_month_end' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $v['build_basis'] = json_encode($v['build_basis'], JSON_UNESCAPED_UNICODE);
        $out = [];
        foreach (self::COLUMNS as $c) {
            $out[$c] = $v[$c] ?? null;
        }
        foreach (['overdue_days', 'remaining_tenor', 'tenor', 'interest_rate', 'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount', 'commitments'] as $c) {
            $out[$c] ??= 0;
        }
        $out['customer_id'] = $out['customer_id'] === null || $out['customer_id'] === '' ? '0' : $out['customer_id'];
        $out['external_identity_id'] ??= 'TBA';

        return $out;
    }

    private ?int $portfolio = null;

    private function portfolioId(): int
    {
        return $this->portfolio ??= (int) (DB::table('loan_portfolios')->orderBy('id')->value('id') ?? 1);
    }

    /** Upsert on (contract_id, reporting_period), touching only the build columns. */
    private function write(array $rows): void
    {
        $update = array_values(array_diff(self::COLUMNS, ['contract_id', 'reporting_period', 'created_at']));
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('loan_books')->upsert($chunk, ['contract_id', 'reporting_period'], $update);
        }
    }

    /** @return list<string> YYYY-MM from $from to $to inclusive */
    public function periods(string $from, string $to): array
    {
        foreach ([$from, $to] as $p) {
            if (! preg_match('/^\d{4}-\d{2}$/', $p)) {
                throw new RuntimeException("Period must be YYYY-MM, got '{$p}'.");
            }
        }
        $out = [];
        for ($d = CarbonImmutable::parse($from . '-01'); $d->format('Y-m') <= $to; $d = $d->addMonth()) {
            $out[] = $d->format('Y-m');
        }
        if ($out === []) {
            throw new RuntimeException("'{$from}' is after '{$to}'.");
        }

        return $out;
    }

    public function monthEnd(string $period): string
    {
        return CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString();
    }

    /** @return list<string> */
    public function lockedAmong(array $periods): array
    {
        return DB::table('reporting_period_locks')->whereIn('reporting_period', $periods)->pluck('reporting_period')->all();
    }

    private function num(mixed $v): float
    {
        $s = str_replace([',', ' ', "\xC2\xA0"], '', trim((string) $v));

        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function str(mixed $v, int $max = 199): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /** E-Banker's m/d/Y, padded or not, with or without a time; null when blank or not a date. */
    private function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        foreach (['m/d/Y', 'n/j/Y', 'm/d/Y g:i:s A', 'n/j/Y g:i:s A', 'Y-m-d'] as $f) {
            if (($d = $this->strictDate($v, $f)) !== null) {
                return $d;
            }
        }

        return null;
    }

    private function strictDate(string $v, string $format): ?string
    {
        foreach (array_unique([$format, strtr($format, ['m' => 'n', 'd' => 'j'])]) as $f) {
            try {
                $d = CarbonImmutable::createFromFormat('!' . $f, $v);
            } catch (Throwable) {
                continue;
            }
            if ($d !== false && $d->format($f) === $v) {
                return $d->toDateString();
            }
        }

        return null;
    }
}
