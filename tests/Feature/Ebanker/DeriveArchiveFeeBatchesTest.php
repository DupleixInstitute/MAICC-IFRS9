<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pack 14 RUN 6 (GL_14f) derived from the landed pack 7 archive (CV_04a): every row of each batch that holds a fee GL
 * leg dated before 1 September 2024, the source named, landed on the route DERIVED_LOCAL under its own id with its
 * lineage in the manifest, and never as if MAIIC had extracted it.
 *
 * The archive: batch TRF 10 of 5 Jan 2024 has a fee leg on GL 4873 and a loan leg (both taken); batch TRF 11 of the same
 * day has no fee leg (left out); batch TRF 12 of 2 Sep 2024 has a fee leg but is dated after the rule's cut-off (left out).
 */
class DeriveArchiveFeeBatchesTest extends TestCase
{
    protected $seed = false;

    private string $dir;

    private string $out;

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
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'p7_' . uniqid();
        $this->out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'derived_' . uniqid();
        mkdir($this->dir);
        $csv = "\"CUMVOUCH_DET_ID\",\"CB_GLCODE\",\"AC_GLCODE\",\"NEW_AC_NUMBER\",\"TRANSACTION_DATE\",\"TRANSAMT\",\"BATCH_TYPE\",\"BATCH_NUMBER\",\"O_BRANCH_MST_ID\"\n"
            . "\"1\",\"4873\",\"4873\",\"\",\"1/5/2024\",\"-500.00\",\"TRF\",\"10\",\"1\"\n"
            . "\"2\",\"1050101\",\"1050101\",\"000104420000005\",\"1/5/2024\",\"500.00\",\"TRF\",\"10\",\"1\"\n"
            . "\"3\",\"1050101\",\"1050101\",\"000104420000005\",\"1/5/2024\",\"900.00\",\"TRF\",\"11\",\"1\"\n"
            . "\"4\",\"4871\",\"4871\",\"\",\"9/2/2024\",\"-70.00\",\"TRF\",\"12\",\"1\"\n";
        file_put_contents($this->dir . '/CV_04a1_bk240824.csv', $csv);
        file_put_contents($this->dir . '/manifest.json', json_encode(['pack' => 'pack 7 test', 'date_format' => 'n/j/Y', 'files' => [['file' => 'CV_04a1_bk240824.csv', 'query_id' => 'CV_04a', 'rows' => 4, 'sha256' => hash('sha256', $csv)]]]));
        $this->assertSame('LANDED', (new PackLandingService())->land($this->dir, 1)['status']);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, ...glob($this->out . '/*', GLOB_ONLYDIR)] as $d) {
            foreach (glob($d . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($d);
        }
        @rmdir($this->out);
        parent::tearDown();
    }

    public function test_the_archive_fee_batches_are_derived_and_land_under_their_own_id_with_their_lineage(): void
    {
        $this->assertSame(0, Artisan::call('eir:derive-archive-fee-batches', ['--out' => $this->out, '--land' => true, '--user' => 1]));

        $rows = DB::table('ebanker_raw_rows')->where('query_id', 'GL_14f')->orderBy('source_key')->get();
        $this->assertSame(['1', '2'], $rows->pluck('source_key')->all(), 'batch TRF 10 whole; TRF 11 has no fee leg; TRF 12 is after the cut-off');
        $this->assertSame('CUMVOUCH_BK240824', json_decode($rows[0]->payload, true)['SOURCE_TABLE']);
        $load = DB::table('ebanker_loads')->where('route', PackLandingService::ROUTE_DERIVED)->first();
        $this->assertNotNull($load);
        $manifest = json_decode($load->manifest, true);
        $this->assertSame('CV_04a', $manifest['derived_from']['query_id']);
        $this->assertSame(hash_file('sha256', $this->dir . '/CV_04a1_bk240824.csv'), $manifest['derived_from']['files'][0]['sha256']);
        $this->assertSame(['GL_14f' => ['rows' => 2, 'source' => 'the derivation itself']], $manifest['controls']);
        $this->assertSame(4, DB::table('ebanker_raw_rows')->where('query_id', 'CV_04a')->count(), 'the archive itself is untouched');
    }
}
