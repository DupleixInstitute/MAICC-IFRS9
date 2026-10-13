<?php

namespace App\Console\Commands;

use App\Services\Lgd\LgdSegmentationService;
use App\Services\Pd\PdSegmentationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * The PD and LGD by segment on the console, month by month.
 *
 *   php artisan ifrs9:segment-parameters 2026-01 2026-08            PD then LGD for every month
 *   php artisan ifrs9:segment-parameters 2026-08 --pd                PD only
 *   php artisan ifrs9:segment-parameters 2026-08 --dry-run           measure and show, write nothing
 *
 * The basis, the minimum and the thin-segment rule are the governed ones in
 * force at each month end. A month that fails closed is reported with the
 * segment and the reason and the next month still runs.
 */
class SegmentParameters extends Command
{
    protected $signature = 'ifrs9:segment-parameters {from : YYYY-MM} {to? : YYYY-MM} {--pd : the PD only} {--lgd : the LGD only} {--window=12 : months} {--user=} {--dry-run : measure and show, write nothing}';

    protected $description = 'Measure and apply the PD and the LGD by segment on the governed basis, month by month';

    public function handle(PdSegmentationService $pd, LgdSegmentationService $lgd): int
    {
        $from = CarbonImmutable::parse($this->argument('from') . '-01');
        $to = CarbonImmutable::parse(($this->argument('to') ?? $this->argument('from')) . '-01');
        $doPd = $this->option('pd') || ! $this->option('lgd');
        $doLgd = $this->option('lgd') || ! $this->option('pd');
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;
        $window = (int) $this->option('window');
        $failures = 0;

        for ($m = $from; $m <= $to; $m = $m->addMonth()) {
            $period = $m->format('Y-m');
            if ($doPd) {
                try {
                    if ($this->option('dry-run')) {
                        $this->dryRun($pd, $period, $window);
                    } else {
                        $r = $pd->run($period, null, $window, $user, 'segments');
                        $this->info("{$period} PD on basis '{$r['basis']}' over {$r['window']}: {$r['updated']} loans given a PD" . ($r['unstaged'] ? ", {$r['unstaged']} unstaged left without one" : '') . " (run {$r['run_id']})");
                        $this->table(['Segment', 'Stage', 'Cohort', 'Defaults', 'Observed', 'Applied PD', 'Source', 'Loans'], array_map(fn ($c) => [
                            $c['label'], $c['stage'], $c['cohort'], $c['defaults'], $c['observed'] === null ? '-' : number_format($c['observed'] * 100, 2) . '%',
                            number_format((float) $c['pd'] * 100, 2) . '%', $c['status'] === 'parent' ? 'parent: ' . $c['from'] : $c['status'], $c['loans']], $r['cells']));
                    }
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("{$period} PD: " . $e->getMessage());
                }
            }
            if ($doLgd && ! $this->option('dry-run')) {
                try {
                    $r = $lgd->run($period, null, $window, $user);
                    $this->info("{$period} LGD over {$r['window']}: {$r['updated']} loans; pooled book cohort {$r['pooled']['cohort']}, LGD " . number_format($r['pooled']['lgd'] * 100, 2) . '%');
                    $this->table(['Portfolio', 'Stage 3 cohort', 'Own LGD', 'Applied LGD', 'Source', 'Loans'], array_map(fn ($p) => [
                        $p['label'], $p['cohort'], $p['own_lgd'] === null ? '-' : number_format($p['own_lgd'] * 100, 2) . '%', $p['lgd'] === null ? '-' : number_format($p['lgd'] * 100, 2) . '%',
                        $p['status'] === 'parent' ? 'parent: ' . $p['from'] : $p['status'], $p['loans']], $r['portfolios']));
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("{$period} LGD: " . $e->getMessage());
                }
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dryRun(PdSegmentationService $pd, string $period, int $window): void
    {
        $cfg = $pd->settings($period);
        $m = $pd->measure($period, $cfg['basis'], $window, null, $cfg['unverified']);
        $this->info("{$period} PD (dry run) on basis '{$cfg['basis']}' over {$m['window_start']} to {$m['window_end']}; minimum: {$cfg['minimum_option']}; thin rule: {$cfg['thin_option']}");
        $rows = [];
        foreach ($m['segments'] as $key => $s) {
            foreach (['1', '2'] as $st) {
                $c = $s['stages'][$st] ?? null;
                $t = PdSegmentationService::test($s, $st, $cfg['min_loans'], $cfg['min_defaults']);
                $rows[] = [$s['label'], $st, (int) ($c['loans'] ?? 0), (int) ($c['default_loans'] ?? 0), isset($c['annual']) ? number_format($c['annual'] * 100, 2) . '%' : '-', $t['ok'] ? 'meets' : 'thin'];
            }
        }
        $this->table(['Segment', 'Stage', 'Cohort', 'Defaults', 'PD', 'Minimum'], $rows);
    }
}
