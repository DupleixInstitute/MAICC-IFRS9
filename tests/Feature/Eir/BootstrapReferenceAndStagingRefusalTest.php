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

    public function test_the_collateral_types_carry_the_ecl_models_policy_values_and_the_sample_passes_its_check(): void
    {
        // db:seed (the test database is seeded) holds them already; from empty, as a bootstrap database was
        $this->assertSame(28, DB::table('collateral_types')->count());
        DB::table('collateral_types')->delete();
        (new CollateralTypesSeeder())->run();
        $this->assertSame(28, DB::table('collateral_types')->count());
        $this->assertSame('Bank Guarantee', DB::table('collateral_types')->where('type_code', '101')->value('type_name'));
        // the ten types MAIIC's ECL model of December 2025 prices (ASS4-Security, "Per Collateral Policy")
        // carry its realisation months and the share kept; the rest are not set (MAIIC's policy to set)
        $this->assertSame(10, DB::table('collateral_types')->where('standard_haircut', '>', 0)->count());
        $this->assertEqualsWithDelta(0.70, (float) DB::table('collateral_types')->where('type_code', '109')->value('standard_haircut'), 1e-9);
        $this->assertSame(18, (int) DB::table('collateral_types')->where('type_code', '109')->value('realisation_period'));
        $this->assertSame(CollateralTypesSeeder::SOURCE, DB::table('collateral_types')->where('type_code', '108')->value('description'));
        $this->assertEqualsWithDelta(0.0, (float) DB::table('collateral_types')->where('type_code', '111')->value('standard_haircut'), 1e-9);

        // a type MAIIC has set is never overwritten, and a second run adds nothing
        DB::table('collateral_types')->where('type_code', '111')->update(['standard_haircut' => 0.6, 'realisation_period' => 12]);
        (new CollateralTypesSeeder())->run();
        $this->assertSame(28, DB::table('collateral_types')->count());
        $this->assertEqualsWithDelta(0.6, (float) DB::table('collateral_types')->where('type_code', '111')->value('standard_haircut'), 1e-9);

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

    public function test_the_settings_seed_in_full_after_the_currency_seeder_has_set_the_currency(): void
    {
        // the bootstrap runs MaiicCurrencySeeder before SettingsTableSeeder; the one bulk insert used to fail
        // on "currency" and leave a fresh install with no company name, timezone or date format
        DB::table('settings')->delete();
        DB::table('settings')->insert(['name' => 'Currency', 'setting_key' => 'currency', 'setting_value' => '37', 'category' => 'general', 'type' => 'text', 'options' => '', 'class' => '', 'required' => '1', 'db_columns' => '', 'displayed' => '1', 'rules' => '']);
        (new \Database\Seeders\SettingsTableSeeder())->run();
        $this->assertSame('Malawi Agricultural and Industrial Investment Corporation plc', DB::table('settings')->where('setting_key', 'company_name')->value('setting_value'));
        $this->assertSame('37', DB::table('settings')->where('setting_key', 'currency')->value('setting_value'));
        $this->assertSame('YYYY-mm-dd', DB::table('settings')->where('setting_key', 'date_format')->value('setting_value'));
        $count = DB::table('settings')->count();
        (new \Database\Seeders\SettingsTableSeeder())->run();
        $this->assertSame($count, DB::table('settings')->count());
    }

    public function test_the_bootstrap_loads_the_s_and_p_grading_of_the_ecl_model_as_a_draft(): void
    {
        $files = glob(base_path('docs/bootstrap/models/*S&P grading*.xlsx')) ?: [];
        $this->assertCount(1, $files);
        $profile = app(\App\Services\Ifrs9\SpGradesImportService::class)->import($files[0], basename($files[0]), 'S&P test', null);
        $this->assertFalse((bool) $profile->is_active);
        $this->assertSame(8, $profile->mappings()->count());
        $this->assertSame(80, DB::table('grade_tenor_pd')->whereIn('grade_mapping_id', $profile->mappings()->pluck('id'))->count());
        $this->assertStringContainsString("'3c S&P grading'", file_get_contents(app_path('Console/Commands/Bootstrap.php')));
    }
}
