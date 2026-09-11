<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;

/**
 * Capture hi-res, logged-in screenshots of every manual-referenced page via
 * headless Chromium (scripts/manual-screenshots.cjs). Output lands in
 * public/manual/screenshots so the User and Administrator Manuals embed
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
     * skipped with a warning. The manuals' figure maps key off the file key.
     * Every user-facing page and create form is listed so the User Manual can
     * show each screen (Ticket #011 rewrite).
     */
    private const SHOTS = [
        // Getting started and shell
        'dashboard'                   => 'dashboard',
        'profile.show'                => 'profile',
        'workspace.index'             => 'workspace',
        'help.index'                  => 'help',
        'help.admin'                  => 'help-admin',
        'docs.technical'              => 'docs-technical',
        'docs.installation'           => 'docs-installation',
        'help.manage.index'           => 'help-manage',
        'tickets.index'               => 'tickets',
        'tickets.create'              => 'tickets-create',
        // Portfolio setup
        'portfolios.index'            => 'portfolios',
        'portfolios.create'           => 'portfolios-create',
        'industry_types.index'        => 'sector-types',
        'industry_types.create'       => 'sector-types-create',
        'groups.index'                => 'product-groups',
        // Customer and loan data
        'clients.index'               => 'clients',
        'clients.create'              => 'clients-create',
        'loan_applications.loan-book' => 'loanbook',
        'loan_applications.loan-book.import.create' => 'loanbook-import',
        'imports.index'               => 'imports',
        // Collateral
        'collateral.register.index'   => 'collateral-register',
        'collateral.register.import'  => 'collateral-import',
        'collateral.types.index'      => 'collateral-types',
        'collateral.allocations.index' => 'collateral',
        // EIR and revenue recognition
        'eir-accounting-rules.index'  => 'eir-rules',
        'eir-data.index'              => 'eir-data',
        'eir-intake.index'            => 'eir-intake',
        'eir-fee-classification.index' => 'eir-fees',
        'eir-calculations.index'      => 'eir-calculations',
        'eir-reconciliation.index'    => 'eir-reconciliation',
        'eir-coverage.index'          => 'eir-coverage',
        // Staging and SICR
        'stageing-rules.index'        => 'staging',
        'sicr-groups.index'           => 'sicr-groups',
        'sicr-items.index'            => 'sicr-items',
        'sicr-triggers.index'         => 'sicr-triggers',
        'sicr-triggers.customers'     => 'sicr-customers',
        // PD
        'transition-profiles.index'   => 'tprofiles',
        'transition-profiles.create'  => 'tprofiles-create',
        'transition-matrices.index'   => 'tmatrix',
        'transition-matrices.create'  => 'tmatrix-create',
        'transition-matrices.report-by-period' => 'tmatrix-report',
        'transition-matrix-cummulative.index' => 'tmatrix-cumulative',
        'transition-matrix-cummulative.create' => 'tmatrix-cumulative-create',
        'internal-grading.profiles'   => 'internal-grades',
        // LGD
        'loss-given-default.index'    => 'lgd',
        'loss-given-default.create'   => 'lgd-create',
        'loss-given-default.report-by-period' => 'lgd-report',
        'lgd-cummulative.index'       => 'lgd-cumulative',
        'lgd-cummulative.create'      => 'lgd-cumulative-create',
        'lgd-calculations.index'      => 'lgd-calculations',
        'lgd-payment-report.index'    => 'lgd-payment-report',
        // Forward-looking
        'macro-statistics.index'      => 'macro-elements',
        'scenarios.profiles'          => 'scenario-profiles',
        'macro-forecast-weighted.index' => 'fli',
        'credit-loss-data.index'      => 'credit-loss-data',
        'credit-loss-data.create'     => 'credit-loss-data-create',
        'credit-loss-data.import'     => 'credit-loss-data-import',
        'forecasting.manual'          => 'adjusted-forecast',
        'regression.index'            => 'regression',
        'regression.create'           => 'regression-create',
        'fli.scenarios.index'         => 'economic-scenarios',
        'fli.external.index'          => 'external-calculations',
        'fli.external.list'           => 'calculation-history',
        // ECL
        'expected-credit-loss.index'  => 'ecl',
        'expected-credit-loss.create' => 'ecl-create',
        'expected-credit-loss.projections' => 'ecl-projections',
        // Reports
        'ifrs9-reports.index'         => 'reports',
        'ifrs9-reports.executive'     => 'report-executive',
        'ifrs9-reports.ecl'           => 'report-ecl',
        'ifrs9-reports.portfolio-trend' => 'report-portfolio-trend',
        'ifrs9-reports.sector-ecl'    => 'report-sector-ecl',
        'ifrs9-reports.product-group-ecl' => 'report-product-group-ecl',
        'ifrs9-reports.grade-ecl'     => 'report-grade-ecl',
        'ifrs9-reports.account-ecl'   => 'report-account-ecl',
        'ifrs9-reports.stage-allocation' => 'report-stage-allocation',
        'ifrs9-reports.sicr-trigger'  => 'report-sicr-trigger',
        'ifrs9-reports.stage-migration' => 'report-stage-migration',
        'ifrs9-reports.ecl-reconciliation' => 'report-ecl-reconciliation',
        'ifrs9-reports.gross-movement' => 'report-gross-movement',
        'ifrs9-reports.ecl-charge'    => 'report-ecl-charge',
        'ifrs9-reports.pd-report'     => 'report-pd',
        'ifrs9-reports.lgd-collateral' => 'report-lgd-collateral',
        'ifrs9-reports.crm-agri'      => 'report-crm-agri',
        'ifrs9-reports.ead-report'    => 'report-ead',
        'ifrs9-reports.macro-scenario' => 'report-macro-scenario',
        'ifrs9-reports.scenario-ecl'  => 'report-scenario-ecl',
        'ifrs9-reports.rbm-classification' => 'report-rbm-classification',
        'ifrs9-reports.ifrs9-vs-rbm'  => 'report-ifrs9-vs-rbm',
        'ifrs9-reports.npl-arrears'   => 'report-npl-arrears',
        'ifrs9-reports.provision-comparison' => 'report-provision-comparison',
        'ifrs9-reports.concentration' => 'report-concentration',
        'ifrs9-reports.coop-linkage'  => 'report-coop-linkage',
        'ifrs9-reports.fs-disclosure' => 'report-fs-disclosure',
        'ifrs9-reports.data-quality'  => 'report-data-quality',
        'ifrs9-reports.ews'           => 'ews',
        'ifrs9-reports.ai-narrative'  => 'report-ai-narrative',
        'reports.ecl-reconciliation'  => 'ecl-reconciliation',
        'reports.loan-book-reconciliation' => 'loanbook-reconciliation',
        'reports.disbursement-report' => 'disbursements',
        'stress-testing.index'        => 'stress-testing',
        // Administration
        'users.index'                 => 'users',
        'users.create'                => 'users-create',
        'users.roles.index'           => 'roles',
        'users.roles.create'          => 'roles-create',
        'accounting.financial_periods.index' => 'financial-periods',
        'accounting.financial_periods.create' => 'financial-periods-create',
        'audit-trail.index'           => 'audit-trail',
        'settings.index'              => 'settings',
        'settings.organisation'       => 'settings-organisation',
        'settings.general'            => 'settings-general',
        'settings.system'             => 'settings-system',
        'settings.email'              => 'settings-email',
        'settings.sms'                => 'settings-sms',
        'settings.other'              => 'settings-other',
        'currencies.index'            => 'currencies',
        'chart_of_accounts.index'     => 'chart-of-accounts',
        'branches.index'              => 'branches',
        'legal_types.index'           => 'legal-types',
        'banks.index'                 => 'banks',
        'license.index'               => 'license',
    ];

    /**
     * Keys captured full-page (the whole scroll height) because the screen
     * is a long report or a page whose lower half carries the tables the
     * manual explains. Everything else is a viewport shot.
     */
    private const FULL_PAGE = [
        'dashboard', 'workspace', 'loanbook', 'ecl', 'ecl-create', 'ecl-projections', 'tmatrix', 'lgd',
        'stress-testing', 'ecl-reconciliation', 'loanbook-reconciliation', 'disbursements',
        'eir-data', 'eir-intake', 'eir-fees', 'eir-calculations', 'eir-reconciliation', 'eir-coverage',
        'report-executive', 'report-ecl', 'report-account-ecl', 'report-rbm-classification', 'report-fs-disclosure', 'ews',
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
                // Viewport by default so figures stay a sensible size; long
                // report and data pages are captured whole.
                'fullPage' => in_array($file, self::FULL_PAGE, true),
            ];
        }
        if (! $shots) {
            $this->error('No shots selected.');

            return self::FAILURE;
        }

        $cfg = [
            // Bundled Chromium by default; an explicit --edge path overrides it.
            'edge'     => $this->option('edge') ?: ($bundled ? null : $edge),
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
