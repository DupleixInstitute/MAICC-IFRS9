<?php

namespace App\Services\Eir;

use App\Services\Imports\MappedFileReader;
use InvalidArgumentException;

/**
 * Downloadable column templates for the EIR intake screen.
 *
 * An operator meeting the intake for the first time has to guess two things
 * the screen cannot tell them: which columns a type actually needs, and what
 * a valid value in each one looks like. The mapping screen reports a missing
 * required field only after a file has been uploaded and rejected, which is a
 * slow way to learn that `scheduled_actual_flag` is spelled with underscores
 * or that a fee type outside the accepted vocabulary is folded into `other`.
 *
 * The header row is the canonical internal field name for every required
 * field followed by every optional one, so a file built from this template
 * maps to itself with no manual pass — see MappedFileReader::identityTemplate.
 * The one exception is a field whose own name an alias already claims for a
 * different field: `RATE_BASIS` in a delivered contract master carries
 * Fixed/Variable and is deliberately read as `rate_type`, so emitting a
 * `rate_basis` column here would produce a template with two headers feeding
 * one field. Such a field is withdrawn rather than shipped broken, and the
 * rule is applied by inspecting the alias table rather than by naming the
 * field, so a later alias decision cannot quietly reintroduce the collision.
 *
 * Example rows are illustrative, not loadable: the identifiers use an
 * 8-prefix that matches no MAIIC account and no test fixture, so a template
 * imported unedited creates recognisable junk rather than plausible-looking
 * data that reconciles against nothing.
 */
class EirSampleFileService
{
    /** Identifiers that cannot collide with a real account or the fixture. */
    private const SAMPLE_CONTRACT_A = '800000000001';

    private const SAMPLE_CONTRACT_B = '800000000002';

    /** @return list<string> */
    public function headers(string $importType): array
    {
        $this->assertKnownType($importType);

        $claimed = MappedFileReader::aliasTemplateFor($importType);
        $fields = array_merge(
            MappedFileReader::REQUIRED_FIELDS[$importType],
            MappedFileReader::OPTIONAL_FIELDS[$importType]
        );

        return array_values(array_filter(
            $fields,
            fn ($field) => ($claimed[$field] ?? $field) === $field
        ));
    }

    /**
     * Example rows keyed by field name. A key with no matching header is
     * dropped by rows(), so a field withdrawn for an alias collision does not
     * have to be removed here as well.
     *
     * @return list<array<string,string|int|float>>
     */
    public function examples(string $importType): array
    {
        $this->assertKnownType($importType);

        return match ($importType) {
            'contract_master' => $this->contractMasterExamples(),
            'schedule' => $this->scheduleExamples(),
            'fees' => $this->feeExamples(),
            'contract_transactions' => $this->transactionExamples(),
            'gl_interest' => $this->glInterestExamples(),
        };
    }

    /** @return list<list<string|int|float>> Header row followed by example rows. */
    public function rows(string $importType): array
    {
        $headers = $this->headers($importType);
        $rows = [$headers];

        foreach ($this->examples($importType) as $example) {
            $rows[] = array_map(fn ($field) => $example[$field] ?? '', $headers);
        }

        return $rows;
    }

    /** Named for the type it belongs to, so several downloads stay apart. */
    public function fileName(string $importType): string
    {
        $this->assertKnownType($importType);

        return $importType.'_sample.csv';
    }

