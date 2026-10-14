<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A query with no key column re-sent (14 October 2026): PackLandingService
 * keyed its rows by row number, so a re-sent CV_07 landed its 47 rows again as
 * 47 new current rows, and family() kept, row number by row number, the later
 * load's row. The rows are now keyed by content: a re-sent file lands nothing
 * new, identical rows in one file both land, a changed row is a new row and
 * the row it replaced is superseded (the query comes whole), and the
 * migration re-keys what was landed before, superseding the duplicates.
 */
class KeylessQueryLandingTest extends TestCase
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
        foreach (['2026_10_08_000000_create_ebanker_landing_zone.php', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone.php'] as $m) {
            (require base_path('database/migrations/' . $m))->up();
        }
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'keyless_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** A pack of one CV_07 file (a keyless probe: OWNER, TABLE_NAME, COLUMN_NAME); $name makes each manifest its own. */
    private function land(array $rows, string $name): array
    {
        $csv = "\"OWNER\",\"TABLE_NAME\",\"COLUMN_NAME\"\n";
        foreach ($rows as [$o, $t, $c]) {
            $csv .= "\"{$o}\",\"{$t}\",\"{$c}\"\n";
        }
        file_put_contents($this->dir . '/CV_07.csv', $csv);
        file_put_contents($this->dir . '/manifest.json', json_encode(['pack' => $name, 'source' => 'dates exported as m/d/yyyy',
            'files' => [['file' => 'CV_07.csv', 'query_id' => 'CV_07', 'rows' => count($rows), 'sha256' => hash('sha256', $csv)]]]));

        return (new PackLandingService())->land($this->dir, 1);
    }

    private function current(): int
    {
        return (new LandingZoneReader())->current()->where('query_id', 'CV_07')->count();
    }

    public function test_cv_07_is_a_query_with_no_key(): void
    {
        $this->assertNull(DB::table('ebanker_queries')->where('query_id', 'CV_07')->value('key_column'));
    }

    public function test_a_re_sent_keyless_file_lands_no_new_row(): void
    {
        $rows = [['VGCBS', 'G_ACMASTER', 'NEW_AC_NUMBER'], ['VGCBS', 'G_ACMASTER', 'OLD_AC_NUMBER'], ['VGCBS', 'CUMVOUCH', 'CUMVOUCH_DET_ID']];
        $first = $this->land($rows, 'pack 8');
        $this->assertSame('LANDED', $first['status']);
        $this->assertSame(3, $first['files']['CV_07.csv']['new']);

        $again = $this->land($rows, 'pack 8, sent again');
        $this->assertSame('LANDED', $again['status']);
        $this->assertNotSame($first['load_id'], $again['load_id'], 'a different manifest: the files go through the door again');
        $this->assertSame(0, $again['files']['CV_07.csv']['new']);
        $this->assertSame(3, $again['files']['CV_07.csv']['unchanged']);
        $this->assertSame(3, $this->current(), 'no duplicate current rows (it was 6 before 14 October 2026)');
        $this->assertSame(3, DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->count());
        // the pack inventory counts each manifest row as new, a new version or held
        $this->assertSame(3, (int) DB::table('ebanker_pack_files')->where('load_id', $again['load_id'])->selectRaw('rows_new + rows_versioned + rows_unchanged n')->value('n'));
        // the key is the content, never the row number
        $this->assertStringStartsWith('c:', (string) DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->value('source_key'));
    }

    public function test_identical_rows_in_one_file_both_land_and_are_recognised_again(): void
    {
        $rows = [['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'B']];
        $first = $this->land($rows, 'one');
        $this->assertSame(3, $first['files']['CV_07.csv']['new']);
        $this->assertSame(3, $this->current());
        $again = $this->land($rows, 'two');
        $this->assertSame(0, $again['files']['CV_07.csv']['new']);
        $this->assertSame(3, $this->current());
    }

    public function test_a_changed_row_is_new_and_the_row_it_replaces_is_superseded(): void
    {
        $this->land([['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'B']], 'one');
        $r = $this->land([['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'B2']], 'two');

        $this->assertSame(1, $r['files']['CV_07.csv']['new']);
        $this->assertSame(1, $r['files']['CV_07.csv']['unchanged']);
        $this->assertSame(1, $r['files']['CV_07.csv']['withdrawn']);
        $current = (new LandingZoneReader())->family(['CV_07']);
        $this->assertSame(['A', 'B2'], array_values(array_map(fn ($x) => $x['payload']['COLUMN_NAME'], $current)));
        $this->assertSame(1, DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->whereNotNull('superseded_at')->count(), 'superseded, never deleted');
        $this->assertStringContainsString('superseded', (string) DB::table('ebanker_pack_files')->where('load_id', $r['load_id'])->value('note'));
    }

    public function test_the_migration_re_keys_the_rows_landed_by_row_number_and_supersedes_the_duplicates(): void
    {
        // what the old door wrote: CV_07 landed by two loads, keyed 1..2 each, all current
        $now = now();
        foreach ([1, 2] as $load) {
            DB::table('ebanker_loads')->insert(['id' => $load, 'pack_hash' => "h{$load}", 'pack_name' => "pack {$load}", 'route' => PackLandingService::ROUTE_MANUAL, 'date_format' => 'm/d/Y', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => $now, 'updated_at' => $now]);
            foreach ([['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'B']] as $i => [$o, $t, $c]) {
                $payload = json_encode(['OWNER' => $o, 'TABLE_NAME' => $t, 'COLUMN_NAME' => $c]);
                DB::table('ebanker_raw_rows')->insert(['load_id' => $load, 'query_id' => 'CV_07', 'source_key' => (string) ($i + 1), 'payload' => $payload, 'row_hash' => hash('sha256', $payload), 'version' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $this->assertSame(4, $this->current());

        (require base_path('database/migrations/2026_10_14_270000_key_keyless_raw_rows_by_content.php'))->up();

        $this->assertSame(2, $this->current(), 'the second load\'s identical rows are superseded');
        $this->assertSame(4, DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->count(), 'nothing deleted');
        $this->assertSame([1], DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->whereNull('superseded_at')->distinct()->pluck('load_id')->all(), 'the earlier load stays current');
        $this->assertSame(0, DB::table('ebanker_raw_rows')->where('query_id', 'CV_07')->where('source_key', 'not like', 'c:%')->count());
        // re-landing the same rows after the migration lands nothing new
        $r = $this->land([['VGCBS', 'T', 'A'], ['VGCBS', 'T', 'B']], 'three');
        $this->assertSame(0, $r['files']['CV_07.csv']['new']);
        $this->assertSame(2, $this->current());
        // idempotent
        (require base_path('database/migrations/2026_10_14_270000_key_keyless_raw_rows_by_content.php'))->up();
        $this->assertSame(2, $this->current());
    }
}
