<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\DirectRead\DirectReadException;
use App\Services\Ebanker\DirectRead\DirectReadRunner;
use App\Services\Ebanker\DirectRead\EbankerSource;
use App\Services\Ebanker\DirectRead\OracleConnectionSource;
use App\Services\Ebanker\DirectRead\RegisteredStatements;
use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Route 4 (data access request of 14 October 2026): the register carries each statement route 4
 * runs with its fingerprint; a read with the registered statements lands through the same door as
 * a manual pack, as ROUTE_4_DIRECT, reading above each query's own watermark; a statement changed
 * in the register refuses the read before anything is read; an Oracle failure lands nothing and
 * names only the query and the error number. E-Banker is a fake here: the Oracle connection itself
 * is proved on E-Banker's test copy (stage B).
 */
class DirectReadRunnerTest extends TestCase
{
    protected $seed = false;
    private array $dirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone.php', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone.php'] as $m) {
            (require base_path('database/migrations/' . $m))->up();
        }
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $d) {
            File::deleteDirectory($d);
        }
        parent::tearDown();
    }

    private function run4(EbankerSource $source, string $period = '2026-11'): array
    {
        $r = app(DirectReadRunner::class)->run($source, $period, 1);
        $this->dirs[] = $r['pack_dir'];

        return $r;
    }

    private function master(): array
    {
        return [['NEW_AC_NUMBER' => '000104420000005', 'GLCODE' => '1050101', 'ACCOUNT_NAME' => 'Customer 5', 'ACCOUNT_OPEN_DATE' => '2024-07-01']];
    }

    private function ledger(array $ids): array
    {
        return array_map(fn ($id) => ['CUMVOUCH_DET_ID' => (string) $id, 'NEW_AC_NUMBER' => '000104420000005', 'TRANSACTION_DATE' => '2026-11-30', 'TRANSAMT' => '-0.5', 'TRANTYPE' => '303'], $ids);
    }

    public function test_the_register_holds_each_route_4_statement_with_its_fingerprint(): void
    {
        $files = RegisteredStatements::files();
        $this->assertCount(15, $files);
        foreach ($files as $id => $sql) {
            $q = DB::table('ebanker_queries')->where('query_id', $id)->first();
            $this->assertSame($sql, $q->sql, $id);
            $this->assertSame(hash('sha256', $sql), $q->sha256, $id);
        }
        $this->assertSame(15, DB::table('ebanker_queries')->whereNotNull('sql')->count());
        // an incremental statement reads above its watermark; a master comes whole
        $this->assertSame(['watermark', 'period_start'], RegisteredStatements::bindsIn($files['M09_01']));
        $this->assertSame(['watermark'], RegisteredStatements::bindsIn($files['M09_02']));
        $this->assertSame([], RegisteredStatements::bindsIn($files['P1_02']));
    }

    public function test_a_statement_that_is_not_one_select_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        RegisteredStatements::assertSelect('X', "SELECT 1 FROM dual; DELETE FROM acmaster");
    }

    public function test_a_read_lands_as_route_4_with_its_watermarks(): void
    {
        $source = new FakeEbanker(['P1_02' => $this->master(), 'M09_01' => $this->ledger([100, 101])]);
        $r = $this->run4($source);
        $this->assertSame('LANDED', $r['landing']['status']);
        $load = DB::table('ebanker_loads')->first();
        $this->assertSame(PackLandingService::ROUTE_DIRECT, $load->route);
        $this->assertSame('2026-11', $load->period);
        $this->assertSame(['M09_01' => 101], json_decode($load->watermarks, true));
        $this->assertSame(3, DB::table('ebanker_raw_rows')->count());
        // the first read of an incremental statement is its whole history; the re-pull starts two months back
        $this->assertSame(['watermark' => 0, 'period_start' => '2026-11-01'], $source->bindsFor('M09_01'));
        // every registered statement was run; those with no rows are named in the manifest, not landed
        $this->assertCount(15, $source->calls);
        $manifest = json_decode(file_get_contents($r['pack_dir'] . '/manifest.json'), true);
        $this->assertCount(2, $manifest['files']);
        $this->assertCount(13, $manifest['not_landed']);
        $this->assertSame('Y-m-d', $manifest['date_format']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'E-Banker Direct Read')->count());
    }

    public function test_a_second_read_starts_above_the_watermark_and_lands_held_rows_as_unchanged(): void
    {
        $this->run4(new FakeEbanker(['P1_02' => $this->master(), 'M09_01' => $this->ledger([100, 101])]));
        $source = new FakeEbanker(['P1_02' => $this->master(), 'M09_01' => $this->ledger([101, 102])]);
        $r = $this->run4($source);
        $this->assertSame('LANDED', $r['landing']['status']);
        $this->assertSame(101, $source->bindsFor('M09_01')['watermark']);
        $ledger = collect($r['landing']['files'])->first(fn ($f, $file) => str_starts_with($file, 'M09_01_'));
        $this->assertSame(['new' => 1, 'unchanged' => 1], ['new' => $ledger['new'], 'unchanged' => $ledger['unchanged']]);
        $this->assertSame(4, DB::table('ebanker_raw_rows')->count());
        $this->assertSame(2, DB::table('ebanker_loads')->where('route', PackLandingService::ROUTE_DIRECT)->count());
    }

    public function test_a_statement_changed_in_the_register_refuses_the_read_before_anything_is_read(): void
    {
        DB::table('ebanker_queries')->where('query_id', 'P2_06')->update(['sql' => 'SELECT * FROM plr_master']);
        $source = new FakeEbanker([]);
        try {
            app(DirectReadRunner::class)->run($source, '2026-11', 1);
            $this->fail('the read was not refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('P2_06', $e->getMessage());
            $this->assertStringContainsString('fingerprint', $e->getMessage());
        }
        $this->assertSame([], $source->calls);
        $this->assertSame(0, DB::table('ebanker_loads')->count());
    }

    public function test_an_oracle_failure_names_the_query_and_error_number_only_and_lands_nothing(): void
    {
        $source = new FakeEbanker(['P1_02' => $this->master()], failOn: 'M09_01');
        try {
            $this->run4($source);
            $this->fail('the read did not stop');
        } catch (DirectReadException $e) {
            $this->assertSame('Route 4 stopped at M09_01: ORA-00942. Nothing was landed.', $e->getMessage());
        }
        $this->assertSame(0, DB::table('ebanker_loads')->count());
        $logged = DB::table('audit_logs')->where('action', 'E-Banker Direct Read Failed')->value('new_values');
        $this->assertStringNotContainsString('secret-host', (string) $logged);
        foreach (glob(\App\Support\DatabaseScope::storagePath('ebanker-direct') . '/pack-2026-11-*') ?: [] as $d) {
            $this->dirs[] = $d;
        }
    }

    public function test_a_period_that_is_not_a_month_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(DirectReadRunner::class)->run(new FakeEbanker([]), '2026-13', 1);
    }

    public function test_an_oracle_number_below_one_gets_its_leading_zero(): void
    {
        $this->assertSame('0.5', OracleConnectionSource::normaliseValue('.5'));
        $this->assertSame('-0.25', OracleConnectionSource::normaliseValue('-.25'));
        $this->assertSame('12.5', OracleConnectionSource::normaliseValue('12.5'));
        $this->assertSame('.5 inch', OracleConnectionSource::normaliseValue('.5 inch'));
        $this->assertNull(OracleConnectionSource::normaliseValue(null));
    }
}
