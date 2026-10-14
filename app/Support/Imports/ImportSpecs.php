<?php

namespace App\Support\Imports;

use App\Services\Eir\EirSampleFileService;
use App\Services\Eir\FeeImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * The one column-spec registry of every file import (13 October 2026).
 *
 * Each import screen, its downloadable sample file, its column guide and the
 * server-side check that runs before anything is written read the same entry
 * here, so the screen, the template and the validation cannot drift apart.
 * The columns are the ones each importer actually reads (named in the entry's
 * comment); nothing is listed that an importer ignores.
 *
 * An entry is an array:
 *  - key, title, summary, steps (numbered instructions), notes
 *  - format: csv | tsv | xlsx | zip | workbooks (what is uploaded)
 *  - template: csv | tsv | xlsx-layout | manifest | none (what Download sample file gives)
 *  - headers: slug | normalize | lower | exact | positional (how the importer reads headings)
 *  - columns: list of column specs (see col())
 *  - unique: list of column-key sets that must not repeat within the file
 *  - files: for machine-generated packs, the files or sheets expected
 *  - sample_rows: illustrative rows keyed by column name
 *  - checkable: the generic check endpoint can test a file without writing
 */
class ImportSpecs
{
    /** Formats every day-first date reader in the system accepts. */
    public const DATE_FORMATS_TAPE = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s', 'm/d/Y H:i', 'm/d/Y H:i:s'];

    /** What MappedFileReader resolves (an Excel date cell is accepted as well). */
    public const DATE_FORMATS_EIR = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y', 'd-M-Y', 'd-M-y', 'j-M-Y', 'j-M-y', 'd M Y', 'j M Y'];

    /** The EIR intake types, by spec key. */
    public const EIR_KEYS = [
        'eir-contract-master' => 'contract_master',
        'eir-schedule' => 'schedule',
        'eir-fees' => 'fees',
        'eir-contract-transactions' => 'contract_transactions',
        'eir-gl-interest' => 'gl_interest',
        'eir-reference-rates' => 'reference_rates',
        'eir-disbursements' => 'disbursements',
    ];

    /** @return list<string> every registered key */
    public static function keys(): array
    {
        return array_merge([
            'clients', 'loan-book', 'loan-book-ebanker', 'collateral', 'credit-loss-data',
        ], array_keys(self::EIR_KEYS), [
            'trial-balance', 'takeon', 'ebanker-pack', 'sp-grades',
            'sicr-groups', 'sicr-items', 'macro-series', 'rbm-policy-rate', 'imf-weo',
        ]);
    }

    public static function has(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function eirKey(string $importType): string
    {
        $key = array_search($importType, self::EIR_KEYS, true);
        if ($key === false) {
            throw new InvalidArgumentException("Unknown EIR import type '{$importType}'.");
        }

        return $key;
    }

    /** One entry, with every column's normalised key filled in. */
    public static function get(string $key): array
    {
        if (! self::has($key)) {
            throw new InvalidArgumentException("No import spec named '{$key}'.");
        }

        $spec = isset(self::EIR_KEYS[$key]) ? self::eir(self::EIR_KEYS[$key]) : match ($key) {
            'clients' => self::clients(),
            'loan-book' => self::loanBook(),
            'loan-book-ebanker' => self::loanBookEbanker(),
            'collateral' => self::collateral(),
            'credit-loss-data' => self::creditLossData(),
            'trial-balance' => self::trialBalance(),
            'takeon' => self::takeon(),
            'ebanker-pack' => self::ebankerPack(),
            'sp-grades' => self::spGrades(),
            'sicr-groups' => self::sicrGroups(),
            'sicr-items' => self::sicrItems(),
            'macro-series' => self::macroSeries(),
            'rbm-policy-rate' => self::rbmPolicyRate(),
            'imf-weo' => self::imfWeo(),
        };

        $spec += ['key' => $key, 'notes' => [], 'unique' => [], 'files' => [], 'sample_rows' => [], 'checkable' => false, 'headers' => 'slug', 'template' => 'csv', 'unique_severity' => 'error'];
        $spec['key'] = $key;
        foreach ($spec['columns'] ?? [] as $i => $c) {
            $spec['columns'][$i]['key'] = $c['key'] ?? self::normaliseHeader($c['name'], $spec['headers']);
            $spec['columns'][$i]['aliases_keys'] = array_values(array_unique(array_map(fn ($a) => self::normaliseHeader($a, $spec['headers']), $c['aliases'] ?? [])));
        }
        $spec['columns'] ??= [];

        return $spec;
    }

    /** A heading as the importer of a spec reads it. */
    public static function normaliseHeader(string $header, string $style): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return match ($style) {
            // Laravel Excel's heading row formatter (config/excel.php: slug)
            'slug' => \Illuminate\Support\Str::slug($header, '_'),
            'normalize' => MappedFileReader::normalizeHeader($header),
            'lower' => strtolower(trim($header)),
            default => trim($header),
        };
    }

    /**
     * One column.
     *
     * Types: text, code (an identifier, kept as text), integer, decimal,
     * percent (28.5 means 28.5%), fraction (0 to 1), date, month (YYYY-MM),
     * boolean, enum.
     *
     * Extra keys: aliases, allowed, strict (enum: false = a warning, the value
     * is still loaded), formats (date), serial (an Excel date cell is
     * accepted), min, max, min_exclusive, max_exclusive, parens (accounting
     * negatives), blank_ok (the heading is required, a blank cell is not an
     * error), pattern + pattern_hint, severity (warning: a type failure does
     * not refuse the file), position (positional layouts).
     */
    public static function col(string $name, string $type, bool $required, string $description, $example = null, array $extra = []): array
    {
        return ['name' => $name, 'type' => $type, 'required' => $required, 'description' => $description, 'example' => $example] + $extra;
    }

    /** The human label of a column's type and format, for screens and guides. */
    public static function typeLabel(array $c): string
    {
        return match ($c['type']) {
            'text' => 'Text',
            'code' => 'Text (identifier)',
            'integer' => 'Whole number',
            'decimal' => 'Number',
            'percent' => 'Percentage',
            'fraction' => 'Decimal fraction',
            'date' => 'Date',
            'month' => 'Month',
            'boolean' => 'Yes / no',
            'enum' => 'One of a list',
            default => ucfirst($c['type']),
        };
    }

