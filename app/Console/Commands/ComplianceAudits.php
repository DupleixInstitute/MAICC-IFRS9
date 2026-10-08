<?php

namespace App\Console\Commands;

use App\Services\Compliance\ComplianceAuditService;
use App\Services\Eir\BaselineService;
use App\Services\Eir\EirAsAtService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;
use ZipArchive;

/**
 * The compliance register and the auditor's pack on the console (spec v4 section 12.5).
 *
 *   php artisan compliance:audits --load             load the register from docs/compliance/*.json
 *   php artisan compliance:audits --export-signed    write the signed state beside each workbook for the builder
 *   php artisan compliance:audits --pack=2026-08     the auditor's pack: the workbooks, the EIR as at the period end,
 *                                                    the baselines, a manifest with a checksum per file
 */
class ComplianceAudits extends Command
{
    protected $signature = 'compliance:audits {--load} {--export-signed} {--pack=}';

    protected $description = 'Load the compliance register from the workbooks, export the signed state, build the auditor\'s pack for a period';

    public function handle(ComplianceAuditService $service): int
    {
        try {
            if ($this->option('load')) {
                $r = $service->load();
                $this->info('Register loaded: ' . collect($r)->map(fn ($n, $k) => "{$k} {$n} rows")->implode(', '));
            }
            if ($this->option('export-signed')) {
                $r = $service->exportSigned();
                $this->info('Signed state exported: ' . collect($r)->map(fn ($n, $k) => "{$k} {$n} approved rows")->implode(', '));
            }
            if ($period = $this->option('pack')) {
                $this->line($this->pack((string) $period));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        if (! $this->option('load') && ! $this->option('export-signed') && ! $this->option('pack')) {
            foreach (DB::table('compliance_audits')->get() as $a) {
                $this->info("{$a->key}: {$a->short}: " . json_encode($service->counts($a->id)));
            }
        }

        return self::SUCCESS;
    }

    /** The pack: an archive with a manifest and a SHA-256 per file, the way the suite archives a return. */
    private function pack(string $period): string
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \RuntimeException('Period must be YYYY-MM.');
        }
        $dir = storage_path('app/auditor-packs');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = $dir . '/tmp-' . $period;
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }
        $files = [];
        foreach (glob(base_path('docs/compliance/*.{xlsx,pdf,md}'), GLOB_BRACE) ?: [] as $f) {
            copy($f, $tmp . '/' . basename($f));
            $files[] = basename($f);
        }
        // the baselines as read now
        $baselines = app(BaselineService::class)->checks();
        file_put_contents($tmp . "/Baselines {$period}.json", json_encode(['period' => $period, 'generated_at' => now()->toDateTimeString(), 'checks' => $baselines], JSON_PRETTY_PRINT));
        $files[] = "Baselines {$period}.json";
        // the EIR as at the period end (the book), as the screen's CSV
        try {
            $end = \Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString();
            $book = app(EirAsAtService::class)->book($end);
            $csv = fopen($tmp . "/EIR as at {$end}.csv", 'w');
            fputcsv($csv, ['Contract', 'Customer', 'Product', 'GL', 'EIR interest YTD', 'Contractual YTD', 'Difference', 'Amortised cost', 'Gross']);
            foreach ($book['contracts'] as $l) {
                fputcsv($csv, [$l['contract_id'], $l['customer_name'], $l['product'], $l['gl'], $l['eir_ytd'], $l['contractual_ytd'], $l['difference'], $l['amortised_cost'], $l['gross']]);
            }
            fclose($csv);
            $files[] = "EIR as at {$end}.csv";
        } catch (Throwable $e) {
            file_put_contents($tmp . '/EIR as at - not available.txt', $e->getMessage());
            $files[] = 'EIR as at - not available.txt';
        }
        // the ECL of the period by stage
        $ecl = DB::table('loan_books')->where('reporting_period', $period)->selectRaw('ifrs9stage_post_qualitative stage, count(*) loans, round(sum(carrying_amount), 2) carrying, round(sum(ecl_value), 2) ecl')->groupBy('ifrs9stage_post_qualitative')->get();
        file_put_contents($tmp . "/ECL by stage {$period}.json", json_encode($ecl, JSON_PRETTY_PRINT));
        $files[] = "ECL by stage {$period}.json";
        // the manifest
        $manifest = ['pack' => "MAIIC auditor's pack {$period}", 'built_at' => now()->toDateTimeString(), 'files' => []];
        foreach ($files as $f) {
            $manifest['files'][] = ['file' => $f, 'bytes' => filesize($tmp . '/' . $f), 'sha256' => hash_file('sha256', $tmp . '/' . $f)];
        }
        file_put_contents($tmp . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zipPath = $dir . "/MAIIC auditor pack {$period}.zip";
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (array_merge($files, ['manifest.json']) as $f) {
            $zip->addFile($tmp . '/' . $f, $f);
        }
        $zip->close();
        foreach (glob($tmp . '/*') as $f) {
            unlink($f);
        }
        rmdir($tmp);

        return "Auditor's pack written: {$zipPath} (" . count($files) . ' files, each with its SHA-256 in manifest.json).';
    }
}
