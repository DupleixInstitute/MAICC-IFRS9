<?php

namespace Tests\Feature\Imports;

use App\Support\Imports\ImportSpecs;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Imports\Concerns\CreatesImportSpecSchema;
use Tests\TestCase;

/**
 * Every import spec serves its sample file and its column guide, and the
 * sample file carries exactly the spec's headings (13 October 2026).
 */
class ImportTemplateDownloadTest extends TestCase
{
    use CreatesImportSpecSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createImportSpecSchema();
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        $this->dropImportSpecStorage();
        parent::tearDown();
    }

    public static function keys(): array
    {
        return array_combine(ImportSpecs::keys(), array_map(fn ($k) => [$k], ImportSpecs::keys()));
    }

    /** @dataProvider keys */
    public function test_every_key_serves_a_template_with_the_spec_headings(string $key): void
    {
        $spec = ImportSpecs::get($key);
        $response = $this->get(route('import-templates.download', $key));

        if ($spec['template'] === 'none') {
            // a workbook from the source system: no blank template, the guide describes it
            $response->assertNotFound();
            $this->assertNotEmpty($spec['files']);

            return;
        }
        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
        $body = $response->getContent();
        $names = array_map(fn ($c) => $c['name'], $spec['columns']);

        switch ($spec['template']) {
            case 'csv':
                $first = str_getcsv(strtok($body, "\n"));
                if ($key === 'loan-book-ebanker') {
                    // a Loan Type row, then the report's header row; read by position
                    $this->assertStringStartsWith('Loan Type :', $first[0]);
                    $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", $body))));
                    $this->assertSame('Sr.', $lines[1][0]);
                    $this->assertGreaterThanOrEqual(29, count($lines[2]));
                } else {
                    $this->assertSame($names, array_map('trim', $first), "{$key}: the sample's heading row is the spec's columns");
                    $this->assertGreaterThanOrEqual(2, substr_count(trim($body), "\n") + 1, "{$key}: at least one illustrative row");
                }
                break;
            case 'tsv':
                $first = explode("\t", trim(strtok($body, "\r\n")));
                $this->assertSame(['ISO', 'WEO Subject Code', 'Units', 'Estimates Start After'], array_slice($first, 0, 4));
                break;
            case 'xlsx-layout':
                $sheet = $this->xlsx($body)->getSheet(0);
                $this->assertSame(['GL Title', 'Debit', 'Credit'], [$sheet->getCell('B2')->getValue(), $sheet->getCell('C2')->getValue(), $sheet->getCell('D2')->getValue()]);
                break;
            case 'manifest':
                $manifest = json_decode($body, true);
                $this->assertIsArray($manifest['files']);
                $this->assertSame(['P1_01', 'P1_02'], array_column($manifest['files'], 'query_id'));
                break;
        }
    }

    /** @dataProvider keys */
    public function test_every_key_serves_a_column_guide_listing_each_column(string $key): void
    {
        $spec = ImportSpecs::get($key);
        $response = $this->get(route('import-templates.guide', $key));
        $response->assertOk();
        $book = $this->xlsx($response->getContent());
        $this->assertNotNull($book->getSheetByName('Instructions'));

        if ($spec['columns'] !== []) {
            $sheet = $book->getSheetByName('Columns');
            $this->assertNotNull($sheet, "{$key}: a Columns sheet");
            $col = $spec['headers'] === 'positional' ? 'B' : 'A';
            $listed = [];
            for ($r = 2; $r <= $sheet->getHighestRow(); $r++) {
                $listed[] = (string) $sheet->getCell($col . $r)->getValue();
            }
            $this->assertSame(array_map(fn ($c) => (string) $c['name'], $spec['columns']), $listed);
            // the type and the required flag of each column
            $this->assertContains($sheet->getCell(($spec['headers'] === 'positional' ? 'C' : 'B') . '2')->getValue(), ['Required', 'Optional']);
        } else {
            $this->assertNotNull($book->getSheetByName('Files'), "{$key}: a Files sheet for a machine-generated input");
        }
    }

    /** @dataProvider keys */
    public function test_every_key_serves_its_spec_for_the_screens(string $key): void
    {
        $json = $this->getJson(route('import-templates.spec', $key))->assertOk()->json();
        $this->assertSame($key, $json['key']);
        $this->assertNotEmpty($json['steps']);
        $this->assertNotEmpty($json['guide_url']);
        foreach ($json['columns'] as $c) {
            $this->assertNotSame('', $c['type']);
            $this->assertIsBool($c['required']);
        }
    }

    public function test_an_unknown_key_is_not_found(): void
    {
        $this->get(route('import-templates.download', 'no-such-import'))->assertNotFound();
    }

    private function xlsx(string $body)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        file_put_contents($tmp, $body);
        $book = IOFactory::load($tmp);
        @unlink($tmp);

        return $book;
    }
}
