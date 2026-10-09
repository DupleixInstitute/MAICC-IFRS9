<?php

namespace App\Services\Ebanker;

use App\Exceptions\GovernanceSettingMissingException;
use App\Services\AuditLoggerService;
use App\Services\Eir\CalculateEirService;
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
        try {
            $setting = $this->governance->get('takeon_history_basis', CarbonImmutable::parse(self::TAKEON_DATE));
        } catch (Throwable) {
            $setting = 'Recompute from origination where the block and fees exist, else start at the take-on balance';
        }
        $mode = str_starts_with($setting, 'Start every') ? 'BALANCE' : (str_starts_with($setting, 'Recompute from origination for every') ? 'STRICT' : 'WHERE_EVIDENCED');
        $blocks = DB::table('takeon_blocks')->where('load_id', $loadId)->whereNotNull('account')->where('status', '!=', 'REFUSED')->get()->keyBy('account');
        $postings = $this->takeonPostings();
        $counts = ['accounts' => 0, 'recomputed' => 0, 'takeon_balance' => 0, 'refused' => 0, 'schedule_lines' => 0, 'setting' => $setting];
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
                $counts[match ($basis) { 'RECOMPUTED' => 'recomputed', 'TAKEON_BALANCE' => 'takeon_balance', default => 'refused' }]++;
            }
            AuditLoggerService::log('Take-on Population Built', 'contract_takeon', null, ['new_values' => $counts, 'meta' => ['user' => $userId]]);
        });

        return $counts;
    }

    // ----- the recompute from origination (6.9; audit finding M7) -----------

    /**
     * For one evidenced block: the version 1 schedule from the workbook, the
     * EIR solved from origination on the net investment, and the amortised
     * cost rolled forward to the take-on date. Returns the contract_takeon
     * columns to write, the flags raised and whether the solve succeeded.
     *
     * @return array{solved:bool,columns:array,lines:int,flags:list<string>}
     */
    private function recompute(string $account, object $b, float $takeonBalance): array
    {
        $flags = [];
        $contractId = ltrim($account, '0');
        $origination = $b->value_date !== null ? CarbonImmutable::parse($b->value_date) : null;
        $principal = $b->principal !== null ? (float) $b->principal : null;
        // the fees are a condition of the loan and so integral; none exist today
        // (every fee row is blank), so a known nil must work as well as a figure
        $fees = (float) ($b->total_fees ?? 0);
        if ($origination === null || $principal === null || $principal <= 0) {
            $flags[] = 'recompute not possible: the block has no origination date or principal';

            return ['solved' => false, 'columns' => [], 'lines' => 0, 'flags' => $flags];
        }

        // the schedule: one row per due date (two workbook lines on one date are
        // added together, since the schedule table keys on the date)
        $byDate = [];
        $undated = 0;
        foreach (DB::table('takeon_schedule_lines')->where('block_id', $b->id)->orderBy('period_end')->orderBy('serial')->get() as $l) {
            if ($l->period_end === null) {
                $undated++;
                continue;
            }
            $instalment = $l->instalment !== null ? (float) $l->instalment : (float) ($l->principal ?? 0) + (float) ($l->interest ?? 0);
            $interest = $l->interest !== null ? (float) $l->interest : 0.0;
            $principalDue = $l->principal !== null ? (float) $l->principal : $instalment - $interest;
            $byDate[$l->period_end] ??= ['principal' => 0.0, 'interest' => 0.0, 'amount' => 0.0, 'rows' => []];
            $byDate[$l->period_end]['principal'] += $principalDue;
            $byDate[$l->period_end]['interest'] += $interest;
            $byDate[$l->period_end]['amount'] += $instalment;
            $byDate[$l->period_end]['rows'][] = (int) $l->sheet_row;
        }
        if ($undated > 0) {
            $flags[] = "{$undated} schedule line(s) without a due date left out of the recompute";
        }
        ksort($byDate);

        $lines = $this->writeWorkbookSchedule($contractId, $b, $origination, $byDate, $flags);

        // the solve: the dated EIR on the receipts after origination, under the
        // governed day count in force at origination (no basis written here)
        $flows = [];
        $onOrBefore = 0;
        foreach ($byDate as $date => $d) {
            if ($date <= $origination->toDateString()) {
                $onOrBefore++;
                continue;
            }
            $flows[] = ['due_date' => $date, 'amount' => round($d['amount'], 4)];
        }
        if ($onOrBefore > 0) {
            $flags[] = "{$onOrBefore} schedule line(s) dated on or before origination left out of the solve";
        }
        $net = round($principal - $fees, 4);
        try {
            $dayCount = $this->governance->get('day_count', $origination);
            $ppy = in_array((int) $b->periods_per_year, [1, 2, 4, 6, 12], true) ? (int) $b->periods_per_year : 12;
            $solve = $this->eir->calculateDated($net, $flows, $ppy, $origination->toDateString(), $dayCount);
        } catch (GovernanceSettingMissingException $e) {
            $flags[] = 'recompute not possible: ' . $e->getMessage();

            return ['solved' => false, 'columns' => ['net_investment' => $net, 'schedule_lines_written' => $lines], 'lines' => $lines, 'flags' => $flags];
        } catch (Throwable $e) {
            $flags[] = 'EIR not solved from origination: ' . $e->getMessage();

            return ['solved' => false, 'columns' => ['net_investment' => $net, 'schedule_lines_written' => $lines], 'lines' => $lines, 'flags' => $flags];
        }

        $roll = $this->rollForward($net, (float) $solve['eir_effective_annual'], $origination, $byDate, CarbonImmutable::parse(self::TAKEON_DATE));
        if ($roll === []) {
            $flags[] = 'origination is after the take-on date: nothing to roll forward';

            return ['solved' => false, 'columns' => ['net_investment' => $net, 'recomputed_eir' => $solve['eir_effective_annual'], 'schedule_lines_written' => $lines], 'lines' => $lines, 'flags' => $flags];
        }
        $closing = round(end($roll)['closing'], 4);

        return ['solved' => true, 'lines' => $lines, 'flags' => $flags, 'columns' => [
            'net_investment' => $net,
            'recomputed_eir' => round((float) $solve['eir_effective_annual'], 8),
            'recomputed_eir_monthly' => round(pow(1 + (float) $solve['eir_effective_annual'], 1 / 12) - 1, 8),
            'recomputed_amortised_cost' => $closing,
            'recomputed_difference' => round($takeonBalance - $closing, 4),
            'schedule_lines_written' => $lines,
            'recompute_detail' => json_encode([
                'solve' => ['method' => $solve['method'], 'iterations' => $solve['solver_iterations'], 'residual' => $solve['solver_residual'], 'day_count' => $dayCount,
                    'payments_per_year' => $ppy, 'net_investment' => $net, 'fees' => $fees, 'flows' => count($flows), 'receipts' => round(array_sum(array_column($flows, 'amount')), 4)],
                'roll_forward' => $roll,
            ]),
        ]];
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
     * up the schedule's cash due in the month; the first, part, month from
     * origination to its month-end earns the EIR for its actual days over
     * 365, the same daily basis the dated solve discounts on.
     *
     * @return list<array{period:string,opening:float,interest:float,cash:float,closing:float,days:?int}>
     */
    private function rollForward(float $net, float $eir, CarbonImmutable $origination, array $byDate, CarbonImmutable $to): array
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
            foreach ($byDate as $date => $d) {
                if ($date > $prevEnd->toDateString() && $date <= $monthEnd->toDateString()) {
                    $cash += $d['amount'];
                }
            }
            $closing = $balance + $interest - $cash;
            $rows[] = ['period' => $monthEnd->format('Y-m'), 'opening' => round($balance, 4), 'interest' => round($interest, 4), 'cash' => round($cash, 4), 'closing' => round($closing, 4), 'days' => $stub ? $days : null];
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
