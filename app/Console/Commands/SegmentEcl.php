<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExpectedCreditLossController;
use App\Services\Ecl\EclDiscountingService;
use App\Support\ReportingPeriodLock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The ECL of every portfolio of a month, after the PD and LGD by segment.
 *
 *   php artisan ifrs9:segment-ecl 2026-01 2026-08 --pd=pd_post_fli --prune-stale
 *
 * Before any row is written the month is checked, and it fails closed with
 * the loans named when: a loan with an exposure has no measured stage, no
 * PD of the chosen kind or no LGD; or the loans carry PDs from more than one
 * PD segment run (a part-run month). Each portfolio is then calculated by
 * the ECL engine (ExpectedCreditLossController::calculateECL) after its old
 * rows for the month are removed, in one transaction, so a stage that
 * emptied since the last run leaves no stale row behind. --prune-stale
 * removes the portfolio rows of the month for portfolios that no longer
 * hold a loan (the single "Loans" portfolio the book was in before it was
 * split); without it they are listed and kept.
 */
class SegmentEcl extends Command
{
    protected $signature = 'ifrs9:segment-ecl {from : YYYY-MM} {to? : YYYY-MM} {--pd=pd_post_fli : pd_prefli|pd_post_fli} {--lgd=collection_lgd} {--discounting=undiscounted} {--prune-stale}';

    protected $description = 'Calculate the ECL of every portfolio of each month after the PD and LGD by segment, failing closed on a missing input';

    private const EAD = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';

    public function handle(ExpectedCreditLossController $controller, EclDiscountingService $discounting): int
    {
        $from = CarbonImmutable::parse($this->argument('from') . '-01');
        $to = CarbonImmutable::parse(($this->argument('to') ?? $this->argument('from')) . '-01');
        $pdCol = $this->option('pd') === 'pd_prefli' ? 'pd_prefli' : 'pd_post_fli';
        $lgdCol = $this->option('lgd');
        $failures = 0;

        for ($m = $from; $m <= $to; $m = $m->addMonth()) {
            $period = $m->format('Y-m');
            if (ReportingPeriodLock::isLocked($period)) {
                $this->error("{$period}: the period is locked; nothing written.");
                $failures++;
                continue;
            }
            $problems = $this->check($period, $pdCol, $lgdCol);
            if ($problems !== []) {
                $failures++;
                $this->error("{$period}: ECL not run (fail closed). " . implode(' ', $problems));
                continue;
            }
            $portfolios = DB::table('loan_books')->where('reporting_period', $period)->distinct()->orderBy('loan_portfolio_id')->pluck('loan_portfolio_id')->map(fn ($v) => (int) $v)->all();
            try {
                foreach ($portfolios as $pid) {
                    DB::transaction(function () use ($controller, $discounting, $period, $pid, $pdCol, $lgdCol) {
                        DB::table('expected_credit_loss')->where('reporting_period', $period)->where('ecl_calculation_level', 'portfolio')->where('ecl_calculation_id', $pid)->delete();
                        $controller->calculateECL(Request::create('/expected-credit-loss/calculations', 'POST', [
                            'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => $pid, 'reporting_period' => $period . '-01',
                            'pd_type' => $pdCol, 'lgd_type' => $lgdCol, 'discounting_mode' => $this->option('discounting'),
                        ]), $discounting);
                    });
                }
            } catch (Throwable $e) {
                $failures++;
                $this->error("{$period}: ECL failed: " . $e->getMessage());
                continue;
            }
            $stale = DB::table('expected_credit_loss')->where('reporting_period', $period)->where('ecl_calculation_level', 'portfolio')->whereNotIn('ecl_calculation_id', $portfolios);
            $staleRows = (clone $stale)->get(['ecl_calculation_id', 'ifrs9_stage', 'total_ecl']);
            if ($staleRows->isNotEmpty()) {
                if ($this->option('prune-stale')) {
                    $n = (clone $stale)->delete();
                    $this->warn("{$period}: removed {$n} stale portfolio row(s) for portfolio(s) " . $staleRows->pluck('ecl_calculation_id')->unique()->implode(', ') . ' that no longer hold a loan.');
                } else {
                    $this->warn("{$period}: " . $staleRows->count() . ' row(s) remain for portfolio(s) ' . $staleRows->pluck('ecl_calculation_id')->unique()->implode(', ') . ' that no longer hold a loan; rerun with --prune-stale to remove them.');
                }
            }
            $rows = DB::table('expected_credit_loss as e')->leftJoin('loan_portfolios as p', 'p.id', '=', 'e.ecl_calculation_id')
                ->where('e.reporting_period', $period)->where('e.ecl_calculation_level', 'portfolio')
                ->groupBy('e.ecl_calculation_id', 'p.name')->orderBy('e.ecl_calculation_id')
                ->selectRaw('e.ecl_calculation_id id, p.name, SUM(e.total_ead) ead, SUM(e.total_ecl) ecl, SUM(e.total_loans) n')->get();
            $book = DB::table('loan_books')->where('reporting_period', $period)->selectRaw('SUM(COALESCE(ecl_value,0)) ecl')->value('ecl');
            $this->info("{$period}: ECL by portfolio on {$pdCol}; ties to the loan book " . number_format((float) $book, 2));
            $this->table(['Portfolio', 'Loans', 'EAD', 'ECL', 'Coverage'], $rows->map(fn ($r) => [$r->name ?? $r->id, $r->n, number_format((float) $r->ead, 2), number_format((float) $r->ecl, 2),
                (float) $r->ead > 0 ? number_format($r->ecl / $r->ead * 100, 2) . '%' : '-'])->push(['Total', $rows->sum('n'), number_format((float) $rows->sum('ead'), 2), number_format((float) $rows->sum('ecl'), 2), ''])->all());
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> the reasons the month cannot be calculated; empty when it can */
    private function check(string $period, string $pdCol, string $lgdCol): array
    {
        $stage = 'COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative)';
        $pd = $pdCol === 'pd_prefli' ? 'COALESCE(pd_prefli, pd_post_fli)' : 'pd_post_fli';
        $problems = [];
        $exposed = DB::table('loan_books')->where('reporting_period', $period)->whereRaw('(' . self::EAD . ') <> 0');
        if (! DB::table('loan_books')->where('reporting_period', $period)->exists()) {
            return ["The loan book of {$period} is empty."];
        }
        foreach (["{$stage} IS NULL" => 'no measured stage', "{$pd} IS NULL" => "no {$pdCol}", "{$lgdCol} IS NULL" => "no {$lgdCol}"] as $cond => $what) {
            $ids = (clone $exposed)->whereRaw($cond)->limit(6)->pluck('contract_id');
            $n = (clone $exposed)->whereRaw($cond)->count();
            if ($n > 0) {
                $problems[] = "{$n} loan(s) with an exposure have {$what} (" . $ids->implode(', ') . ($n > 6 ? ', ...' : '') . ').';
            }
        }
        if (Schema::hasColumn('loan_books', 'pd_segment_run_id')) {
            $runs = DB::table('loan_books')->where('reporting_period', $period)->whereRaw("{$stage} IS NOT NULL")->distinct()->pluck('pd_segment_run_id');
            if ($runs->count() > 1 || $runs->contains(null)) {
                $problems[] = 'The loans carry PDs from ' . ($runs->contains(null) ? 'outside a PD segment run' : $runs->count() . ' PD segment runs') . '; run ifrs9:segment-parameters for the month first.';
            }
        }

        return $problems;
    }
}
