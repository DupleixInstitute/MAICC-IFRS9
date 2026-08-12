<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/**
 * Capture hi-res, logged-in screenshots of every manual-referenced page via
 * headless Chromium (scripts/manual-screenshots.cjs). Output lands in
 * public/manual/screenshots so the User Manual embeds
 * /manual/screenshots/*.jpg. Read-only: it navigates and shoots, never
 * submits. Run it after any UI change so the manual pictures stay current:
 *
 *   php artisan manual:screenshots --base-url=http://127.0.0.1:8000 \
 *     --email=admin@localhost.com --password=secret
 */
class CaptureManualScreenshotsCommand extends Command
{
    protected $signature = 'manual:screenshots
        {--base-url= : App base URL (default config app.url or http://127.0.0.1:8000)}
        {--email= : Login email (default MANUAL_SHOT_EMAIL)}
        {--password= : Login password (default MANUAL_SHOT_PASSWORD)}
        {--out= : Output directory (default public/manual/screenshots)}
        {--only= : Comma-separated file keys to limit capture to (default: all)}
        {--edge= : Path to msedge.exe / chrome.exe (default: bundled puppeteer browser)}';

    protected $description = 'Capture hi-res logged-in screenshots of the manual pages via headless Chromium.';

    /**
     * Manual screens: Laravel ROUTE NAME => output file key. Route names (not
     * hardcoded paths) so the list survives URL changes; unknown names are
     * skipped with a warning. The manual's figure map keys off the file key.
     */
    private const SHOTS = [
        'dashboard'                   => 'dashboard',
        'workspace.index'             => 'workspace',
        'clients.index'               => 'clients',
        'portfolios.index'            => 'portfolios',
        'loan_applications.loan-book' => 'loanbook',
        'imports.index'               => 'imports',
        'collateral.allocations.index' => 'collateral',
        'eir-intake.index'            => 'eir-intake',
        'eir-fee-classification.index' => 'eir-fees',
        'eir-accounting-rules.index'  => 'eir-rules',
        'stageing-rules.index'        => 'staging',
        'transition-profiles.index'   => 'tprofiles',
        'transition-matrices.index'   => 'tmatrix',
        'loss-given-default.index'    => 'lgd',
        'macro-forecast-weighted.index' => 'fli',
        'expected-credit-loss.index'  => 'ecl',
        'ifrs9-reports.index'         => 'reports',
        'stress-testing.index'        => 'stress-testing',
        'ifrs9-reports.ews'           => 'ews',
        'tickets.index'               => 'tickets',
        'users.index'                 => 'users',
        'users.roles.index'           => 'roles',
        'settings.index'              => 'settings',
    ];

    public function handle(): int
    {
        $bundled = is_file(base_path('node_modules/puppeteer/package.json'));
        $edge = $this->option('edge') ?: $this->detectBrowser();
        if (! $edge && ! $bundled) {
            $this->error('No Chromium browser found. Run `npm install puppeteer` or pass --edge.');

            return self::FAILURE;
        }
        if (! is_file(base_path('scripts/manual-screenshots.cjs'))) {
            $this->error('scripts/manual-screenshots.cjs is missing.');

            return self::FAILURE;
        }

        $baseUrl  = rtrim($this->option('base-url') ?: (config('app.url') ?: 'http://127.0.0.1:8000'), '/');
        $email    = $this->option('email') ?: env('MANUAL_SHOT_EMAIL', 'admin@localhost.com');
        $password = $this->option('password') ?: env('MANUAL_SHOT_PASSWORD');
        if (! $password) {
            $this->error('No password. Pass --password or set MANUAL_SHOT_PASSWORD.');

            return self::FAILURE;
        }
        $captcha = (string) config('captcha.manual_shot_code', '');
        if (config('captcha.enabled', true) && $captcha === '') {
            $this->error('Login CAPTCHA is enabled but MANUAL_SHOT_CAPTCHA is not set (local env only).');

            return self::FAILURE;
        }

        $outDir = $this->option('out') ?: public_path('manual/screenshots');
        File::ensureDirectoryExists($outDir);

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $shots = [];
        foreach (self::SHOTS as $routeName => $file) {
            if ($only && ! in_array($file, $only, true)) {
                continue;
            }
            if (! Route::has($routeName)) {
                $this->warn("Route {$routeName} not registered; skipped.");
                continue;
            }
            $shots[] = [
                'url'      => $baseUrl . route($routeName, [], false),
                // The .cjs script does path.join(outDir, file): bare filename only.
                'file'     => $file . '.jpg',
                // Viewport-only (not full scroll) so figures stay a sensible size.
                'fullPage' => false,
            ];
        }
        if (! $shots) {
            $this->error('No shots selected.');

            return self::FAILURE;
        }

        $cfg = [
            'edge'     => $edge,
            'baseUrl'  => $baseUrl,
            'email'    => $email,
            'password' => $password,
            'captcha'  => $captcha,
            'outDir'   => $outDir,
            'viewport' => ['width' => 1440, 'height' => 900, 'deviceScaleFactor' => 2],
            'shots'    => $shots,
        ];
        $cfgPath = storage_path('app/manual-shots-' . bin2hex(random_bytes(4)) . '.json');
        File::put($cfgPath, json_encode($cfg));

        $this->info('Capturing ' . count($shots) . " screenshots from {$baseUrl} as {$email} -> {$outDir}");
        $process = new Process([$this->detectNode(), base_path('scripts/manual-screenshots.cjs'), $cfgPath]);
        $process->setTimeout(600);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));
        @unlink($cfgPath);

        if (! $process->isSuccessful()) {
            $this->error('Screenshot capture failed. Check the app is running and the credentials are valid.');

            return self::FAILURE;
        }
        $this->info('Screenshots written to ' . $outDir);

        return self::SUCCESS;
    }

    private function detectBrowser(): ?string
    {
        foreach (array_filter([
            env('MAIIC_BROWSER_PATH'),
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
        ]) as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    private function detectNode(): string
    {
        foreach (['C:\\Program Files\\nodejs\\node.exe', '/usr/bin/node', '/usr/local/bin/node'] as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return 'node';
    }
}