    /** Format and allowed-values text of a column. */
    public static function formatText(array $c): string
    {
        $parts = [];
        switch ($c['type']) {
            case 'code':
                $parts[] = 'keep as text so leading zeros stay; not in scientific notation';
                break;
            case 'integer':
                $parts[] = 'digits only, e.g. 12';
                break;
            case 'decimal':
                $parts[] = 'digits with a decimal point; thousands commas allowed' . (! empty($c['parens']) ? '; (1,234.50) is read as negative' : '; write negatives with a minus sign');
                break;
            case 'percent':
                $parts[] = 'a percentage written as a number, e.g. 28.5 for 28.5%; a % sign is allowed';
                break;
            case 'fraction':
                $parts[] = 'a decimal between 0 and 1, e.g. 0.045 for 4.5%';
                break;
            case 'date':
                $formats = $c['formats'] ?? ['Y-m-d'];
                $parts[] = 'YYYY-MM-DD preferred' . (count($formats) > 1 ? '; also ' . implode(', ', array_map([self::class, 'humanFormat'], array_slice($formats, 1, 4))) : ' only');
                if (! empty($c['serial'])) {
                    $parts[] = 'an Excel date cell is accepted';
                }
                break;
            case 'month':
                $parts[] = 'YYYY-MM, e.g. 2025-12';
                break;
            case 'boolean':
                $parts[] = 'yes / no, true / false or 1 / 0';
                break;
        }
        if (! empty($c['allowed'])) {
            $parts[] = 'one of: ' . implode(', ', $c['allowed']) . (($c['strict'] ?? true) ? '' : ' (another value is loaded with a warning)');
        }
        if (isset($c['min']) || isset($c['max'])) {
            $min = $c['min'] ?? null;
            $max = $c['max'] ?? null;
            $parts[] = match (true) {
                $min !== null && $max !== null => 'from ' . $min . ' to ' . $max,
                $min !== null => (! empty($c['min_exclusive']) ? 'above ' : 'at least ') . $min,
                default => (! empty($c['max_exclusive']) ? 'below ' : 'at most ') . $max,
            };
        }
        if (! empty($c['pattern_hint'])) {
            $parts[] = $c['pattern_hint'];
        }
        if (! empty($c['aliases'])) {
            $parts[] = 'also read under: ' . implode(', ', $c['aliases']);
        }

        return implode('; ', $parts);
    }

    public static function humanFormat(string $format): string
    {
        return strtr($format, ['Y' => 'YYYY', 'y' => 'YY', 'm' => 'MM', 'n' => 'M', 'd' => 'DD', 'j' => 'D', 'M' => 'Mon', 'H' => 'hh', 'i' => 'mm', 's' => 'ss']);
    }

