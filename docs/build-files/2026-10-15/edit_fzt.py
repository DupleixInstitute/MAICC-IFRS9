def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Ebanker\FeedZipGuardTest.php', [
("""use App\\Services\\Ebanker\\FeedZip;""", """use App\\Services\\Ebanker\\FeedRoute;
use App\\Services\\Ebanker\\FeedZip;
use App\\Support\\DatabaseScope;"""),
("""        config(['services.ebanker_feed.api_token' => 'feed-secret']);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'feedzip_' . uniqid();
        mkdir($this->dir);
    }""", """        config(['services.ebanker_feed.api_token' => 'feed-secret']);
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
    }"""),
("""glob(storage_path('app/ebanker-inbox/*'))""", """glob(DatabaseScope::storagePath('ebanker-inbox') . '/*')"""),
("""        $inbox = storage_path('app/ebanker-inbox/' . $response->json('received'));""",
"""        $inbox = DatabaseScope::storagePath('ebanker-inbox') . '/' . $response->json('received');"""),
("""    public function test_the_extractor_used_by_the_poller_skips_an_unsafe_entry_with_a_log_line(): void""",
"""    public function test_the_api_refuses_a_pack_when_route_3_is_not_the_route_in_force(): void
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

    public function test_the_extractor_used_by_the_poller_skips_an_unsafe_entry_with_a_log_line(): void"""),
])
print('ok')
