<?php

namespace App\Console\Commands;

use App\Models\SourceImportBatch;
use App\Services\SourceImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Ingests the ZNBS granular loan listing (the obligor-level IFRS 9 input file)
 * into the canonical credit register (customers + credit_facilities). The
 * regulatory IFRS 9 engine then reads these through the ifrs9_snapshot_input
 * view to compute ECL on the REAL book, and concentration / large-exposures are
 * derived from the same obligors - replacing the interim N-A declarations.
 *
 * Fail-closed: a row with no usable outstanding amount is SKIPPED and counted,
 * never imported as a silent zero.
 */
class ImportZnbsLoanListingCommand extends Command
{
    protected $signature = 'znbs:import-loan-listing '
        . '{--file= : path to the loan-listing xlsx} '
        . '{--as-of= : as-of date (default: the intake cycle date for the period, else its month end)} '
        . '{--period= : intake period code, e.g. 2026-03 (required with --file)}';

    protected $description = 'Import the ZNBS granular loan listing into the canonical credit register (customers + credit_facilities).';

    /**
     * Sheet name PREFIX => loan_type_code. Matched case-insensitively against the
     * actual workbook sheet names, so both the original file ('Mortgage' /
     * 'Unsecured') and the 23-Jun-2026 file ('Mortgages' / 'Unsecured Loans')
     * resolve without a code change.
     */
    private const SHEET_PREFIXES = ['mortgage' => 'mortgage', 'unsecured' => 'unsecured'];

