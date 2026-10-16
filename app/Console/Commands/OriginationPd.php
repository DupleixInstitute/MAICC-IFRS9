<?php

namespace App\Console\Commands;

use App\Services\Pd\OriginationPdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Each loan's starting risk (PD at Origination Specification v1).
 *
 *   php artisan pd:origination                     a summary of the records, by label
 *   php artisan pd:origination --backfill          write the record of every loan that has none, every month held, oldest first
 *   php artisan pd:origination --shadow=2026-09    the PD-change test in shadow for a month: who would move to Stage 2; changes nothing
 */
class OriginationPd extends Command
{
    protected $signature = 'pd:origination {--backfill : Write the starting reading of every loan that has none, from every month held, oldest first}
        {--shadow= : Run the PD-change test in shadow for a month, YYYY-MM (writes nothing)}';

    protected $description = "Keep each loan's PD at origination, and run the PD-change test (setting 9) in shadow";

    public function handle(OriginationPdService $service): int
    {
        if (! OriginationPdService::available()) {
            $this->error('This database has no PD at origination table yet (run the migrations).');

            return self::FAILURE;
        }
        if ($this->option('backfill')) {
            $r = $service->backfill();
            $this->info("{$r['captured']} starting reading(s) written " . json_encode($r['by_basis']) . "; {$r['unavailable']} loan(s) marked unavailable (no PD in any month).");
        }
        if ($period = $this->option('shadow')) {
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $this->error('The month is YYYY-MM.');

                return self::FAILURE;
            }
            $s = $service->shadow((string) $period);
            $this->info("Shadow PD-change test, {$period} (at least {$s['thresholds']['ratio_label']} the starting PD and {$s['thresholds']['points_label']} higher): "
                . "{$s['tested']} Stage 1 loan(s) tested, {$s['would_move']} would move to Stage 2. Not tested: " . json_encode($s['excluded']) . '. Staging is unchanged.');
            foreach (array_slice(array_filter($s['rows'], fn ($r) => $r['would_move']), 0, 20) as $r) {
                $this->line(sprintf('  %s  %-32s %6.2f%% -> %6.2f%%  x%.2f  +%.2f points', $r['contract_id'], mb_substr((string) $r['customer'], 0, 32), $r['pd_start'] * 100, $r['pd_now'] * 100, $r['ratio'], $r['points']));
            }
        }
        $by = DB::table('loan_origination_pds')->selectRaw('basis, count(*) n')->groupBy('basis')->pluck('n', 'basis')->all();
        $this->line('Starting readings held: ' . collect(OriginationPdService::LABELS)->map(fn ($label, $b) => $label . ' ' . ($by[$b] ?? 0))->implode(', ') . '.');

        return self::SUCCESS;
    }
}
