<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use App\Services\Eir\ReferenceRateImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The reference-rate series import on a private in-memory sqlite schema
 * (same isolation as EirIntakeServicesTest). The fixture is the repaired
 * PLR file MAIIC's data was corrected into: 48 rows, 26 genuine rate
 * changes, 4 March 2020 to 3 September 2026.
 */
class ReferenceRateImportServiceTest extends TestCase
{
    protected $seed = false;

    private const FIXTURE = __DIR__ . '/../../fixtures/eir/plr_reference_rates_corrected.csv';

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

        Schema::create('reference_rate_series', function (Blueprint $t) {
            $t->increments('id');
            $t->string('index_code', 20)->default('PLR');
            $t->date('effective_date');
            $t->decimal('rate', 8, 5);
            $t->string('source_row')->nullable();
            $t->string('as_delivered')->nullable();
            $t->string('interpretation')->nullable();
            $t->unsignedBigInteger('import_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->unique(['index_code', 'effective_date']);
        });

        Schema::create('import_mappings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('import_type');
            $t->string('source_header');
            $t->string('target_field');
            $t->string('transform')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('rows_affected')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('meta')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });
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
        $path = tempnam(sys_get_temp_dir(), 'eir_plr_') . '.csv';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /** Rows as the reader hands them to the service, with no transforms. */
    private function readRows(string $path): array
    {
        return (new MappedFileReader())->read($path, 'reference_rates')['rows'];
    }

    private function service(): ReferenceRateImportService
    {
        return app(ReferenceRateImportService::class);
    }