    public function handle(): int
    {
        // Reading the ~10MB listing + 24k rows needs headroom; ensure it even when
        // the command is invoked in-process from the bootstrap.
        if ((int) ini_get('memory_limit') !== -1) {
            ini_set('memory_limit', '4096M');
        }

        // PORTABLE: the committed docs/ copy (refreshed from the 23-June email) is
        // the authoritative source so a client server resolves the SAME file. No
        // external OneDrive/local fallback - bootstrap must run from project files.
        $file = $this->option('file')
            ?: $this->firstExisting([
                base_path('docs/ICAAP docs/ZNBS Dec2025 Client Inputs/20251231_Loan Listing.xlsx'),
            ]);
        // Universal import log: open a master batch up-front so even a missing
        // file or no-sheets failure is recorded (no silent imports).
        $svc   = app(SourceImportService::class);
        $batch = $svc->startBatch([
            'source_kind'      => SourceImportBatch::SOURCE_LOAN_TAPE,
            'primary_filename' => $file ? basename($file) : '20251231_Loan Listing.xlsx',
        ]);

        if (! $file || ! is_file($file)) {
            $this->error('Loan listing file not found. Pass --file= or place it at docs/ICAAP docs/ZNBS Dec2025 Client Inputs/20251231_Loan Listing.xlsx.');
            $svc->finishBatch($batch, SourceImportBatch::STATUS_FAILED, 'Loan listing file not found.');
            return self::FAILURE;
        }
        $this->info("Loan listing: {$file}");

        // Resolve the actual sheet names in this workbook against the known
        // prefixes, so 'Mortgage'/'Mortgages' and 'Unsecured'/'Unsecured Loans'
        // both work.
        $sheets = $this->resolveSheets($file);
        if ($sheets === []) {
            $this->error('No Mortgage/Unsecured sheets found in the loan listing.');
            $svc->finishBatch($batch, SourceImportBatch::STATUS_FAILED, 'No Mortgage/Unsecured sheets found in the loan listing.');
            return self::FAILURE;
        }
        // The file's own period (spec E4.3): --period, or the bundled December 2025
        // client inputs when no --file is given; never a default period or date.
        try {
            [$period, $asOf] = \App\Support\IntakePeriodOption::resolve(
                $this->option('period'),
                $this->option('as-of'),
                $this->option('file') ? null : \App\Support\IntakePeriodOption::BUNDLED_CLIENT_INPUTS_PERIOD,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $svc->finishBatch($batch, SourceImportBatch::STATUS_FAILED, $e->getMessage());

            return self::FAILURE;
        }

        $cycleId = $this->resolveCycle($period, $asOf);
        $this->info("Intake cycle #{$cycleId} ({$period}, as-of {$asOf}).");

        // Fail-closed re-import: clear this cycle's facilities + derived collateral.
        DB::table('credit_facilities')->where('cycle_id', $cycleId)->delete();
        DB::table('collateral_items')->where('cycle_id', $cycleId)->delete();

        $custMap = [];   // customer_code => customer_id
        $collateral = []; // facility_code => collateral market value (mortgage book)
        $imported = 0; $skipped = 0; $totalExposure = 0.0;

        foreach ($sheets as $sheet => $loanType) {
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$sheet]);
            $this->info("Reading sheet: {$sheet} ...");
            $rows = $reader->load($file)->getActiveSheet()->toArray(null, true, false, false);
            $header = array_shift($rows); // header row

            // Resolve the GROSS carrying-amount column by NAME (robust to column
            // reordering). The filed gross balance the statement ties to is
            // "Book Balance_Final" = Book Balance + Normal Accrual Amount, i.e.
            // principal PLUS accrued interest - the IFRS 9 gross carrying amount.
            // Reading plain "Book Balance" (ex-accrual) understated the book by the
            // accrual (~0.77%, ~K19.5m) and missed the listing's own Gross Balance.
            // Fall back to Book Balance, then Exposure, then the legacy indices.
            $grossCol = $this->resolveCol($header, ['book balance_final', 'book balance final']) ?? 27;
            $bookCol  = $this->resolveCol($header, ['book balance']) ?? 19;
            $expCol   = $this->resolveCol($header, ['exposure']) ?? 9;

            $facBatch = [];
            foreach ($rows as $r) {
                $facilityCode = trim((string) ($r[0] ?? ''));
                if ($facilityCode === '') {
                    continue;
                }
                // Gross carrying amount: Book Balance_Final (incl. accrual) first,
                // then Book Balance, then Exposure. Fail-closed: no usable amount -> skip.
                $outstanding = $this->num($r[$grossCol] ?? null)
                    ?: $this->num($r[$bookCol] ?? null)
                    ?: $this->num($r[$expCol] ?? null);
                if ($outstanding === null || abs($outstanding) <= 0.0) {
                    $skipped++;
                    continue;
                }
                $outstanding = abs($outstanding);

                $surname = trim((string) ($r[2] ?? ''));
                $first   = trim((string) ($r[3] ?? ''));
                $name    = trim($surname . ' ' . $first) ?: ('FACILITY ' . $facilityCode);
                $custCode = 'C' . substr(hash('crc32b', mb_strtoupper($name)), 0, 12);

                if (! isset($custMap[$custCode])) {
                    $custMap[$custCode] = DB::table('customers')->updateOrInsert(
                        ['customer_code' => $custCode],
                        // institutional_unit_code is the BoZ prudential taxonomy the
                        // regulatory RWA counterparty-mapping keys on; retail obligors
                        // are individuals & households. Without it, regulatory-mode RWA
                        // hard-fails (no matching exposure-class rule).
                        ['name' => mb_substr($name, 0, 300), 'customer_type_code' => 'retail', 'institutional_unit_code' => 'individuals_and_households', 'residency' => 'resident', 'updated_at' => now(), 'created_at' => now()]
                    );
                    $custMap[$custCode] = DB::table('customers')->where('customer_code', $custCode)->value('id');
                }
                $dpd = (int) ($this->num($r[10] ?? null) ?? 0);
                $collValue = $loanType === 'mortgage' ? $this->num($r[18] ?? null) : null;

                $facBatch[] = [
                    'cycle_id'               => $cycleId,
                    'facility_code'          => mb_substr($facilityCode, 0, 100),
                    'customer_id'            => $custMap[$custCode],
                    'loan_type_code'         => $loanType,
                    'product_code'           => mb_substr(trim((string) ($r[1] ?? $loanType)), 0, 80),
                    'classification_code'    => $this->bozClassification($dpd),
                    'currency_code'          => 'ZMW',
                    'amount_outstanding'     => $outstanding,
                    'amount_outstanding_zmw' => $outstanding,
                    'days_past_due'          => min($dpd, 65535),
                    'security_indicator'     => $loanType === 'mortgage' ? 1 : 0,
                    // #064 granular IRRBB: map the source maturity + rate so the
                    // canonical repricing-ladder view exposes a real repricing date
                    // (AccExp Date) instead of falling back to the NMD core-runoff.
                    'date_maturity'          => $this->parseDate($r[5] ?? null),  // AccExp Date
                    'interest_rate'          => $this->num($r[12] ?? null),       // EIR (annual %)
                    'attributes'             => json_encode(array_filter([
                        'boz_exposure'    => $this->num($r[21] ?? null),
                        'collateral_value' => $collValue,
                        'eir'             => $this->num($r[12] ?? null),
                    ], fn ($v) => $v !== null)),
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ];
                // P1.4: remember the mortgage's collateral market value so it can be
                // materialised into collateral_items once the facility id exists.
                if ($collValue !== null && abs($collValue) > 0.0) {
                    $collateral[$facilityCode] = abs($collValue);
                }
                $imported++;
                $totalExposure += $outstanding;

                if (count($facBatch) >= 1000) {
                    DB::table('credit_facilities')->insert($facBatch);
                    $facBatch = [];
                }
            }
            if ($facBatch) {
                DB::table('credit_facilities')->insert($facBatch);
            }
            $this->line("  {$sheet}: done.");
        }

