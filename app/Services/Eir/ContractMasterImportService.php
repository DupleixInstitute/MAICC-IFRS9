<?php

namespace App\Services\Eir;

use App\Imports\ContractMasterImport;
use App\Support\ContractId;
use Illuminate\Support\Facades\DB;

/**
 * Imports the contract master file (Extract A) into contract_eir.
 *
 * Extract A is a facility master, not a monthly snapshot: one row per
 * contract, upserted on every delivery. The book turns over — new facilities
 * originate, others restructure or close — so the same file arrives each
 * month carrying mostly unchanged rows. This service is therefore built
 * around three refusals rather than around loading:
 *
 *  - a locked contract is never rewritten. Once an EIR is solved and locked,
 *    its origination terms are the audited basis of that rate; a re-delivered
 *    file that disagrees raises a named exception for a human instead of
 *    silently invalidating the solved result and its input snapshot;
 *  - instrument_type is never set from the file. Whether a facility is an
 *    amortised loan, a preference share or excluded equity is an IAS 32/IFRS 9
 *    judgement (the Nascomex memo), not a product code;
 *  - a repayment frequency that is not recognised is reported, never guessed,
 *    because payments_per_year changes the solved periodic rate directly.
 *
 * Origination fees carried on the row (arrangement, legal) are routed to
 * contract_fees as PENDING through the existing FeeImportService, so the
 * maker/checker classification that gates solver readiness still applies.
 * Cash direction is deliberately left unset — the sign convention on the
 * delivered extracts is an open item, and a guess here would flow straight
 * into the solver's net initial investment.
 */
class ContractMasterImportService
{
    /**
     * Persisted lineage: names the vendor file, not this class. An auditor
     * tracing a term back to source asks for "Extract A".
     */
    public const SOURCE = 'MAIIC_EXTRACT_A';

    /** Terms this import owns. Solver output and lock state are never touched. */
    private const TERM_FIELDS = [
        'sub_account_no', 'gl_account_code', 'currency',
        'origination_date', 'first_repayment_date', 'maturity_date',
        'closure_date', 'last_restructure_date',
        'approved_amount', 'drawn_amount',
        'contractual_rate', 'rate_basis', 'rate_type',
        'source_day_count_basis', 'source_compounding', 'disbursement_tranches',
        'reference_rate_at_origination', 'markup',
        'payments_per_year', 'frequency_source', 'tenor_months', 'moratorium_months',
        'opening_amortised_cost', 'opening_amortised_cost_date',
    ];

    public function __construct(private readonly FeeImportService $fees) {}

