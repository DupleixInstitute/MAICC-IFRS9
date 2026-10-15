def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Console\Commands\PollFeedFolder.php', [
(""" * is lost.
 *""", """ * is lost.
 *
 * The governed route is enforced (14 October 2026): the folder is polled only
 * while route 2 is in force, and the API inbox (--also-route-3) only while
 * route 3 is, unless the setting opens routes 1 to 3 together (FeedRoute);
 * otherwise the poller refuses with the reason and lands nothing. A missing
 * value refuses too; it used to be read as route 1. The API inbox is the
 * database's own (DatabaseScope).
 *"""),
("""use App\\Services\\Ebanker\\FeedZip;""", """use App\\Services\\Ebanker\\FeedRoute;
use App\\Services\\Ebanker\\FeedZip;"""),
("""        $folder = (string) ($this->argument('folder') ?? config('services.ebanker_feed.folder'));
        if ($folder === '' || ! is_dir($folder)) {
            $this->warn('No feed folder: set EBANKER_FEED_FOLDER or pass the folder. Nothing landed.');
            return self::SUCCESS;
        }
        try {
            $inForce = $governance->get('ebanker_feed_route');
        } catch (Throwable) {
            $inForce = 'Route 1';
        }
        $this->unpackZips($folder);
        $candidates = [];
        if (is_file($folder . DIRECTORY_SEPARATOR . 'manifest.json')) {
            $candidates[] = $folder;
        }
        foreach (glob($folder . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
            if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                $candidates[] = $sub;
            }
        }
        if ($this->option('also-route-3')) {
            foreach (glob(storage_path('app/ebanker-inbox/*'), GLOB_ONLYDIR) ?: [] as $sub) {
                if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $sub;
                }
            }
        }""", """        $folder = (string) ($this->argument('folder') ?? config('services.ebanker_feed.folder'));
        // the governed route in force decides which of the two doors may land (FeedRoute)
        $route2 = FeedRoute::check(2, $governance);
        $route3 = $this->option('also-route-3') ? FeedRoute::check(3, $governance) : null;
        $inForce = $route2['in_force'] ?? 'none approved';
        if (! $route2['allowed'] && ($route3 === null || ! $route3['allowed'])) {
            $this->error('Refused: ' . $route2['reason'] . ($route3 !== null && $route3['reason'] !== $route2['reason'] ? ' ' . $route3['reason'] : ''));

            return self::FAILURE;
        }
        $candidates = [];
        if ($route2['allowed']) {
            if ($folder === '' || ! is_dir($folder)) {
                $this->warn('No feed folder: set EBANKER_FEED_FOLDER or pass the folder. Nothing landed from a folder.');
            } else {
                $this->unpackZips($folder);
                if (is_file($folder . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $folder;
                }
                foreach (glob($folder . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
                    if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                        $candidates[] = $sub;
                    }
                }
            }
        } else {
            $this->warn('The feed folder is not polled: ' . $route2['reason']);
        }
        $inbox = \\App\\Support\\DatabaseScope::storagePath('ebanker-inbox');
        if ($route3 !== null && $route3['allowed']) {
            foreach (glob($inbox . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
                if (is_file($sub . DIRECTORY_SEPARATOR . 'manifest.json')) {
                    $candidates[] = $sub;
                }
            }
        } elseif ($route3 !== null) {
            $this->warn('The API inbox is not landed: ' . $route3['reason']);
        }"""),
("""                $route = str_starts_with($dir, storage_path('app/ebanker-inbox')) ? PackLandingService::ROUTE_API : PackLandingService::ROUTE_FOLDER;""",
"""                $route = str_starts_with($dir, $inbox) ? PackLandingService::ROUTE_API : PackLandingService::ROUTE_FOLDER;"""),
("""        $this->info("Feed folder {$folder}: " . count($candidates) . ' pack(s) found; route in force: ' . $inForce . '.');""",
"""        $this->info(($route2['allowed'] ? "Feed folder {$folder}" : 'API inbox') . ': ' . count($candidates) . ' pack(s) found; route in force: ' . $inForce . '.');"""),
])
print('ok')
