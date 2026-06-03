<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExpectedCreditLossController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Full-year 2025 PD -> LGD -> ECL generation for the capability demo.
 *
 * The live 2025-11 snapshot is fully modelled; the earlier 2025 monthly
 * snapshots are thin (some have no pd_prefli, most have no collection_lgd).
 * Rather than invent numbers, this command CALIBRATES from the book's own
 * 2025-11 basis (PD per IFRS 9 stage, flat collection LGD) and propagates it
 * into the gap months, then runs the real per-portfolio ECL engine for every
 * 2025 period. Existing real values are never overwritten (gap-fill only).
 *
 * This is a demo enrichment, not a model build: it makes the year complete and
 * internally consistent so per-portfolio capability can be shown. Reversible
 * via the DB backup. Idempotent and supports --dry-run.
 */
class Generate2025Ecl extends Command
{
    protected $signature = 'ifrs9:generate-2025
        {--dry-run : Show the plan and calibration without writing}';

    protected $description = 'Calibrate PD/LGD from the 2025-11 basis, fill 2025 gap months, and run per-portfolio ECL for all of 2025.';

    private const CAL_PERIOD = '2025-11';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // ---- 1. Calibrate from the book's own 2025-11 basis --------------
        $pdByStage = DB::table('loan_books')
            ->where('reporting_period', self::CAL_PERIOD)
            ->whereNotNull('pd_prefli')
            ->select('ifrs9stage_pre_qualitative as s', DB::raw('ROUND(AVG(pd_prefli),4) v'))
            ->groupBy('ifrs9stage_pre_qualitative')
            ->pluck('v', 's');

        $lgdFlat = (float) DB::table('loan_books')
            ->where('reporting_period', self::CAL_PERIOD)
            ->whereNotNull('collection_lgd')
            ->avg('collection_lgd');
        $lgdFlat = round($lgdFlat, 4);

        if ($pdByStage->isEmpty() || $lgdFlat <= 0) {
            $this->error('Cannot calibrate: 2025-11 has no usable pd_prefli / collection_lgd.');
            return self::FAILURE;
        }

        $this->info('Calibration from ' . self::CAL_PERIOD . ' basis:');
        foreach ($pdByStage as $stage => $v) {
            $this->line("  Stage {$stage}: PD = {$v}");
        }
        $this->line("  Collection LGD (flat) = {$lgdFlat}");
        $this->line('');

        // ---- 2. Target periods + portfolios -----------------------------
        $periods = DB::table('loan_books')
            ->where('reporting_period', 'like', '2025-%')
            ->distinct()->orderBy('reporting_period')
            ->pluck('reporting_period');

        $portfolios = DB::table('loan_portfolios')
            ->whereIn('name', ['Agri-Inputs', 'Farm Equipment', 'Irrigation', 'Agri Working Capital', 'Industrial'])
            ->pluck('id', 'name');

        $this->line('Periods: ' . $periods->implode(', '));
        $this->line('Portfolios: ' . $portfolios->keys()->implode(', '));
        $this->line('');

        $controller = app(ExpectedCreditLossController::class);

        foreach ($periods as $period) {
            // ---- 2a. PD gap-fill (stage-calibrated, NULL only) ----------
            $pdFilled = 0;
            foreach ($pdByStage as $stage => $v) {
                $q = DB::table('loan_books')
                    ->where('reporting_period', $period)
                    ->where('ifrs9stage_pre_qualitative', $stage)
                    ->whereNull('pd_prefli');
                $n = (clone $q)->count();
                if ($n && ! $dry) {
                    (clone $q)->update(['pd_prefli' => $v]);
                }
                $pdFilled += $n;
            }

            // ---- 2b. LGD gap-fill (flat-calibrated, NULL/zero only) -----
            $lgdQ = DB::table('loan_books')
                ->where('reporting_period', $period)
                ->where(fn ($w) => $w->whereNull('collection_lgd')->orWhere('collection_lgd', 0));
            $lgdFilled = (clone $lgdQ)->count();
            if ($lgdFilled && ! $dry) {
                (clone $lgdQ)->update(['collection_lgd' => $lgdFlat]);
            }

            // ---- 2c. Per-portfolio ECL (real engine) -------------------
            $eclRuns = 0;
            foreach ($portfolios as $pName => $pId) {
                $has = DB::table('loan_books')
                    ->where('reporting_period', $period)
                    ->where('loan_portfolio_id', $pId)
                    ->exists();
                if (! $has) {
                    continue;
                }
                $eclRuns++;
                if ($dry) {
                    continue;
                }
                $request = Request::create('/expected-credit-loss/calculations', 'POST', [
                    'ecl_calculation_level' => 'portfolio',
                    'ecl_calculation_id'    => $pId,
                    'reporting_period'      => $period . '-01',
                    'pd_type'               => 'pd_prefli',
                    'lgd_type'              => 'collection_lgd',
                ]);
                try {
                    $controller->calculateECL($request);
                } catch (\Throwable $e) {
                    $this->error("  ECL failed {$period}/{$pName}: " . $e->getMessage());
                }
            }

            $this->line(sprintf(
                '%s %s  PD filled %4d | LGD filled %4d | ECL runs %d',
                $dry ? '[dry]' : '  ok ',
                $period,
                $pdFilled,
                $lgdFilled,
                $eclRuns
            ));
        }

        // ---- 3. Drop stale pre-segmentation rows for the blended bucket --
        //    Portfolio 1 ("Loans") was the single blended bucket before
        //    segmentation. Its 2025 portfolio-level ECL rows now double-count
        //    the real portfolios. Residual unmapped loans are an audit
        //    exception (flagged in Data Quality), not a reporting portfolio.
        $staleQ = DB::table('expected_credit_loss')
            ->where('ecl_calculation_level', 'portfolio')
            ->where('ecl_calculation_id', 1)
            ->where('reporting_period', 'like', '2025-%');
        $stale = (clone $staleQ)->count();
        if ($stale > 0) {
            if (! $dry) {
                (clone $staleQ)->delete();
            }
            $this->line(($dry ? '[dry] ' : '  ok ') . "removed {$stale} stale blended-bucket ECL rows (portfolio 1)");
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . 'Full-year 2025 generation complete.');
        return self::SUCCESS;
    }
}
