<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\DirectRead\EbankerSource;
use App\Services\Ebanker\FeedRoute;
use App\Support\DatabaseScope;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The route 4 commands: eir:direct-read lands only while route 4 is the governed route in force,
 * as the poller does for routes 2 and 3; eir:ebanker-connection-test prints who, where and when and,
 * with --statements, proves each registered statement without reading a row.
 */
class DirectReadCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insertOrIgnore(['id' => 1, 'name' => 'Loader', 'email' => 'loader@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach (glob(DatabaseScope::storagePath('ebanker-direct') . '/pack-*') ?: [] as $d) {
            File::deleteDirectory($d);
        }
        parent::tearDown();
    }

    private function routeInForce(string $value): void
    {
        DB::table('governance_settings')->where('key', FeedRoute::KEY)->delete();
        DB::table('governance_settings')->insert(['key' => FeedRoute::KEY, 'value' => $value, 'options' => json_encode([$value]), 'label' => 'route', 'description' => '',
            'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function fake(array $rows = []): FakeEbanker
    {
        $fake = new FakeEbanker($rows);
        $this->app->instance(EbankerSource::class, $fake);

        return $fake;
    }

    public function test_the_read_is_refused_unless_route_4_is_in_force(): void
    {
        $fake = $this->fake();
        $this->routeInForce(FeedRoute::SEVERAL);
        $this->artisan('eir:direct-read', ['period' => '2026-11'])->expectsOutputToContain('Refused')->assertFailed();
        $this->assertSame([], $fake->calls);
        $this->assertSame(0, DB::table('ebanker_loads')->count());
    }

    public function test_under_route_4_the_read_lands(): void
    {
        $this->fake(['P1_02' => [['NEW_AC_NUMBER' => '000104420000005', 'GLCODE' => '1050101', 'ACCOUNT_NAME' => 'Customer 5', 'ACCOUNT_OPEN_DATE' => '2024-07-01']]]);
        $this->routeInForce('Route 4: direct read over the VPN');
        $this->artisan('eir:direct-read', ['period' => '2026-11'])->expectsOutputToContain('Pack LANDED')->assertSuccessful();
        $this->assertSame('ROUTE_4_DIRECT', DB::table('ebanker_loads')->value('route'));
    }

    public function test_the_bootstrap_reads_a_month_end_by_route_4_and_reports_what_changed(): void
    {
        $this->fake(['P1_02' => [['NEW_AC_NUMBER' => '000104420000005', 'GLCODE' => '1050101', 'ACCOUNT_NAME' => 'Customer 5', 'ACCOUNT_OPEN_DATE' => '2024-07-01']]]);
        $this->artisan('eir:bootstrap', ['--direct-read' => '2026-11'])
            ->expectsOutputToContain('2026-11: LANDED')
            ->assertSuccessful();
        $this->assertSame('ROUTE_4_DIRECT', DB::table('ebanker_loads')->value('route'));
    }

    public function test_the_connection_test_proves_every_statement_without_reading_a_row(): void
    {
        $fake = $this->fake();
        $this->artisan('eir:ebanker-connection-test', ['--statements' => true])->assertSuccessful();
        // the who-where-when query, the scope check, then each of the 15 statements wrapped to return no row
        $this->assertCount(17, $fake->calls);
        $this->assertSame(\App\Console\Commands\EbankerConnectionTest::WHO, $fake->calls[0]['sql']);
        $wrapped = array_filter($fake->calls, fn ($c) => str_starts_with($c['sql'], 'SELECT * FROM (') && str_ends_with($c['sql'], ') WHERE 1 = 0'));
        $this->assertCount(15, $wrapped);
    }
}
