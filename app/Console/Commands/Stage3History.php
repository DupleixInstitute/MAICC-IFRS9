<?php

namespace App\Console\Commands;

use App\Services\Ecl\TimePhasedEclService;
use App\Services\Lgd\LgdSegmentationService;
use App\Services\MegaFarm\MegaFarmEclService;
use App\Services\Pd\PdSegmentationService;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The Stage 3 history (15 October 2026): the booked allowance of Stage 3 loans in the months before the first
 * month with a full PD, so their interest on the net carrying amount (IFRS 9 5.4.1(b)) can run.
 *
 * Before December 2025 the book held too few Stage 2 loans (3 or 4 at each window start, against the governed
 * minimum of 10 loans and 1 default) for any stage-2 PD, so every month's PD failed closed whole and no loan
 * carried an allowance; the revenue engine then refuses a Stage 3 loan's month for want of the opening
 * allowance, and 34 locked loans stopped accruing at their first Stage 3 month. A Stage 3 loan is in default:
 * its PD is 1 whatever the transitions. So for each month this runs, on MAIIC's own book (Mega Farm aside):
 *
 *   1. the LGD by portfolio (LgdSegmentationService, unchanged; it fails closed when thin, and then the month
 *      gets nothing),
 *   2. the PD on the Stage 3 loans only (PdSegmentationService with ['3']: PD 1, recorded with the scope),
 *   3. the ECL of those loans discounted at the EIR (TimePhasedEclService on the pre-FLI PD, which needs no
 *      scenario set; with PD 1 every scenario gives the same figure), its undiscounted figure beside it.
 *
 * Stage 1 and 2 loans of these months keep no PD and no ECL: the month is not a full allowance and is never
 * presented as one (the projection run is marked TIME_PHASED_V1_STAGE3_HISTORY). A month that already holds an
 * ECL on any loan is left alone.
 */
class Stage3History extends Command
{
    public const METHOD = 'TIME_PHASED_V1_STAGE3_HISTORY';

    protected $signature = 'ifrs9:stage3-history {from : YYYY-MM} {to : YYYY-MM} {--user= : Id of the user the runs are recorded against (required)}';

    protected $description = 'Book the Stage 3 allowance (PD 1, LGD by portfolio, discounted at the EIR) for months before the first full PD, for the interest on the net basis';

    public function handle(LgdSegmentationService $lgd, PdSegmentationService $pd, TimePhasedEclService $ecl): int
    {
        $user = (int) $this->option('user');
        if ($user <= 0) {
            $this->error('Give --user: the id the runs are recorded against.');

            return self::FAILURE;
        }
        $rows = [];
        for ($m = CarbonImmutable::parse($this->argument('from') . '-01'); $m <= CarbonImmutable::parse($this->argument('to') . '-01'); $m = $m->addMonth()) {
            $p = $m->format('Y-m');
            $book = DB::table('loan_books')->where('reporting_period', $p)->whereNotIn('product_code', MegaFarmEclService::GLS);
            if ((clone $book)->whereNotNull('ecl_value')->exists()) {
                $rows[] = [$p, '-', '-', '-', 'left alone: the month already holds an ECL'];
                continue;
            }
            $stage3 = (clone $book)->where('ifrs9stage_post_qualitative', 3)->count();
            if ($stage3 === 0) {
                $rows[] = [$p, 0, '-', '-', 'no Stage 3 loan'];
                continue;
            }
            try {
                $lgd->run($p, null, 12, $user);
            } catch (Throwable $e) {
                $rows[] = [$p, $stage3, '-', '-', 'no LGD, nothing booked: ' . mb_substr($e->getMessage(), 0, 160)];
                continue;
            }
            try {
                $pd->run($p, null, 12, $user, 'stage3-history', ['3']);
            } catch (Throwable $e) {
                $rows[] = [$p, $stage3, '-', '-', 'no PD, nothing booked: ' . mb_substr($e->getMessage(), 0, 160)];
                continue;
            }
            $loans = (clone $book)->where('ifrs9stage_post_qualitative', 3)->whereNotNull('pd_prefli')->whereNotNull('collection_lgd')->get();
            $r = $ecl->run($loans, $p, 'collection_lgd', $user, 'pd_prefli');
            DB::table('ecl_projection_runs')->where('run_id', $r['run_id'])->update(['methodology_version' => self::METHOD]);
            // the undiscounted figure beside the booked one: discounted plus the discounting effect
            DB::table('loan_books')->where('ecl_calculation_run_id', $r['run_id'])->where('ecl_discount_status', 'CALCULATED_TIME_PHASED')
                ->update(['ecl_value' => DB::raw('ecl_value_discounted + COALESCE(ecl_discounting_effect, 0)')]);
            AuditLoggerService::log('Stage 3 History Allowance', 'loan_books', null, ['reporting_period' => $p, 'rows_affected' => $r['calculated'],
                'new_values' => ['run_id' => $r['run_id'], 'discounted' => round($r['discounted'], 2), 'unresolved' => $r['unresolved']], 'meta' => ['user' => $user, 'method' => self::METHOD]]);
            $rows[] = [$p, $stage3, $r['calculated'], number_format($r['discounted'], 2),
                $r['unresolved'] ? $r['unresolved'] . ' not discounted: ' . mb_substr(implode(' | ', array_slice(array_map(fn ($k, $v) => "{$k} {$v}", array_keys($r['exceptions']), $r['exceptions']), 0, 2)), 0, 160) : 'booked'];
        }
        $this->table(['Month', 'Stage 3 loans', 'Booked', 'Discounted allowance', 'Note'], $rows);

        return self::SUCCESS;
    }
}