    public function test_the_repaired_plr_file_loads_48_rows_with_26_rate_changes(): void
    {
        $rows = $this->readRows(self::FIXTURE);
        $this->assertCount(48, $rows);
        // The aliases place the file's own headers with no mapping pass.
        $this->assertSame('2020-03-04', $rows[0]['effective_date']);
        $this->assertSame('13.4', (string) $rows[0]['rate']);
        $this->assertSame('03/04/2020', $rows[0]['as_delivered']);

        $result = $this->service()->import($rows, 'PLR', 7, 3);

        $this->assertSame(48, $result['source_rows']);
        $this->assertSame(48, $result['loaded_rows']);
        $this->assertSame(0, $result['unchanged']);
        $this->assertSame(0, $result['duplicate_source_rows']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(26, $result['rate_changes']);
        $this->assertSame(22, $result['repeated_rate_rows']);
        $this->assertCount(22, $result['notes']);
        $this->assertSame('2020-03-04', $result['first_date']);
        $this->assertSame('2026-09-03', $result['last_date']);
        $this->assertEqualsWithDelta(21.2, $result['current_rate'], 0.000001);

        $series = $result['series'];
        $this->assertSame(48, $series['rows']);
        $this->assertSame(26, $series['changes']);
        $this->assertEqualsWithDelta(21.2, $series['current_rate'], 0.000001);
        $this->assertSame('2020-03-04', $series['first_date']);
        $this->assertSame('2026-09-03', $series['last_date']);

        $this->assertSame(48, DB::table('reference_rate_series')->where('index_code', 'PLR')->count());

        // The repair stays visible on the row: what the file said, how it was read.
        $first = DB::table('reference_rate_series')->where('effective_date', '2020-03-04')->first();
        $this->assertEqualsWithDelta(13.4, (float) $first->rate, 0.000001);
        $this->assertSame('1', $first->source_row);
        $this->assertSame('03/04/2020', $first->as_delivered);
        $this->assertSame('REPAIRED - Excel had transposed day and month', $first->interpretation);
        $this->assertSame(7, (int) $first->import_id);
        $this->assertSame(3, (int) $first->created_by);

        $audit = AuditLog::where('action', 'EIR Reference Rate Import')->first();
        $this->assertNotNull($audit);
        $this->assertSame(48, $audit->meta['result']['loaded_rows']);
        $this->assertSame(26, $audit->meta['result']['rate_changes']);
    }

    public function test_reimporting_the_same_file_loads_nothing_twice(): void
    {
        $rows = $this->readRows(self::FIXTURE);
        $this->service()->import($rows);
        $second = $this->service()->import($rows);

        $this->assertSame(0, $second['loaded_rows']);
        $this->assertSame(48, $second['unchanged']);
        $this->assertSame(48, DB::table('reference_rate_series')->count());
    }

    public function test_a_stored_rate_is_never_overwritten_by_a_later_file(): void
    {
        $this->service()->import([['effective_date' => '2024-12-09', 'rate' => '25.3']]);

        $result = $this->service()->import([
            ['effective_date' => '2024-12-09', 'rate' => '25.4'],
            ['effective_date' => '2025-04-14', 'rate' => '25.1'],
        ]);

        $this->assertSame(1, $result['loaded_rows']);
        $this->assertArrayHasKey('PLR @ 2024-12-09', $result['skipped']);
        $this->assertStringContainsString('stored rate 25.30 differs from the file\'s 25.40', $result['skipped']['PLR @ 2024-12-09']);
        $this->assertEqualsWithDelta(25.3, (float) DB::table('reference_rate_series')->where('effective_date', '2024-12-09')->value('rate'), 0.000001);
    }

    /**
     * The delivered file had 1,134 dates with day and month transposed by
     * Excel. A slash date is refused whichever way round it could be read.
     */
    public function test_a_day_first_date_rejects_the_whole_file_naming_the_column_and_row(): void
    {
        $rows = $this->readRows($this->csv(
            "effective_date,rate\n2020-03-04,13.4\n03/04/2020,13.3\n2020-09-01,13.4\n"
        ));

        try {
            $this->service()->import($rows);
            $this->fail('A dd/mm date was accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('effective_date on row 3', $e->getMessage());
            $this->assertStringContainsString("'03/04/2020'", $e->getMessage());
            $this->assertStringContainsString('yyyy-mm-dd', $e->getMessage());
        }

        $this->assertSame(0, DB::table('reference_rate_series')->count(), 'nothing may load from a refused file');
    }

    public function test_an_excel_serial_or_an_impossible_iso_date_is_refused_too(): void
    {
        foreach (['43894', '2020-13-04', '2020-04-31', '2020-4-3'] as $bad) {
            try {
                $this->service()->import([
                    ['effective_date' => '2020-03-04', 'rate' => '13.4'],
                    ['effective_date' => $bad, 'rate' => '13.3'],
                ]);
                $this->fail("'{$bad}' was accepted as a date");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString("row 3 is '{$bad}'", $e->getMessage());
            }
        }
        $this->assertSame(0, DB::table('reference_rate_series')->count());
    }

    public function test_an_ordering_break_rejects_the_file_naming_both_rows(): void
    {
        try {
            $this->service()->import([
                ['effective_date' => '2020-03-04', 'rate' => '13.4'],
                ['effective_date' => '2020-05-13', 'rate' => '13.3'],
                ['effective_date' => '2020-05-01', 'rate' => '13.5'],
            ]);
            $this->fail('An out-of-order date was accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('row 4 (2020-05-01) is earlier than row 3 (2020-05-13)', $e->getMessage());
            $this->assertStringContainsString('strictly increasing', $e->getMessage());
        }

        $this->assertSame(0, DB::table('reference_rate_series')->count());
    }

    public function test_an_exact_duplicate_row_is_collapsed_but_a_conflicting_one_is_refused(): void
    {
        $result = $this->service()->import([
            ['effective_date' => '2020-03-04', 'rate' => '13.4'],
            ['effective_date' => '2020-03-04', 'rate' => '13.40'],
            ['effective_date' => '2020-05-13', 'rate' => '13.3'],
        ]);
        $this->assertSame(1, $result['duplicate_source_rows']);
        $this->assertSame(2, $result['loaded_rows']);
        $this->assertSame(2, $result['rate_changes']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('row 3 repeats the date 2021-03-11 of row 2 with a different rate (12.20 against 12.00)');
        $this->service()->import([
            ['effective_date' => '2021-03-11', 'rate' => '12.0'],
            ['effective_date' => '2021-03-11', 'rate' => '12.2'],
        ]);
    }

    public function test_a_rate_written_as_a_fraction_is_refused_rather_than_scaled(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rate on row 2 is 0.253');
        $this->service()->import([['effective_date' => '2020-03-04', 'rate' => '0.253']]);
    }

    public function test_rows_repeating_the_previous_rate_are_stored_but_noted_as_not_changes(): void
    {
        $result = $this->service()->import([
            ['effective_date' => '2020-05-13', 'rate' => '13.3'],
            ['effective_date' => '2020-05-29', 'rate' => '13.3'],
            ['effective_date' => '2020-09-01', 'rate' => '13.4'],
        ]);

        $this->assertSame(3, $result['loaded_rows']);
        $this->assertSame(2, $result['rate_changes']);
        $this->assertSame(1, $result['repeated_rate_rows']);
        $this->assertStringContainsString('row 3 (2020-05-29) repeats the previous PLR rate of 13.30', $result['notes'][0]);
    }

    public function test_the_index_defaults_to_plr_and_a_file_index_wins(): void
    {
        $result = $this->service()->import([
            ['effective_date' => '2020-03-04', 'rate' => '13.4'],
            ['effective_date' => '2020-03-04', 'rate' => '9.5', 'index_code' => 'tbill'],
        ], 'PLR');

        $this->assertSame(2, $result['loaded_rows']);
        $this->assertSame(['PLR', 'TBILL'], DB::table('reference_rate_series')->orderBy('index_code')->pluck('index_code')->all());
    }
}
