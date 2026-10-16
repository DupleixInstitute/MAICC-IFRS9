<?php

namespace Tests\Feature\Reports;

use App\Services\Eir\GovernanceService;
use App\Services\Reports\EclBuildUpService;
use App\Services\Reports\Ifrs9NoteDocuments;
use App\Services\Reports\Ifrs9NoteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsGovernedDefaults;
use Tests\TestCase;

/**
 * IFRS 7 Disclosures Specification v1 (16 October 2026), sections (i) and (j)
 * of the IFRS 9 note:
 *
 *  (i) Interest revenue, effective interest method (IFRS 7.20(b)), from the
 *      EIR revenue engine's own locked roll-forward (eir_amortisation),
 *      governed by ifrs7_interest_revenue_basis and ifrs7_interest_revenue_detail.
 *  (j) Sensitivity analysis (IFRS 7.35G): Table 1 the governed macro-economic
 *      scenarios of the FLI month-end close, Table 2 an isolated PD/LGD
 *      stress on the booked ECL (EclBuildUpService), governed by
 *      ifrs7_sensitivity_disclosure (and, for Table 2, the shock-size settings).
 *
 * The book, May to June 2026, portfolio 1: A Stage 1 (EAD 1000, LGD 0.5, PD
 * 0.05, booked ECL 25.00), W Stage 3 (EAD 500, LGD 0.6, booked ECL 300.00).
 * Only June carries an EIR roll-forward row (the note's period is one month).
 */
