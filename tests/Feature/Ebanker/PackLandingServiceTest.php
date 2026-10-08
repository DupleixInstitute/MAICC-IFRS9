<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The landing zone's one door (spec v4 sections 6.3 and 6.4): a pack passes
 * its gates and is landed verbatim with watermarks; a hash that differs, a
 * date in the wrong format or an unknown query quarantines the pack and
 * writes no row; a row that arrives again with different content becomes a
 * new version and the earlier one is kept; the same pack landed twice is a
 * no-op.
 */
class PackLandingServiceTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        $migration = require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php');
        $migration->up();
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pack_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function writePack(array $files, array $extra = []): void
    {
        $manifest = ['pack' => 'test pack', 'source' => 'dates exported as m/d/yyyy', 'files' => []] + $extra;
        foreach ($files as $name => [$queryId, $csv, $override]) {
            file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $csv);
            $rows = max(substr_count(trim($csv), "\n"), 0);
            $manifest['files'][] = array_merge(['file' => $name, 'query_id' => $queryId, 'rows' => $rows, 'sha256' => hash('sha256', $csv)], $override);
        }
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest));
    }

    private function ledgerCsv(array $rows): string
    {
        $out = "\"   \",\"CUMVOUCH_DET_ID\",\"NEW_AC_NUMBER\",\"TRANSACTION_DATE\",\"TRANSAMT\",\"TRANTYPE\"\n";
        foreach ($rows as $i => [$id, $ac, $date, $amt, $type]) {
            $out .= "\"" . ($i + 1) . "\",\"{$id}\",\"{$ac}\",\"{$date}\",\"{$amt}\",\"{$type}\"\n";
        }
        return $out;
    }

    public function test_a_good_pack_is_landed_verbatim_with_watermarks(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1000.50', '303'], [101, '000104420000005', '1/31/2026', '2000.00', '305']]), []]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(['P1_01' => 101], $r['watermarks']);
        $this->assertSame(2, DB::table('ebanker_raw_rows')->count());
        $row = DB::table('ebanker_raw_rows')->where('source_key', '100')->first();
        $this->assertSame('000104420000005', $row->account);
        $this->assertSame('2025-12-31', $row->row_date);
        $this->assertSame('-1000.50', json_decode($row->payload, true)['TRANSAMT']);
        $this->assertSame('LANDED', DB::table('ebanker_loads')->first()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'E-Banker Pack Landed')->count());
    }

    public function test_a_tampered_hash_quarantines_the_pack_and_writes_no_row(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1', '303']]), ['sha256' => str_repeat('0', 64)]]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertStringContainsString('SHA-256 differs', $r['gates']['files']['P1_01_ledger.csv']['failures'][0]);
        $this->assertSame(0, DB::table('ebanker_raw_rows')->count());
        $this->assertSame('QUARANTINED', DB::table('ebanker_loads')->first()->status);
    }

    public function test_a_date_in_the_wrong_format_names_the_row(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '31-12-2025', '-1', '303']]), []]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertStringContainsString("row 2: '31-12-2025'", $r['gates']['files']['P1_01_ledger.csv']['failures'][0]);
    }

    public function test_a_timestamp_column_keeps_its_date_part(): void
    {
        $s = new PackLandingService();
        $this->assertSame('2025-09-03', $s->parseDate('9/3/2025 10:57:57 AM', 'm/d/Y'));
        $this->assertSame('2025-12-31', $s->parseDate('12/31/2025 11:05:00 PM', 'm/d/Y'));
        $this->assertSame('2026-01-31', $s->parseDate('1/31/2026', 'm/d/Y'));
        $this->assertNull($s->parseDate('31/12/2025', 'm/d/Y'));
    }

    public function test_a_duplicate_key_inside_a_file_names_both_rows(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1', '303'], [100, '000104420000005', '12/31/2025', '-2', '303']]), []]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertStringContainsString("row 3: key CUMVOUCH_DET_ID = '100' already appears at row 2", $r['gates']['files']['P1_01_ledger.csv']['failures'][0]);
    }

    public function test_a_composite_key_lands_one_row_per_pair(): void
    {
        $csv = "\"   \",\"NEW_AC_NUMBER\",\"CUST_SEC_DET_ID\",\"TRANSACTION_DATE\",\"SECURITY_VALUE\"\n"
            . "\"1\",\"000104420000063\",\"16\",\"8/6/2024\",\"10000000.00\"\n"
            . "\"2\",\"000104420000064\",\"16\",\"8/6/2024\",\"10000000.00\"\n";
        $this->writePack(['P2_11_security_details.csv' => ['P2_11', $csv, []]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(2, DB::table('ebanker_raw_rows')->where('query_id', 'P2_11')->count());
        $this->assertSame(['16|000104420000063', '16|000104420000064'], DB::table('ebanker_raw_rows')->orderBy('id')->pluck('source_key')->all());
        $this->assertSame([], $r['watermarks']);
    }

    public function test_an_unknown_query_id_is_refused(): void
    {
        $this->writePack(['X9_99_thing.csv' => ['X9_99', "\"A\",\"B\"\n\"1\",\"2\"\n", []]]);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertStringContainsString('not in the register', $r['gates']['files']['X9_99_thing.csv']['failures'][0]);
    }

    public function test_a_changed_row_becomes_a_new_version_and_the_old_one_is_kept(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1000.50', '303']]), []]]);
        (new PackLandingService())->land($this->dir, 1);
        // the same row re-delivered with a back-dated correction, plus a new row
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1000.75', '303'], [102, '000104420000005', '2/28/2026', '-5', '303']]), []]], ['pack' => 'second pack']);
        $r = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(1, $r['files']['P1_01_ledger.csv']['versioned']);
        $this->assertSame(1, $r['files']['P1_01_ledger.csv']['new']);
        $versions = DB::table('ebanker_raw_rows')->where('source_key', '100')->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertNotNull($versions[0]->superseded_at);
        $this->assertNull($versions[1]->superseded_at);
        $this->assertSame('-1000.75', json_decode($versions[1]->payload, true)['TRANSAMT']);
        $this->assertSame(['P1_01' => 102], $r['watermarks']);
    }

    public function test_the_same_pack_landed_twice_is_a_no_op(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1', '303']]), []]]);
        $first = (new PackLandingService())->land($this->dir, 1);
        $second = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('ALREADY_LANDED', $second['status']);
        $this->assertSame($first['load_id'], $second['load_id']);
        $this->assertSame(1, DB::table('ebanker_raw_rows')->count());
    }

    public function test_dry_run_runs_the_gates_and_writes_nothing(): void
    {
        $this->writePack(['P1_01_ledger.csv' => ['P1_01', $this->ledgerCsv([[100, '000104420000005', '12/31/2025', '-1', '303']]), []]]);
        $r = (new PackLandingService())->land($this->dir, 1, PackLandingService::ROUTE_MANUAL, true);
        $this->assertSame('DRY_RUN_LANDED', $r['status']);
        $this->assertSame(0, DB::table('ebanker_loads')->count());
    }
}
