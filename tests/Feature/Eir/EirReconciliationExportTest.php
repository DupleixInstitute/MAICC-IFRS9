<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirReconciliationController;
use App\Models\User;
use App\Services\Eir\EirGlReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The reconciliation downloads (spec v3 section 9.1, the Exports screen row).
 *
 * The controller is called directly, the same pattern as the other EIR
 * controller tests, so the payload and the audit trail are tested without
 * standing up the permission tables. Which permission the route demands is
 * asserted from the registered middleware in EirGovernanceControllerTest.
 */
class EirReconciliationExportTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->timestamps(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('portfolio')->nullable(); $t->double('drawn_amount')->default(0); $t->double('contractual_rate')->nullable(); $t->double('eir_effective_annual')->nullable(); $t->string('origination_date')->nullable(); $t->string('interest_start_date')->nullable(); $t->string('moratorium_type')->nullable(); $t->integer('moratorium_months')->nullable(); $t->string('moratorium_from')->nullable(); $t->double('reference_rate_at_origination')->nullable(); $t->double('spread_over_prime')->nullable(); $t->double('markup')->nullable(); $t->timestamps(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period', 7); $t->double('opening_gross'); $t->double('interest_accrued'); $t->string('interest_basis')->default('GROSS'); $t->double('unwind_amount')->default(0); $t->double('cash_received')->default(0); $t->string('cash_source')->default('IMPORTED'); $t->double('modification_gain_loss')->default(0); $t->double('closing_gross')->default(0); $t->double('ecl_allowance')->default(0); $t->timestamps(); });
        Schema::create('gl_interest_postings', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('gl_account_code')->nullable(); $t->integer('period_year'); $t->integer('period_month'); $t->double('interest_income_posted'); $t->timestamps(); });
        $this->createLoanBookSchema();
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();

        DB::table('users')->insert(['id' => 10, 'name' => 'Finance Officer', 'email' => 'finance@example.test',
            'created_at' => now(), 'updated_at' => now()]);

        // JVD Agro, April 2026: a clean month, plus one account the ledger has
        // nothing for, so the download carries both a match and an exception.
        DB::table('contract_eir')->insert(['contract_id' => 'JVD-AGRO', 'portfolio' => 'MAIIC',
            'drawn_amount' => 570964515.32, 'contractual_rate' => 0.10,
            'eir_effective_annual' => pow(1 + 0.10 / 12, 12) - 1, 'origination_date' => '2025-05-08',
            'created_at' => now(), 'updated_at' => now()]);
        foreach ([['2026-03', 570964515.32], ['2026-04', 575657374.35]] as [$period, $balance]) {
            DB::table('loan_books')->insert(['contract_id' => 'JVD-AGRO', 'customer_name' => 'JVD Agro Limited',
                'reporting_period' => $period, 'interest_rate' => 10.00, 'carrying_amount' => $balance,
                'principal_balance' => $balance, 'disbursed' => $balance, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('gl_interest_postings')->insert(['contract_id' => 'JVD-AGRO', 'gl_account_code' => '4501',
            'period_year' => 2026, 'period_month' => 4, 'interest_income_posted' => 4692858.90,
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function export(string $format): mixed
    {
        $this->actingAs(User::findOrFail(10));
        $request = Request::create('/eir-reconciliation/export', 'GET', ['period' => '2026-04', 'format' => $format]);

        return (new EirReconciliationController())->export($request, new EirGlReconciliationService());
    }

    public function test_the_workbook_download_is_named_for_the_period_and_the_export_is_audit_logged(): void
    {
        $response = $this->export('xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringContainsString('MAIIC-EIR-Interest-Reconciliation-2026-04.xlsx',
            $response->headers->get('content-disposition'));

        $log = DB::table('audit_logs')->where('action', 'EIR Reconciliation Exported')->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-04', $log->reporting_period);
        $this->assertSame('ALL_PORTFOLIOS', $log->scope);
        $meta = json_decode($log->meta, true);
        $this->assertSame(1, $meta['rows']);
        $this->assertSame('ACT/365', $meta['day_count']);
        $this->assertEquals(1.0, $meta['tolerance_percent']);
        $this->assertSame(1, $meta['causes']['WITHIN_TOLERANCE']);
        $values = json_decode($log->new_values, true);
        $this->assertSame('xlsx', $values['format']);
        $this->assertSame(4692858.90, $values['gl_posted']);
        $this->assertSame(4692859.03, $values['expected_interest']);
    }

    public function test_the_pdf_download_returns_a_pdf_for_the_period(): void
    {
        $response = $this->export('pdf');

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('MAIIC-EIR-Interest-Reconciliation-2026-04.pdf',
            $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_format_the_engine_does_not_produce_is_refused(): void
    {
        $this->actingAs(User::findOrFail(10));
        $request = Request::create('/eir-reconciliation/export', 'GET', ['period' => '2026-04', 'format' => 'csv']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new EirReconciliationController())->export($request, new EirGlReconciliationService());
    }
}