    /** The payload the import screens read (no closures, plain arrays). */
    public static function forScreen(string $key): array
    {
        $s = self::get($key);

        return [
            'key' => $s['key'], 'title' => $s['title'], 'summary' => $s['summary'], 'steps' => $s['steps'], 'notes' => $s['notes'],
            'format' => $s['format'], 'template' => $s['template'], 'headers' => $s['headers'], 'checkable' => (bool) $s['checkable'],
            'required_count' => count(array_filter($s['columns'], fn ($c) => $c['required'])) + count(array_filter($s['files'], fn ($f) => $f['required'] ?? false)),
            'columns' => array_map(fn ($c) => [
                'name' => $c['name'], 'type' => self::typeLabel($c), 'required' => (bool) $c['required'], 'format' => self::formatText($c),
                'description' => $c['description'], 'example' => $c['example'] === null ? '' : (string) $c['example'], 'position' => $c['position'] ?? null,
            ], $s['columns']),
            'files' => $s['files'],
            'unique' => $s['unique'],
            'template_name' => ImportTemplateBuilder::templateFileName($s),
            'guide_name' => ImportTemplateBuilder::guideFileName($s),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Customer and loan data                                             */
    /* ------------------------------------------------------------------ */

    /** ClientsImport, standard layout: customer_id and name (headings slugged). */
    private static function clients(): array
    {
        return [
            'title' => 'Clients',
            'summary' => 'One row per client: the E-Banker customer number and the client name. A client already held is updated, not duplicated.',
            'format' => 'csv', 'accept' => '.csv,.txt,.xlsx,.xls', 'checkable' => true,
            'steps' => [
                'Download the sample file and keep its two column headings.',
                'Fill one row per client. customer_id is the E-Banker customer number, written as text so leading zeros stay.',
                'Choose the file and press Check file: every problem is listed by row before anything is saved.',
                'Start the import. A customer_id that already exists is updated with the new name.',
            ],
            'notes' => ['To load a file with other headings, use Match columns and match a column to customer_id; the same checks then run on the matched columns.'],
            'columns' => [
                self::col('customer_id', 'code', true, 'E-Banker customer number. Each customer_id once in the file.', '00012345', ['aliases' => ['Customer ID']]),
                self::col('name', 'text', false, 'Client name as it should appear on the reports.', 'Sample Farms Ltd'),
            ],
            'unique' => [['customer_id']],
            'sample_rows' => [
                ['customer_id' => '80000001', 'name' => 'Sample Farms Ltd'],
                ['customer_id' => '80000002', 'name' => 'Sample Traders'],
            ],
        ];
    }

    /**
     * LoanBooksImport, standard layout: the direct field mapping it reads
     * after normalising the headings (lower case, spaces and dashes to
     * underscores). The portfolio and the period are chosen on the screen.
     */
    private static function loanBook(): array
    {
        $amount = fn (string $name, string $d, $ex) => self::col($name, 'decimal', false, $d, $ex);

        return [
            'title' => 'Loan book (standard layout)',
            'summary' => 'One row per loan at the month-end chosen on the screen. A loan is keyed by customer_id and contract_id within the portfolio and period, so loading the same month again updates it.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'checkable' => true,
            'steps' => [
                'Choose the portfolio and the month-end the file reports.',
                'Download the sample file and keep its column headings. Columns may be in any order; other columns are ignored.',
                'Each row needs customer_id, contract_id, value_date and maturity_date. Amounts may carry thousands commas.',
                'Press Check file: every problem is listed by row and column, and nothing is saved until the file is clean.',
                'Start the import, then follow it on the History tab.',
            ],
            'notes' => [
                'The arrears columns are the amounts overdue in each band; the stage is set from the highest band that holds an amount.',
                'If principal is 0 or blank and carrying_amount is above 0, the carrying amount is used as the principal.',
            ],
            'columns' => [
                self::col('customer_id', 'code', true, 'E-Banker customer number. The client is created if new.', '00012345'),
                self::col('contract_id', 'code', true, 'Loan account number. Each customer_id and contract_id pair once in the file.', '104450000053'),
                self::col('name', 'text', false, 'Customer name. Blank is loaded as Unknown.', 'Sample Farms Ltd', ['aliases' => ['customer_name']]),
                self::col('type', 'text', false, 'Product group of the loan, as the loan book report names it.', 'TERM LOAN'),
                self::col('industry_code', 'code', false, 'Sector code of the borrower, as set up on Sector Types.', 'AGR'),
                self::col('industry_type', 'text', false, 'Sector name of the borrower.', 'Agriculture'),
                self::col('internal_grade', 'code', false, 'Internal credit grade code, as held on Internal Grades.', '4', ['aliases' => ['internal_grade_code']]),
                self::col('value_date', 'date', true, 'Date the loan was disbursed (value date).', '2024-07-31', ['formats' => self::DATE_FORMATS_TAPE]),
                self::col('maturity_date', 'date', true, 'Contractual maturity date.', '2027-11-30', ['formats' => self::DATE_FORMATS_TAPE]),
                self::col('tenor', 'integer', false, 'Contractual tenor in months.', 40, ['min' => 0]),
                self::col('interest_rate', 'percent', false, 'Annual contractual rate as a percentage.', 28.5, ['min' => 0, 'max' => 100]),
                $amount('approved_amount', 'Amount approved (sanctioned). The undrawn commitment is approved less disbursed.', 500000000),
                $amount('disbursed', 'Amount disbursed to date.', 500000000),
                $amount('principal', 'Principal balance at the month-end.', 412500000.00),
                $amount('carrying_amount', 'Carrying amount at the month-end (principal plus accrued interest).', 418230114.25),
                $amount('repayments', 'Cumulative repayments to date, as the report counts them.', 87500000),
                $amount('1-30 Days', 'Amount 1 to 30 days past due.', 0),
                $amount('31-90 Days', 'Amount 31 to 90 days past due.', 0),
                $amount('91-180 Days', 'Amount 91 to 180 days past due.', 0),
                $amount('181-270 Days', 'Amount 181 to 270 days past due.', 0),
            ],
            'unique' => [['customer_id', 'contract_id']],
            'sample_rows' => [
                ['customer_id' => '80000001', 'contract_id' => '800000000001', 'name' => 'Sample Farms Ltd', 'type' => 'TERM LOAN', 'industry_code' => 'AGR', 'industry_type' => 'Agriculture', 'internal_grade' => '4',
                    'value_date' => '2024-07-31', 'maturity_date' => '2027-11-30', 'tenor' => 40, 'interest_rate' => 28.5, 'approved_amount' => 500000000, 'disbursed' => 500000000,
                    'principal' => 412500000, 'carrying_amount' => 418230114.25, 'repayments' => 87500000, '1-30 Days' => 0, '31-90 Days' => 0, '91-180 Days' => 0, '181-270 Days' => 0],
                ['customer_id' => '80000002', 'contract_id' => '800000000002', 'name' => 'Sample Traders', 'type' => 'WORKING CAPITAL', 'industry_code' => 'TRD', 'industry_type' => 'Trade', 'internal_grade' => '6',
                    'value_date' => '2025-01-15', 'maturity_date' => '2026-01-15', 'tenor' => 12, 'interest_rate' => 34.49, 'approved_amount' => 96000000, 'disbursed' => 80000000,
                    'principal' => 52000000, 'carrying_amount' => 53110000, 'repayments' => 28000000, '1-30 Days' => 0, '31-90 Days' => 4500000, '91-180 Days' => 0, '181-270 Days' => 0],
            ],
        ];
    }

    /**
     * ProcessLoanImportJob: the E-Banker loan book report as exported. It is
     * read by position, not by heading: a "Loan Type :" row opens each product
     * group, header rows are skipped, and a data row starts with its serial
     * number. Two shapes exist; in the second the report leaves column A
     * blank and every field moves one column right.
     */
    private static function loanBookEbanker(): array
    {
        $p = fn (string $letter, string $name, string $type, bool $req, string $d, $ex, array $extra = []) => self::col($name, $type, $req, $d, $ex, ['position' => $letter] + $extra);

        return [
            'title' => 'Loan book (E-Banker report)',
            'summary' => 'The E-Banker loan book report saved as CSV, exactly as the core banking system exports it. It is read by column position, so do not add, remove or move columns.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'headers' => 'positional', 'template' => 'csv', 'checkable' => true,
            'steps' => [
                'Run the loan book report in E-Banker for the month-end and save it as CSV. Do not edit or re-order its columns.',
                'Each product group starts with a row such as "Loan Type : 01 - TERM LOAN"; keep those rows, they set the product group of the loans below them.',
                'Choose the portfolio and the month-end, then press Check file. Rows whose dates or amounts cannot be read are listed by row.',
                'Start the import. The sample file shows the layout: one Loan Type row, the header row and an illustrative row.',
            ],
            'notes' => [
                'Positions below are for the report with the serial number in column A. When the report leaves column A blank, every field is one column to the right; both shapes are read.',
                'A row without a serial number in its first two cells is not a loan and is skipped.',
            ],
            'columns' => [
                $p('A', 'Sr.', 'integer', true, 'Serial number. A row starting with a number is a loan.', 1),
                $p('B', 'Customer ID', 'code', true, 'E-Banker customer number.', '00012345'),
                $p('D', 'Loan account', 'code', true, 'Loan account number (contract id).', '104450000053'),
                $p('E', 'Name', 'text', false, 'Customer name.', 'Sample Farms Ltd'),
                $p('F', 'Value date', 'date', true, 'Disbursement (value) date, DD/MM/YYYY.', '31/07/2024', ['formats' => ['d/m/Y', 'Y-m-d']]),
                $p('G', 'Maturity', 'date', true, 'Maturity date, DD/MM/YYYY.', '30/11/2027', ['formats' => ['d/m/Y', 'Y-m-d']]),
                $p('J', 'Interest', 'percent', false, 'Annual contractual rate as a percentage.', 28.5),
                $p('N', 'Principal', 'decimal', false, 'Principal balance.', '412,500,000.00'),
                $p('T', 'Carrying', 'decimal', false, 'Carrying amount.', '418,230,114.25'),
                $p('Y', '1 - 30 Days', 'decimal', false, 'Amount 1 to 30 days past due.', '0.00'),
                $p('Z', '31 - 90 Days', 'decimal', false, 'Amount 31 to 90 days past due.', '0.00'),
                $p('AA', '91 - 180 Days', 'decimal', false, 'Amount 91 to 180 days past due.', '0.00'),
                $p('AC', '181 - 270 Days', 'decimal', false, 'Amount 181 to 270 days past due.', '0.00'),
            ],
        ];
    }

    /** CollateralRegisterImport, standard layout (headings normalised like the loan book). */
    private static function collateral(): array
    {
        $types = self::tableValues('collateral_types', 'type_code');

        return [
            'title' => 'Collateral register',
            'summary' => 'One row per item of collateral for the month-end chosen on the screen, with its values. An item is keyed by customer_id, collateral_type and registration_date, so a re-load updates it.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'checkable' => true,
            'steps' => [
                'Choose the month-end the register is for.',
                'Download the sample file and keep its column headings.',
                'collateral_type must be a code listed on the Collateral Types tab' . ($types !== [] ? ' (' . implode(', ', array_slice($types, 0, 8)) . (count($types) > 8 ? ', ...' : '') . ').' : '. Add the types there first.'),
                'Press Check file: every problem is listed by row before anything is saved.',
                'Start the import, then run the allocation on the Collateral Allocation tab.',
            ],
            'notes' => ['A blank registration_date takes the first day of the chosen month. A blank status is loaded as ACTIVE.'],
            'columns' => [
                self::col('customer_id', 'code', true, 'E-Banker customer number of the owner of the loan the collateral secures.', '00012345'),
                self::col('customer_name', 'text', false, 'Customer name. Blank is loaded as Unknown.', 'Sample Farms Ltd', ['aliases' => ['name']]),
                self::col('collateral_type', 'enum', true, 'Collateral type code, as held on Collateral Types.', $types[0] ?? 'PROPERTY', ['allowed' => $types, 'allowed_source' => 'collateral_types']),
                self::col('property_use', 'text', false, 'Use of the property, e.g. Residential or Commercial.', 'Commercial'),
                self::col('description', 'text', false, 'Description of the item.', 'Warehouse on plot 12/345'),
                self::col('location', 'text', false, 'Where the item is.', 'Lilongwe'),
                self::col('registration_date', 'date', false, 'Date the charge was registered.', '2024-07-31', ['formats' => self::DATE_FORMATS_TAPE]),
                self::col('expiry_date', 'date', false, 'Date the charge or insurance expires.', '2029-07-31', ['formats' => self::DATE_FORMATS_TAPE]),
                self::col('valuation_date', 'date', false, 'Date of the latest valuation.', '2025-06-30', ['formats' => self::DATE_FORMATS_TAPE]),
                self::col('nominal_value', 'decimal', false, 'Nominal (face) value.', 650000000, ['min' => 0]),
                self::col('market_value', 'decimal', false, 'Open market value at the valuation date.', 600000000, ['min' => 0]),
                self::col('execution_value', 'decimal', false, 'Forced-sale (execution) value used in the LGD.', 420000000, ['min' => 0]),
                self::col('status', 'text', false, 'Status of the item; stored in capitals.', 'ACTIVE'),
            ],
            'unique' => [['customer_id', 'collateral_type', 'registration_date']],
            'sample_rows' => [
                ['customer_id' => '80000001', 'customer_name' => 'Sample Farms Ltd', 'collateral_type' => $types[0] ?? 'PROPERTY', 'property_use' => 'Commercial', 'description' => 'Warehouse on plot 12/345', 'location' => 'Lilongwe',
                    'registration_date' => '2024-07-31', 'expiry_date' => '2029-07-31', 'valuation_date' => '2025-06-30', 'nominal_value' => 650000000, 'market_value' => 600000000, 'execution_value' => 420000000, 'status' => 'ACTIVE'],
                ['customer_id' => '80000002', 'customer_name' => 'Sample Traders', 'collateral_type' => $types[1] ?? ($types[0] ?? 'VEHICLE'), 'property_use' => '', 'description' => 'Delivery truck', 'location' => 'Blantyre',
                    'registration_date' => '2025-01-15', 'expiry_date' => '', 'valuation_date' => '2025-01-10', 'nominal_value' => 85000000, 'market_value' => 70000000, 'execution_value' => 49000000, 'status' => 'ACTIVE'],
            ],
        ];
    }

    /**
     * CreditLossDataImport: period, one column per credit loss definition
     * (any alias the importer accepts, slugged), source, notes, published_at.
     * The value rules are the importer's own (getValidationRules).
     */
    private static function creditLossData(): array
    {
        $aliases = [];
        try {
            $aliases = \App\Http\Controllers\ImportSampleController::creditLossAliases();
        } catch (Throwable) {
            $aliases = [];
        }
        $names = [];
        try {
            $names = DB::table('macro_credit_loss_definitions')->pluck('name', 'code')->all();
        } catch (Throwable) {
        }
        $measures = [];
        foreach ($aliases as $code => $list) {
            $first = collect($list)->first(fn ($a) => ! str_contains($a, ' ')) ?? strtolower($code);
            // only the aliases a slugged heading can equal: the importer compares slugged headings
            $others = array_values(array_filter($list, fn ($a) => $a !== $first && \Illuminate\Support\Str::slug($a, '_') === $a));
            $label = $names[$code] ?? $code;
            $measures[] = match ($code) {
                'PD', 'LGD' => self::col($first, 'fraction', false, "{$label} ({$code}) for the period.", $code === 'PD' ? 0.045 : 0.38, ['aliases' => $others, 'min' => 0, 'max' => 1, 'measure' => $code]),
                'STAGE' => self::col($first, 'enum', false, "{$label} ({$code}): the IFRS 9 stage.", 1, ['aliases' => $others, 'allowed' => ['1', '2', '3'], 'measure' => $code]),
                'CREDIT_RATING' => self::col($first, 'text', false, "{$label} ({$code}).", 'B+', ['aliases' => $others, 'measure' => $code]),
                'ECL', 'NPL', 'EAD' => self::col($first, 'decimal', false, "{$label} ({$code}) for the period.", $code === 'NPL' ? 0.082 : 1250000000, ['aliases' => $others, 'measure' => $code]),
                default => self::col($first, 'text', false, "{$label} ({$code}).", null, ['aliases' => $others, 'measure' => $code]),
            };
        }

        $sample = [];
        foreach (['2025-10-31', '2025-11-30'] as $i => $period) {
            $row = ['period' => $period, 'source' => 'Sample source', 'notes' => '', 'published_at' => ''];
            foreach ($measures as $m) {
                $row[$m['name']] = $m['example'];
            }
            $sample[] = $row;
        }

        return [
            'title' => 'Credit loss data',
            'summary' => 'The historical loss measures of one portfolio, one row per period. Columns are read by name; a value already held for the same portfolio, measure and period is replaced.',
            'format' => 'csv', 'accept' => '.csv,.txt,.xlsx', 'checkable' => true,
            'steps' => [
                'Download the sample file. It has a period column, one column per measure (' . implode(', ', array_keys($aliases)) . '), and the optional source, notes and published_at.',
                'Fill one row per period. Keep the sample\'s headings; other columns are ignored.',
                'PD and LGD are fractions between 0 and 1, and the stage is 1, 2 or 3.',
                'Choose the portfolio and the file, then press Check file. Every problem is listed by row before anything is saved.',
                'Start the import. Each upload is logged under its file name on the Imports screen.',
            ],
            'notes' => ['In an Excel file, write the period as text (YYYY-MM-DD); a date cell is not read by this import.'],
            'columns' => array_merge(
                [self::col('period', 'date', true, 'The period the figures are for (a month-end date). One row per period.', '2025-12-31', ['formats' => self::DATE_FORMATS_TAPE])],
                $measures,
                [
                    self::col('source', 'text', false, 'Where the figures come from. Blank is recorded as CSV Import.', 'Finance ECL workbook'),
                    self::col('notes', 'text', false, 'Any note on the row.', ''),
                    self::col('published_at', 'date', false, 'Date the publisher released the figures.', '', ['formats' => self::DATE_FORMATS_TAPE, 'aliases' => ['release_date', 'published']]),
                ]
            ),
            'require_one_of' => array_map(fn ($m) => $m['name'], $measures),
            'unique' => [['period']],
            'sample_rows' => $sample,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* EIR intake                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * MappedFileReader: the canonical field names (REQUIRED_FIELDS and
     * OPTIONAL_FIELDS), in the order EirSampleFileService writes the sample.
     */
    private static function eir(string $type): array
    {
        $samples = new EirSampleFileService();
        $required = MappedFileReader::REQUIRED_FIELDS[$type];
        $valueRequired = self::EIR_VALUE_REQUIRED[$type] ?? [];
        $columns = [];
        foreach ($samples->headers($type) as $field) {
            $meta = self::eirField($field, $type);
            $isRequired = in_array($field, $required, true);
            $columns[] = self::col($field, $meta['type'], $isRequired, $meta['description'], null, array_diff_key($meta, ['type' => 1, 'description' => 1])
                + ($isRequired && ! in_array($field, $valueRequired, true) ? ['blank_ok' => true] : []));
        }
        $examples = $samples->examples($type);
        foreach ($columns as $i => $c) {
            $columns[$i]['example'] = $examples[0][$c['name']] ?? null;
        }

        $label = self::EIR_TITLES[$type];

        return [
            'title' => 'EIR intake: ' . $label[0],
            'summary' => $label[1],
            'format' => 'csv', 'accept' => '.csv,.txt,.xlsx,.xls,.ods', 'headers' => 'normalize', 'template' => 'csv',
            'checkable' => false, 'import_type' => $type, 'unique_severity' => 'warning',
            'steps' => array_merge([
                'Download the sample file of this type. A file with the same headings maps to itself with no column matching.',
                'The required columns must be present; columns in another file can be matched on the mapping screen.',
            ], self::EIR_STEPS[$type] ?? [], [
                'Choose the file and press Import file. The file is checked first: if any row breaks a rule below, nothing is queued and every problem is listed by row.',
            ]),
            'notes' => ['Headings are matched without regard to case, spaces or punctuation: "Due Date" reads as due_date.'],
            'columns' => $columns,
            'unique' => self::EIR_UNIQUE[$type] ?? [],
            'sample_rows' => $examples,
        ];
    }

    private const EIR_TITLES = [
        'contract_master' => ['contract master (Extract A)', 'One row per loan account with its contractual terms. Only the account number is required; each term found is stored and a term left blank does not overwrite one held.'],
        'schedule' => ['repayment schedules', 'One dated row per instalment of a contract: principal and interest due, and the fee due where there is one.'],
        'fees' => ['fees', 'One row per fee charged on a contract. Fees are matched to the fee classification rules and wait for review before they enter the EIR.'],
        'contract_transactions' => ['contract transactions (Extract B)', 'Scheduled and actual cash flows per loan account. Scheduled rows become the remaining schedule; actual rows are kept as evidence.'],
        'gl_interest' => ['GL interest (Extract C)', 'The interest income the ledger posted per loan per month, for the GL reconciliation.'],
        'reference_rates' => ['reference rates (File C)', 'The dated reference-rate series (PLR) that floating loans reprice from.'],
        'disbursements' => ['drawdowns', 'One row per tranche paid out: the facility, the date and the amount.'],
    ];

    private const EIR_STEPS = [
        'contract_transactions' => ['scheduled_actual_flag is Scheduled or Actual. Rows marked Scheduled are kept as the remaining schedule; rows marked Actual are kept as transaction evidence.'],
        'fees' => ['fee_type is one of arrangement, legal, appraisal, default, levy or other; another value loads as other. cashflow_direction is RECEIVED or PAID.'],
        'reference_rates' => ['Write dates year first (YYYY-MM-DD); dates must rise down the file. Rates are percentages between 1 and 100, such as 25.30.'],
        'disbursements' => ['One row per tranche, dates written year first (YYYY-MM-DD). Every amount must be above zero.'],
        'gl_interest' => ['period_month 1 to 12 is a month; 0 marks an annual summary row, which is kept out of the reconciliation.'],
    ];

    /** Fields whose cell must hold a value on every row (the services skip or refuse a blank). */
    private const EIR_VALUE_REQUIRED = [
        'contract_master' => ['contract_id'],
        'schedule' => ['contract_id', 'due_date'],
        'fees' => ['contract_id', 'amount'],
        'contract_transactions' => ['contract_id', 'transaction_date', 'scheduled_actual_flag'],
        'gl_interest' => ['contract_id', 'period_year', 'period_month'],
        'reference_rates' => ['effective_date', 'rate'],
        'disbursements' => ['contract_id', 'disbursement_date', 'amount'],
    ];

    /** Repeats the services count and skip; reported as warnings. */
    private const EIR_UNIQUE = [
        'contract_master' => [['contract_id']],
        'schedule' => [['contract_id', 'due_date']],
        'gl_interest' => [['contract_id', 'period_year', 'period_month']],
        'reference_rates' => [['effective_date']],
    ];

    /** Type, description and rules of an EIR field. */
    private static function eirField(string $field, string $type): array
    {
        $date = fn (string $d) => ['type' => 'date', 'description' => $d, 'formats' => self::DATE_FORMATS_EIR, 'serial' => true];
        $isoDate = fn (string $d) => ['type' => 'date', 'description' => $d, 'formats' => ['Y-m-d'], 'serial' => true];
        $amount = fn (string $d, array $x = []) => ['type' => 'decimal', 'description' => $d, 'parens' => true] + $x;
        $int = fn (string $d, array $x = []) => ['type' => 'integer', 'description' => $d] + $x;
        $code1 = fn (string $d) => ['type' => 'code', 'description' => $d . ' A one-letter E-Banker code; a longer value is read by its leading letter.'];

        return match ($field) {
            'contract_id' => ['type' => 'code', 'description' => 'Loan account number as E-Banker writes it. Leading zeros and padding are normalised.'],
            'run_id' => ['type' => 'text', 'description' => 'Identifier of the extract run, kept for lineage.'],
            'customer_id' => ['type' => 'code', 'description' => 'E-Banker customer number.'],
            'customer_name' => ['type' => 'text', 'description' => 'Customer name.'],
            'portfolio' => ['type' => 'text', 'description' => 'Portfolio the loan belongs to, e.g. MAIIC or FInES.'],
            'sub_account_no' => ['type' => 'code', 'description' => 'Sub-account number of the facility.'],
            'gl_account_code' => ['type' => 'code', 'description' => 'GL account the loan or posting sits on.'],
            'currency' => ['type' => 'text', 'description' => 'Currency of the facility.', 'pattern' => '/^[A-Za-z]{3}$/', 'pattern_hint' => 'a three-letter code such as MWK'],
            'product_type' => ['type' => 'text', 'description' => 'Product type, e.g. TERM LOAN.'],
            'origination_date' => $date('Date the facility was first drawn.'),
            'first_repayment_date' => $date('Date of the first repayment.'),
            'maturity_date' => $date('Contractual maturity date.'),
            'closure_date' => $date('Date the account closed, if closed.'),
            'last_restructure_date' => $date('Date of the latest restructure, if any.'),
            'opening_amortised_cost_date' => $date('Date the opening amortised cost is stated at.'),
            'interest_start_date' => $date('Date interest starts to run.'),
            'first_instalment_date' => $date('Date of the first instalment.'),
            'approved_amount' => $amount('Amount approved.'),
            'drawn_amount' => $amount('Amount disbursed (the principal the EIR is solved on).'),
            'arrangement_fee' => $amount('Arrangement fee charged at origination.'),
            'legal_fees' => $amount('Legal fees charged at origination.'),
            'opening_amortised_cost' => $amount('Opening amortised cost at its date.'),
            'contractual_rate' => ['type' => 'percent', 'description' => 'Annual contractual rate as a percentage (a value of 1 or less is read as a fraction).', 'min' => 0, 'max' => 100],
            'reference_rate_at_origination' => ['type' => 'percent', 'description' => 'Reference rate (PLR) at origination, as a percentage.', 'min' => 0, 'max' => 100],
            'markup' => ['type' => 'percent', 'description' => 'Margin over the reference rate, as a percentage.', 'min' => 0, 'max' => 100],
            'rate_type' => ['type' => 'enum', 'description' => 'Fixed or floating rate. Another value is stored blank.', 'allowed' => ['Fixed', 'Floating', 'Variable'], 'strict' => false],
            'source_day_count_basis' => ['type' => 'text', 'description' => 'Day count basis the source system uses, e.g. 365.'],
            'source_compounding' => ['type' => 'text', 'description' => 'Compounding the source system uses, e.g. Compound.'],
            'disbursement_tranches' => $int('Number of tranches the facility is drawn in.', ['min' => 0]),
            'repayment_frequency' => ['type' => 'text', 'description' => 'Stated repayment frequency, e.g. Monthly or Quarterly.'],
            'payments_per_year' => $int('Instalments per year, e.g. 12 or 4.', ['min' => 0, 'max' => 365]),
            'tenor_months' => $int('Contractual tenor in months.', ['min' => 0]),
            'moratorium_months' => $int('Moratorium (grace) at the start, in months.', ['min' => 0]),
            'grace_period_months' => $int('Grace period in months.', ['min' => 0]),
            'scheme_code' => ['type' => 'text', 'description' => 'E-Banker scheme code, stored as given.'],
            'interest_policy' => $code1('Interest policy.'),
            'floating_flag' => $code1('Floating flag.'),
            'interest_calc_base' => $code1('Interest calculation base.'),
            'installment_based_on' => $code1('What the instalment is based on.'),
            'emi_calc_type' => $code1('EMI calculation type.'),
            'moratorium_type' => $code1('Moratorium type.'),
            'account_status_code' => ['type' => 'text', 'description' => 'E-Banker account status code.'],
            'los_application_no' => ['type' => 'text', 'description' => 'Loan origination application number.'],
            'los_process_ref' => ['type' => 'text', 'description' => 'Loan origination process reference.'],
            'predecessor_sub_account' => ['type' => 'code', 'description' => 'Sub-account this facility replaced, if any.'],
            'due_date' => $date('Instalment due date.'),
            'principal_due' => $amount('Principal due on the instalment.'),
            'interest_due' => $amount('Interest due on the instalment.'),
            'fee_due' => $amount('Fee due on the instalment. Blank is no fee.'),
            'fee_type' => ['type' => 'enum', 'description' => 'Kind of fee. Another value is loaded as other and matches no rule.', 'allowed' => FeeImportService::KNOWN_TYPES, 'strict' => false],
            'amount' => $type === 'disbursements'
                ? $amount('Amount paid out in the tranche.', ['min' => 0, 'min_exclusive' => true])
                : $amount('Fee amount. A zero amount is skipped.'),
            'description' => ['type' => 'text', 'description' => 'Description of the fee.'],
            'transaction_date' => $date('Date of the transaction.'),
            'cashflow_direction' => ['type' => 'enum', 'description' => 'RECEIVED (from the borrower) or PAID (by MAIIC on the borrower\'s behalf).', 'allowed' => ['RECEIVED', 'PAID']],
            'source_system' => ['type' => 'text', 'description' => 'System the fee came from; with external_transaction_id it makes a re-import idempotent.'],
            'source_reference' => ['type' => 'text', 'description' => 'Reference in the source system.'],
            'external_transaction_id' => ['type' => 'text', 'description' => 'Unique id of the fee in the source system.'],
            'basis' => ['type' => 'enum', 'description' => 'Whether the fee is on the approved or the drawn amount. Blank is ON_APPROVED.', 'allowed' => ['ON_APPROVED', 'ON_DRAWN']],
            'gl_account_ref' => ['type' => 'code', 'description' => 'GL account the fee was credited to.'],
            'transaction_type' => ['type' => 'text', 'description' => 'Kind of transaction, e.g. Disbursement or Principal+Interest.'],
            'principal_component' => $amount('Principal part of the transaction.'),
            'interest_component' => $amount('Interest part of the transaction.'),
            'fee_component' => $amount('Fee part of the transaction. Blank is no fee.'),
            'total_amount' => $amount('Total of the transaction.'),
            'balance_after_transaction' => $amount('Balance after the transaction.'),
            'scheduled_actual_flag' => ['type' => 'enum', 'description' => 'Scheduled (the contractual promise) or Actual (what was collected).', 'allowed' => ['Scheduled', 'Actual']],
            'gl_posting_ref' => ['type' => 'code', 'description' => 'Posting reference; keeps two rows of the same day apart. May be blank.'],
            'row_note' => ['type' => 'text', 'description' => 'Any note on the row.'],
            'period_year' => $int('Year of the posting.', ['min' => 1990, 'max' => 2100]),
            'period_month' => $int('Month of the posting, 1 to 12; 0 marks an annual summary row.', ['min' => 0, 'max' => 12]),
            'interest_income_posted' => $amount('Interest income the ledger posted for the loan in the month.'),
            'period_type' => ['type' => 'text', 'description' => 'Whether the figure is for the month or year to date, e.g. MONTH.'],
            'reporting_period' => ['type' => 'month', 'description' => 'Reporting month, YYYY-MM.', 'severity' => 'warning'],
            'transaction_count' => $int('Number of postings in the figure.', ['min' => 0]),
            'posting_references' => ['type' => 'text', 'description' => 'References of the postings.'],
            'generated_on' => $date('Date the extract was generated.'),
            'effective_date' => $isoDate('Date the rate took effect, written year first.'),
            'rate' => ['type' => 'percent', 'description' => 'The rate as a percentage, such as 25.30.', 'min' => 1, 'max' => 100, 'min_exclusive' => true, 'max_exclusive' => true],
            'index_code' => ['type' => 'text', 'description' => 'Index the rate belongs to. Blank is PLR.', 'pattern' => '/^[A-Za-z0-9_\-]{1,20}$/', 'pattern_hint' => 'up to 20 letters or digits, such as PLR'],
            'source_row' => $int('Row of the source file, kept for the audit trail.', ['min' => 0]),
            'as_delivered' => ['type' => 'text', 'description' => 'What the source cell held, kept for the audit trail.'],
            'interpretation' => ['type' => 'text', 'description' => 'How the source cell was read.'],
            'disbursement_date' => $isoDate('Date the tranche was paid out, written year first.'),
            'tranche_no' => $int('Tranche number, counting from 1.', ['min' => 1]),
            'reference' => ['type' => 'text', 'description' => 'Payment reference; keeps two drawdowns of the same amount on the same day apart.'],
            default => ['type' => 'text', 'description' => 'Stored as given.'],
        };
    }

    /* ------------------------------------------------------------------ */
    /* Machine-generated files                                            */
    /* ------------------------------------------------------------------ */

    /**
     * TrialBalanceImportService: rpt_Trial_Balance_Malawi. Row 1 column B
     * holds the period, row 2 the header, every data row "code...title" in
     * column B with Debit in C and Credit in D, and a Grand Total row the
     * rows must tie to.
     */
    private static function trialBalance(): array
    {
        return [
            'title' => 'Trial balance',
            'summary' => 'Finance\'s monthly trial balance (rpt_Trial_Balance_Malawi) as E-Banker exports it. It is read by position: the period in B1, one GL line per row and the file\'s own Grand Total.',
            'format' => 'xlsx', 'accept' => '.xls,.xlsx', 'headers' => 'positional', 'template' => 'xlsx-layout', 'checkable' => true,
            'steps' => [
                'Export the trial balance from E-Banker for the month and keep it as it comes (XLS or XLSX).',
                'Choose the basis: post-closing for the monthly file, pre-closing for the AFS December sheet.',
                'If cell B1 carries no date, enter the period on the screen; for a workbook with several sheets, name the sheet.',
                'Press Check file: the file is read and every gate run, and each failing row is listed. Nothing is saved.',
                'Load the file. A file that fails a gate is refused and kept as a refused load for the audit trail.',
            ],
            'notes' => ['The sample file shows the layout with two illustrative lines that tie; it is not a trial balance.'],
            'columns' => [
                self::col('Period (B1)', 'date', false, 'The period as an Excel date in cell B1. Read as the month it falls in; may be supplied on the screen instead.', '2025-12-01', ['position' => 'B1', 'serial' => true]),
                self::col('GL Title', 'text', true, 'Column B of each data row: the GL code, three dots and the title.', '1050102...Loans and advances - term loans', ['position' => 'B', 'pattern_hint' => 'code...title, e.g. 1050102...Term loans']),
                self::col('Debit', 'decimal', true, 'Column C: the debit balance. Thousands commas allowed; blank or - is zero.', '868,069,514.47', ['position' => 'C', 'parens' => true]),
                self::col('Credit', 'decimal', true, 'Column D: the credit balance.', '0.00', ['position' => 'D', 'parens' => true]),
                self::col('Grand Total :', 'decimal', true, 'The file\'s own total row (title in column B, total in column C). It is not a GL line; the lines must add up to it.', '868,069,514.47', ['position' => 'B / C', 'parens' => true]),
            ],
            'files' => [],
        ];
    }

    /** TakeonLandingService::land: the original workbook and the mapping workbook. */
    private static function takeon(): array
    {
        return [
            'title' => 'Take-on workbook',
            'summary' => 'The two take-on workbooks: Finance\'s original schedules as received, and the mapping workbook returned with the E-Banker account, the tick and the fees per facility. Both are read by sheet name and cell.',
            'format' => 'workbooks', 'accept' => '.xlsx', 'headers' => 'positional', 'template' => 'none', 'checkable' => true,
            'steps' => [
                'Choose the original workbook exactly as received (Excel-saved, so every formula carries its value).',
                'Choose the mapping workbook returned by Finance.',
                'Press Check only: both workbooks are read and every block gated; nothing is kept.',
                'Land the workbooks. A block that fails a gate lands as refused with its reason and cell; the build leaves it out.',
            ],
            'notes' => ['These workbooks are produced by Finance from the source schedules; there is no blank template. The column guide lists the sheets and cells read.'],
            'columns' => [],
            'files' => [
                ['name' => 'Original workbook', 'required' => true, 'description' => 'Sheets "Loan Book" (one row per facility) and "Amortisation and Repayments" (one schedule block per facility).'],
                ['name' => 'Mapping workbook: sheet "Mapping"', 'required' => true, 'description' => 'From row 2: A Loan Book row, J proposed E-Banker account, K confidence, X confirmed (Y or N), Y corrected account, Z comment.'],
                ['name' => 'Mapping workbook: sheet "Upload summary"', 'required' => true, 'description' => 'From row 2: C Loan Book row, AO arrangement fee, AP legal fees, AQ other fees, AR fee date, AS fee deducted (Y or N), AT fee source.'],
                ['name' => 'Mapping workbook: sheet "Blocks"', 'required' => true, 'description' => 'From row 2: A block number, E row of the block on "Amortisation and Repayments", G Loan Book row, J restructured (TRUE or FALSE).'],
            ],
        ];
    }

    /** PackLandingService::land: a zip of CSV extracts plus manifest.json. */
    private static function ebankerPack(): array
    {
        $files = [['name' => 'manifest.json', 'required' => true, 'description' => 'The pack manifest: source, date_format, and a "files" list naming each CSV with its query_id, query_version, rows and sha256.']];
        foreach (self::ebankerQueries() as $q) {
            $files[] = ['name' => $q->query_id . ' ' . $q->title . '.csv', 'required' => false,
                'description' => "Source table {$q->source_table}; key {$q->key_column}" . ($q->date_column ? "; dates in {$q->date_column}" : '') . ($q->account_column ? "; account in {$q->account_column}" : '') . '. Query version ' . $q->version . '.'];
        }

        return [
            'title' => 'E-Banker pack',
            'summary' => 'A zip of the E-Banker extract files (CSV) with their manifest.json. Every file is checked against the manifest and the query register before any row is landed.',
            'format' => 'zip', 'accept' => '.zip', 'headers' => 'positional', 'template' => 'manifest', 'checkable' => true,
            'steps' => [
                'Run the registered E-Banker queries and save each result as CSV with its column headings.',
                'Write manifest.json beside them (the sample manifest shows the shape): for each file its query_id, query_version, row count and SHA-256.',
                'Zip the CSV files and the manifest together.',
                'Tick Check only and upload: every gate is run and failures are named by file and row; nothing is kept.',
                'Upload again without Check only to land the pack. A pack that fails a gate is quarantined with the failures named.',
            ],
            'notes' => [
                'Dates are read in the manifest\'s date_format (YYYY-MM-DD when none is given). Amounts must be numbers once commas are removed.',
                'The extracts come from E-Banker; there is no blank data template. The sample manifest lists every registered query.',
            ],
            'columns' => [],
            'files' => $files,
        ];
    }

    /** SpGradesImportService: the two sheets of the ECL model workbook. */
    private static function spGrades(): array
    {
        return [
            'title' => 'S&P internal grades (ECL model workbook)',
            'summary' => 'The S&P internal grading of the ECL model workbook, loaded as a draft grading profile. Two sheets are read; the rest of the workbook is ignored.',
            'format' => 'xlsx', 'accept' => '.xlsx,.xlsm', 'headers' => 'positional', 'template' => 'none', 'checkable' => true,
            'steps' => [
                'Choose the ECL model workbook (XLSX or XLSM) built from the model template.',
                'Press Preview: the two sheets are read and the grades and cumulative PDs found are shown, with any grade left out and why; nothing is saved yet.',
                'Name the new profile and press Create draft profile. It changes no PD until it is activated.',
            ],
            'notes' => ['The workbook is the ECL model itself; there is no blank template. The column guide lists the sheets and columns read.'],
            'columns' => [],
            'files' => [
                ['name' => 'Sheet "ASS1-Internal Grades"', 'required' => true, 'description' => 'One row per grade: A grade number, B description, C S&P rating, D broad grade, E combined label (required), F lower and G upper PD bound.'],
                ['name' => 'Sheet "PDs-S&P"', 'required' => true, 'description' => 'A header row with "S&P Rating" in B and "Internal Grade" in C and the horizons 1, 2, 3 ... from D; then one row per grade, its combined label in C and the cumulative PD per horizon.'],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Model setup and macro data                                         */
    /* ------------------------------------------------------------------ */

    /** SicrGroupController::import: name, description (headings lower-cased). */
    private static function sicrGroups(): array
    {
        return [
            'title' => 'SICR groups',
            'summary' => 'One row per SICR group. A group already held under the same name is left as it is.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'headers' => 'lower', 'checkable' => true,
            'steps' => ['Download the sample file and keep its two headings.', 'Fill one row per group, each name once.', 'Choose the file: it is checked first and every problem listed by row; then the groups are added.'],
            'columns' => [
                self::col('name', 'text', true, 'Name of the group. Each name once in the file.', 'Financial difficulty'),
                self::col('description', 'text', true, 'What the group covers. The heading is required; the cell may be blank.', 'Signs that the borrower cannot meet its obligations', ['blank_ok' => true]),
            ],
            'unique' => [['name']],
            'sample_rows' => [
                ['name' => 'Financial difficulty', 'description' => 'Signs that the borrower cannot meet its obligations'],
                ['name' => 'Account conduct', 'description' => 'Behaviour of the account'],
            ],
        ];
    }

    /** SicrItemController::import: group, name, active, stage, description, evidence_required, standard_reference. */
    private static function sicrItems(): array
    {
        return [
            'title' => 'SICR alert items',
            'summary' => 'One row per SICR alert item. An item is keyed by its group and name; a group named that does not exist is created.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'headers' => 'lower', 'checkable' => true,
            'steps' => ['Download the sample file and keep its headings.', 'Fill one row per item. stage is 2 (significant increase) or 3 (credit-impaired).', 'Choose the file: it is checked first and every problem listed by row; then the items are added or updated.'],
            'columns' => [
                self::col('group', 'text', true, 'Name of the SICR group the item belongs to.', 'Financial difficulty'),
                self::col('name', 'text', true, 'Name of the alert item. Each group and name pair once in the file.', 'Covenant breach'),
                self::col('active', 'boolean', true, 'Whether new triggers can be raised on the item.', 'yes'),
                self::col('stage', 'enum', false, 'Stage the item implies. Blank is 2.', 2, ['allowed' => ['2', '3']]),
                self::col('description', 'text', false, 'What the item means.', 'A financial covenant was breached'),
                self::col('evidence_required', 'boolean', false, 'Whether evidence must be attached when the item is raised.', 'yes'),
                self::col('standard_reference', 'text', false, 'Paragraph of the standard the item rests on.', 'IFRS 9 B5.5.17(e)'),
            ],
            'unique' => [['group', 'name']],
            'sample_rows' => [
                ['group' => 'Financial difficulty', 'name' => 'Covenant breach', 'active' => 'yes', 'stage' => 2, 'description' => 'A financial covenant was breached', 'evidence_required' => 'yes', 'standard_reference' => 'IFRS 9 B5.5.17(e)'],
                ['group' => 'Account conduct', 'name' => 'Restructure for distress', 'active' => 'yes', 'stage' => 3, 'description' => 'Terms changed because of financial difficulty', 'evidence_required' => 'yes', 'standard_reference' => 'IFRS 9 Appendix A'],
            ],
        ];
    }

    /** MacroStatisticsController::csvPreview: the export's own layout. */
    private static function macroSeries(): array
    {
        $codes = self::tableValues('macro_statistics', 'statistic_code');

        return [
            'title' => 'Macro statistics (any variable)',
            'summary' => 'Observations of any variable the system holds, in the layout of the Macro Statistics export. The rows are shown grouped by variable before anything is saved.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'headers' => 'lower', 'checkable' => true,
            'steps' => ['Download the sample file (or the export, which has the same columns).', 'series_code must be a variable listed on Variables; add a new one there first.', 'Choose the file. It is checked, every problem is listed by line, and the preview shows what will be saved; then commit.'],
            'columns' => [
                self::col('series_code', 'enum', true, 'Code of the variable, as listed on Variables.', $codes[0] ?? 'GDP_GROWTH', ['allowed' => $codes, 'allowed_source' => 'macro_statistics', 'case_insensitive' => true]),
                self::col('period', 'text', true, 'The period: YYYY-MM, YYYY-MM-DD or a year YYYY (read as December).', '2025-12', ['pattern' => '/^\d{4}(-\d{2}(-\d{2})?)?$/', 'pattern_hint' => 'YYYY-MM, YYYY-MM-DD or YYYY']),
                self::col('value', 'decimal', true, 'The observation. A % sign and thousands commas are allowed.', 4.2, ['percent_sign' => true]),
                self::col('is_forecast', 'boolean', false, 'Whether the value is a forecast. Blank is an actual.', 'no', ['tokens_true' => ['1', 'yes', 'true', 'forecast', 'y'], 'tokens_false' => ['0', 'no', 'false', 'actual', 'n']]),
                self::col('source', 'text', false, 'Publisher of the figure.', 'National Statistical Office'),
                self::col('published_at', 'date', false, 'Date the publisher released the figure.', '2026-01-20', ['formats' => self::DATE_FORMATS_TAPE]),
            ],
            'unique' => [['series_code', 'period']],
            'sample_rows' => [
                ['series_code' => $codes[0] ?? 'GDP_GROWTH', 'period' => '2025-11', 'value' => 4.1, 'is_forecast' => 'no', 'source' => 'Sample source', 'published_at' => ''],
                ['series_code' => $codes[0] ?? 'GDP_GROWTH', 'period' => '2025-12', 'value' => 4.2, 'is_forecast' => 'yes', 'source' => 'Sample source', 'published_at' => ''],
            ],
        ];
    }

    /** MacroStatisticsController::rbmPreview: two columns, date and rate. */
    private static function rbmPolicyRate(): array
    {
        return [
            'title' => 'RBM policy rate',
            'summary' => 'The Reserve Bank of Malawi policy-rate decisions: two columns, the date and the rate. A first row whose rate is not a number is read as the heading.',
            'format' => 'csv', 'accept' => '.csv,.txt', 'headers' => 'positional', 'checkable' => true, 'header_optional' => true,
            'steps' => ['Download the sample file.', 'One row per month or decision: the date in column A, the rate in column B.', 'Choose the file and press Preview: every row is read and shown before anything is saved.'],
            'columns' => [
                self::col('date', 'date', true, 'Date of the rate; read as the end of its month.', '2025-12-31', ['position' => 'A', 'formats' => ['Y-m-d', 'd/m/Y', 'Y-m'], 'round_trip' => true]),
                self::col('rate', 'percent', true, 'The policy rate as a percentage.', 26, ['position' => 'B', 'min' => 0, 'max' => 100]),
            ],
            'unique' => [['date']],
            'sample_rows' => [['date' => '2025-11-30', 'rate' => 26], ['date' => '2025-12-31', 'rate' => 26]],
        ];
    }

    /** ImfWeoParserService: the IMF WEO download, tab-delimited. */
    private static function imfWeo(): array
    {
        return [
            'title' => 'IMF World Economic Outlook',
            'summary' => 'The IMF WEO database download (tab-delimited, often UTF-16, despite its .xls name). The row of the country and the series\' WEO code is read; years after "Estimates Start After" are forecasts.',
            'format' => 'tsv', 'accept' => '.xls,.tsv,.txt', 'headers' => 'exact', 'template' => 'tsv', 'checkable' => true,
            'steps' => ['Download the WEO database (by countries) from the IMF and keep the file as it comes.', 'Choose the series and the file, then press Preview; the series\' row is shown before anything is saved.'],
            'notes' => ['Values such as n/a in other countries\' rows are ignored. The sample file has one row per series that carries a WEO code, with the year cells blank.'],
            'columns' => [
                self::col('ISO', 'text', true, 'Three-letter country code; the default country is MWI.', 'MWI'),
                self::col('WEO Subject Code', 'code', true, 'The IMF indicator code, as held on the series\' external codes.', 'NGDP_RPCH'),
                self::col('Units', 'text', false, 'Units of the indicator.', 'Percent change'),
                self::col('Estimates Start After', 'integer', false, 'Last year of actual data; later years are forecasts.', 2024),
                self::col('1980 ... 2030', 'decimal', true, 'One column per year, headed by the year. Non-numeric cells are skipped.', 4.2, ['year_columns' => true]),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */

    /** @return list<string> the values of one column of a table, when it exists */
    public static function tableValues(string $table, string $column): array
    {
        try {
            if (! Schema::hasTable($table)) {
                return [];
            }

            return DB::table($table)->orderBy($column)->pluck($column)->map(fn ($v) => (string) $v)->filter()->unique()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public static function ebankerQueries()
    {
        try {
            return Schema::hasTable('ebanker_queries') ? DB::table('ebanker_queries')->orderBy('query_id')->get() : collect();
        } catch (Throwable) {
            return collect();
        }
    }
}
