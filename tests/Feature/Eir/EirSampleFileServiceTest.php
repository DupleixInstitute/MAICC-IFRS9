<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirIntakeController;
use App\Models\ImportMapping;
use App\Services\Eir\EirSampleFileService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The intake templates are only worth downloading if a file built from one
 * imports without a mapping pass. That is a property of the pair — generator
 * and reader — so it is tested through the reader rather than by asserting a
 * column list the reader never sees.
 */
class EirSampleFileServiceTest extends TestCase
{
    protected $seed = false;

    private EirSampleFileService $samples;

    private MappedFileReader $reader;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // templateFor() reads saved mappings; an empty table is the state a
        // first-time operator is actually in.
        Schema::create('import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 50);
            $table->string('source_header');
            $table->string('target_field');
            $table->string('transform', 100)->nullable();
            $table->timestamps();
        });

        $this->samples = new EirSampleFileService();
        $this->reader = new MappedFileReader();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function write(string $importType): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eir_sample_').'.csv';
        file_put_contents($path, $this->samples->csv($importType));
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_every_sample_maps_itself_with_no_manual_pass(): void
    {
        foreach (EirIntakeController::IMPORT_TYPES as $importType) {
            $analysis = $this->reader->analyze($this->write($importType), $importType);

            $this->assertSame($importType, $analysis['import_type'],
                "{$importType} sample was detected as another type");
            $this->assertSame([], $analysis['missing_required'],
                "{$importType} sample omits a required column");
            $this->assertSame([], $analysis['unmapped_headers'],
                "{$importType} sample carries a column the reader cannot place");

            // Two headers feeding one field would silently drop a column's
            // values, which is exactly the defect the withdrawal rule exists
            // to prevent.
            $targets = array_values($analysis['mapping']);
            $this->assertSame(array_unique($targets), $targets,
                "{$importType} sample maps two headers to the same field");
        }
    }

    public function test_every_sample_reads_back_as_rows_keyed_by_canonical_field(): void
    {
        foreach (EirIntakeController::IMPORT_TYPES as $importType) {
            $read = $this->reader->read($this->write($importType), $importType);

            $this->assertNotEmpty($read['rows'], "{$importType} sample has no example rows");
            foreach (MappedFileReader::REQUIRED_FIELDS[$importType] as $field) {
                $this->assertArrayHasKey($field, $read['rows'][0],
                    "{$importType} sample's first row has no {$field}");
                $this->assertNotSame('', (string) $read['rows'][0][$field],
                    "{$importType} sample leaves the required field {$field} blank");
            }
        }
    }

    /**
     * RATE_BASIS in a delivered contract master carries Fixed/Variable and is
     * deliberately read as rate_type. A rate_basis column in the template
     * would therefore land on rate_type alongside the real one, so the
     * generator withdraws it.
     */
    public function test_a_field_whose_name_an_alias_claims_is_withdrawn(): void
    {
        $headers = $this->samples->headers('contract_master');

        $this->assertContains('rate_type', $headers);
        $this->assertNotContains('rate_basis', $headers);
        $this->assertContains('rate_basis', MappedFileReader::OPTIONAL_FIELDS['contract_master'],
            'the field is still supported; only the template column is withdrawn');
    }

    public function test_a_saved_template_still_overrides_the_identity_mapping(): void
    {
        ImportMapping::create([
            'import_type' => 'fees',
            'source_header' => 'description',
            'target_field' => 'source_reference',
        ]);

        $analysis = $this->reader->analyze($this->write('fees'), 'fees');

        $this->assertSame('source_reference', $analysis['mapping']['description'],
            'an operator-saved mapping must outrank a header that matches a field name');
    }

    public function test_each_file_is_named_for_its_import_type(): void
    {
        foreach (EirIntakeController::IMPORT_TYPES as $importType) {
            $this->assertSame($importType.'_sample.csv', $this->samples->fileName($importType));
        }
    }

    public function test_an_unknown_type_is_refused_rather_than_guessed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->samples->csv('extract_b');
    }
}