        // P1.4 - materialise mortgage collateral into collateral_items so the RWA
        // engine's recognised_collateral_zmw view aggregate recognises the property
        // security for LTV banding. It reads collateral_items, not the facility
        // attributes, so storing the value only in attributes left it unrecognised.
        if ($collateral !== []) {
            $idMap = [];
            foreach (array_chunk(array_keys($collateral), 5000) as $codeChunk) {
                foreach (DB::table('credit_facilities')->where('cycle_id', $cycleId)
                    ->whereIn('facility_code', $codeChunk)->pluck('id', 'facility_code') as $code => $id) {
                    $idMap[$code] = $id;
                }
            }
            $collBatch = [];
            $collCount = 0;
            $collTotal = 0.0;
            foreach ($collateral as $facCode => $mv) {
                $fid = $idMap[$facCode] ?? null;
                if ($fid === null) {
                    continue;
                }
                $collBatch[] = [
                    'cycle_id'              => $cycleId,
                    'collateral_code'       => 'COL-' . mb_substr((string) $facCode, 0, 96),
                    'facility_id'           => $fid,
                    'collateral_type_label' => 'Residential Property',
                    'collateral_group'      => 'real_estate',
                    'currency_code'         => 'ZMW',
                    'market_value'          => $mv,
                    'market_value_zmw'      => $mv,
                    // These *_pct columns are decimal(6,4) FRACTIONS (1.0 = 100%);
                    // residential property is fully recognised, the risk weighting
                    // comes from the LTV band, not a CRM haircut.
                    'recognition_pct'       => 1.0,
                    'encumbrance_status'    => 'unencumbered',
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ];
                $collTotal += $mv;
                $collCount++;
                if (count($collBatch) >= 1000) {
                    DB::table('collateral_items')->insert($collBatch);
                    $collBatch = [];
                }
            }
            if ($collBatch) {
                DB::table('collateral_items')->insert($collBatch);
            }
            $this->info("Materialised {$collCount} mortgage collateral items; total market value (ZMW): " . number_format($collTotal, 0));
        }

