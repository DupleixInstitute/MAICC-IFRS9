<?php

namespace Tests\Feature\Eir;

use App\Support\Imports\ImportSpecValidator;
use App\Support\Imports\ImportSpecs;
use App\Support\Imports\ImportTemplateBuilder;
use Database\Seeders\CollateralTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two refusals of the final check of 14 October 2026:
 *  - eir:stage reported success with 0 rows for a month with no loan book; it
 *    now refuses, naming the month and where the landed ledger ends;
 *  - a bootstrap database had no collateral types, so the collateral sample's
 *    Check file failed ("no values are set up yet"); the bootstrap and db:seed
 *    now seed the type codes and names, with no value recognised until MAIIC
 *    sets its policy on Collateral Types.
 */
class BootstrapReferenceAndStagingRefusalTest extends TestCase
{
    use RefreshDatabase;

    public function test_staging_a_month_with_no_loan_book_is_refused_by_name(): void
    {
        $this->artisan('eir:stage', ['from' => '2026-10', '--user' => 1])
            ->expectsOutputToContain('Staging is refused for October 2026: the system holds no loan book for 2026-10')
            ->assertExitCode(1);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'Loan Book Staged')->count(), 'nothing recorded as staged');
    }

    public function test_the_collateral_types_are_seeded_without_a_value_and_the_sample_passes_its_check(): void
    {
        // db:seed (the test database is seeded) holds them already; from empty, as a bootstrap database was
        $this->assertSame(19, DB::table('collateral_types')->count());
        DB::table('collateral_types')->delete();
        (new CollateralTypesSeeder())->run();
        $this->assertSame(19, DB::table('collateral_types')->count());
        $this->assertSame('Bank Guarantee', DB::table('collateral_types')->where('type_code', '101')->value('type_name'));
        // MAIIC's policy is not seeded: no value is recognised until a person sets it
        $this->assertSame(0, DB::table('collateral_types')->where('standard_haircut', '>', 0)->count());
        $this->assertSame(0, DB::table('collateral_types')->where('realisation_period', '>', 0)->count());

        // a type MAIIC has set is never overwritten, and a second run adds nothing
        DB::table('collateral_types')->where('type_code', '109')->update(['standard_haircut' => 0.7, 'realisation_period' => 18]);
        (new CollateralTypesSeeder())->run();
        $this->assertSame(19, DB::table('collateral_types')->count());
        $this->assertEqualsWithDelta(0.7, (float) DB::table('collateral_types')->where('type_code', '109')->value('standard_haircut'), 1e-9);

        // the sample file of the collateral import now passes its own Check file
        $spec = ImportSpecs::get('collateral');
        $path = tempnam(sys_get_temp_dir(), 'coll') . '.csv';
        file_put_contents($path, (new ImportTemplateBuilder())->csv($spec));
        try {
            $check = (new ImportSpecValidator())->checkFile('collateral', $path, 'collateral_sample.csv');
        } finally {
            @unlink($path);
        }
        $this->assertTrue($check['ok'], json_encode($check['issues']));
        $this->assertSame(0, $check['error_count']);
        $this->assertGreaterThan(0, $check['rows']);

        // and from empty it fails, as it did on a bootstrap database before 14 October 2026
        DB::table('collateral_types')->delete();
        $path = tempnam(sys_get_temp_dir(), 'coll') . '.csv';
        file_put_contents($path, (new ImportTemplateBuilder())->csv(ImportSpecs::get('collateral')));
        try {
            $empty = (new ImportSpecValidator())->checkFile('collateral', $path, 'collateral_sample.csv');
        } finally {
            @unlink($path);
        }
        $this->assertFalse($empty['ok']);
        $this->assertStringContainsString('no values are set up yet', json_encode($empty['issues']));
    }

    public function test_the_bootstrap_and_db_seed_run_the_collateral_seeder(): void
    {
        $this->assertStringContainsString("'CollateralTypesSeeder'", file_get_contents(app_path('Console/Commands/Bootstrap.php')));
        $this->assertStringContainsString('CollateralTypesSeeder::class', file_get_contents(database_path('seeders/DatabaseSeeder.php')));
    }
}
