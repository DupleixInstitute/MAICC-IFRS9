<?php

namespace Tests\Feature\Rbm;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Rbm\RbmReturnService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The provisional RBM return (directive s.17): the directive's day bands by
 * term of facility; the minimum provision per class against the IFRS 9
 * allowance with the higher of the two; interest in suspense as the
 * contractual interest posted on non-performing facilities over the EIR
 * interest on the net basis.
 */
class RbmReturnServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->string('reporting_period'); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->date('overdue_principal_date')->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('principal_balance', 20, 2)->default(0); $t->decimal('interest_to_date', 20, 2)->nullable(); $t->decimal('ecl_value', 20, 2)->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('contract_status')->nullable(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->decimal('interest_accrued', 20, 2); });
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('e', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $rows = [
            ['A', 12, 0, 1000, 0],      // short-term, current: pass
            ['B', 12, 100, 1000, 100],  // short-term, 100 days: substandard (20 percent)
            ['C', 36, 100, 1000, 0],    // medium-term, 100 days: special mention (5 percent)
            ['D', 36, 400, 1000, 600],  // medium-term, 400 days: doubtful (50 percent)
            ['E', 12, 400, 1000, 1000], // short-term, 400 days: loss (100 percent)
        ];
        foreach ($rows as [$id, $tenor, $dpd, $ca, $ecl]) {
            DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => $tenor, 'overdue_days' => $dpd, 'carrying_amount' => $ca, 'principal_balance' => $ca, 'ecl_value' => $ecl, 'ifrs9stage_post_qualitative' => $dpd > 90 ? '3' : '1']);
        }
        // contractual interest posted on B in August (120 charged) against 40 recognised on the net basis
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => '1', 'account' => '000000000000B', 'row_date' => '2026-08-31', 'payload' => json_encode(['TRANSAMT' => '-120.00', 'TRANTYPE' => '303', 'AC_GLCODE' => '1050101', 'DELETE_FLAG' => 'N']), 'row_hash' => 'x', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('eir_amortisation')->insert(['contract_id' => 'B', 'reporting_period' => '2026-08', 'interest_accrued' => 40]);
    }

    public function test_the_directive_bands_by_term(): void
    {
        $this->assertSame('Pass', RbmReturnService::classify(30, 12));
        $this->assertSame('Special mention', RbmReturnService::classify(31, 12));
        $this->assertSame('Substandard', RbmReturnService::classify(91, 12));
        $this->assertSame('Special mention', RbmReturnService::classify(91, 36));
        $this->assertSame('Substandard', RbmReturnService::classify(181, 36));
        $this->assertSame('Doubtful', RbmReturnService::classify(181, 12));
        $this->assertSame('Doubtful', RbmReturnService::classify(361, 36));
        $this->assertSame('Loss', RbmReturnService::classify(361, 12));
        $this->assertSame('Loss', RbmReturnService::classify(721, 36));
    }

    public function test_the_return_fills_the_sections_from_the_book(): void
    {
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $this->assertSame('PROVISIONAL', $r['status']);
        $classes = array_column($r['sections']['A_classification'], 'total', 'class');
        $this->assertSame(1, $classes['Pass']['accounts']);
        $this->assertSame(1, $classes['Special mention']['accounts']);
        $this->assertSame(1, $classes['Substandard']['accounts']);
        $this->assertSame(1, $classes['Doubtful']['accounts']);
        $this->assertSame(1, $classes['Loss']['accounts']);
        $this->assertEqualsWithDelta(0.6, $r['npl_ratio'], 1e-6);
        $b = array_column($r['sections']['B_provisions'], null, 'class');
        $this->assertEquals(50, $b['Special mention']['minimum_provision']);
        $this->assertEquals(200, $b['Substandard']['minimum_provision']);
        $this->assertEquals(100, $b['Substandard']['shortfall']);      // held 100 against 200
        $this->assertEquals(0, $b['Doubtful']['shortfall']);           // held 600 against 500
        $this->assertEquals(1750, $r['sections']['B_totals']['minimum_provision']);
        $this->assertEquals(1700, $r['sections']['B_totals']['ifrs9_allowance']);
        $this->assertEquals(1750, $r['sections']['B_totals']['higher_of_the_two']);
        $c = $r['sections']['C_interest_in_suspense'];
        $this->assertEquals(120, $c['interest_posted_in_period']);
        $this->assertEquals(40, $c['interest_recognised_eir_net_basis']);
        $this->assertEquals(80, $c['interest_in_suspense']);
        $this->assertSame('Substandard', $r['per_loan']['B']['class']);
        $this->assertStringContainsString('PROVISIONAL', $r['notes'][0]);
    }
}
