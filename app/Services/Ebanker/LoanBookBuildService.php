<?php

namespace App\Services\Ebanker;

use App\Jobs\ProcessLoanImportJob;
use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
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
 * method, the load it came from and the inputs it was built from.
 */
class LoanBookBuildService
{
    public const METHODS = ['A' => 'bootstrap', 'B' => 'derive', 'C' => 'report'];
    public const BOOTSTRAP_LABEL = 'System Bootstrap (automated data-readiness, not a MAIIC approval)';

    /** Ledger transaction types, from DD_12 and the GL audit (spec v4 sections 3.4 and 7.1). */
    public const TYPE_DISBURSEMENT = ['301', '400'];
    public const TYPE_INTEREST = ['303', '120'];
    public const TYPE_RECEIPT = ['305', '306', '343', '900', '901', '401', '300'];

    /** The loan-book columns a build writes; nothing else on the row is touched. */
    private const COLUMNS = [
        'contract_id', 'customer_id', 'customer_name', 'external_identity_id', 'product_group', 'product_code', 'funding_source',
        'loan_portfolio_id', 'reporting_year', 'reporting_month', 'reporting_period', 'create_date', 'due_date', 'tenor', 'remaining_tenor',
        'interest_rate', 'principal_balance', 'approved_amount', 'disbursed', 'repayments', 'carrying_amount', 'interest_to_date',
        'stored_carrying_amount', 'commitments', 'facility_utilisation_rate', 'ead', 'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180',
        'arrears_180_to_270', 'arrears_271_to_360', 'overdue_days', 'overdue_principal_date', 'overdue_status', 'contract_status',
        'industry_code', 'industry_type', 'is_month_end', 'build_method', 'build_load_id', 'build_source_key', 'build_flag', 'build_basis',
        'created_at', 'updated_at',
    ];

    public function __construct(private LandingZoneReader $zone, private GovernanceService $governance)
    {
    }

