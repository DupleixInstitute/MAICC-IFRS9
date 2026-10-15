P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Console/Commands/Bootstrap.php'
s = open(P, encoding='utf-8').read()

a = """        if ($r['status'] === 'QUARANTINED') {
            throw new \\RuntimeException('The committed pack did not pass its gates.');
        }
        $takeon = $inputs . DIRECTORY_SEPARATOR . 'takeon';"""
b = """        if ($r['status'] === 'QUARANTINED') {
            throw new \\RuntimeException('The committed pack did not pass its gates.');
        }
        $this->landLaterPacks($inputs, $user);
        $takeon = $inputs . DIRECTORY_SEPARATOR . 'takeon';"""
assert a in s
s = s.replace(a, b, 1)

a = """    private function stepBuild(int $user): string"""
b = """    /**
     * The packs MAIIC sent after the first two, each with its own manifest, in the
     * order they were received (the date in the name): a folder under docs/bootstrap
     * (ebanker-pack-YYYY-MM-DD/manifest.json), or a zip under docs/bootstrap/packs/
     * for a pack too large to commit as files (pack 4 of 12 October 2026: 143 MB of
     * CSVs, 12.4 MB zipped), unzipped into storage first through the same entry-name
     * check as an uploaded pack. Each lands through the gates like the first; a pack
     * already landed (the same hash) is recognised and not loaded twice.
     */
    private function landLaterPacks(string $inputs, int $user): void
    {
        $packs = [];
        foreach (glob($inputs . '/ebanker-pack-*/manifest.json') ?: [] as $m) {
            $packs[basename(dirname($m))] = dirname($m);
        }
        foreach (glob($inputs . '/packs/ebanker-pack-*.zip') ?: [] as $zipPath) {
            $name = basename($zipPath, '.zip');
            if (isset($packs[$name])) {
                continue;
            }
            $dir = storage_path('app/bootstrap-packs');
            if (! is_dir($dir . '/' . $name)) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $zip = new \\ZipArchive();
                if ($zip->open($zipPath) !== true) {
                    throw new \\RuntimeException("The committed pack {$name}.zip is not a readable zip.");
                }
                \\App\\Support\\FeedZip::extract($zip, $dir, 'bootstrap');
                $zip->close();
            }
            if (is_file($dir . '/' . $name . '/manifest.json')) {
                $packs[$name] = $dir . '/' . $name;
            }
        }
        ksort($packs);
        foreach ($packs as $name => $dir) {
            // the 7 and 8 October folders are listed by the top-level manifest and landed with it
            if (! is_file($dir . '/manifest.json')) {
                continue;
            }
            $r = app(PackLandingService::class)->land($dir, $user, PackLandingService::ROUTE_MANUAL);
            $files = count(array_filter($r['files'] ?? [], fn ($f) => is_array($f) && isset($f['new'])));
            $this->note('3 land ' . $name, "{$r['status']} (load {$r['load_id']}): {$files} files");
            if ($r['status'] === 'QUARANTINED') {
                throw new \\RuntimeException("The committed pack {$name} did not pass its gates.");
            }
        }
    }

    private function stepBuild(int $user): string"""
assert a in s
s = s.replace(a, b, 1)
open(P, 'w', encoding='utf-8').write(s)
print('ok')
