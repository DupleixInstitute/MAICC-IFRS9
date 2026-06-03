<?php

namespace Tests\Feature\Ecl;

use App\Http\Controllers\ExpectedCreditLossController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Golden-number coverage for ExpectedCreditLossController::calculateECL.
 *
 * The ECL engine is raw SQL with no extractable pure function, so the only
 * honest regression test is to run the real method against a known dataset
 * and assert the persisted numbers byte-for-byte against values computed by
 * hand below.
 *
 * Isolation: this test stands up its own in-memory sqlite schema (only the
 * tables calculateECL touches) and never uses RefreshDatabase, so it neither
 * runs the full migration set nor touches the developer's MySQL database.
 * calculateECL's MySQL named lock degrades to a no-op on sqlite by design.
 */
class EclGoldenNumberTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Route every query in this test at a private in-memory sqlite db.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->buildSchema();
    }

    private function buildSchema(): void
    {
        Schema::create('loan_portfolios', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('industry_types', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code')->nullable();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('reporting_period')->nullable();
            $t->unsignedInteger('loan_portfolio_id')->nullable();
            $t->string('industry_code')->nullable();
            $t->double('pd_prefli')->nullable();
            $t->double('pd_post_fli')->nullable();
            $t->double('customer_lgd')->nullable();
            $t->double('collection_lgd')->nullable();
            $t->double('carrying_amount')->nullable();
            $t->double('commitments')->nullable();
            $t->double('facility_utilisation_rate')->nullable();
            $t->double('lgd_value')->nullable();
            $t->double('ecl_value')->nullable();
            $t->string('ifrs9stage_pre_qualitative')->nullable();
            $t->timestamps();
        });

        Schema::create('expected_credit_loss', function (Blueprint $t) {
            $t->increments('id');
            $t->string('reporting_period')->nullable();
            $t->string('ifrs9_stage')->nullable();
            $t->string('ecl_calculation_level')->nullable();
            $t->unsignedInteger('ecl_calculation_id')->nullable();
            $t->string('ecl_calculation_code')->nullable();
            $t->double('total_ead')->nullable();
            $t->double('total_ecl')->nullable();
            $t->double('lgd_value_used')->nullable();
            $t->double('pd_value_used')->nullable();
            $t->unsignedInteger('total_loans')->nullable();
            $t->string('last_reporting_period')->nullable();
            $t->timestamps();
        });

        Schema::create('reporting_periods', function (Blueprint $t) {
            $t->increments('id');
            $t->string('period')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('reporting_year')->nullable();
            $t->integer('reporting_month')->nullable();
            $t->boolean('ecl_calculated')->default(false);
            $t->double('ecl_calculation_time')->nullable();
            $t->string('ecl_calculation_level')->nullable();
            $t->unsignedInteger('ecl_calculation_id')->nullable();
            $t->string('ecl_calculation_code')->nullable();
            $t->timestamps();
        });

        DB::table('loan_portfolios')->insert([['id' => 1, 'name' => 'Retail'], ['id' => 2, 'name' => 'SME']]);
        DB::table('industry_types')->insert([['id' => 1, 'code' => 'AGRI', 'name' => 'Agriculture']]);
    }

    /**
     * Seed the canonical book. Stages 1/2/3 under portfolio 1 / 2025-11 are
     * the subject under test; the last two rows are noise that must be
     * excluded by the period and portfolio filters.
     */
    private function seedCanonicalBook(): void
    {
        DB::table('loan_books')->insert([
            // Stage 1 — A: pd falls back to pd_prefli; no commitments/util.
            ['reporting_period' => '2025-11', 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => '1',
             'pd_prefli' => 0.02, 'pd_post_fli' => 0.05, 'customer_lgd' => 0.50, 'collection_lgd' => 0.40,
             'carrying_amount' => 100000, 'commitments' => 0, 'facility_utilisation_rate' => null],

            // Stage 1 — B: pd_prefli null -> COALESCE to pd_post_fli; EAD adds commitments * util.
            ['reporting_period' => '2025-11', 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => '1',
             'pd_prefli' => null, 'pd_post_fli' => 0.10, 'customer_lgd' => 0.70, 'collection_lgd' => 0.50,
             'carrying_amount' => 200000, 'commitments' => 50000, 'facility_utilisation_rate' => 0.5],

            // Stage 2 — C.
            ['reporting_period' => '2025-11', 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => '2',
             'pd_prefli' => 0.20, 'pd_post_fli' => 0.25, 'customer_lgd' => 0.60, 'collection_lgd' => 0.60,
             'carrying_amount' => 300000, 'commitments' => 0, 'facility_utilisation_rate' => 1],

            // Stage 3 — D.
            ['reporting_period' => '2025-11', 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => '3',
             'pd_prefli' => 1.0, 'pd_post_fli' => 1.0, 'customer_lgd' => 0.90, 'collection_lgd' => 0.80,
             'carrying_amount' => 150000, 'commitments' => 0, 'facility_utilisation_rate' => null],

            // Noise: same portfolio, different period — must be excluded.
            ['reporting_period' => '2025-10', 'loan_portfolio_id' => 1, 'ifrs9stage_pre_qualitative' => '1',
             'pd_prefli' => 0.99, 'pd_post_fli' => 0.99, 'customer_lgd' => 0.99, 'collection_lgd' => 0.99,
             'carrying_amount' => 9999999, 'commitments' => 0, 'facility_utilisation_rate' => null],

            // Noise: same period, different portfolio — must be excluded.
            ['reporting_period' => '2025-11', 'loan_portfolio_id' => 2, 'ifrs9stage_pre_qualitative' => '1',
             'pd_prefli' => 0.99, 'pd_post_fli' => 0.99, 'customer_lgd' => 0.99, 'collection_lgd' => 0.99,
             'carrying_amount' => 8888888, 'commitments' => 0, 'facility_utilisation_rate' => null],
        ]);
    }

    private function runEcl(string $pdType, string $lgdType): void
    {
        $request = Request::create('/expected-credit-loss/calculations', 'POST', [
            'ecl_calculation_level' => 'portfolio',
            'ecl_calculation_id'    => 1,
            'reporting_period'      => '2025-11-01',
            'pd_type'               => $pdType,
            'lgd_type'              => $lgdType,
        ]);

        app(ExpectedCreditLossController::class)->calculateECL($request);
    }

    private function eclRow(string $stage): object
    {
        return DB::table('expected_credit_loss')
            ->where('reporting_period', '2025-11')
            ->where('ecl_calculation_level', 'portfolio')
            ->where('ecl_calculation_id', 1)
            ->where('ifrs9_stage', $stage)
            ->first();
    }

    /** @test */
    public function it_computes_golden_ecl_per_stage_for_prefli_collection_lgd(): void
    {
        $this->seedCanonicalBook();
        $this->runEcl('pd_prefli', 'collection_lgd');

        // ---- Stage 1: loans A + B ----
        // A: pd=0.02 lgd=0.40 carry=100000 -> ecl=800,   ead=100000
        // B: pd=0.10 lgd=0.50 carry=200000 -> ecl=10000,  ead=200000+50000*0.5=225000
        $s1 = $this->eclRow('1');
        $this->assertNotNull($s1, 'Stage 1 ECL row missing');
        $this->assertEqualsWithDelta(325000.0, (float) $s1->total_ead, 0.001);
        $this->assertEqualsWithDelta(10800.0,  (float) $s1->total_ecl, 0.001);
        $this->assertEqualsWithDelta(0.06,     (float) $s1->pd_value_used, 1e-9);  // avg(0.02,0.10)
        $this->assertEqualsWithDelta(0.45,     (float) $s1->lgd_value_used, 1e-9); // avg(0.40,0.50)
        $this->assertEquals(2, (int) $s1->total_loans);

        // ---- Stage 2: loan C ----
        $s2 = $this->eclRow('2');
        $this->assertEqualsWithDelta(300000.0, (float) $s2->total_ead, 0.001);
        $this->assertEqualsWithDelta(36000.0,  (float) $s2->total_ecl, 0.001); // 0.20*0.60*300000
        $this->assertEqualsWithDelta(0.20,     (float) $s2->pd_value_used, 1e-9);
        $this->assertEqualsWithDelta(0.60,     (float) $s2->lgd_value_used, 1e-9);
        $this->assertEquals(1, (int) $s2->total_loans);

        // ---- Stage 3: loan D ----
        $s3 = $this->eclRow('3');
        $this->assertEqualsWithDelta(150000.0,  (float) $s3->total_ead, 0.001);
        $this->assertEqualsWithDelta(120000.0,  (float) $s3->total_ecl, 0.001); // 1.0*0.80*150000
        $this->assertEqualsWithDelta(1.0,       (float) $s3->pd_value_used, 1e-9);
        $this->assertEqualsWithDelta(0.80,      (float) $s3->lgd_value_used, 1e-9);
        $this->assertEquals(1, (int) $s3->total_loans);

        // The two noise rows (other period / other portfolio) must not leak in.
        $this->assertNull(
            DB::table('expected_credit_loss')->where('reporting_period', '2025-10')->first(),
            'Loans from another period leaked into the ECL result'
        );

        // STEP 4 must flag the period calculated.
        $rp = DB::table('reporting_periods')->where('period', '2025-11-01')->first();
        $this->assertNotNull($rp);
        $this->assertEquals(1, (int) $rp->ecl_calculated);
    }

    /** @test */
    public function it_does_not_overwrite_post_fli_pd_when_running_in_prefli_mode(): void
    {
        $this->seedCanonicalBook();

        // Loan A: pd_prefli=0.02, pd_post_fli=0.05. The old code wrote the
        // chosen PD back onto pd_post_fli, corrupting the FLI engine's output.
        $this->runEcl('pd_prefli', 'collection_lgd');

        $a = DB::table('loan_books')
            ->where('reporting_period', '2025-11')
            ->where('loan_portfolio_id', 1)
            ->where('carrying_amount', 100000)
            ->first();

        $this->assertEqualsWithDelta(0.05, (float) $a->pd_post_fli, 1e-9,
            'pd_post_fli must remain the FLI engine value, not be overwritten by the ECL run');

        // The recompute is still expected to refresh lgd_value / ecl_value.
        $this->assertEqualsWithDelta(0.40, (float) $a->lgd_value, 1e-9);
        $this->assertEqualsWithDelta(800.0, (float) $a->ecl_value, 0.001);
    }

    /** @test */
    public function lgd_both_multiplies_customer_and_collection_lgd(): void
    {
        $this->seedCanonicalBook();
        $this->runEcl('pd_prefli', 'both');

        // Stage 1 loan A: customer_lgd=0.50, collection_lgd=0.40 -> lgd=0.20
        //                 pd=0.02, carry=100000 -> ecl=0.02*0.20*100000=400
        // Stage 1 loan B: customer_lgd=0.70, collection_lgd=0.50 -> lgd=0.35
        //                 pd=0.10, carry=200000 -> ecl=0.10*0.35*200000=7000
        $s1 = $this->eclRow('1');
        $this->assertEqualsWithDelta(7400.0, (float) $s1->total_ecl, 0.001);
        $this->assertEqualsWithDelta(0.275, (float) $s1->lgd_value_used, 1e-9); // avg(0.20,0.35)
    }
}
