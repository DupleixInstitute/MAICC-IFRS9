<?php

namespace Tests\Feature\Eir;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The two P4 migrations apply and reverse cleanly on an in-memory schema that
 * holds only the tables they depend on, and the unique key on the drawdown's
 * source ids holds. Never run against the .env database: the point is to prove
 * down() before anyone needs it.
 */
class DisbursementMigrationTest extends TestCase
{
    protected $seed = false;

    private const DISBURSEMENTS = __DIR__ . '/../../../database/migrations/2026_09_24_000003_create_contract_disbursements.php';

    private const SCHEDULE_SHAPE = __DIR__ . '/../../../database/migrations/2026_09_24_000002_add_schedule_shape_to_contract_eir.php';

    private const SCHEDULE_SHAPE_COLUMNS = [
        'schedule_amortising_balance', 'schedule_moratorium_type', 'schedule_emi_calc_type',
        'schedule_interest_basis', 'schedule_instalment_basis', 'schedule_day_count', 'schedule_basis_sources',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id')->unique();
            $t->string('schedule_generated_at')->nullable();
            $t->timestamps();
        });
    }

    private function migration(string $path): Migration
    {
        return require $path;
    }

    public function test_the_drawdown_table_applies_and_reverses(): void
    {
        $migration = $this->migration(self::DISBURSEMENTS);
        $migration->up();

        $this->assertTrue(Schema::hasTable('contract_disbursements'));
        foreach ([
            'contract_id', 'sub_account_no', 'tranche_no', 'disbursement_date', 'amount', 'reference',
            'source_system', 'source_reference', 'external_transaction_id', 'import_id', 'created_by',
            'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('contract_disbursements', $column), "contract_disbursements needs {$column}");
        }

        $migration->down();
        $this->assertFalse(Schema::hasTable('contract_disbursements'));
    }

    public function test_the_same_source_transaction_cannot_be_stored_twice(): void
    {
        $this->migration(self::DISBURSEMENTS)->up();

        $row = [
            'contract_id' => '104450000103', 'disbursement_date' => '2025-06-30', 'amount' => 150000000,
            'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => '104450000103|2025-06-30|150000000.00|1|PV-1',
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('contract_disbursements')->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('contract_disbursements')->insert($row);
    }

    public function test_the_schedule_shape_columns_apply_and_reverse(): void
    {
        $migration = $this->migration(self::SCHEDULE_SHAPE);
        $migration->up();

        foreach (self::SCHEDULE_SHAPE_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('contract_eir', $column), "contract_eir needs {$column}");
        }

        $migration->down();
        foreach (self::SCHEDULE_SHAPE_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('contract_eir', $column), "contract_eir should no longer have {$column}");
        }
    }
}
