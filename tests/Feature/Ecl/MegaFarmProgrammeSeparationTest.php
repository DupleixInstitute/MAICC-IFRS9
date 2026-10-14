<?php

namespace Tests\Feature\Ecl;

use App\Services\Eir\GovernanceService;
use App\Services\MegaFarm\MegaFarmEclService;
use App\Services\Pd\PdSegmentationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The Mega Farm programme is provided by the Mega Farm module and kept out of
 * MAIIC's own PD, LGD and ECL (spec v4 section 16, D30; results review of 13
 * October 2026). Before this the bootstrap ran every engine on one blended
 * portfolio, so the programme's defaults entered MAIIC's measures, and the
 * module filled a missing measure with typed numbers (a scalar from the 2025
 * statements, an LGD of 0.45).
 */
class MegaFarmProgrammeSeparationTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            foreach (['contract_id', 'reporting_period', 'product_group', 'product_code', 'ifrs9stage_post_qualitative', 'calculated_ifrs9_stage', 'fli_route', 'fli_method'] as $c) { $t->string($c)->nullable(); }
            $t->integer('loan_portfolio_id')->nullable();
            foreach (['carrying_amount', 'commitments', 'facility_utilisation_rate', 'lgd_value', 'collection_lgd', 'pd_prefli', 'pd_post_fli', 'ead', 'ecl_value', 'repayments', 'interest_rate'] as $c) { $t->double($c)->nullable(); }
            $t->string('contract_status')->nullable();
        });
        Schema::create('megafarm_ecl_runs', function (Blueprint $t) { $t->increments('id'); $t->string('reporting_period'); $t->string('scope'); $t->string('method'); $t->integer('loans'); $t->double('gross'); $t->double('programme_ecl'); $t->double('share'); $t->double('maiic_ecl'); $t->text('declined')->nullable(); $t->text('basis')->nullable(); $t->integer('run_by')->nullable(); $t->string('approver_label')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
    }

    private function loan(string $id, string $period, int $portfolio, string $gl, string $group, string $stage, float $amount, ?float $pd = null): void
    {
        DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => $period, 'loan_portfolio_id' => $portfolio, 'product_code' => $gl, 'product_group' => $group,
            'ifrs9stage_post_qualitative' => $stage, 'calculated_ifrs9_stage' => $stage, 'carrying_amount' => $amount, 'commitments' => 0, 'pd_prefli' => $pd]);
    }

    public function test_the_programme_portfolio_is_kept_out_of_the_maiic_scope_and_a_blended_book_is_refused(): void
    {
        $this->loan('M1', '2025-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100);
        $this->loan('F1', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 50);
        $this->assertSame([2], MegaFarmEclService::programmePortfolios('2025-12'));
        $this->assertSame([], MegaFarmEclService::mixedPortfolios('2025-12'));
        $this->assertSame([1], (new PdSegmentationService(new GovernanceService()))->scope('2025-12'));

        // the same book left in one portfolio: the MAIIC engines refuse it rather than measure the programme as MAIIC's
        DB::table('loan_books')->update(['loan_portfolio_id' => 1]);
        $this->assertSame([1], MegaFarmEclService::mixedPortfolios('2025-12'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('run ifrs9:segment-portfolios first');
        (new PdSegmentationService(new GovernanceService()))->scope('2025-12');
    }

    public function test_without_a_programme_book_a_year_earlier_the_scalar_is_declined_not_typed_in(): void
    {
        $this->loan('M1', '2025-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100, 0.02);
        $this->loan('F1', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 50);
        $r = (new MegaFarmEclService(new GovernanceService()))->run('2025-12');

        $this->assertNotNull($r['declined']);
        $this->assertStringContainsString('cannot be measured', $r['declined']);
        $this->assertArrayNotHasKey('measured_scalar', $r['basis']);
        $this->assertSame(0.0, $r['programme_ecl']);
        $this->assertNull(DB::table('loan_books')->where('contract_id', 'F1')->value('ecl_value'));
    }

    public function test_the_programme_lgd_is_its_own_cohort_and_a_missing_cohort_declines(): void
    {
        // a programme book a year earlier: one Stage 1 loan (for the scalar) that defaulted, and no Stage 3 cohort
        $this->loan('M1', '2024-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100, 0.02);
        $this->loan('F1', '2024-12', 2, '1050302', 'Mega Farm Seed Loans', '1', 50);
        $this->loan('M1', '2025-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100, 0.02);
        $this->loan('F1', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 50);
        DB::table('loan_books')->where('product_code', '1050101')->update(['collection_lgd' => 0.3]);
        $r = (new MegaFarmEclService(new GovernanceService()))->run('2025-12');

        // the scalar is measured (the loan that was Stage 1 a year ago is in default), but there is no
        // Stage 3 programme cohort to follow, so the LGD cannot be measured: MAIIC's own 0.3 is not borrowed
        $this->assertNotNull($r['scalar']);
        $this->assertStringContainsString('no Stage 3 programme loan', (string) $r['declined']);
        $this->assertSame(0.0, $r['programme_ecl']);
    }

    public function test_a_defaulted_programme_loan_carries_a_pd_of_one_whatever_the_scalar(): void
    {
        // a programme book a year earlier in which nothing defaulted from Stage 1 would give a nil scalar;
        // here one of two Stage 1 loans defaulted, so the scalar is 0.5 / 0.8 = 0.625, below one
        $this->loan('M1', '2024-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100, 0.8);
        $this->loan('F1', '2024-12', 2, '1050302', 'Mega Farm Seed Loans', '1', 50);
        $this->loan('F2', '2024-12', 2, '1050302', 'Mega Farm Seed Loans', '1', 50);
        $this->loan('F3', '2024-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 40);
        $this->loan('M1', '2025-12', 1, '1050101', 'MAIIC Agricultural Loans', '1', 100, 0.8);
        $this->loan('M3', '2025-12', 1, '1050101', 'MAIIC Agricultural Loans', '3', 10, 1.0);
        $this->loan('F1', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 50);
        $this->loan('F2', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '1', 50);
        $this->loan('F3', '2025-12', 2, '1050302', 'Mega Farm Seed Loans', '3', 20);
        $r = (new MegaFarmEclService(new GovernanceService()))->run('2025-12');

        $this->assertNull($r['declined']);
        $this->assertEqualsWithDelta(0.625, $r['scalar'], 1e-9);
        $this->assertSame(1.0, $r['pd_by_stage']['3']);
        $this->assertEqualsWithDelta(0.5, $r['pd_by_stage']['1'], 1e-9);
    }
}
