<?php

namespace App\Services\Ebanker;

use App\Exceptions\GovernanceSettingMissingException;
use App\Services\AuditLoggerService;
use App\Services\Eir\CalculateEirService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * The take-on schedules, landed not typed (spec v4 section 6.9).
 *
 * Two files enter by the feed door as a pack of their own kind: Tamanda's
 * workbook of 11 September 2026 exactly as received (Excel-saved, so every
 * formula carries its value), and the mapping workbook returned to her on
 * 7 October with the proposed E-Banker account per facility, her tick, and
 * the fee columns. The landing reads the figures from the original and the
 * mapping, ticks and fees from the mapping copy, recording the sheet and
 * cell every value came from, runs the gates, and the build writes
 * contract_takeon under the governed basis (takeon_history_basis).
 *
 * Under the recompute basis the build does what the specification asks and
 * the system audit of 9 October 2026 (finding M7) found missing: it writes
 * the workbook's lines as the contract's version 1 schedule with
 * schedule_source TAKEON_WORKBOOK, records the fees it finds, solves the EIR
 * from origination on that schedule and the net investment, and rolls the
 * amortised cost forward month by month to 31 July 2024, so that
 * contract_takeon carries the recomputed amortised cost beside E-Banker's
 * take-on balance and the difference between them.
 */
class TakeonLandingService
{
    public const ROUTE = 'TAKEON_WORKBOOK';
    public const TAKEON_DATE = '2024-07-31';

    private CalculateEirService $eir;

    public function __construct(private LandingZoneReader $zone, private GovernanceService $governance, ?CalculateEirService $eir = null)
    {
        $this->eir = $eir ?? new CalculateEirService();
    }

