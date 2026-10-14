<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\FeedRoute;
use App\Services\Ebanker\FeedZip;
use App\Support\DatabaseScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use ZipArchive;

/**
 * System audit of 9 October 2026, finding M14: the feed zip is checked
 * entry by entry before anything is written. The API door refuses a pack
 * with an entry that climbs out of the inbox (422, the entries named); the
 * poller and the upload screen extract the rest and log each entry left out.
 *
 * The route runs under the web middleware, which reads the legacy tables on
 * every request, so this test migrates the schema (as the login test does).
 */
class FeedZipGuardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ebanker_feed.api_token' => 'feed-secret']);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'feedzip_' . uniqid();
        mkdir($this->dir);
        // the API intake is open only while route 3 is the governed route in force (FeedRoute, 14 October 2026)
        $this->routeInForce('Route 3: push by API');
    }

    private function routeInForce(string $value): void
    {
        DB::table('governance_settings')->where('key', FeedRoute::KEY)->delete();
        DB::table('governance_settings')->insert(['key' => FeedRoute::KEY, 'value' => $value, 'options' => json_encode([$value]), 'label' => 'route', 'description' => '',
            'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->dir);
        parent::tearDown();
    }

    private function rmdir(string $dir): void
    {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (in_array(basename($f), ['.', '..'], true)) {
                continue;
            }
            is_dir($f) ? $this->rmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /** @param array<string,string> $entries name => content */
    private function zip(string $name, array $entries): string
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return $path;
    }

    public function test_the_guard_names_every_entry_that_climbs_out(): void
    {
        $this->assertTrue(FeedZip::isSafe('manifest.json'));
        $this->assertTrue(FeedZip::isSafe('pack-2026-09/P1_01.csv'));
        $this->assertFalse(FeedZip::isSafe('../evil.php'));
        $this->assertFalse(FeedZip::isSafe('pack/../../evil.php'));
        $this->assertFalse(FeedZip::isSafe('/etc/passwd'));
        $this->assertFalse(FeedZip::isSafe('\\windows\\system32\\x.dll'));
        $this->assertFalse(FeedZip::isSafe('C:\\xampp\\htdocs\\x.php'));
        $this->assertFalse(FeedZip::isSafe(''));
    }

    public function test_the_api_refuses_a_zip_with_an_entry_that_climbs_out_of_the_inbox(): void
    {
        $path = $this->zip('crafted.zip', ['manifest.json' => '{}', '../../routes/evil.php' => '<?php echo 1;', 'P1_01.csv' => 'a,b']);
        $zip = new ZipArchive();
        $zip->open($path);
        $this->assertSame(['../../routes/evil.php'], FeedZip::unsafeEntries($zip));
        $zip->close();
        $before = count(glob(DatabaseScope::storagePath('ebanker-inbox') . '/*') ?: []);

        $response = $this->withHeader('Authorization', 'Bearer feed-secret')
            ->post('/api/ebanker-feed/pack', ['pack' => new UploadedFile($path, 'crafted.zip', 'application/zip', null, true)]);

        $response->assertStatus(422);
        $this->assertStringContainsString('../../routes/evil.php', $response->exception?->getMessage() ?? $response->getContent());
        $this->assertCount($before, glob(DatabaseScope::storagePath('ebanker-inbox') . '/*') ?: [], 'nothing was written to the inbox');
        $this->assertFileDoesNotExist(base_path('routes/evil.php'));
    }

    public function test_the_api_still_takes_a_clean_pack_and_refuses_a_bad_token(): void
    {
        $path = $this->zip('clean.zip', ['manifest.json' => '{"pack":"test"}', 'P1_01.csv' => 'a,b']);
        $this->withHeader('Authorization', 'Bearer wrong')
            ->post('/api/ebanker-feed/pack', ['pack' => new UploadedFile($path, 'clean.zip', 'application/zip', null, true)])->assertStatus(401);

        $response = $this->withHeader('Authorization', 'Bearer feed-secret')
            ->post('/api/ebanker-feed/pack', ['pack' => new UploadedFile($path, 'clean.zip', 'application/zip', null, true)]);
        $response->assertStatus(202);
        $inbox = DatabaseScope::storagePath('ebanker-inbox') . '/' . $response->json('received');
        try {
            $this->assertFileExists($inbox . '/manifest.json');
            $this->assertFileExists($inbox . '/P1_01.csv');
            $this->assertSame(1, DB::table('audit_logs')->where('action', 'E-Banker Pack Received by API')->count());
        } finally {
            $this->rmdir($inbox);
        }
    }

    public function test_the_api_refuses_a_pack_when_route_3_is_not_the_route_in_force(): void
    {
        $path = $this->zip('clean.zip', ['manifest.json' => '{"pack":"test"}', 'P1_01.csv' => 'a,b']);
        $before = count(glob(DatabaseScope::storagePath('ebanker-inbox') . '/*') ?: []);
        $this->routeInForce('Route 1: manual pack upload');

        $response = $this->withHeader('Authorization', 'Bearer feed-secret')
            ->post('/api/ebanker-feed/pack', ['pack' => new UploadedFile($path, 'clean.zip', 'application/zip', null, true)]);

        $response->assertStatus(409);
        $this->assertStringContainsString("The feed route in force is 'Route 1: manual pack upload'", $response->json('refused'));
        $this->assertCount($before, glob(DatabaseScope::storagePath('ebanker-inbox') . '/*') ?: [], 'nothing was written to the inbox');

        // the setting may open routes 1 to 3 together
        $this->assertTrue(FeedRoute::check(3)['allowed'] === false);
        $this->routeInForce(FeedRoute::SEVERAL);
        $this->assertTrue(FeedRoute::check(3)['allowed']);
        $this->assertTrue(FeedRoute::check(2)['allowed']);
        $this->assertFalse(FeedRoute::check(4)['allowed']);
        // no approved value refuses (fail closed); it used to be read as route 1
        DB::table('governance_settings')->where('key', FeedRoute::KEY)->delete();
        $this->assertFalse(FeedRoute::check(3)['allowed']);
        $this->assertStringContainsString('No route is approved', FeedRoute::check(3)['reason']);
    }

    public function test_the_poller_refuses_when_route_2_is_not_in_force_and_lands_from_the_folder_when_it_is(): void
    {
        $this->routeInForce('Route 1: manual pack upload');
        $this->artisan('eir:poll-feed-folder', ['folder' => $this->dir])
            ->expectsOutputToContain("Refused: The feed route in force is 'Route 1: manual pack upload'")
            ->assertExitCode(1);

        $this->routeInForce('Route 2: scheduled export to a folder');
        $this->artisan('eir:poll-feed-folder', ['folder' => $this->dir])
            ->expectsOutputToContain('0 pack(s) found; route in force: Route 2: scheduled export to a folder')
            ->assertExitCode(0);
    }

    public function test_the_run_history_and_the_inbox_are_kept_per_database(): void
    {
        config(['database.connections.mysql.database' => 'maiic_ifrs9_scratch', 'database.default' => 'mysql']);
        $this->assertSame('maiic_ifrs9_scratch', DatabaseScope::name());
        $this->assertStringEndsWith('ebanker-inbox' . DIRECTORY_SEPARATOR . 'maiic_ifrs9_scratch', str_replace('/', DIRECTORY_SEPARATOR, DatabaseScope::storagePath('ebanker-inbox')));
        config(['database.connections.mysql.database' => 'other_db']);
        $this->assertStringEndsWith('command-runs' . DIRECTORY_SEPARATOR . 'other_db', str_replace('/', DIRECTORY_SEPARATOR, DatabaseScope::storagePath('command-runs')));
        @rmdir(storage_path('app/ebanker-inbox/maiic_ifrs9_scratch'));
        @rmdir(storage_path('app/command-runs/other_db'));
        config(['database.default' => 'sqlite']);
    }

    public function test_the_extractor_used_by_the_poller_skips_an_unsafe_entry_with_a_log_line(): void
    {
        $path = $this->zip('pack.zip', ['manifest.json' => '{}', '../outside.txt' => 'x', 'P1_01.csv' => 'a,b']);
        $target = $this->dir . DIRECTORY_SEPARATOR . 'unpacked';
        mkdir($target);
        Log::shouldReceive('warning')->once()->withArgs(fn ($m) => str_contains($m, '"../outside.txt" skipped'));

        $zip = new ZipArchive();
        $zip->open($path);
        $r = FeedZip::extract($zip, $target, 'feed folder');
        $zip->close();

        $this->assertSame(2, $r['extracted']);
        $this->assertSame(['../outside.txt'], $r['skipped']);
        $this->assertFileExists($target . '/manifest.json');
        $this->assertFileExists($target . '/P1_01.csv');
        $this->assertFileDoesNotExist($this->dir . '/outside.txt');
    }
}