class InterestRevenueAndSensitivityDisclosureTest extends TestCase
{
    use SeedsGovernedDefaults;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('active')->default(true); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('loan_portfolio_id')->nullable();
            $t->string('contract_id');
            $t->string('customer_name')->nullable();
            $t->string('product_code')->nullable();
            $t->string('product_group')->nullable();
            $t->string('industry_type')->nullable();
            $t->string('industry_code')->nullable();
            $t->string('reporting_period');
            $t->integer('tenor')->default(24);
            $t->double('remaining_tenor')->nullable();
            $t->integer('overdue_days')->default(0);
            $t->decimal('carrying_amount', 20, 2)->default(0);
            $t->decimal('commitments', 20, 2)->nullable();
            $t->decimal('facility_utilisation_rate', 10, 4)->nullable();
            $t->decimal('ecl_value', 20, 2)->nullable();
            $t->decimal('ecl_value_discounted', 20, 2)->nullable();
            $t->decimal('ecl_discounting_effect', 20, 2)->nullable();
            $t->string('ecl_discount_status')->nullable();
            $t->string('ifrs9stage_post_qualitative')->nullable();
            $t->string('calculated_ifrs9_stage')->nullable();
            $t->string('ifrs9stage_pre_qualitative')->nullable();
            $t->double('pd_post_fli')->nullable();
            $t->double('pd_prefli')->nullable();
            $t->double('fli_adj')->nullable();
            $t->string('fli_route')->nullable();
            $t->string('fli_method')->nullable();
            $t->unsignedBigInteger('fli_fit_id')->nullable();
            $t->unsignedBigInteger('fli_set_id')->nullable();
            $t->text('fli_overlay_ids')->nullable();
            $t->double('lgd_value')->nullable();
            $t->double('collection_lgd')->nullable();
            $t->double('customer_lgd')->nullable();
            $t->unsignedBigInteger('ecl_calculation_run_id')->nullable();
        });
        Schema::create('expected_credit_loss', function (Blueprint $t) {
            $t->increments('id'); $t->string('reporting_period'); $t->string('ifrs9_stage'); $t->string('ecl_calculation_level'); $t->integer('ecl_calculation_id')->nullable();
            $t->decimal('total_ecl', 20, 2)->nullable(); $t->decimal('total_ecl_discounted', 20, 2)->nullable(); $t->decimal('total_ead', 20, 2)->nullable(); $t->integer('total_loans')->default(0);
            $t->double('pd_value_used')->nullable(); $t->double('lgd_value_used')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('reporting_periods', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->integer('pd_id')->nullable(); $t->integer('lgd_id')->nullable(); $t->string('pd_calculation_source')->nullable(); $t->string('lgd_calculation_source')->nullable(); });
        Schema::create('eir_amortisation', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7);
            $t->decimal('interest_accrued', 20, 2)->default(0); $t->string('interest_basis')->default('GROSS'); $t->decimal('unwind_amount', 20, 2)->default(0);
            $t->decimal('modification_gain_loss', 20, 2)->default(0);
        });
        Schema::create('gl_interest_postings', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->decimal('interest_income_posted', 20, 2)->default(0);
        });
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        (require base_path('database/migrations/2026_10_13_170000_create_loan_write_offs.php'))->up();
        (require base_path('database/migrations/2026_10_09_100000_create_governed_scenario_sets.php'))->up();
        (require base_path('database/migrations/2026_10_13_100000_fli_module_registry_and_snapshots.php'))->up();

        $this->seedGovernedDefaults();

        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('m', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);
        foreach (['2026-05', '2026-06'] as $p) {
            DB::table('loan_books')->insert(['loan_portfolio_id' => 1, 'contract_id' => 'A', 'customer_name' => 'Customer A', 'product_code' => '1050101', 'reporting_period' => $p,
                'carrying_amount' => 1000, 'ecl_value' => 25, 'ecl_value_discounted' => 25, 'ecl_discounting_effect' => 0, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED',
                'ifrs9stage_post_qualitative' => '1', 'calculated_ifrs9_stage' => '1', 'pd_post_fli' => 0.05, 'pd_prefli' => 0.05, 'lgd_value' => 0.5]);
            DB::table('loan_books')->insert(['loan_portfolio_id' => 1, 'contract_id' => 'W', 'customer_name' => 'Customer W', 'product_code' => '1050101', 'reporting_period' => $p,
                'carrying_amount' => 500, 'ecl_value' => 300, 'ecl_value_discounted' => 300, 'ecl_discounting_effect' => 0, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED',
                'ifrs9stage_post_qualitative' => '3', 'calculated_ifrs9_stage' => '3', 'pd_post_fli' => 0.05, 'pd_prefli' => 0.05, 'lgd_value' => 0.6]);
            DB::table('expected_credit_loss')->insert(['reporting_period' => $p, 'ifrs9_stage' => '1', 'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 1,
                'total_ecl' => 25, 'total_ecl_discounted' => 25, 'total_ead' => 1000, 'total_loans' => 1, 'pd_value_used' => 0.05, 'lgd_value_used' => 0.5, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('expected_credit_loss')->insert(['reporting_period' => $p, 'ifrs9_stage' => '3', 'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 1,
                'total_ecl' => 300, 'total_ecl_discounted' => 300, 'total_ead' => 500, 'total_loans' => 1, 'pd_value_used' => 0.05, 'lgd_value_used' => 0.6, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function amortisation(): void
    {
        DB::table('eir_amortisation')->insert(['contract_id' => 'A', 'reporting_period' => '2026-06', 'interest_accrued' => 100, 'interest_basis' => 'GROSS', 'unwind_amount' => 0]);
        DB::table('eir_amortisation')->insert(['contract_id' => 'W', 'reporting_period' => '2026-06', 'interest_accrued' => 80, 'interest_basis' => 'NET', 'unwind_amount' => 5]);
        DB::table('gl_interest_postings')->insert(['contract_id' => 'A', 'reporting_period' => '2026-06', 'interest_income_posted' => 60]);
        DB::table('gl_interest_postings')->insert(['contract_id' => 'W', 'reporting_period' => '2026-06', 'interest_income_posted' => 110]);
    }

    private function fliClose(): void
    {
        $setId = DB::table('governed_scenario_sets')->insertGetId(['reporting_period' => '2026-06', 'name' => 'Base case', 'version' => 1, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('governed_scenarios')->insert(['set_id' => $setId, 'name' => 'Base', 'weight' => 60, 'is_base' => true, 'order_position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('governed_scenarios')->insert(['set_id' => $setId, 'name' => 'Downside', 'weight' => 40, 'is_base' => false, 'order_position' => 2, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fli_run_snapshots')->insert(['reporting_period' => '2026-06', 'set_id' => $setId, 'status' => 'SIGNED_OFF', 'booked_ecl' => 325, 'weighted_ecl' => 340,
            'per_scenario' => json_encode([['scenario' => 'Base', 'is_base' => true, 'weight' => 60, 'ecl' => 325], ['scenario' => 'Downside', 'is_base' => false, 'weight' => 40, 'ecl' => 365]]),
            'created_at' => now(), 'updated_at' => now()]);
    }

    // -----------------------------------------------------------------
    // (i) Interest revenue, IFRS 7.20(b)
    // -----------------------------------------------------------------

    public function test_interest_revenue_defaults_to_gl_posted_with_the_eir_figure_as_supporting_detail(): void
    {
        $this->amortisation();
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $ir = $r['interest_revenue'];
        $this->assertTrue($ir['available']);
        $this->assertSame(['2026-06'], $ir['periods']);
        $this->assertSame([], $ir['missing_months']);
        $this->assertEquals(100.0, $ir['by_stage'][1]['gross']);
        $this->assertEquals(80.0, $ir['by_stage'][3]['net']);
        $this->assertEquals(5.0, $ir['by_stage'][3]['unwind']);
        $this->assertEquals(185.0, $ir['eir']['total']); // 100 + 80 + 5
        $this->assertEquals(170.0, $ir['gl']);
        $this->assertEquals(15.0, $ir['variance']); // 185 - 170
        $this->assertSame('GL-posted interest income, with the EIR variance as supporting detail', $ir['basis_setting']);
        $this->assertEquals(170.0, $ir['headline'], 'the recommended default headline is the GL-posted figure');
        $this->assertSame('GL-posted interest income, with the EIR variance as supporting detail', $ir['headline_source']);

        $check = collect($r['checks'])->firstWhere('key', 'interest_revenue');
        $this->assertSame('info', $check['level']);
        $this->assertSame('ok', $r['status'], json_encode(collect($r['checks'])->where('level', 'fail')->values()));

        // the documents: section (i), split by stage with the Stage 3 unwind on its own line
        $note = Ifrs9NoteDocuments::build($r, 'full');
        $section = collect(Ifrs9NoteDocuments::payload($note)['sections'])->firstWhere('heading', '(i) Interest revenue, effective interest method');
        $this->assertNotNull($section);
        $rows = collect($section['rows'])->mapWithKeys(fn ($row) => [$row[0] => $row[1]]);
        $this->assertSame('100.00', $rows['Stage 1: gross basis interest']);
        $this->assertSame('80.00', $rows['Stage 3: net basis interest (IFRS 9 5.4.1(b))']);
        $this->assertSame('5.00', $rows['Stage 3: unwind of discount on the allowance']);
        $this->assertSame('185.00', $rows['Total interest revenue, effective interest method']);
        $this->assertSame('170.00', $rows['Of which GL-posted interest income (trial balance)']);
        $this->assertSame('15.00', $rows['Variance (EIR-run less GL-posted)']);

        $html = view('reports.ifrs9_note_pdf', ['note' => $note, 'company' => 'MAIIC', 'logo' => null, 'preparedOn' => '16 Oct 2026', 'preparedBy' => null])->render();
        $this->assertStringContainsString('(i) Interest revenue, effective interest method', $html);
        $this->assertStringContainsString('IFRS 7.20(b)', $html);
    }

    public function test_the_eir_run_basis_can_be_chosen_by_governance_instead_of_the_default(): void
    {
        $this->amortisation();
        DB::table('governance_settings')->where('key', 'ifrs7_interest_revenue_basis')->update(['value' => 'EIR-run interest revenue (the locked engine figure)']);
        $ir = Ifrs9NoteService::build('2026-05', '2026-06')['interest_revenue'];
        $this->assertEquals(185.0, $ir['headline']);
        $this->assertSame('EIR-run interest revenue (the locked engine figure)', $ir['headline_source']);
    }

    public function test_total_only_detail_omits_the_stage_split_from_the_note(): void
    {
        $this->amortisation();
        DB::table('governance_settings')->where('key', 'ifrs7_interest_revenue_detail')->update(['value' => 'Total only']);
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $note = Ifrs9NoteDocuments::build($r, 'full');
        $section = collect(Ifrs9NoteDocuments::payload($note)['sections'])->firstWhere('heading', '(i) Interest revenue, effective interest method');
        $this->assertCount(3, $section['rows']); // total + GL + variance only, no per-stage rows
    }

    public function test_a_month_the_revenue_run_has_not_reached_is_named_and_fails_the_note(): void
    {
        // no eir_amortisation rows inserted for June
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $check = collect($r['checks'])->firstWhere('key', 'interest_revenue');
        $this->assertSame('fail', $check['level']);
        $this->assertStringContainsString('2026-06', $check['detail']);
        $this->assertSame('failed', $r['status']);
    }

    public function test_the_eir_engine_not_being_installed_is_disclosed_honestly(): void
    {
        Schema::drop('eir_amortisation');
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertFalse($r['interest_revenue']['available']);
        $this->assertStringContainsString('not installed', $r['interest_revenue']['message']);
        $this->assertSame('warn', collect($r['checks'])->firstWhere('key', 'interest_revenue')['level']);
    }

    // -----------------------------------------------------------------
    // (j) Sensitivity analysis, IFRS 7.35G
    // -----------------------------------------------------------------

    public function test_sensitivity_shows_both_governed_tables_by_default(): void
    {
        $this->amortisation();
        $this->fliClose();
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $sv = $r['sensitivity'];
        $this->assertTrue($sv['disclosed']);
        $this->assertSame('Macro scenarios and isolated PD/LGD stress (Tables 1 and 2)', $sv['scope']);

        $t1 = $sv['table1'];
        $this->assertTrue($t1['available']);
        $this->assertSame('Base case v1', $t1['set']);
        $base = collect($t1['rows'])->firstWhere('scenario', 'Base');
        $down = collect($t1['rows'])->firstWhere('scenario', 'Downside');
        $this->assertEquals(0.0, $base['effect']);
        $this->assertEquals(40.0, $down['effect']);

        $t2 = $sv['table2'];
        $this->assertTrue($t2['available']);
        $this->assertSame('+50% relative to the booked PD', $t2['pd_option']);
        $this->assertSame('+20 percentage points', $t2['lgd_option']);
        $this->assertEquals(325.0, $t2['booked_ecl']); // 25 (Stage 1) + 300 (Stage 3)
        $pd = collect($t2['lines'])->firstWhere('label', 'PD +50% relative to the booked PD');
        $this->assertEquals(37.5, $pd['by_stage'][1]); // 25 * (0.075/0.05)
        $this->assertEquals(300.0, $pd['by_stage'][3], 'Stage 3 PD is 100%, never shocked');
        $this->assertEquals(337.5, $pd['total']);
        $this->assertEquals(12.5, $pd['effect']);
        $lgd = collect($t2['lines'])->firstWhere('label', 'LGD +20 percentage points');
        $this->assertEquals(35.0, $lgd['by_stage'][1]); // 25 * (0.7/0.5)
        $this->assertEquals(400.0, $lgd['by_stage'][3]); // 300 * (0.8/0.6)
        $this->assertEquals(435.0, $lgd['total']);
        $this->assertEquals(110.0, $lgd['effect']);

        $check = collect($r['checks'])->firstWhere('key', 'sensitivity');
        $this->assertSame('info', $check['level']);

        $note = Ifrs9NoteDocuments::build($r, 'full');
        $sections = collect(Ifrs9NoteDocuments::payload($note)['sections'])->pluck('heading');
        $this->assertContains('(j) Sensitivity analysis: macro-economic scenarios', $sections);
        $this->assertContains('(j) Sensitivity analysis: isolated PD/LGD stress', $sections);
    }

    public function test_governance_can_exclude_the_whole_sensitivity_section(): void
    {
        $this->amortisation();
        $this->fliClose();
        DB::table('governance_settings')->where('key', 'ifrs7_sensitivity_disclosure')->update(['value' => 'Not disclosed']);
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertFalse($r['sensitivity']['disclosed']);
        $note = Ifrs9NoteDocuments::build($r, 'full');
        $this->assertNull($note['sensitivity']);
        $check = collect($r['checks'])->firstWhere('key', 'sensitivity');
        $this->assertSame('info', $check['level']);
    }

    public function test_governance_can_keep_only_the_macro_scenario_table(): void
    {
        $this->amortisation();
        $this->fliClose();
        DB::table('governance_settings')->where('key', 'ifrs7_sensitivity_disclosure')->update(['value' => 'Macro scenarios only (Table 1)']);
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertTrue($r['sensitivity']['table1']['available']);
        $this->assertNull($r['sensitivity']['table2']);
    }

    public function test_no_signed_off_fli_close_is_disclosed_honestly_not_fabricated(): void
    {
        $this->amortisation();
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertFalse($r['sensitivity']['table1']['available']);
        $this->assertStringContainsString('No FLI month-end close', $r['sensitivity']['table1']['message']);
        $check = collect($r['checks'])->firstWhere('key', 'sensitivity');
        $this->assertSame('warn', $check['level']);
    }

    // -----------------------------------------------------------------
    // The generalised re-basing (EclBuildUpService) and the option parsers
    // -----------------------------------------------------------------

    public function test_pd_and_lgd_stress_tie_to_the_booked_ecl_the_same_way_the_prefli_buildup_does(): void
    {
        $stage1 = (object) ['ifrs9stage_post_qualitative' => '1', 'calculated_ifrs9_stage' => null, 'ifrs9stage_pre_qualitative' => null,
            'carrying_amount' => 1000.0, 'commitments' => 0.0, 'facility_utilisation_rate' => null, 'remaining_tenor' => null,
            'pd_prefli' => 0.05, 'fli_adj' => null, 'pd_post_fli' => 0.05, 'lgd_value' => 0.5, 'collection_lgd' => null, 'customer_lgd' => null, 'ecl_value' => 25.0];
        $stage3 = (object) ['ifrs9stage_post_qualitative' => '3', 'calculated_ifrs9_stage' => null, 'ifrs9stage_pre_qualitative' => null,
            'carrying_amount' => 500.0, 'commitments' => 0.0, 'facility_utilisation_rate' => null, 'remaining_tenor' => null,
            'pd_prefli' => 0.05, 'fli_adj' => null, 'pd_post_fli' => 0.05, 'lgd_value' => 0.6, 'collection_lgd' => null, 'customer_lgd' => null, 'ecl_value' => 300.0];

        $this->assertEquals(37.5, EclBuildUpService::pdStress($stage1, 1.5));
        $this->assertEquals(300.0, EclBuildUpService::pdStress($stage3, 1.5), 'Stage 3 PD is never shocked');
        $this->assertEquals(35.0, EclBuildUpService::lgdStress($stage1, 0.20));
        $this->assertEquals(400.0, EclBuildUpService::lgdStress($stage3, 0.20));

        // a loan that does not tie (the book changed since the last ECL run) is never estimated
        $untied = clone $stage1;
        $untied->ecl_value = 999.0;
        $this->assertNull(EclBuildUpService::pdStress($untied, 1.5));
        $this->assertNull(EclBuildUpService::lgdStress($untied, 0.20));

        // a nil LGD has no ratio to stress by
        $nilLgd = clone $stage1;
        $nilLgd->lgd_value = 0.0;
        $nilLgd->ecl_value = 0.0;
        $this->assertNull(EclBuildUpService::lgdStress($nilLgd, 0.20));
    }

    public function test_the_governed_shock_options_parse_to_the_expected_numbers(): void
    {
        $this->assertEquals(1.25, GovernanceService::parsePdShock('+25% relative to the booked PD'));
        $this->assertEquals(1.5, GovernanceService::parsePdShock('+50% relative to the booked PD'));
        $this->assertEquals(2.0, GovernanceService::parsePdShock('+100% relative to the booked PD (doubling)'));
        $this->assertEquals(0.10, GovernanceService::parseLgdShock('+10 percentage points'));
        $this->assertEquals(0.20, GovernanceService::parseLgdShock('+20 percentage points'));
    }
}