        $this->newLine();
        $this->info("Imported {$imported} facilities for " . count($custMap) . ' obligors; skipped (no amount): ' . $skipped . '.');
        $this->info('Total outstanding (ZMW): ' . number_format($totalExposure, 0));
        $this->line('Run the regulatory IFRS 9 + concentration engines for this cycle to compute engine-true ECL and HHI.');

        $batch->forceFill([
            'file_count'         => count($sheets),
            'row_count'          => $imported + $skipped,
            'accepted_row_count' => $imported,
            'rejected_row_count' => $skipped,
            'cycle_id'           => $cycleId,
        ])->save();
        $svc->finishBatch($batch);

        return self::SUCCESS;
    }

    /** First path that exists, or null. */
    /**
     * Resolve a column index by matching the NORMALISED header (trim, collapse
     * whitespace/newlines, lower-case) against any candidate name with an EXACT
     * match - so "Book Balance" does not also match "Book Balance_Final".
     *
     * @param  array<int, mixed>  $header
     * @param  array<int, string>  $candidates  normalised lower-case names
     */
    private function resolveCol(?array $header, array $candidates): ?int
    {
        foreach (($header ?? []) as $idx => $name) {
            $norm = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $name)));
            if (in_array($norm, $candidates, true)) {
                return (int) $idx;
            }
        }

        return null;
    }

    private function firstExisting(array $paths): ?string
    {
        foreach ($paths as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Map each actual sheet name in the workbook to its loan_type by prefix, so
     * 'Mortgage'/'Mortgages' and 'Unsecured'/'Unsecured Loans' all resolve.
     *
     * @return array<string, string>  actualSheetName => loan_type_code
     */
    private function resolveSheets(string $file): array
    {
        $names = (new Xlsx())->listWorksheetNames($file);
        $out   = [];
        foreach ($names as $name) {
            $lower = strtolower(trim($name));
            foreach (self::SHEET_PREFIXES as $prefix => $loanType) {
                if (str_starts_with($lower, $prefix) && ! in_array($loanType, $out, true)) {
                    $out[$name] = $loanType;
                    break;
                }
            }
        }

        return $out;
    }

    private function resolveCycle(string $period, string $asOf): int
    {
        $existing = \App\Models\RegulatoryIntakeCycle::idForPeriod($period);
        if ($existing) {
            return (int) $existing;
        }

        // Basis and status from their enumerations (spec E7, audit P6): the CLI wrote
        // "regulatory" and "imported", which the intake screen then refused to open.
        return (int) DB::table('regulatory_intake_cycles')->insertGetId([
            'label'           => 'ZNBS ' . $period . ' (loan-listing intake)',
            'reporting_basis' => \App\Models\RegulatoryIntakeCycle::BASIS_QUARTERLY,
            'period_code'     => $period,
            'as_of_date'      => $asOf,
            'period_end'      => $asOf,
            'currency'        => 'ZMW',
            'pack_type'       => 'prudential',
            'status'          => \App\Models\RegulatoryIntakeCycle::STATUS_OPEN,
            'notes'           => 'Granular credit register imported from the ZNBS loan listing (IFRS 9 inputs).',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Parse a source date cell into Y-m-d. The sheet is read unformatted, so a
     * date arrives as an Excel serial number; fall back to free-text parsing.
     * Returns null on a blank / unparseable / nonsensical (pre-1990) value so a
     * missing maturity is recorded as NULL, never silently defaulted.
     */
    private function parseDate(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if (is_numeric($v)) {
                if ((float) $v <= 0) {
                    return null;
                }
                $d = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v);
            } else {
                $d = new \DateTime((string) $v);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return (int) $d->format('Y') < 1990 ? null : $d->format('Y-m-d');
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $v);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /** BoZ loan classification from days past due. */
    private function bozClassification(int $dpd): string
    {
        return match (true) {
            $dpd >= 365 => 'loss',
            $dpd >= 180 => 'doubtful',
            $dpd >= 90  => 'substandard',
            $dpd >= 30  => 'special_mention',
            default     => 'normal',
        };
    }
}