    /** The method in force on a month-end: the Governance Centre setting's letter (section 6.2). */
    public function methodInForce(string $monthEnd): string
    {
        try {
            $value = $this->governance->get('loan_book_build_method', CarbonImmutable::parse($monthEnd));
        } catch (Throwable) {
            $value = 'B';
        }
        $letter = strtoupper(substr(trim($value), 0, 1));

        return isset(self::METHODS[$letter]) ? $letter : 'B';
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
        if ($requestedBy !== null && $approvedBy !== null && $requestedBy === $approvedBy) {
            throw new RuntimeException('Maker-checker: the approver must be a different person from the requester.');
        }

        $loads = $this->zone->loadsOf(array_merge(LandingZoneReader::LEDGER, LandingZoneReader::LOAN_BOOK_RUNS, LandingZoneReader::ACCOUNT_MASTER, LandingZoneReader::LOAN_MASTER));
        $result = [];
        $built = [];
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
        if ((int) $b->requested_by === $approvedBy) {
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
        if ($requestedBy !== null && $approvedBy !== null && $requestedBy === $approvedBy) {
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
        $rows = [];
        foreach ($runs as $account => $run) {
            $p = $run['payload'];
            $gl = trim((string) ($p['GLCODE'] ?? ''));
            [$group, $fund] = LandingZoneReader::LOAN_GLS[$gl] ?? ["GL {$gl}", 'MAIIC'];
            $name = $master[$account]['ACCOUNT_NAME'] ?? null;
            $arrears = $this->arrears($p);
            [$dpd, $odDate] = $this->daysPastDue($p, $monthEnd, $arrears);
            $rows[] = $this->row([
                'contract_id' => ltrim($account, '0'), 'customer_id' => trim((string) ($p['CUSTOMER_ID'] ?? '')), 'customer_name' => $name,
                'external_identity_id' => $account, 'product_group' => $group, 'product_code' => $gl, 'funding_source' => $fund,
                'create_date' => $this->date($p['VALUE_DATE'] ?? ''), 'due_date' => $this->date($p['MATURITY_DATE'] ?? ''),
                'tenor' => (int) round($this->num($p['TENOR_YRS'] ?? 0) * 12), 'interest_rate' => $this->num($p['INTEREST_RATE'] ?? 0),
                'principal_balance' => $this->num($p['PRINCIPAL'] ?? 0), 'approved_amount' => $this->num($p['APPROVED'] ?? 0),
                'disbursed' => $this->num($p['DISBURSD'] ?? 0), 'repayments' => $this->num($p['REPAYMENT'] ?? 0),
                'carrying_amount' => $this->num($p['CARRYING_AMOUNT'] ?? 0), 'interest_to_date' => $this->num($p['INTEREST_TO_DATE'] ?? 0),
                'stored_carrying_amount' => $this->num($p['CARRYING_AMOUNT'] ?? 0), 'commitments' => $this->num($p['NOT_YET_DISBURS'] ?? 0),
                'overdue_days' => $dpd, 'overdue_principal_date' => $odDate, 'contract_status' => $master[$account]['STATUS_CODE'] ?? null,
                'industry_code' => $this->str($p['INDUSTRY_CODE'] ?? null), 'industry_type' => $this->str($p['IND_DESCR'] ?? null, 100),
                'build_method' => 'A', 'build_load_id' => $run['load_id'], 'build_source_key' => $run['source_key'], 'build_flag' => null,
                'build_basis' => ['run' => $run['query_id'] . ':' . $run['source_key'], 'run_date' => $this->date($p['TRANSACTION_DATE'] ?? ''), 'segment' => $p['SEG_MENT'] ?? null],
            ] + $arrears, $monthEnd);
        }

        return $rows;
    }

    // ----- method B -------------------------------------------------------

    private function rowsB(string $monthEnd): array
    {
        $ledger = $this->zone->ledgerByAccount($monthEnd);
        $runs = $this->zone->latestRunsOn($monthEnd);
        $master = $this->zone->accountMaster();
        $loans = $this->zone->loanMaster();
        $rates = $this->zone->rateSetupByAccount();
        $statuses = $this->zone->statusHistoryByAccount();
        $accounts = array_unique(array_merge(array_keys($ledger), array_keys($runs)));
        sort($accounts);
        $rows = [];
        foreach ($accounts as $account) {
            $sum = ['all' => 0.0, 'disb' => 0.0, 'int' => 0.0, 'rec' => 0.0, 'other' => 0.0, 'n' => 0, 'first' => null, 'last' => null, 'load' => null];
            foreach ($ledger[$account] ?? [] as $post) {
                $amt = $this->num($post['payload']['TRANSAMT'] ?? 0);
                $type = (string) ($post['payload']['TRANTYPE'] ?? '');
                $sum['all'] += $amt; $sum['n']++;
                $sum['first'] ??= $post['row_date']; $sum['last'] = $post['row_date']; $sum['load'] = $post['load_id'];
                if (in_array($type, self::TYPE_DISBURSEMENT, true)) {
                    $sum['disb'] += $amt;
                } elseif (in_array($type, self::TYPE_INTEREST, true)) {
                    $sum['int'] += $amt;
                } elseif (in_array($type, self::TYPE_RECEIPT, true)) {
                    $sum['rec'] += $amt;
                } else {
                    $sum['other'] += $amt;
                }
            }
            $carrying = round(-$sum['all'], 2);
            $run = $runs[$account] ?? null;
            if (abs($carrying) < 0.005 && $run === null) {
                continue; // closed and not in the report for the month
            }
            $p = $run['payload'] ?? [];
            $am = $master[$account] ?? [];
            $lm = $loans[$account] ?? [];
            $gl = trim((string) ($am['GLCODE'] ?? ($p['GLCODE'] ?? '')));
            [$group, $fund] = LandingZoneReader::LOAN_GLS[$gl] ?? ["GL {$gl}", 'MAIIC'];
            $flags = [];
            $stored = $run !== null ? $this->num($p['CARRYING_AMOUNT'] ?? 0) : null;
            if ($stored !== null && abs($stored - $carrying) > 0.005) {
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
            $rate = $this->rateOn($account, $monthEnd, $p, $rates, $am);
            $arrears = $this->arrears($p);
            [$dpd, $odDate] = $this->daysPastDue($p, $monthEnd, $arrears);
            $tenor = (int) ($this->num($lm['PERIOD_YEARS'] ?? 0) * 12 + $this->num($lm['PERIOD_MONTHS'] ?? 0));
            if ($tenor === 0 && isset($p['TENOR_YRS'])) {
                $tenor = (int) round($this->num($p['TENOR_YRS']) * 12);
            }
            $rows[] = $this->row([
                'contract_id' => ltrim($account, '0'), 'customer_id' => trim((string) ($am['CUSTOMER_ID'] ?? ($p['CUSTOMER_ID'] ?? ''))),
                'customer_name' => $am['ACCOUNT_NAME'] ?? null, 'external_identity_id' => $account, 'product_group' => $group, 'product_code' => $gl,
                'funding_source' => $fund,
                'create_date' => $this->date($p['VALUE_DATE'] ?? ($am['ACCOUNT_OPEN_DATE'] ?? '')) ?? $sum['first'],
                'due_date' => $this->date($p['MATURITY_DATE'] ?? ($lm['EXPIRY_DATE'] ?? '')),
                'tenor' => $tenor, 'interest_rate' => $rate, 'principal_balance' => $principal, 'approved_amount' => $approved,
                'disbursed' => $disbursed, 'repayments' => $receipts, 'carrying_amount' => $carrying, 'interest_to_date' => $interest,
                'stored_carrying_amount' => $stored, 'commitments' => max(0.0, round($approved - $disbursed, 2)),
                'overdue_days' => $dpd, 'overdue_principal_date' => $odDate, 'contract_status' => $this->statusOn($account, $monthEnd, $statuses, $am),
                'industry_code' => $this->str($p['INDUSTRY_CODE'] ?? ($lm['INDUSTRY_CODE'] ?? null)), 'industry_type' => $this->str($p['IND_DESCR'] ?? null, 100),
                'build_method' => 'B', 'build_load_id' => $sum['load'] ?? ($run['load_id'] ?? null), 'build_source_key' => $run['source_key'] ?? null,
                'build_flag' => $flags === [] ? null : implode('; ', $flags),
                'build_basis' => ['postings' => $sum['n'], 'first_posting' => $sum['first'], 'last_posting' => $sum['last'],
                    'ledger' => ['disbursed' => $disbursed, 'interest' => $interest, 'receipts' => $receipts, 'other' => round($sum['other'], 2)],
                    'stored_run' => $run !== null ? $run['query_id'] . ':' . $run['source_key'] : null, 'rate_source' => $this->rateSource],
            ] + $arrears, $monthEnd);
        }

        return $rows;
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
     * Days past due under the governed basis (dpd_basis, decision D31): from
     * the oldest overdue instalment (OVERDUE_PRINCI_DATE) as the directive
     * counts, or the lower bound of the highest non-empty bucket.
     *
     * @return array{0:int,1:?string}
     */
    private function daysPastDue(array $p, string $monthEnd, array $arrears): array
    {
        $odDate = $this->date($p['OVERDUE_PRINCI_DATE'] ?? '');
        $inArrears = array_sum($arrears) > 0 || $this->num($p['ARREAS_TOTAL'] ?? 0) > 0;
        $basis = 'Oldest';
        try {
            $basis = $this->governance->get('dpd_basis', CarbonImmutable::parse($monthEnd));
        } catch (Throwable) {
        }
        if (str_starts_with($basis, 'Oldest') && $odDate !== null && $inArrears) {
            return [max(0, CarbonImmutable::parse($odDate)->diffInDays(CarbonImmutable::parse($monthEnd), false)), $odDate];
        }
        foreach ([271 => 'arrears_271_to_360', 181 => 'arrears_180_to_270', 91 => 'arrears_91_to_180', 31 => 'arrears_30_to_90', 1 => 'arrears_1_to_30'] as $lower => $col) {
            if ($arrears[$col] > 0) {
                return [$lower, $odDate];
            }
        }

        return [0, $odDate];
    }

    private function row(array $v, string $monthEnd): array
    {
        $end = CarbonImmutable::parse($monthEnd);
        $due = $v['due_date'] ? CarbonImmutable::parse($v['due_date']) : null;
        // loan_books declares the numerics NOT NULL with a zero default; a loan with no due date carries 0
        $remaining = $due ? max(0, round($end->diffInMonths($due, false), 2)) : 0;
        $carrying = (float) $v['carrying_amount'];
        $v += [
            'loan_portfolio_id' => $this->portfolioId(), 'reporting_year' => $end->year, 'reporting_month' => $end->month, 'reporting_period' => $end->format('Y-m'),
            'remaining_tenor' => $remaining, // the ECL engines read this as the credit-conversion fraction on the undrawn commitment (null = 1), so it is a fraction, not a percentage
            'facility_utilisation_rate' => $v['approved_amount'] > 0 ? round(min(1.0, max(0.0, $v['disbursed'] / $v['approved_amount'])), 2) : null,
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
