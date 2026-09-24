<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirDrawdownController;
use App\Services\Eir\DisbursementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The Drawdowns screen, called directly (the same pattern as
 * EirGovernanceControllerTest) so the props are tested without standing up the
 * full permission tables. It is a read-only screen, so it demands the EIR view
 * permission.
 */
class EirDrawdownControllerTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('contract_disbursements', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('sub_account_no', 20)->nullable();
            $t->integer('tranche_no')->nullable();
            $t->string('disbursement_date');
            $t->double('amount');
            $t->string('reference')->nullable();
            $t->string('source_system', 40)->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id', 120)->nullable();
            $t->integer('import_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->double('approved_amount')->nullable();
            $t->double('drawn_amount')->nullable();
            $t->timestamps();
        });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('reporting_period')->nullable();
            $t->double('approved_amount')->default(0);
            $t->double('disbursed')->default(0);
            $t->double('commitments')->default(0);
            $t->timestamps();
        });

        DB::table('contract_eir')->insert([
            ['contract_id' => 'SMALL-UNDRAWN', 'approved_amount' => 300_000_000, 'drawn_amount' => 290_000_000],
            ['contract_id' => 'BIG-UNDRAWN', 'approved_amount' => 1_055_473_655, 'drawn_amount' => 297_161_905],
        ]);
        DB::table('loan_books')->insert([
            ['contract_id' => 'SMALL-UNDRAWN', 'customer_name' => 'Smaller Ltd', 'reporting_period' => '2026-08', 'approved_amount' => 300_000_000, 'disbursed' => 290_000_000],
            ['contract_id' => 'BIG-UNDRAWN', 'customer_name' => 'Lake Malawi Aquaculture', 'reporting_period' => '2026-08', 'approved_amount' => 1_055_473_655, 'disbursed' => 297_161_905],
        ]);
        DB::table('contract_disbursements')->insert([
            ['contract_id' => 'BIG-UNDRAWN', 'tranche_no' => 1, 'disbursement_date' => '2025-06-30', 'amount' => 150_000_000, 'reference' => 'PV-1', 'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => 'A', 'created_at' => now(), 'updated_at' => now()],
            ['contract_id' => 'BIG-UNDRAWN', 'tranche_no' => 2, 'disbursement_date' => '2025-09-15', 'amount' => 147_161_905, 'reference' => 'PV-2', 'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => 'B', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** The Inertia props, read off the response the controller returned. */
    private function props(string $query = ''): array
    {
        $request = Request::create('/eir-drawdowns' . ($query === '' ? '' : '?' . $query), 'GET');
        $response = app(EirDrawdownController::class)->index($request, app(DisbursementService::class));

        $property = new ReflectionProperty($response, 'props');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    public function test_the_screen_demands_the_eir_view_permission(): void
    {
        $middleware = array_column((new EirDrawdownController())->getMiddleware(), 'middleware');

        $this->assertContains('auth', $middleware);
        $this->assertContains('permission:eir.view', $middleware);
    }

    public function test_the_screen_shows_each_facility_its_tranches_and_the_total_row(): void
    {
        $props = $this->props('as_of=2026-08-31');

        $this->assertSame('2026-08-31', $props['asOf']);
        $this->assertCount(2, $props['facilities']);

        // Largest undrawn commitment first.
        $this->assertSame('BIG-UNDRAWN', $props['facilities'][0]['contract_id']);
        $this->assertSame('Lake Malawi Aquaculture', $props['facilities'][0]['customer']);
        $this->assertEqualsWithDelta(758_311_750, $props['facilities'][0]['undrawn'], 0.01);
        $this->assertCount(2, $props['facilities'][0]['tranches']);
        $this->assertSame('PV-1', $props['facilities'][0]['tranches'][0]['reference']);
        $this->assertSame(DisbursementService::SOURCE_DRAWDOWNS, $props['facilities'][0]['drawn_source']);
        $this->assertSame(DisbursementService::SOURCE_LOAN_BOOK, $props['facilities'][1]['drawn_source']);

        $this->assertEqualsWithDelta(1_355_473_655, $props['totals']['approved'], 0.01);
        $this->assertEqualsWithDelta(587_161_905, $props['totals']['drawn'], 0.01);
        $this->assertEqualsWithDelta(768_311_750, $props['totals']['undrawn'], 0.01);
        $this->assertSame(2, $props['totals']['tranches']);
        $this->assertSame(1, $props['totals']['from_drawdowns']);
        $this->assertSame(1, $props['totals']['from_loan_book']);

        // Every source has a label in plain words for the screen.
        $this->assertSame('Drawdown rows loaded', $props['sourceLabels'][DisbursementService::SOURCE_DRAWDOWNS]);
        $this->assertSame('Not known', $props['sourceLabels'][DisbursementService::SOURCE_NONE]);
    }

    public function test_the_date_counts_only_the_tranches_drawn_by_then_and_the_search_narrows_the_list(): void
    {
        $earlier = $this->props('as_of=2025-08-31');
        $facility = collect($earlier['facilities'])->firstWhere('contract_id', 'BIG-UNDRAWN');

        $this->assertEqualsWithDelta(150_000_000, $facility['drawn'], 0.01);
        $this->assertEqualsWithDelta(905_473_655, $facility['undrawn'], 0.01);
        $this->assertSame(1, $facility['tranche_count']);

        $narrowed = $this->props('as_of=2026-08-31&search=BIG');
        $this->assertCount(1, $narrowed['facilities']);
        $this->assertSame('BIG', $narrowed['search']);
    }

    /** A date the operator did not write properly falls back to today, never to nonsense. */
    public function test_an_unreadable_date_falls_back_to_today(): void
    {
        $props = $this->props('as_of=31/08/2026');

        $this->assertSame(now()->toDateString(), $props['asOf']);
    }
}