    /**
     * @param  list<array<string,mixed>>  $rows  mapped Extract A rows
     * @return array{
     *   source_rows:int, created:int, updated:int, unchanged:int,
     *   duplicate_source_rows:int, held:array<string,string>,
     *   skipped:array<string,string>, unknown_frequencies:array<string,int>,
     *   fee_rows_routed:int, fee_result:array
     * }
     */
    public function import(array $rows): array
    {
        $held = [];
        $skipped = [];
        $incomplete = [];
        $unknownFrequencies = [];
        $feeRows = [];
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $now = now();

        $sourceRows = count($rows);
        [$rows, $conflicts, $duplicates] = $this->mergeDuplicateRows($rows, $skipped);
        $skipped += $conflicts;

        foreach ($rows as $contractId => $row) {
            $loan = DB::table('loan_books')->where('contract_id', $contractId)
                ->orderByDesc('reporting_period')->first(['contract_id', 'customer_id']);
            if (! $loan) {
                $held[$contractId] = 'loan account is not present in the imported loan book';
                continue;
            }

            $customer = trim((string) ($row['customer_id'] ?? ''));
            $tapeCustomer = trim((string) $loan->customer_id);
            if ($customer !== '' && $tapeCustomer !== '' && $customer !== $tapeCustomer) {
                $skipped[$contractId] = "customer {$customer} does not match loan-book customer {$tapeCustomer}";
                continue;
            }

            $terms = $this->terms($row, $contractId, $unknownFrequencies);
            $existing = DB::table('contract_eir')->where('contract_id', $contractId)->first();

            if ($existing === null) {
                // contract_eir defaults payments_per_year to 12 and
                // moratorium_months to 0. Those defaults are convenient for a
                // hand-created row and dangerous for an imported one: a
                // facility that is actually quarterly would solve against a
                // monthly period and produce a plausible, wrong rate. Create
                // the contract — it is still the anchor for schedules and fees
                // — but name the gap so it reaches the exception file.
                $missing = $this->missingSolverTerms($terms);
                if ($missing !== []) {
                    $incomplete[$contractId] = 'created without ' . implode(', ', $missing)
                        . '; the EIR cannot be solved until supplied';
                }

                DB::table('contract_eir')->insert($terms + [
                    'contract_id' => $contractId,
                    'terms_source_system' => self::SOURCE,
                    'terms_source_reference' => trim((string) ($row['run_id'] ?? '')) ?: null,
                    'terms_imported_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $created++;
                $feeRows = array_merge($feeRows, $this->feeRows($row, $contractId));
                continue;
            }

            $changes = $this->changedTerms($existing, $terms);

            if ($existing->locked_at !== null) {
                if ($changes !== []) {
                    $skipped[$contractId] = 'EIR is locked and the file disagrees on '
                        . implode(', ', array_keys($changes))
                        . '; unlock and re-solve rather than overwriting the audited basis';
                } else {
                    $unchanged++;
                }
                continue;
            }

            if ($changes === []) {
                $unchanged++;
                continue;
            }

            DB::table('contract_eir')->where('contract_id', $contractId)->update($changes + [
                'terms_source_system' => self::SOURCE,
                'terms_source_reference' => trim((string) ($row['run_id'] ?? '')) ?: null,
                'terms_imported_at' => $now,
                'updated_at' => $now,
            ]);
            $updated++;
            $feeRows = array_merge($feeRows, $this->feeRows($row, $contractId));
        }

        $feeResult = $this->fees->import($feeRows);

        return [
            'source_rows' => $sourceRows,
            'facilities' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'duplicate_source_rows' => $duplicates,
            'held' => $held,
            'skipped' => $skipped,
            'incomplete' => $incomplete,
            'unknown_frequencies' => $unknownFrequencies,
            'fee_rows_routed' => count($feeRows),
            'fee_result' => $feeResult,
            'loaded_rows' => $created + $updated,
        ];
    }

    /**
     * Collapse the file to one row per facility.
     *
     * The delivered Extract A carries every facility twice — 362 rows for 181
     * accounts — and the pairs are not always identical: 42 of them disagree
     * on repayment frequency, 17 between two real values (Monthly vs Yearly,
     * Monthly vs Half-Yearly, Monthly vs Quarterly) rather than against a
     * blank.
     *
     * Taking the first row would resolve those by file order, which for a
     * Monthly-vs-Yearly pair is a twelve-fold error in the annualised rate
     * decided by nothing. So the rows are merged instead: where one side is
     * blank the stated value wins, and where both state a different value the
     * facility is rejected with the field and both values named. A human
     * settles it against the offer letter; the importer does not guess.
     *
     * @param  array<string,string>  $skipped  rows with no identifier, by reference
     * @return array{0: array<string,array<string,mixed>>, 1: array<string,string>, 2: int}
     *         [merged rows keyed by contract, conflicts, duplicate row count]
     */
    private function mergeDuplicateRows(array $rows, array &$skipped): array
    {
        $grouped = [];
        $duplicates = 0;

        foreach ($rows as $index => $row) {
            $contractId = ContractId::normalise($row['contract_id'] ?? null);
            if ($contractId === null) {
                $skipped['row ' . ($index + 2)] = 'no loan account number on the row';
                continue;
            }
            if (isset($grouped[$contractId])) {
                $duplicates++;
            }
            $grouped[$contractId][] = $row;
        }

        $merged = [];
        $conflicts = [];

        foreach ($grouped as $contractId => $group) {
            if (count($group) === 1) {
                $merged[$contractId] = $group[0];
                continue;
            }

            $row = [];
            $disagreements = [];

            foreach ($group as $candidate) {
                foreach ($candidate as $field => $value) {
                    $text = trim((string) ($value ?? ''));
                    if ($text === '' || $text === '-') {
                        continue; // a blank never overrides a stated value
                    }
                    if (! array_key_exists($field, $row)) {
                        $row[$field] = $value;
                        continue;
                    }
                    if (strcasecmp(trim((string) $row[$field]), $text) !== 0) {
                        $disagreements[$field] = "{$field} is both '{$row[$field]}' and '{$text}'";
                    }
                }
            }

            if ($disagreements !== []) {
                $conflicts[$contractId] = 'the file states conflicting values for this facility — '
                    . implode('; ', $disagreements)
                    . '. Confirm against the offer letter rather than accepting either.';
                continue;
            }

            $merged[$contractId] = $row;
        }

        return [$merged, $conflicts, $duplicates];
    }

    /**
     * Build the term set this file provides. Absent columns stay null and are
     * dropped before writing: a sparse re-delivery must not blank a term an
     * earlier, richer file supplied.
     *
     * @param  array<string,int>  $unknownFrequencies  accumulated by reference
     */
    private function terms(array $row, string $contractId, array &$unknownFrequencies): array
    {
        $terms = [
            'sub_account_no' => $this->text($row['sub_account_no'] ?? null),
            'gl_account_code' => $this->text($row['gl_account_code'] ?? null),
            'currency' => $this->currency($row['currency'] ?? null),
            'origination_date' => $this->date($row['origination_date'] ?? null),
            'first_repayment_date' => $this->date($row['first_repayment_date'] ?? null),
            'maturity_date' => $this->date($row['maturity_date'] ?? null),
            'closure_date' => $this->date($row['closure_date'] ?? null),
            'last_restructure_date' => $this->date($row['last_restructure_date'] ?? null),
            'approved_amount' => $this->amount($row['approved_amount'] ?? null),
            'drawn_amount' => $this->amount($row['drawn_amount'] ?? null),
            'contractual_rate' => $this->rate($row['contractual_rate'] ?? null),
            'rate_basis' => $this->text($row['rate_basis'] ?? null),
            // Stated conventions, not applied ones — see the migration note.
            'source_day_count_basis' => $this->text($row['source_day_count_basis'] ?? null),
            'source_compounding' => $this->text($row['source_compounding'] ?? null),
            'disbursement_tranches' => $this->text($row['disbursement_tranches'] ?? null),
            'rate_type' => $this->rateType($row['rate_type'] ?? null),
            'reference_rate_at_origination' => $this->rate($row['reference_rate_at_origination'] ?? null),
            'markup' => $this->rate($row['markup'] ?? null),
            'payments_per_year' => $paymentsPerYear = $this->paymentsPerYear($row, $contractId, $unknownFrequencies),
            // Only claim STATED when the file actually said so. Leaving this
            // null on an unresolved frequency means a sparse re-delivery
            // cannot downgrade a contract whose frequency an earlier, richer
            // file established.
            'frequency_source' => $paymentsPerYear === null ? null : 'STATED',
            // Durations arrive unit-suffixed ("2y 0m 0d", "3 M"), not numeric.
            'tenor_months' => ContractMasterImport::monthsFromDuration($row['tenor_months'] ?? null),
            'moratorium_months' => ContractMasterImport::monthsFromDuration($row['moratorium_months'] ?? null),
            'opening_amortised_cost' => $this->amount($row['opening_amortised_cost'] ?? null),
            'opening_amortised_cost_date' => $this->date($row['opening_amortised_cost_date'] ?? null),
        ];

        return array_filter($terms, fn ($value) => $value !== null);
    }

    /**
     * The terms EirReadinessService requires before a contract can be solved.
     *
     * @return list<string>
     */
    private function missingSolverTerms(array $terms): array
    {
        $labels = [
            'origination_date' => 'an origination date',
            'drawn_amount' => 'a disbursed principal',
            'payments_per_year' => 'a recognised repayment frequency (held at the assumed monthly default)',
        ];

        $missing = [];
        foreach ($labels as $field => $label) {
            if (! isset($terms[$field]) || $terms[$field] === 0 || $terms[$field] === 0.0) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** @return array<string,mixed> only the terms whose stored value differs */
    private function changedTerms(object $existing, array $terms): array
    {
        $changes = [];
        foreach ($terms as $field => $value) {
            if (! in_array($field, self::TERM_FIELDS, true)) {
                continue;
            }
            if (! $this->sameValue($existing->{$field} ?? null, $value)) {
                $changes[$field] = $value;
            }
        }

        return $changes;
    }

    /** Compare stored-vs-incoming without treating 100.00 ≠ 100 as a change. */
    private function sameValue($stored, $incoming): bool
    {
        if ($stored === null) {
            return false;
        }
        if (is_float($incoming) || is_int($incoming)) {
            return abs((float) $stored - (float) $incoming) < 0.005;
        }

        // Stored dates may carry a time component depending on the driver.
        $storedText = trim((string) $stored);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $storedText, $m)) {
            $storedText = $m[1];
        }

        return $storedText === trim((string) $incoming);
    }

    /**
     * Origination fees on the master row become PENDING contract_fees. The
     * external id is derived from the contract and fee type so a monthly
     * re-delivery of the same facility does not create a second fee line.
     */
    private function feeRows(array $row, string $contractId): array
    {
        $rows = [];
        foreach (['arrangement_fee' => 'arrangement', 'legal_fees' => 'legal'] as $column => $feeType) {
            $amount = $this->amount($row[$column] ?? null);
            if ($amount === null || abs($amount) < 0.005) {
                continue;
            }

            $rows[] = [
                'contract_id' => $contractId,
                'fee_type' => $feeType,
                'amount' => $amount,
                'description' => ucfirst($feeType) . ' fee from contract master',
                'transaction_date' => $this->date($row['origination_date'] ?? null),
                'currency' => $this->currency($row['currency'] ?? null),
                'source_system' => self::SOURCE,
                'source_reference' => $this->text($row['run_id'] ?? null),
                'external_transaction_id' => self::SOURCE . "|{$contractId}|{$feeType}",
                'gl_account_ref' => $this->text($row['gl_account_code'] ?? null),
            ];
        }

        return $rows;
    }

    private function paymentsPerYear(array $row, string $contractId, array &$unknown): ?int
    {
        $explicit = $this->wholeNumber($row['payments_per_year'] ?? null);
        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }

        $frequency = trim((string) ($row['repayment_frequency'] ?? ''));
        if ($frequency === '') {
            return null;
        }

        $resolved = ContractMasterImport::paymentsPerYear($frequency);
        if ($resolved === null) {
            $key = strtolower($frequency);
            $unknown[$key] = ($unknown[$key] ?? 0) + 1;
        }

        return $resolved;
    }

    private function text($value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' || $text === '-' ? null : $text;
    }

    private function currency($value): ?string
    {
        $code = strtoupper((string) $this->text($value));

        return preg_match('/^[A-Z]{3}$/', $code) ? $code : null;
    }

    private function date($value): ?string
    {
        // MappedFileReader has already resolved dates to Y-m-d; anything else
        // reaching here was unparseable and must not become a term.
        $text = $this->text($value);

        return $text !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    /**
     * Absent and zero must stay distinguishable: a blank cell means "this file
     * does not carry the term" and must not overwrite a stored value, while a
     * genuine 0 is a term in its own right.
     */
    private function amount($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = $this->text($value);
        if ($text === null) {
            return null;
        }

        $cleaned = str_replace([',', ' ', "\xC2\xA0", '"'], '', $text);
        if (preg_match('/^\((.*)\)$/', $cleaned, $m)) {
            $cleaned = '-' . $m[1];
        }

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    /**
     * Rates arrive either as a fraction (0.321) or as a percentage (32.1)
     * depending on whether the operator applied the 'percent' transform.
     * Anything above 1 is read as a percentage — no MAIIC facility carries a
     * contractual rate above 100% per annum.
     */
    private function rate($value): ?float
    {
        $amount = $this->amount($value);
        if ($amount === null || $amount == 0.0) {
            return null;
        }

        return $amount > 1 ? $amount / 100 : $amount;
    }

    private function rateType($value): ?string
    {
        $type = strtoupper((string) $this->text($value));

        return match (true) {
            $type === 'FIXED' => 'FIXED',
            $type === 'FLOATING', $type === 'VARIABLE' => 'FLOATING',
            default => null,
        };
    }

    private function wholeNumber($value): ?int
    {
        $amount = $this->amount($value);

        return $amount === null ? null : (int) round($amount);
    }
}