    public function csv(string $importType): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($this->rows($importType) as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * A fee-bearing monthly facility and a quarterly one with a moratorium.
     * Between them they carry the terms the readiness gate checks, including
     * the stated payment frequency an assumed one is blocked for.
     */
    private function contractMasterExamples(): array
    {
        return [
            [
                'contract_id' => self::SAMPLE_CONTRACT_A, 'run_id' => 'SAMPLE-RUN', 'customer_id' => 'CUS0001',
                'portfolio' => 'MAIIC', 'sub_account_no' => '01', 'gl_account_code' => '1050102', 'currency' => 'MWK',
                'product_type' => 'TERM LOAN', 'origination_date' => '2024-07-31', 'first_repayment_date' => '2024-08-31',
                'maturity_date' => '2027-11-30', 'approved_amount' => 500000000, 'drawn_amount' => 500000000,
                'contractual_rate' => 28.5, 'rate_type' => 'Fixed', 'source_day_count_basis' => '365',
                'source_compounding' => 'Compound', 'disbursement_tranches' => 1, 'repayment_frequency' => 'Monthly',
                'payments_per_year' => 12, 'tenor_months' => 40, 'moratorium_months' => 0,
                'arrangement_fee' => 12500000, 'legal_fees' => 2400000,
            ],
            [
                'contract_id' => self::SAMPLE_CONTRACT_B, 'run_id' => 'SAMPLE-RUN', 'customer_id' => 'CUS0002',
                'portfolio' => 'FInES', 'sub_account_no' => '01', 'gl_account_code' => '1050102', 'currency' => 'MWK',
                'product_type' => 'TERM LOAN', 'origination_date' => '2024-06-30', 'first_repayment_date' => '2025-01-31',
                'maturity_date' => '2027-06-30', 'approved_amount' => 96000000, 'drawn_amount' => 96000000,
                'contractual_rate' => 34.49, 'rate_type' => 'Variable', 'reference_rate_at_origination' => 22.0,
                'markup' => 12.49, 'source_day_count_basis' => '365', 'source_compounding' => 'Compound',
                'disbursement_tranches' => 1, 'repayment_frequency' => 'Quarterly', 'payments_per_year' => 4,
                'tenor_months' => 36, 'moratorium_months' => 6, 'arrangement_fee' => 4000000, 'legal_fees' => 0,
            ],
        ];
    }

    /**
     * One dated row per instalment. The last row leaves the fee column empty
     * on purpose: a blank fee is a zero fee, not a missing one.
     */
    private function scheduleExamples(): array
    {
        return [
            ['contract_id' => self::SAMPLE_CONTRACT_A, 'due_date' => '2024-08-31',
                'principal_due' => 8214733.51, 'interest_due' => 11875000.00, 'fee_due' => 0],
            ['contract_id' => self::SAMPLE_CONTRACT_A, 'due_date' => '2024-09-30',
                'principal_due' => 8409832.42, 'interest_due' => 11679901.09, 'fee_due' => 0],
            ['contract_id' => self::SAMPLE_CONTRACT_B, 'due_date' => '2025-01-31',
                'principal_due' => 5900000.00, 'interest_due' => 8277600.00, 'fee_due' => ''],
        ];
    }

    /**
     * fee_type must be one of FeeImportService::KNOWN_TYPES; anything else
     * loads as `other` and matches no rule. cashflow_direction decides which
     * side of the initial net investment a line falls on, so a reviewed
     * integral line without one blocks the calculation. source_system with
     * external_transaction_id is what makes a re-import idempotent.
     */
    private function feeExamples(): array
    {
        return [
            [
                'contract_id' => self::SAMPLE_CONTRACT_A, 'fee_type' => 'arrangement', 'amount' => 12500000,
                'description' => 'Arrangement fee on facility', 'transaction_date' => '2024-07-31',
                'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK', 'source_system' => 'EBANKER',
                'source_reference' => 'FEE-0001-1', 'external_transaction_id' => 'FEE-0001-1',
                'basis' => 'ON_DRAWN', 'gl_account_ref' => '4020100',
            ],
            [
                'contract_id' => self::SAMPLE_CONTRACT_A, 'fee_type' => 'legal', 'amount' => 2400000,
                'description' => 'Legal cost - origination and documentation', 'transaction_date' => '2024-07-31',
                'cashflow_direction' => 'PAID', 'currency' => 'MWK', 'source_system' => 'EBANKER',
                'source_reference' => 'FEE-0001-2', 'external_transaction_id' => 'FEE-0001-2',
                'basis' => 'ON_DRAWN', 'gl_account_ref' => '4020100',
            ],
            [
                'contract_id' => self::SAMPLE_CONTRACT_B, 'fee_type' => 'arrangement', 'amount' => 4000000,
                'description' => 'Arrangement fee on facility', 'transaction_date' => '2024-06-30',
                'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK', 'source_system' => 'EBANKER',
                'source_reference' => 'FEE-0002-1', 'external_transaction_id' => 'FEE-0002-1',
                'basis' => 'ON_APPROVED', 'gl_account_ref' => '4020100',
            ],
        ];
    }

    /**
     * Extract B carries both sides: a Scheduled row is the contractual
     * promise, an Actual row is what was collected. A Disbursement is cash
     * advanced and is never netted off a collection.
     */
    private function transactionExamples(): array
    {
        return [
            [
                'customer_id' => 'CUS0001', 'contract_id' => self::SAMPLE_CONTRACT_A, 'sub_account_no' => '01',
                'transaction_date' => '2024-07-31', 'transaction_type' => 'Disbursement',
                'principal_component' => 500000000, 'interest_component' => 0, 'fee_component' => 0,
                'total_amount' => 500000000, 'scheduled_actual_flag' => 'Actual', 'gl_posting_ref' => 'ACT-000001',
                'run_id' => 'SAMPLE-RUN', 'balance_after_transaction' => 500000000,
            ],
            [
                'customer_id' => 'CUS0001', 'contract_id' => self::SAMPLE_CONTRACT_A, 'sub_account_no' => '01',
                'transaction_date' => '2024-08-31', 'transaction_type' => 'Principal+Interest',
                'principal_component' => 8214733.51, 'interest_component' => 11875000.00, 'fee_component' => 0,
                'total_amount' => 20089733.51, 'scheduled_actual_flag' => 'Scheduled', 'gl_posting_ref' => 'SCH-000001',
                'run_id' => 'SAMPLE-RUN',
            ],
            [
                'customer_id' => 'CUS0001', 'contract_id' => self::SAMPLE_CONTRACT_A, 'sub_account_no' => '01',
                'transaction_date' => '2024-08-31', 'transaction_type' => 'Principal+Interest',
                'principal_component' => 8214733.51, 'interest_component' => 11875000.00, 'fee_component' => 0,
                'total_amount' => 20089733.51, 'scheduled_actual_flag' => 'Actual', 'gl_posting_ref' => 'ACT-000002',
                'run_id' => 'SAMPLE-RUN', 'balance_after_transaction' => 491785266.49,
            ],
        ];
    }

    /**
     * One row per loan per month. period_type separates a monthly figure from
     * a year-to-date one; an annual summary row is excluded at import rather
     * than double-counted against the months inside it.
     */
    private function glInterestExamples(): array
    {
        return [
            [
                'contract_id' => self::SAMPLE_CONTRACT_A, 'period_year' => 2025, 'period_month' => 1,
                'interest_income_posted' => 11618000.00, 'run_id' => 'SAMPLE-RUN', 'gl_account_code' => '4010100',
                'period_type' => 'MONTH', 'reporting_period' => '2025-01', 'transaction_count' => 1,
                'posting_references' => 'GL-2025-01-0001', 'generated_on' => '2025-02-05',
            ],
            [
                'contract_id' => self::SAMPLE_CONTRACT_A, 'period_year' => 2025, 'period_month' => 2,
                'interest_income_posted' => 11421000.00, 'run_id' => 'SAMPLE-RUN', 'gl_account_code' => '4010100',
                'period_type' => 'MONTH', 'reporting_period' => '2025-02', 'transaction_count' => 1,
                'posting_references' => 'GL-2025-02-0001', 'generated_on' => '2025-03-05',
            ],
            [
                'contract_id' => self::SAMPLE_CONTRACT_B, 'period_year' => 2025, 'period_month' => 1,
                'interest_income_posted' => 2759200.00, 'run_id' => 'SAMPLE-RUN', 'gl_account_code' => '4010100',
                'period_type' => 'MONTH', 'reporting_period' => '2025-01', 'transaction_count' => 1,
                'posting_references' => 'GL-2025-01-0002', 'generated_on' => '2025-02-05',
            ],
        ];
    }

    private function assertKnownType(string $importType): void
    {
        if (! isset(MappedFileReader::REQUIRED_FIELDS[$importType])) {
            throw new InvalidArgumentException(
                "Unknown import type '{$importType}' — known: "
                .implode(', ', array_keys(MappedFileReader::REQUIRED_FIELDS))
            );
        }
    }
}
