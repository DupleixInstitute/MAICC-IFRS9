<?php

namespace Tests\Unit\Eir;

use App\Imports\ExtractBImport;
use App\Services\Imports\MappedFileReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Pure unit tests for the mapping reader: explicit mapping arrays are
 * passed in, so no database is touched (the ImportMapping-template path is
 * covered by feature tests once the controller lands).
 */
class MappedFileReaderTest extends TestCase
{
    private MappedFileReader $reader;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new MappedFileReader();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eir_test_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_extract_b_aliases_map_alternate_headers_to_canonical_fields(): void
    {
        $aliases = ExtractBImport::aliases();

        $this->assertSame('contract_id', $aliases['LOAN_ACCOUNT_NUMBER']);
        $this->assertSame('contract_id', $aliases['ACCOUNT_NUMBER']);
        $this->assertSame('customer_id', $aliases['CLIENT_ID']);
        $this->assertSame('transaction_date', $aliases['VALUE_DATE']);
        $this->assertSame('scheduled_actual_flag', $aliases['ACTUAL_SCHEDULED_FLAG']);
    }

    /** Ebanker-style headers map onto our fields with transforms applied. */
    public function test_maps_headers_and_applies_transforms(): void
    {
        $path = $this->csv(
            "Arrangement Id,Repayment Date,Principal Amount,Interest Amount\n" .
            "MAIIC-001,22/08/2025,\"3,024,946.57\",\"2,675,000.00\"\n" .
            "MAIIC-001,22/11/2025,\"3,105,863.90\",\"2,594,082.67\"\n"
        );

        $result = $this->reader->read($path, 'schedule',
            mapping: [
                'Arrangement Id'   => 'contract_id',
                'Repayment Date'   => 'due_date',
                'Principal Amount' => 'principal_due',
                'Interest Amount'  => 'interest_due',
            ],
            transforms: [
                'due_date'      => 'date:d/m/Y',
                'principal_due' => 'number',
                'interest_due'  => 'number',
            ]
        );

        $this->assertCount(2, $result['rows']);
        $this->assertSame('MAIIC-001', $result['rows'][0]['contract_id']);
        $this->assertSame('2025-08-22', $result['rows'][0]['due_date']);
        $this->assertSame(3024946.57, $result['rows'][0]['principal_due']);
        $this->assertSame(2675000.00, $result['rows'][0]['interest_due']);
    }

    /** Missing required mappings must block with a named list. */
    public function test_unmapped_required_field_blocks_with_names(): void
    {
        $path = $this->csv("Arrangement Id,Some Column\nX-1,foo\n");

        try {
            $this->reader->read($path, 'schedule', mapping: [
                'Arrangement Id' => 'contract_id',
            ]);
            $this->fail('Expected RuntimeException for unmapped required fields');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('due_date', $e->getMessage());
            $this->assertStringContainsString('principal_due', $e->getMessage());
            $this->assertStringContainsString('interest_due', $e->getMessage());
        }
    }

    /** Extra unmapped columns are ignored but reported, never silent. */
    public function test_unmapped_optional_columns_reported(): void
    {
        $path = $this->csv(
            "Contract,Due,Principal,Interest,Branch Name\n" .
            "C-1,2025-01-31,100,10,Lilongwe\n"
        );

        $result = $this->reader->read($path, 'schedule', mapping: [
            'Contract'  => 'contract_id',
            'Due'       => 'due_date',
            'Principal' => 'principal_due',
            'Interest'  => 'interest_due',
        ], transforms: ['due_date' => 'date']);

        $this->assertSame(['branch_name'], $result['report']['unmapped_headers']);
        $this->assertArrayNotHasKey('branch_name', $result['rows'][0]);
    }

    /** Semicolon-delimited files are detected automatically. */
    public function test_detects_semicolon_delimiter(): void
    {
        $path = $this->csv(
            "Contract;Due;Principal;Interest\n" .
            "C-9;2025-06-30;5000;400\n"
        );

        $result = $this->reader->read($path, 'schedule', mapping: [
            'Contract'  => 'contract_id',
            'Due'       => 'due_date',
            'Principal' => 'principal_due',
            'Interest'  => 'interest_due',
        ], transforms: ['principal_due' => 'number']);

        $this->assertSame('C-9', $result['rows'][0]['contract_id']);
        $this->assertSame(5000.0, $result['rows'][0]['principal_due']);
    }

    /** Signed fee lines: accounting negatives in parentheses survive. */
    public function test_fee_import_keeps_signed_amounts(): void
    {
        $path = $this->csv(
            "Loan Ref,Fee Category,Fee Amount\n" .
            "NYAM-1,legal,\"4,450,000\"\n" .
            "NYAM-1,legal,\"(1,990,000)\"\n"
        );

        $result = $this->reader->read($path, 'fees', mapping: [
            'Loan Ref'     => 'contract_id',
            'Fee Category' => 'fee_type',
            'Fee Amount'   => 'amount',
        ], transforms: ['amount' => 'number']);

        $this->assertSame(4450000.0, $result['rows'][0]['amount']);
        $this->assertSame(-1990000.0, $result['rows'][1]['amount']);
    }

    /** The percent transform normalises 32.10-style tape rates. */
    public function test_percent_transform(): void
    {
        $path = $this->csv("Loan Ref,Fee Category,Fee Amount\nC-1,arrangement,3\n");

        $result = $this->reader->read($path, 'fees', mapping: [
            'Loan Ref'     => 'contract_id',
            'Fee Category' => 'fee_type',
            'Fee Amount'   => 'amount',
        ], transforms: ['amount' => 'percent']);

        $this->assertSame(0.03, $result['rows'][0]['amount']);
    }

    /** NBSP-polluted numbers (the tape's known garbage) clean correctly. */
    public function test_nbsp_and_placeholder_cleaning(): void
    {
        $this->assertSame(1234567.89, MappedFileReader::cleanNumber("1\xC2\xA0234\xC2\xA0567.89"));
        $this->assertSame(0.0, MappedFileReader::cleanNumber(' -   '));
        $this->assertSame(0.0, MappedFileReader::cleanNumber('-'));
        $this->assertSame(-500.0, MappedFileReader::cleanNumber('(500)'));
    }

    /** Blank lines and BOM headers must not produce ghost rows. */
    public function test_skips_blank_lines_and_bom(): void
    {
        $path = $this->csv(
            "\xEF\xBB\xBFContract,Due,Principal,Interest\n" .
            "C-1,2025-01-31,100,10\n" .
            ",,,\n" .
            "\n"
        );

        $result = $this->reader->read($path, 'schedule', mapping: [
            'Contract'  => 'contract_id',
            'Due'       => 'due_date',
            'Principal' => 'principal_due',
            'Interest'  => 'interest_due',
        ]);

        $this->assertCount(1, $result['rows']);
    }

    public function test_rejects_unknown_import_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reader->read($this->csv("A\n1\n"), 'unknown_type', mapping: []);
    }
}
