<?php

namespace Tests\Feature\Eir;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The P2 migration applies and reverses cleanly on an in-memory schema that
 * holds only the two tables it depends on. Never run against the .env
 * database: the point is to prove down() before anyone needs it.
 */
class ReferenceRateMigrationTest extends TestCase
{
    protected $seed = false;

    private const MIGRATION = __DIR__ . '/../../../database/migrations/2026_09_24_000001_create_reference_rates_and_scheme_fields.php';

    private const NEW_CONTRACT_COLUMNS = [
        'scheme_code', 'interest_policy', 'floating_flag', 'interest_calc_base', 'installment_based_on',
        'emi_calc_type', 'moratorium_type', 'moratorium_type_verbatim', 'grace_period_months', 'moratorium_from',
        'interest_start_date', 'first_instalment_date', 'spread_over_prime', 'spread_source', 'spread_drift_flag',
        'reprice_flag', 'account_status_code', 'los_application_no', 'los_process_ref', 'predecessor_sub_account',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id')->unique();
            $t->string('product_type')->nullable();
            $t->timestamps();
        });
    }

    private function migration(): Migration
    {
        return require self::MIGRATION;
    }

    public function test_up_creates_the_two_tables_and_the_contract_columns(): void
    {
        $this->migration()->up();

        $this->assertTrue(Schema::hasTable('reference_rate_series'));
        $this->assertTrue(Schema::hasColumns('reference_rate_series', [
            'index_code', 'effective_date', 'rate', 'source_row', 'as_delivered', 'interpretation', 'import_id', 'created_by',
        ]));
        $this->assertTrue(Schema::hasTable('schemes'));
        $this->assertTrue(Schema::hasColumns('schemes', [
            'scheme_code', 'product_code', 'interest_policy', 'floating_flag', 'interest_calc_base',
            'installment_based_on', 'emi_calc_type', 'default_moratorium_type', 'effective_from',
        ]));
        $this->assertTrue(Schema::hasColumns('contract_eir', self::NEW_CONTRACT_COLUMNS));

        // Every new contract column is nullable except the drift flag, which defaults to false.
        DB::table('contract_eir')->insert(['contract_id' => 'X', 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('contract_eir')->where('contract_id', 'X')->first();
        $this->assertNull($row->spread_over_prime);
        $this->assertNull($row->reprice_flag);
        $this->assertSame(0, (int) $row->spread_drift_flag);

        // One rate per index per date.
        DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => '2026-09-03', 'rate' => 21.2]);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('reference_rate_series')->insert(['index_code' => 'PLR', 'effective_date' => '2026-09-03', 'rate' => 21.3]);
    }

    public function test_down_removes_everything_up_added_and_nothing_else(): void
    {
        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasTable('reference_rate_series'));
        $this->assertFalse(Schema::hasTable('schemes'));
        foreach (self::NEW_CONTRACT_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('contract_eir', $column), "{$column} survived down()");
        }
        $this->assertTrue(Schema::hasColumns('contract_eir', ['contract_id', 'product_type']));
    }
}
