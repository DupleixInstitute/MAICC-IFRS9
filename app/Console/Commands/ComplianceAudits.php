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
        // the latest staging comparison run of the period (Staging Module Specification v1, section 4): the stages
        // and ECL under the other policy beside those booked, with the setting behind each difference
        $run = \Illuminate\Support\Facades\Schema::hasTable('staging_comparison_runs')
            ? DB::table('staging_comparison_runs')->where('reporting_period', $period)->orderByDesc('id')->first() : null;
        if ($run !== null) {
            $name = "Staging comparison {$period}.csv";
            $csv = fopen($tmp . '/' . $name, 'w');
            fputcsv($csv, ["Staging comparison run {$run->id} of {$run->created_at}: booked under '{$run->booked_preset}', compared with '{$run->compared_preset}'. Nothing booked was changed."]);
            fputcsv($csv, ['Contract', 'Customer', 'Portfolio', 'Term (months)', 'Booked stage', 'Compared stage', 'Days (booked rules)', 'Days (compared rules)', 'Reason', 'Carrying amount', 'EAD', 'Booked ECL', 'Compared ECL', 'ECL effect', 'Stage 3 interest effect', 'RBM class']);
            foreach (DB::table('staging_comparison_rows')->where('run_id', $run->id)->orderBy('contract_id')->get() as $r) {
                fputcsv($csv, [$r->contract_id, $r->customer_name, $r->product_group, $r->tenor, $r->booked_stage, $r->compared_stage, $r->booked_dpd, $r->compared_dpd, $r->reason, $r->carrying_amount, $r->ead, $r->booked_ecl, $r->compared_ecl, $r->ecl_effect, $r->stage3_interest_effect, $r->rbm_class]);
            }
            fclose($csv);
            $files[] = $name;
        }
        // every governed change up to the period end, with its reasons, decisions and documents (Staging Module
        // Specification v1, section 8.4): the history of each Governance Centre setting
        if (\Illuminate\Support\Facades\Schema::hasTable('governance_settings')) {
            $end = \Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString();
            $names = DB::table('users')->pluck('name', 'id');
            $docs = \Illuminate\Support\Facades\Schema::hasTable('supporting_documents')
                ? DB::table('supporting_documents')->where('documentable_type', \App\Models\GovernanceSetting::class)->get()->groupBy('documentable_id') : collect();
            $name = "Governance changes to {$end}.csv";
            $csv = fopen($tmp . '/' . $name, 'w');
            fputcsv($csv, ['Setting', 'Change', 'Value', 'Effective from', 'Status', 'Proposed by', 'Reason', 'Approved by', 'Approved at', 'Rejected or withdrawn by', 'Why', 'Documents (SHA-256)']);
            foreach (DB::table('governance_settings')->whereDate('created_at', '<=', $end)->orderBy('key')->orderBy('effective_from')->orderBy('id')->get() as $s) {
                fputcsv($csv, [$s->key, $s->id, $s->value, substr((string) $s->effective_from, 0, 10), $s->status, $s->set_by ? ($names[$s->set_by] ?? $s->set_by) : 'installation', $s->reason,
                    $s->approved_by ? ($names[$s->approved_by] ?? $s->approved_by) : null, $s->approved_at, ($s->reviewed_by ?? null) ? ($names[$s->reviewed_by] ?? $s->reviewed_by) : null, $s->review_reason ?? null,
                    collect($docs->get($s->id, []))->map(fn ($d) => $d->original_name . ($d->deleted_at ? ' (removed)' : '') . ' ' . $d->hash)->implode('; ')]);
            }
            fclose($csv);
            $files[] = $name;
        }
        // the register of approvals to the period end, with its reconciliation to the audit log (Staging Module
        // Specification v1, section 8.7; acceptance 15)
        if (\Illuminate\Support\Facades\Schema::hasTable('governance_settings')) {
            $end = \Carbon\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString();
            $register = app(\App\Services\ApprovalsRegisterService::class);
            $rows = $register->rows(['to' => $end]);
            $rec = $register->reconcile();
            $name = "Approvals register to {$end}.csv";
            $csv = fopen($tmp . '/' . $name, 'w');
            fputcsv($csv, ['Register of approvals to ' . $end . '; reconciled to the audit log: ' . ($rec['reconciled'] ? 'yes' : 'no, ' . (count($rec['decided_without_entry']) + count($rec['entries_without_item'])) . ' finding(s) listed at the end')]);
            fputcsv($csv, \App\Services\ApprovalsRegisterService::EXPORT_HEADINGS);
            foreach (\App\Services\ApprovalsRegisterService::exportRows($rows) as $line) {
                fputcsv($csv, $line);
            }
            foreach ($rec['decided_without_entry'] as $w) {
                fputcsv($csv, ['Finding: decided, with no audit entry', $w['workflow'], $w['id'], $w['title'], $w['status']]);
            }
            foreach ($rec['entries_without_item'] as $o) {
                fputcsv($csv, ['Finding: audit entry for an item not on record', $o['workflow'], $o['id'], 'entry ' . $o['entry'], $o['action'], $o['at']]);
            }
            fclose($csv);
            $files[] = $name;
        }
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
