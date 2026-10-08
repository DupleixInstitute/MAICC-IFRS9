<?php

namespace App\Http\Controllers;

use App\Services\Eir\GovernanceService;
use App\Services\MegaFarm\MegaFarmEclService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Financial Modelling, Mega Farm Programme (spec v4 section 16, decision
 * D30): the programme's book by period, the governed settings in force,
 * each run with its basis or the reason it was declined, and the Run
 * action for the governed role. Nothing here is a MAIIC approval until
 * the CFO confirms D30 in the Governance Centre.
 */
class MegaFarmController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:eir.govern')->only(['run']);
    }

    public function index(Request $request, MegaFarmEclService $service, GovernanceService $governance)
    {
        $periods = $service->loans(null)->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');
        $period = (string) $request->query('period', $periods->first() ?? '');
        $book = $period !== '' ? $service->loans($period)->selectRaw("product_group, product_code, count(*) loans, round(sum(carrying_amount), 2) carrying, sum(case when ifrs9stage_post_qualitative = '3' then 1 else 0 end) stage3_loans, round(sum(case when ifrs9stage_post_qualitative = '3' then carrying_amount else 0 end), 2) stage3_carrying, round(sum(ecl_value), 2) ecl, round(avg(pd_prefli), 6) pd")
            ->groupBy('product_group', 'product_code')->orderBy('product_code')->get() : collect();
        $byStage = $period !== '' ? $service->loans($period)->selectRaw("ifrs9stage_post_qualitative stage, count(*) loans, round(sum(carrying_amount), 2) carrying, round(sum(ecl_value), 2) ecl")->groupBy('stage')->orderBy('stage')->get() : collect();
        $settings = [];
        foreach (['mega_farms_scope', 'megafarm_pd_method', 'megafarm_scalar_ceiling'] as $k) {
            try {
                $settings[$k] = $governance->get($k);
            } catch (Throwable) {
                $settings[$k] = null;
            }
        }
        $runs = DB::table('megafarm_ecl_runs as r')->leftJoin('users as u', 'u.id', '=', 'r.run_by')->orderByDesc('r.id')->limit(20)->get(['r.*', 'u.name as run_by_name'])->map(function ($r) {
            $r->basis = json_decode($r->basis ?? '', true) ?: [];

            return $r;
        });

        return Inertia::render('MegaFarm/Index', [
            'period' => $period, 'periods' => $periods, 'book' => $book, 'byStage' => $byStage, 'settings' => $settings, 'runs' => $runs,
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            'confirmed' => DB::table('governance_settings')->where('key', 'mega_farms_scope')->where('status', 'APPROVED')->whereNotNull('approved_by')->exists(),
        ]);
    }

    public function run(Request $request, MegaFarmEclService $service)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $r = $service->run($data['period'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($r['declined'] ? 'error' : 'success', $r['declined'] ? "Declined: {$r['declined']}" : "Mega Farm {$r['period']}: programme ECL " . number_format($r['programme_ecl'], 0) . ', MAIIC share ' . ($r['share'] * 100) . '% = ' . number_format($r['maiic_ecl'], 0) . '.');
    }
}