    /** @return array{load_id:int,status:string,blocks:int,lines:int,gates:array} */
    public function land(string $originalPath, string $mappingPath, ?int $userId = null, bool $dryRun = false): array
    {
        foreach ([$originalPath, $mappingPath] as $p) {
            if (! is_file($p)) {
                throw new RuntimeException("Not a file: {$p}");
            }
        }
        $shaOriginal = hash_file('sha256', $originalPath);
        $shaMapping = hash_file('sha256', $mappingPath);
        $packHash = hash('sha256', $shaOriginal . $shaMapping);
        $existing = DB::table('ebanker_loads')->where('pack_hash', $packHash)->first();
        if ($existing && $existing->status === 'LANDED' && DB::table('takeon_blocks')->where('load_id', $existing->id)->exists()) {
            return ['load_id' => (int) $existing->id, 'status' => 'ALREADY_LANDED', 'blocks' => DB::table('takeon_blocks')->where('load_id', $existing->id)->count(), 'lines' => 0, 'gates' => json_decode($existing->gates, true) ?? []];
        }

        $original = $this->open($originalPath);
        $mapping = $this->open($mappingPath);
        $loanBook = $original->getSheetByName('Loan Book') ?? throw new RuntimeException('The original has no Loan Book sheet.');
        $amort = $original->getSheetByName('Amortisation and Repayments') ?? throw new RuntimeException('The original has no Amortisation and Repayments sheet.');
        $mapSheet = $mapping->getSheetByName('Mapping') ?? throw new RuntimeException('The mapping workbook has no Mapping sheet.');
        $summary = $mapping->getSheetByName('Upload summary') ?? throw new RuntimeException('The mapping workbook has no Upload summary sheet.');
        $blocksSheet = $mapping->getSheetByName('Blocks') ?? throw new RuntimeException('The mapping workbook has no Blocks sheet.');

        // the mapping and the fees, by Loan Book row
        $byRow = [];
        for ($r = 2; $r <= $mapSheet->getHighestRow(); $r++) {
            $lbRow = (int) $this->v($mapSheet, "A{$r}");
            if ($lbRow === 0) {
                continue;
            }
            $byRow[$lbRow] = [
                'proposed_account' => $this->s($mapSheet, "J{$r}"), 'confidence' => $this->s($mapSheet, "K{$r}"),
                'confirmed' => strtoupper(substr((string) $this->s($mapSheet, "X{$r}"), 0, 1)) ?: null,
                'corrected_account' => $this->s($mapSheet, "Y{$r}"), 'comment' => $this->s($mapSheet, "Z{$r}"),
                'cells' => ['proposed_account' => "Mapping!J{$r}", 'confidence' => "Mapping!K{$r}", 'confirmed' => "Mapping!X{$r}", 'corrected_account' => "Mapping!Y{$r}"],
            ];
        }
        for ($r = 2; $r <= $summary->getHighestRow(); $r++) {
            $lbRow = (int) $this->v($summary, "C{$r}");
            if ($lbRow === 0 || ! isset($byRow[$lbRow])) {
                continue;
            }
            $fees = ['arrangement_fee' => $this->n($summary, "AO{$r}"), 'legal_fees' => $this->n($summary, "AP{$r}"), 'other_fees' => $this->n($summary, "AQ{$r}"),
                'fee_date' => $this->d($summary, "AR{$r}"), 'fee_deducted' => strtoupper(substr((string) $this->s($summary, "AS{$r}"), 0, 1)) ?: null, 'fee_source' => $this->s($summary, "AT{$r}")];
            $given = array_filter([$fees['arrangement_fee'], $fees['legal_fees'], $fees['other_fees']], fn ($x) => $x !== null);
            $fees['total_fees'] = $given === [] ? null : round(array_sum($given), 4);
            $byRow[$lbRow] += $fees;
            $byRow[$lbRow]['cells'] += ['arrangement_fee' => "Upload summary!AO{$r}", 'legal_fees' => "Upload summary!AP{$r}", 'other_fees' => "Upload summary!AQ{$r}", 'fee_date' => "Upload summary!AR{$r}", 'fee_deducted' => "Upload summary!AS{$r}", 'fee_source' => "Upload summary!AT{$r}"];
        }

        // the blocks
        $blocks = [];
        for ($r = 2; $r <= $blocksSheet->getHighestRow(); $r++) {
            $no = (int) $this->v($blocksSheet, "A{$r}");
            $sheetRow = (int) $this->v($blocksSheet, "E{$r}");
            if ($no === 0 || $sheetRow === 0) {
                continue;
            }
            $lbRow = (int) $this->v($blocksSheet, "G{$r}") ?: null;
            $b = $this->readBlock($amort, $no, $sheetRow);
            $b['restructured'] = strtolower((string) $this->s($blocksSheet, "J{$r}")) === 'true';
            $b['loan_book_row'] = $lbRow;
            if ($lbRow !== null) {
                $b += $this->readFacility($loanBook, $lbRow);
                $m = $byRow[$lbRow] ?? [];
                $b['proposed_account'] = $m['proposed_account'] ?? null;
                $b['confidence'] = $m['confidence'] ?? null;
                $b['confirmed'] = $m['confirmed'] ?? null;
                $b['corrected_account'] = $m['corrected_account'] ?? null;
                $b['comment'] = $m['comment'] ?? null;
                $b['account'] = $b['confirmed'] === 'N' && $b['corrected_account'] ? $b['corrected_account'] : $b['proposed_account'];
                foreach (['arrangement_fee', 'legal_fees', 'other_fees', 'fee_date', 'fee_deducted', 'fee_source', 'total_fees'] as $k) {
                    $b[$k] = $m[$k] ?? null;
                }
                $b['cells'] += $m['cells'] ?? [];
            }
            $blocks[$no] = $b;
        }
        if ($blocks === []) {
            throw new RuntimeException('No blocks found: the Blocks sheet names none.');
        }

        // a refusal is of the block, not the workbook: the block lands as REFUSED with
        // its reason and cell, and the build leaves it out; the workbook itself is
        // quarantined only when nothing in it can be read
        $gates = $this->gates($blocks);
        $landable = array_filter($gates['blocks'], fn ($g) => $g['refusals'] === []);
        $status = $landable === [] ? 'QUARANTINED' : 'LANDED';
        if ($dryRun) {
            return ['load_id' => 0, 'status' => 'DRY_RUN_' . $status, 'blocks' => count($blocks), 'lines' => array_sum(array_map(fn ($b) => count($b['lines']), $blocks)), 'gates' => $gates];
        }

        return DB::transaction(function () use ($existing, $packHash, $originalPath, $mappingPath, $shaOriginal, $shaMapping, $blocks, $gates, $status, $userId) {
            $manifest = ['pack' => 'take-on workbook', 'files' => [
                ['file' => basename($originalPath), 'role' => 'original', 'sha256' => $shaOriginal],
                ['file' => basename($mappingPath), 'role' => 'mapping and fees', 'sha256' => $shaMapping],
            ]];
            $loadId = $existing ? (int) $existing->id : (int) DB::table('ebanker_loads')->insertGetId([
                'pack_hash' => $packHash, 'pack_name' => 'Take-on workbook ' . basename($originalPath), 'route' => self::ROUTE, 'period' => '2024-07',
                'date_format' => 'excel', 'manifest' => json_encode($manifest), 'status' => 'PENDING', 'loaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('takeon_blocks')->where('load_id', $loadId)->delete();
            $lines = 0;
            if ($status === 'LANDED') {
                foreach ($blocks as $no => $b) {
                    $g = $gates['blocks'][$no];
                    $blockId = (int) DB::table('takeon_blocks')->insertGetId([
                        'load_id' => $loadId, 'block_no' => $no, 'sheet_row' => $b['sheet_row'], 'title' => $b['title'], 'principal' => $b['principal'], 'rate' => $b['rate'],
                        'periods_per_year' => $b['periods_per_year'], 'total_periods' => $b['total_periods'], 'pmt' => $b['pmt'], 'restructured' => $b['restructured'],
                        'loan_book_row' => $b['loan_book_row'], 'facility_name' => $b['facility_name'] ?? null, 'facility_type' => $b['facility_type'] ?? null,
                        'value_date' => $b['value_date'] ?? null, 'maturity_date' => $b['maturity_date'] ?? null, 'tenor_years' => $b['tenor_years'] ?? null, 'moratorium' => $b['moratorium'] ?? null,
                        'loan_book_rate' => $b['loan_book_rate'] ?? null, 'approved' => $b['approved'] ?? null, 'disbursed' => $b['disbursed'] ?? null,
                        'principal_31_oct_2024' => $b['principal_31_oct_2024'] ?? null, 'interest_to_date' => $b['interest_to_date'] ?? null, 'repayments' => $b['repayments'] ?? null,
                        'carrying_31_oct_2024' => $b['carrying_31_oct_2024'] ?? null, 'account' => $b['account'] ?? null, 'proposed_account' => $b['proposed_account'] ?? null,
                        'confidence' => $b['confidence'] ?? null, 'confirmed' => $b['confirmed'] ?? null, 'corrected_account' => $b['corrected_account'] ?? null, 'comment' => $b['comment'] ?? null,
                        'arrangement_fee' => $b['arrangement_fee'] ?? null, 'legal_fees' => $b['legal_fees'] ?? null, 'other_fees' => $b['other_fees'] ?? null, 'fee_date' => $b['fee_date'] ?? null,
                        'fee_deducted' => $b['fee_deducted'] ?? null, 'fee_source' => $b['fee_source'] ?? null, 'total_fees' => $b['total_fees'] ?? null,
                        'cells' => json_encode($b['cells']), 'gates' => json_encode($g), 'status' => $g['status'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $batch = [];
                    foreach ($b['lines'] as $l) {
                        $batch[] = $l + ['block_id' => $blockId, 'created_at' => now(), 'updated_at' => now()];
                    }
                    if ($batch !== []) {
                        DB::table('takeon_schedule_lines')->insert($batch);
                        $lines += count($batch);
                    }
                }
            }
            DB::table('ebanker_loads')->where('id', $loadId)->update(['status' => $status, 'gates' => json_encode($gates), 'loaded_at' => $status === 'LANDED' ? now() : null, 'updated_at' => now()]);
            AuditLoggerService::log($status === 'LANDED' ? 'Take-on Workbook Landed' : 'Take-on Workbook Quarantined', 'ebanker_loads', $loadId,
                ['new_values' => ['blocks' => count($blocks), 'lines' => $lines, 'summary' => $gates['summary']], 'meta' => ['loaded_by' => $userId]]);

            return ['load_id' => $loadId, 'status' => $status, 'blocks' => count($blocks), 'lines' => $lines, 'gates' => $gates];
        });
    }

    /**
     * The build: contract_takeon per take-on account under takeon_history_basis.
     *
     * @return array{accounts:int,recomputed:int,takeon_balance:int,refused:int,setting:string}
     */
    public function build(?int $userId = null): array
    {
        $loadId = DB::table('ebanker_loads')->where('route', self::ROUTE)->where('status', 'LANDED')->orderByDesc('id')->value('id');
        if ($loadId === null) {
            throw new RuntimeException('No take-on workbook has been landed.');
        }
        // governed, never defaulted in code: a missing setting stops the build (fail closed)
        $setting = $this->governance->get('takeon_history_basis', CarbonImmutable::parse(self::TAKEON_DATE));
        $mode = str_starts_with($setting, 'Start every') ? 'BALANCE' : (str_starts_with($setting, 'Recompute from origination for every') ? 'STRICT' : 'WHERE_EVIDENCED');
        $blocks = DB::table('takeon_blocks')->where('load_id', $loadId)->whereNotNull('account')->where('status', '!=', 'REFUSED')->get()->keyBy('account');
        $postings = $this->takeonPostings();
        $counts = ['accounts' => 0, 'recomputed' => 0, 'takeon_balance' => 0, 'refused' => 0, 'schedule_lines' => 0, 'eir_check_pass' => 0, 'eir_check_refused' => 0, 'setting' => $setting];
        DB::transaction(function () use ($blocks, $postings, $mode, $setting, &$counts, $userId) {
            DB::table('contract_takeon')->delete();
            foreach ($postings as $account => $p) {
                $b = $blocks->get($account);
                $flags = [];
                $evidenced = $b !== null && $b->total_fees !== null && in_array($b->status, ['MAPPED', 'FLAGGED'], true);
                if ($b === null) {
                    $flags[] = 'no block mapped to this account';
                } elseif ($b->total_fees === null) {
                    $flags[] = 'fees not supplied (blank fee row): O14';
                }
                if ($b !== null && $b->confirmed !== 'Y') {
                    $flags[] = 'mapping not yet confirmed by Finance';
                }
                $basis = match ($mode) {
                    'BALANCE' => 'TAKEON_BALANCE',
                    'STRICT' => $evidenced ? 'RECOMPUTED' : 'REFUSED',
                    default => $evidenced ? 'RECOMPUTED' : 'TAKEON_BALANCE',
                };
                $scheduleBalance = $b !== null ? $this->scheduleBalanceAt((int) $b->id, self::TAKEON_DATE) : null;
                $takeonBalance = round($p['principal'] + $p['interest'] - $p['recovery'], 4);

                // the recompute of 6.9, only where the basis says so; a block the solver
                // cannot use falls back to the take-on balance (or a refusal under the
                // strict option) with the reason on the row, never a silent label
                $recompute = ['columns' => [], 'lines' => 0];
                if ($basis === 'RECOMPUTED') {
                    $recompute = $this->recompute($account, $b, $takeonBalance);
                    $flags = array_merge($flags, $recompute['flags']);
                    if (! $recompute['solved']) {
                        $basis = $mode === 'STRICT' ? 'REFUSED' : 'TAKEON_BALANCE';
                    }
                }
                DB::table('contract_takeon')->insert($recompute['columns'] + [
                    'account' => $account, 'contract_id' => ltrim($account, '0'), 'block_id' => $b?->id, 'basis' => $basis,
                    'origination_date' => $b?->value_date, 'original_principal' => $b?->principal, 'contractual_rate' => $b?->rate,
                    'fees_total' => $b?->total_fees, 'fee_date' => $b?->fee_date, 'fees_deducted' => $b?->fee_deducted === null ? null : $b->fee_deducted === 'Y',
                    'takeon_posting' => $p['principal'], 'takeon_opening_interest' => $p['interest'], 'takeon_opening_recovery' => $p['recovery'], 'schedule_balance_at_takeon' => $scheduleBalance,
                    'difference_at_takeon' => $scheduleBalance !== null ? round($p['principal'] - $p['recovery'] - $scheduleBalance, 4) : null,
                    'takeon_balance' => $takeonBalance, 'schedule_lines_written' => $recompute['lines'],
                    'fees_detail' => $b !== null ? json_encode($this->feesFound($b)) : null,
                    'flags' => json_encode($flags), 'setting_value' => $setting, 'built_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $counts['accounts']++;
                $counts['schedule_lines'] += $recompute['lines'];
                if (isset($recompute['columns']['eir_check'])) {
                    $counts[$recompute['columns']['eir_check'] === 'PASS' ? 'eir_check_pass' : 'eir_check_refused']++;
                }
                $counts[match ($basis) { 'RECOMPUTED' => 'recomputed', 'TAKEON_BALANCE' => 'takeon_balance', default => 'refused' }]++;
            }
            AuditLoggerService::log('Take-on Population Built', 'contract_takeon', null, ['new_values' => $counts, 'meta' => ['user' => $userId]]);
        });
        $counts['fees'] = $this->landFees($blocks, array_keys($postings));

        return $counts;
    }

    /**
     * The take-on workbook's fees as fee lines (source TAKEON_WORKBOOK), one
     * per fee column filled, with the cell each came from. They are the only
     * record of these loans' fees and never compete with another source
     * (spec 7.8.2), but like every fee they land PENDING: no fee affects an
     * EIR until a person has classified and reviewed it (results review of
     * 13 October 2026, E2 and E3). A figure changed in a later workbook
     * replaces the line's amount and sends it back for review.
     *
     * @param  \Illuminate\Support\Collection  $blocks  keyed by account
     * @param  list<string>  $accounts  the take-on population
     */
    private function landFees($blocks, array $accounts): array
    {
        if (! DB::getSchemaBuilder()->hasTable('contract_fees')) {
            return ['loaded_rows' => 0, 'note' => 'no contract_fees table'];
        }
        $rows = []; $changed = 0;
        foreach ($accounts as $account) {
            $b = $blocks->get($account);
            if ($b === null) {
                continue;
            }
            $cells = json_decode((string) $b->cells, true) ?? [];
            foreach (['arrangement_fee' => 'arrangement', 'legal_fees' => 'legal', 'other_fees' => 'other'] as $column => $type) {
                if ($b->$column === null || abs((float) $b->$column) < 0.005) {
                    continue;
                }
                $external = 'TAKEON:' . ltrim($account, '0') . ':' . $type;
                $existing = DB::table('contract_fees')->where('source_system', FeeSourcePrecedence::SOURCE_TAKEON)->where('external_transaction_id', $external)->first();
                if ($existing !== null) {
                    if (abs((float) $existing->amount - (float) $b->$column) >= 0.005) {
                        DB::table('contract_fees')->where('id', $existing->id)->update(['amount' => round((float) $b->$column, 2), 'classification_status' => 'PENDING',
                            'integral' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'classification_reason' => 'amount changed in the take-on workbook', 'updated_at' => now()]);
                        $changed++;
                    }
                    continue;
                }
                $rows[] = [
                    'contract_id' => ltrim($account, '0'), 'fee_type' => $type, 'amount' => round((float) $b->$column, 2),
                    'transaction_date' => $b->fee_date ?? $b->value_date, 'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK',
                    'description' => mb_substr(ucfirst($type) . ' fee from the take-on workbook (' . ($cells[$column] ?? 'Upload summary') . ($b->fee_source ? '; ' . $b->fee_source : '') . ')', 0, 199),
                    'source_system' => FeeSourcePrecedence::SOURCE_TAKEON, 'source_reference' => 'block ' . $b->block_no . ' ' . ($cells[$column] ?? ''),
                    'external_transaction_id' => $external,
                ];
            }
        }
        $result = $rows === [] ? ['loaded_rows' => 0] : app(FeeImportService::class)->import($rows);

        return $result + ['amounts_changed' => $changed];
    }

    // ----- the recompute from origination (6.9; audit finding M7) -----------

    /**
     * For one evidenced block: the version 1 schedule from the workbook, the
     * EIR solved from the date the workbook starts, the check that the solve
     * is a true reading of the schedule, and the amortised cost rolled forward
     * to the take-on date. Returns the contract_takeon columns to write, the
     * flags raised and whether the solve may be used.
     *
     * The cash-flow vector is read from the workbook's balance chain, in the
     * order the lines stand in the sheet (results review of 13 October 2026,
     * E2). Each line's principal and interest is what falls due on its end
     * date. A balance that rises between two lines, or within a line by more
     * than its capitalised interest, is money advanced on that date (a later
     * tranche, or arrears added to the balance when the loan was rescheduled);
     * a balance that falls by more than the line's principal is money received
     * then (the repayments Tamanda recorded in the schedule). Read this way the
     * vector holds exactly the money the workbook says moved, so with no fee it
     * solves to the rates the lines charge. The solve until 14 October 2026
     * took the header principal at the value date and the instalment column
     * only, so 21 of the 73 solves were implausible: Infracon's second tranche
     * of 265.7 million was never paid out (63.8 percent), Ruo Textiles opened
     * at the approved 70 million instead of the 9.7 million first drawn
     * (-46.5 percent), Steel Base's schedule starts a year after the Loan Book
     * value date (7.2 percent), Nsanama's interest-only months fell out of the
     * vector because their instalment column is nil (14.2 percent).
     *
     * @return array{solved:bool,columns:array,lines:int,flags:list<string>}
     */
    private function recompute(string $account, object $b, float $takeonBalance): array
    {
        $flags = [];
        $contractId = ltrim($account, '0');
        $valueDate = $b->value_date !== null ? CarbonImmutable::parse($b->value_date) : null;
        // the fees are a condition of the loan; the workbook's fee columns are the
        // only record of them (spec 6.9), and a known nil works as well as a figure
        $fees = (float) ($b->total_fees ?? 0);

        $all = DB::table('takeon_schedule_lines')->where('block_id', $b->id)->orderBy('sheet_row')->orderBy('serial')->get();
        $lines = $all->filter(fn ($l) => $l->period_end !== null)->values();
        if ($all->count() > $lines->count()) {
            $flags[] = ($all->count() - $lines->count()) . ' schedule line(s) without a due date left out of the recompute';
        }
        if ($lines->isEmpty()) {
            $flags[] = 'recompute not possible: the block has no dated schedule lines';

            return ['solved' => false, 'columns' => ['eir_check' => 'REFUSED', 'eir_check_reason' => 'the block has no dated schedule lines'], 'lines' => 0, 'flags' => $flags];
        }

        // the version 1 schedule as the workbook states it, one row per due date
        $byDate = [];
        foreach ($lines as $l) {
            $principalDue = (float) ($l->principal ?? 0);
            $interest = (float) ($l->interest ?? 0);
            $byDate[$l->period_end] ??= ['principal' => 0.0, 'interest' => 0.0, 'amount' => 0.0, 'rows' => []];
            $byDate[$l->period_end]['principal'] += $principalDue;
            $byDate[$l->period_end]['interest'] += $interest;
            $byDate[$l->period_end]['amount'] += $principalDue + $interest;
            $byDate[$l->period_end]['rows'][] = (int) $l->sheet_row;
        }
        ksort($byDate);
        $start = CarbonImmutable::parse($lines[0]->period_start ?? $lines[0]->period_end);
        $origination = $start;
        if ($valueDate !== null && ! $valueDate->isSameDay($start)) {
            $flags[] = sprintf('the schedule starts on %s and the Loan Book value date is %s: the solve runs from the schedule\'s start, the date it first charges interest', $start->toDateString(), $valueDate->toDateString());
        }
        $lines_ = $this->writeWorkbookSchedule($contractId, $b, $origination, $byDate, $flags);

        // the balance chain
        $opening = $lines[0]->opening_balance !== null ? (float) $lines[0]->opening_balance : (float) ($b->principal ?? 0);
        if ($opening <= 0) {
            $flags[] = 'recompute not possible: the schedule opens with no balance';

            return ['solved' => false, 'columns' => ['schedule_lines_written' => $lines_, 'eir_check' => 'REFUSED', 'eir_check_reason' => 'the schedule opens with no balance'], 'lines' => $lines_, 'flags' => $flags];
        }
        if ($b->principal !== null && abs((float) $b->principal - $opening) >= 1) {
            $flags[] = sprintf('the block\'s principal is %s and its schedule opens at %s: the schedule\'s balance is taken', number_format((float) $b->principal, 2), number_format($opening, 2));
        }
        $chain = $this->chain($lines, $start);
        $flags = array_merge($flags, $chain['notes']);
        $opening = round($opening + $chain['opening_adjustment'], 4);

        $check = $this->check($chain, $opening, $start, $lines);
        $detail = ['chain' => ['start' => $start->toDateString(), 'opening' => round($opening, 4), 'flows' => $chain['flows'], 'advances' => $chain['advances'],
            'reductions' => $chain['reductions'], 'shared_dates' => $chain['shared_dates'], 'closing' => $chain['closing'], 'line_rates' => $check['rates']]];
        $base = ['schedule_lines_written' => $lines_, 'solve_start_date' => $start->toDateString(), 'eir_check' => $check['status'], 'eir_check_reason' => $check['reason'],
            'nofee_eir' => $check['nofee'] === null ? null : round($check['nofee'], 8), 'net_investment' => round($opening - $fees, 4)];
        if ($check['status'] !== 'PASS') {
            $flags[] = 'take-on EIR refused: ' . $check['reason'];

            return ['solved' => false, 'columns' => $base + ['recompute_detail' => json_encode($detail)], 'lines' => $lines_, 'flags' => $flags];
        }

        // the solve with the fees: the net investment is the opening balance less them
        $net = round($opening - $fees, 4);
        try {
            $dayCount = $this->governance->get('day_count', $origination);
            $ppy = in_array((int) $b->periods_per_year, [1, 2, 4, 6, 12], true) ? (int) $b->periods_per_year : 12;
            $solve = $this->eir->calculateDated($net, $chain['flows'], $ppy, $origination->toDateString(), $dayCount);
        } catch (GovernanceSettingMissingException $e) {
            $flags[] = 'recompute not possible: ' . $e->getMessage();

            return ['solved' => false, 'columns' => $base + ['eir_check' => 'REFUSED', 'eir_check_reason' => $e->getMessage()], 'lines' => $lines_, 'flags' => $flags];
        } catch (Throwable $e) {
            $flags[] = 'EIR not solved from origination: ' . $e->getMessage();

            return ['solved' => false, 'columns' => $base + ['eir_check' => 'REFUSED', 'eir_check_reason' => 'the EIR with the fees did not solve: ' . $e->getMessage()], 'lines' => $lines_, 'flags' => $flags];
        }

        $roll = $this->rollForward($net, (float) $solve['eir_effective_annual'], $origination, $chain['flows'], CarbonImmutable::parse(self::TAKEON_DATE));
        if ($roll === []) {
            $flags[] = 'origination is after the take-on date: nothing to roll forward';

            return ['solved' => false, 'columns' => $base + ['recomputed_eir' => $solve['eir_effective_annual'], 'eir_check' => 'REFUSED', 'eir_check_reason' => 'the schedule starts after the take-on date'], 'lines' => $lines_, 'flags' => $flags];
        }
        $closing = round(end($roll)['closing'], 4);
        $detail['solve'] = ['method' => $solve['method'], 'iterations' => $solve['solver_iterations'], 'residual' => $solve['solver_residual'], 'day_count' => $dayCount,
            'payments_per_year' => $ppy, 'net_investment' => $net, 'fees' => $fees, 'flows' => count($chain['flows']),
            'receipts' => round(array_sum(array_filter(array_column($chain['flows'], 'amount'), fn ($a) => $a > 0)), 4)];
        $detail['roll_forward'] = $roll;

        return ['solved' => true, 'lines' => $lines_, 'flags' => $flags, 'columns' => $base + [
            'recomputed_eir' => round((float) $solve['eir_effective_annual'], 8),
            'recomputed_eir_monthly' => round(pow(1 + (float) $solve['eir_effective_annual'], 1 / 12) - 1, 8),
            'recomputed_amortised_cost' => $closing,
            'recomputed_difference' => round($takeonBalance - $closing, 4),
            'recompute_detail' => json_encode($detail),
        ]];
    }

    /**
     * The workbook's balance chain as money in and out: each line's principal
     * and interest on its end date; a rise in the balance the line does not
     * explain, money advanced (between two lines on the later line's start,
     * within a line on its end); a fall it does not explain, money received.
     *
     * @return array{flows:list<array>, advances:list<array>, reductions:list<array>, notes:list<string>, shared_dates:int, closing:float}
     */
    private function chain($lines, CarbonImmutable $start): array
    {
        $flows = []; $advances = []; $reductions = []; $notes = []; $seen = []; $shared = 0; $before = 0; $atStart = 0.0;
        $move = function (string $date, float $amount, string $where, $line) use (&$flows, &$advances, &$reductions, &$atStart, $start) {
            $amount = round($amount, 4);
            if ($date <= $start->toDateString()) {
                // a movement on the start date belongs to the opening balance
                $atStart += $amount;

                return;
            }
            if ($amount > 0) {
                $advances[] = ['date' => $date, 'amount' => $amount, 'where' => $where, 'sheet_row' => (int) $line->sheet_row];
                $flows[] = ['due_date' => $date, 'amount' => -$amount, 'flow_type' => CalculateEirService::FLOW_DISBURSEMENT, 'note' => 'advanced ' . $where . ' (row ' . $line->sheet_row . ')'];
            } else {
                $reductions[] = ['date' => $date, 'amount' => -$amount, 'where' => $where, 'sheet_row' => (int) $line->sheet_row, 'repaid_column_k' => $line->amount_repaid];
                $flows[] = ['due_date' => $date, 'amount' => -$amount, 'note' => 'received ' . $where . ' (row ' . $line->sheet_row . ')'];
            }
        };
        $previous = null;
        foreach ($lines as $l) {
            $opening = $l->opening_balance !== null ? (float) $l->opening_balance : null;
            $closing = $l->closing_balance !== null ? (float) $l->closing_balance : null;
            $principal = (float) ($l->principal ?? 0);
            $interest = (float) ($l->interest ?? 0);
            if (isset($seen[$l->period_end])) {
                $shared++;
            }
            $seen[$l->period_end] = true;
            if ($previous !== null && $opening !== null && $previous->closing_balance !== null) {
                $jump = $opening - (float) $previous->closing_balance;
                if (abs($jump) >= 1) {
                    $move((string) ($l->period_start ?? $l->period_end), $jump, 'between two lines', $l);
                }
            }
            if ($opening !== null && $closing !== null) {
                $within = $closing - ($opening - $principal);
                if (abs($within) >= 1) {
                    $move((string) $l->period_end, $within, 'within a line', $l);
                }
            }
            $due = $principal + $interest;
            if (abs($due) >= 0.005) {
                if ($l->period_end <= $start->toDateString()) {
                    $before++;
                } else {
                    $flows[] = ['due_date' => (string) $l->period_end, 'amount' => round($due, 4)];
                }
            }
            $previous = $l;
        }
        if ($before > 0) {
            $notes[] = "{$before} schedule line(s) due on or before the start left out of the solve";
        }
        if ($advances !== []) {
            $notes[] = sprintf('%d rise(s) in the balance read as money advanced, %s in all', count($advances), number_format(array_sum(array_column($advances, 'amount')), 2));
        }
        if ($reductions !== []) {
            $notes[] = sprintf('%d fall(s) in the balance not in the instalments read as money received, %s in all', count($reductions), number_format(array_sum(array_column($reductions, 'amount')), 2));
        }
        if ($shared > 0) {
            $notes[] = "{$shared} schedule line(s) share a due date with another line (F16)";
        }
        usort($flows, fn ($a, $b) => [$a['due_date'], $a['amount'] < 0 ? 0 : 1] <=> [$b['due_date'], $b['amount'] < 0 ? 0 : 1]);

        if (abs($atStart) >= 1) {
            $notes[] = sprintf('a movement of %s in the balance on the start date is taken into the opening balance', number_format($atStart, 2));
        }

        return ['flows' => $flows, 'advances' => $advances, 'reductions' => $reductions, 'notes' => $notes, 'shared_dates' => $shared,
            'opening_adjustment' => round($atStart, 4), 'closing' => round((float) ($previous?->closing_balance ?? 0), 4)];
    }

    /**
     * The governed check of a take-on solve (takeon_eir_plausibility): the
     * schedule must retire the loan, and with no fee its own cash flows must
     * solve to a rate inside the lowest and highest rate its lines charge,
     * widened by the governed margin. A schedule that fails is not a reading
     * of the loan the EIR can rest on, and the reason says why.
     *
     * @return array{status:string, reason:?string, nofee:?float, rates:array}
     */
    private function check(array $chain, float $opening, CarbonImmutable $start, $lines): array
    {
        $rates = [];
        foreach ($lines as $l) {
            if ((float) ($l->opening_balance ?? 0) > 0 && (float) ($l->interest ?? 0) > 0 && (int) ($l->days ?? 0) > 0) {
                $rates[] = (float) $l->interest / (float) $l->opening_balance * 365 / (int) $l->days;
            }
        }
        $range = $rates === [] ? null : ['low' => round(min($rates), 6), 'high' => round(max($rates), 6)];
        $setting = $this->governance->get('takeon_eir_plausibility', $start);
        if (preg_match('/(\d+(?:\.\d+)?) point/', $setting, $m) !== 1) {
            throw new RuntimeException("takeon_eir_plausibility holds \"{$setting}\", which names no margin.");
        }
        $margin = (float) $m[1] / 100;
        if (abs($chain['closing']) >= 1) {
            return ['status' => 'REFUSED', 'reason' => sprintf('the schedule does not retire the loan: %s is still owed at its last line; it is an account history, not a contractual schedule', number_format($chain['closing'], 2)), 'nofee' => null, 'rates' => $range];
        }
        if ($range === null) {
            return ['status' => 'REFUSED', 'reason' => 'no line of the schedule charges interest, so there is no rate to hold the solve to', 'nofee' => null, 'rates' => null];
        }
        try {
            $dayCount = $this->governance->get('day_count', $start);
            $nofee = (float) $this->eir->calculateDated($opening, $chain['flows'], 12, $start->toDateString(), $dayCount)['eir_effective_annual'];
        } catch (Throwable $e) {
            return ['status' => 'REFUSED', 'reason' => 'the schedule with no fee does not solve: ' . $e->getMessage(), 'nofee' => null, 'rates' => $range];
        }
        $nominal = 12 * (pow(1 + $nofee, 1 / 12) - 1);
        if ($nominal < $range['low'] - $margin || $nominal > $range['high'] + $margin) {
            $why = [];
            if ($chain['shared_dates'] > 0) {
                $why[] = $chain['shared_dates'] . ' line(s) share a due date with another, so instalments are dated earlier than they fall';
            }
            if ($chain['reductions'] !== []) {
                $why[] = 'the balance falls by ' . number_format(array_sum(array_column($chain['reductions'], 'amount')), 2) . ' outside the instalments';
            }
            if ($chain['advances'] !== []) {
                $why[] = 'the balance rises by ' . number_format(array_sum(array_column($chain['advances'], 'amount')), 2) . ' outside the instalments';
            }

            return ['status' => 'REFUSED', 'nofee' => $nofee, 'rates' => $range, 'reason' => sprintf(
                'with no fee the schedule solves to %.2f percent while its lines charge %.2f to %.2f percent (%s): the dates or amounts do not describe the loan%s',
                $nominal * 100, $range['low'] * 100, $range['high'] * 100, $setting, $why === [] ? '' : '; ' . implode('; ', $why)
            )];
        }

        return ['status' => 'PASS', 'reason' => null, 'nofee' => $nofee, 'rates' => $range];
    }

    /**
     * The workbook schedule as the contract's version 1 (schedule_source
     * TAKEON_WORKBOOK). A generated version 1 or an earlier workbook version
     * is replaced; an imported version 1 is left as it is and the row says so,
     * since version 1 schedules from a delivered file are never overwritten.
     */
    private function writeWorkbookSchedule(string $contractId, object $b, CarbonImmutable $origination, array $byDate, array &$flags): int
    {
        if ($byDate === []) {
            $flags[] = 'no dated schedule lines: no version 1 schedule written';

            return 0;
        }
        $imported = DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->where('schedule_version', 1)->where('schedule_source', 'IMPORTED')->count();
        if ($imported > 0) {
            $flags[] = "version 1 schedule is IMPORTED ({$imported} lines): the workbook schedule was not written over it";

            return 0;
        }
        DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->where('schedule_version', 1)->whereIn('schedule_source', ['GENERATED', self::ROUTE])->delete();
        $rows = [];
        foreach ($byDate as $date => $d) {
            $rows[] = [
                'contract_id' => $contractId, 'schedule_version' => 1, 'effective_from' => $origination->toDateString(), 'due_date' => $date,
                'principal_due' => round($d['principal'], 2), 'interest_due' => round($d['interest'], 2), 'fee_due' => 0,
                'schedule_source' => self::ROUTE, 'source_system' => self::ROUTE,
                'source_reference' => 'block ' . $b->block_no . ' row ' . implode(',', $d['rows']), 'external_transaction_id' => self::ROUTE . ':' . $contractId . ':' . $date,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('contract_cashflow_schedule')->insert($rows);
        DB::table('contract_eir')->where('contract_id', $contractId)->update(['schedule_source' => self::ROUTE, 'updated_at' => now()]);

        return count($rows);
    }

    /**
     * The amortised cost from origination to the take-on date: each whole
     * calendar month earns the monthly EIR on the opening balance and gives
     * up the cash the schedule says was due in the month; money advanced in
     * the month (a later tranche) is added and earns the EIR from its date;
     * the first, part, month from origination to its month-end earns the EIR
     * for its actual days over 365, the same daily basis the dated solve
     * discounts on.
     *
     * @param  list<array{due_date:string, amount:float}>  $flows  the solved vector: receipts positive, advances negative
     * @return list<array{period:string,opening:float,interest:float,cash:float,advanced:float,closing:float,days:?int}>
     */
    private function rollForward(float $net, float $eir, CarbonImmutable $origination, array $flows, CarbonImmutable $to): array
    {
        $to = $to->endOfDay();
        if ($origination->gt($to)) {
            return [];
        }
        $monthly = pow(1 + $eir, 1 / 12) - 1;
        $rows = [];
        $balance = $net;
        $prevEnd = $origination;
        $monthEnd = $origination->endOfMonth();
        $stub = ! $origination->isSameDay($monthEnd);   // originated on a month-end: the first full month starts tomorrow
        if (! $stub) {
            $monthEnd = $monthEnd->addDay()->endOfMonth();
        }
        while ($monthEnd->lte($to)) {
            $days = $prevEnd->diffInDays($monthEnd);
            $interest = $stub ? $balance * (pow(1 + $eir, $days / 365) - 1) : $balance * $monthly;
            $cash = 0.0;
            $advanced = 0.0;
            foreach ($flows as $f) {
                $date = (string) $f['due_date'];
                if ($date > $prevEnd->toDateString() && $date <= $monthEnd->toDateString()) {
                    if ((float) $f['amount'] >= 0) {
                        $cash += (float) $f['amount'];
                    } else {
                        $advance = -(float) $f['amount'];
                        $advanced += $advance;
                        $interest += $advance * (pow(1 + $eir, CarbonImmutable::parse($date)->diffInDays($monthEnd->startOfDay()) / 365) - 1);
                    }
                }
            }
            $closing = $balance + $advanced + $interest - $cash;
            $rows[] = ['period' => $monthEnd->format('Y-m'), 'opening' => round($balance, 4), 'interest' => round($interest, 4), 'cash' => round($cash, 4),
                'advanced' => round($advanced, 4), 'closing' => round($closing, 4), 'days' => $stub ? $days : null];
            $balance = $closing;
            $prevEnd = $monthEnd;
            $monthEnd = $monthEnd->addDay()->endOfMonth();
            $stub = false;
        }

        return $rows;
    }

    /** The fees found on a block, each with the cell it came from; a blank is not a fee. */
    private function feesFound(object $b): array
    {
        $cells = json_decode($b->cells ?? '', true) ?? [];
        $out = ['total' => $b->total_fees !== null ? (float) $b->total_fees : null, 'date' => $b->fee_date, 'deducted' => $b->fee_deducted, 'source' => $b->fee_source, 'lines' => []];
        foreach (['arrangement_fee', 'legal_fees', 'other_fees'] as $k) {
            if ($b->$k !== null) {
                $out['lines'][] = ['type' => $k, 'amount' => (float) $b->$k, 'cell' => $cells[$k] ?? null];
            }
        }

        return $out;
    }

    // ----- gates ----------------------------------------------------------

    private function gates(array $blocks): array
    {
        $master = $this->zone->accountMaster();
        $postings = $this->takeonPostings();
        $seen = [];
        $out = ['blocks' => [], 'summary' => ['blocks' => count($blocks), 'mapped' => 0, 'not_matched' => 0, 'refused' => 0, 'flagged' => 0, 'principal_exact' => 0, 'no_fee_row' => 0]];
        foreach ($blocks as $no => $b) {
            $g = ['refusals' => [], 'flags' => [], 'status' => 'MAPPED'];
            $account = $b['account'] ?? null;
            $cell = $b['cells']['proposed_account'] ?? "Blocks!A" . ($no + 1);
            if ($account === null || $account === '') {
                $g['status'] = 'NOT_MATCHED';
                $g['flags'][] = "no E-Banker account mapped ({$cell})";
                $out['summary']['not_matched']++;
            } elseif (! isset($master[$account])) {
                $g['refusals'][] = "account {$account} is not in the account master ({$cell})";
            } elseif (isset($seen[$account])) {
                $g['refusals'][] = "account {$account} already has block {$seen[$account]} ({$cell})";
            } else {
                $seen[$account] = $no;
                $p = $postings[$account] ?? null;
                if ($p === null) {
                    $g['flags'][] = "no take-on posting on 31 Jul 2024 for {$account}";
                } elseif ($b['principal'] !== null) {
                    if (abs($b['principal'] - $p['principal']) < 1) {
                        $out['summary']['principal_exact']++;
                    } else {
                        $g['flags'][] = sprintf('block principal %s differs from the take-on posting %s by %s (%s)', number_format($b['principal'], 2), number_format($p['principal'], 2), number_format($b['principal'] - $p['principal'], 2), $b['cells']['principal']);
                    }
                }
                if (($b['total_fees'] ?? null) === null) {
                    $g['flags'][] = 'no fee row: not assumed fee-free (' . ($b['cells']['arrangement_fee'] ?? 'Upload summary') . ')';
                    $out['summary']['no_fee_row']++;
                }
                if (($b['confirmed'] ?? null) === 'N' && empty($b['corrected_account'])) {
                    $g['refusals'][] = "marked N without a corrected account ({$b['cells']['corrected_account']})";
                }
            }
            if ($b['out_of_order'] > 0) {
                $g['flags'][] = "{$b['out_of_order']} schedule line(s) out of due-date order; sorted by date (F16)";
            }
            if ($b['bad_dates'] > 0) {
                $g['refusals'][] = "{$b['bad_dates']} schedule line(s) with an unreadable date";
            }
            if ($g['refusals'] !== []) {
                $g['status'] = 'REFUSED';
                $out['summary']['refused']++;
            } elseif ($g['status'] === 'MAPPED') {
                $out['summary']['mapped']++;
                if ($g['flags'] !== []) {
                    $g['status'] = 'FLAGGED';
                    $out['summary']['flagged']++;
                }
            }
            $out['blocks'][$no] = $g;
        }

        return $out;
    }

    /** E-Banker's opening postings per account at the take-on: the 301 of 31 Jul 2024 and the opening interest charge. */
    private function takeonPostings(): array
    {
        $out = [];
        foreach ($this->zone->ledgerByAccount(self::TAKEON_DATE) as $account => $posts) {
            // the three opening legs are keyed by their operation date (the migration
            // day), not their transaction date, which E-Banker back-dated to the loan
            $principal = 0.0; $interest = 0.0; $recovery = 0.0; $found = false;
            foreach ($posts as $p) {
                $x = $p['payload'];
                if (($x['OPERATION_DATE'] ?? '') !== '7/31/2024' || stripos((string) ($x['PARTICULARS'] ?? ''), 'opening') === false) {
                    continue;
                }
                $amt = (float) str_replace(',', '', (string) ($x['TRANSAMT'] ?? 0));
                $found = true;
                match ($x['TRANTYPE'] ?? '') {
                    '301' => $principal -= $amt,
                    '303' => $interest -= $amt,
                    '305' => $recovery += $amt,
                    default => null,
                };
            }
            if ($found) {
                $out[$account] = ['principal' => round($principal, 4), 'interest' => round($interest, 4), 'recovery' => round($recovery, 4)];
            }
        }

        return $out;
    }

    private function scheduleBalanceAt(int $blockId, string $date): ?float
    {
        $line = DB::table('takeon_schedule_lines')->where('block_id', $blockId)->whereNotNull('period_end')->where('period_end', '<=', $date)->orderByDesc('period_end')->first();

        return $line?->closing_balance !== null ? (float) $line->closing_balance : null;
    }

    // ----- reading the workbook ---------------------------------------------

    private function readBlock(Worksheet $amort, int $no, int $row): array
    {
        $b = ['block_no' => $no, 'sheet_row' => $row, 'title' => $this->s($amort, "C{$row}"), 'principal' => null, 'rate' => null, 'periods_per_year' => null, 'total_periods' => null, 'pmt' => null,
            'lines' => [], 'out_of_order' => 0, 'bad_dates' => 0, 'cells' => ['title' => "Amortisation and Repayments!C{$row}"]];
        $header = null;
        for ($r = $row + 1; $r <= $row + 14; $r++) {
            $label = strtolower(trim((string) $this->s($amort, "C{$r}")));
            $val = $this->v($amort, "D{$r}");
            if (str_starts_with($label, 'principal')) { $b['principal'] = is_numeric($val) ? round((float) $val, 4) : null; $b['cells']['principal'] = "Amortisation and Repayments!D{$r}"; }
            elseif ($label === 'rate') { $b['rate'] = is_numeric($val) ? round((float) $val < 1 ? (float) $val * 100 : (float) $val, 6) : null; $b['cells']['rate'] = "Amortisation and Repayments!D{$r}"; }
            elseif ($label === 'per') { $b['periods_per_year'] = is_numeric($val) ? (int) $val : null; }
            elseif ($label === 'nper') { $b['total_periods'] = is_numeric($val) ? (int) $val : null; }
            elseif (str_starts_with($label, 'pmt')) { $b['pmt'] = is_numeric($val) ? round((float) $val, 4) : null; $b['cells']['pmt'] = "Amortisation and Repayments!D{$r}"; }
            elseif ($label === 'period start') { $header = $r; break; }
        }
        if ($header === null) {
            return $b;
        }
        $prevEnd = null;
        for ($r = $header + 1; $r <= $header + 400; $r++) {
            $serial = $this->v($amort, "B{$r}");
            if (! is_numeric($serial)) {
                break;
            }
            $start = $this->d($amort, "C{$r}");
            $end = $this->d($amort, "D{$r}");
            if ($end === null && $this->v($amort, "D{$r}") !== null) {
                $b['bad_dates']++;
            }
            if ($end !== null && $prevEnd !== null && $end < $prevEnd) {
                $b['out_of_order']++;
            }
            $prevEnd = $end ?? $prevEnd;
            $b['lines'][] = ['serial' => (int) $serial, 'sheet_row' => $r, 'period_start' => $start, 'period_end' => $end, 'days' => $this->i($amort, "E{$r}"),
                'opening_balance' => $this->n($amort, "F{$r}"), 'instalment' => $this->n($amort, "G{$r}"), 'interest' => $this->n($amort, "H{$r}"), 'principal' => $this->n($amort, "I{$r}"),
                'closing_balance' => $this->n($amort, "J{$r}"), 'amount_repaid' => $this->n($amort, "K{$r}"), 'accumulated_arrears' => $this->n($amort, "L{$r}"), 'out_of_order' => false];
        }
        if ($b['out_of_order'] > 0) {
            usort($b['lines'], fn ($x, $y) => [$x['period_end'] ?? '', $x['serial']] <=> [$y['period_end'] ?? '', $y['serial']]);
        }

        return $b;
    }

    private function readFacility(Worksheet $lb, int $row): array
    {
        $rate = $this->n($lb, "K{$row}");

        return [
            'facility_name' => $this->s($lb, "E{$row}"), 'facility_type' => $this->s($lb, "F{$row}"), 'value_date' => $this->d($lb, "G{$row}"), 'maturity_date' => $this->d($lb, "H{$row}"),
            'tenor_years' => $this->n($lb, "I{$row}"), 'moratorium' => $this->s($lb, "J{$row}"), 'loan_book_rate' => $rate !== null ? ($rate < 1 ? round($rate * 100, 6) : $rate) : null,
            'approved' => $this->n($lb, "L{$row}"), 'disbursed' => $this->n($lb, "M{$row}"), 'principal_31_oct_2024' => $this->n($lb, "O{$row}"), 'interest_to_date' => $this->n($lb, "P{$row}"),
            'repayments' => $this->n($lb, "Q{$row}"), 'carrying_31_oct_2024' => $this->n($lb, "R{$row}"),
            'cells' => ['facility_name' => "Loan Book!E{$row}", 'value_date' => "Loan Book!G{$row}", 'maturity_date' => "Loan Book!H{$row}", 'loan_book_rate' => "Loan Book!K{$row}", 'approved' => "Loan Book!L{$row}", 'carrying_31_oct_2024' => "Loan Book!R{$row}"],
        ];
    }

    private function open(string $path)
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        return $reader->load($path);
    }

    /** A cell's value; for a formula cell the value Excel last computed (the original is Excel-saved, so it is there). */
    private function v(Worksheet $ws, string $cell): mixed
    {
        $c = $ws->getCell($cell);
        $v = $c->getValue();
        if (is_string($v) && str_starts_with($v, '=')) {
            $v = $c->getOldCalculatedValue();
            if ($v === null) {
                try {
                    $v = $c->getCalculatedValue();
                } catch (Throwable) {
                    $v = null;
                }
            }
        }

        return is_string($v) && trim($v) === '' ? null : $v;
    }

    private function s(Worksheet $ws, string $cell): ?string
    {
        $v = $this->v($ws, $cell);

        return $v === null ? null : trim((string) $v);
    }

    private function n(Worksheet $ws, string $cell): ?float
    {
        $v = $this->v($ws, $cell);
        if (is_string($v)) {
            $v = str_replace([',', ' '], '', $v);
        }

        return is_numeric($v) ? round((float) $v, 4) : null;
    }

    private function i(Worksheet $ws, string $cell): ?int
    {
        $n = $this->n($ws, $cell);

        return $n === null ? null : (int) $n;
    }

    /** A date cell: an Excel serial, a DateTime, or text in d/m/Y or Y-m-d; null when blank or unreadable. */
    private function d(Worksheet $ws, string $cell): ?string
    {
        $v = $this->v($ws, $cell);
        if ($v === null) {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d'] as $f) {
            try {
                $d = CarbonImmutable::createFromFormat('!' . $f, trim((string) $v));
                if ($d !== false && $d->format($f) === trim((string) $v)) {
                    return $d->toDateString();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }
}
