<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EclProjectionController extends Controller
{
    public function __construct(){ $this->middleware('auth'); }
    public function index(Request $request)
    {
        $runId=$request->input('run_id') ?: DB::table('ecl_projection_runs')->where('status','COMPLETED')->latest('id')->value('run_id');
        $search=trim((string)$request->input('search'));$scenario=trim((string)$request->input('scenario'));
        $run=$runId?DB::table('ecl_projection_runs')->where('run_id',$runId)->first():null;
        $rows=DB::table('ecl_cashflow_projections')->when($runId,fn($q)=>$q->where('run_id',$runId))
            ->when($search!=='',fn($q)=>$q->where('contract_id','like',"%{$search}%"))
            ->when($scenario!=='',fn($q)=>$q->where('scenario_code',$scenario))
            ->orderBy('contract_id')->orderBy('scenario_code')->orderBy('period_index')->paginate(30)->withQueryString();
        $contracts=$runId?DB::table('ecl_cashflow_projections')->where('run_id',$runId)->select('contract_id','ifrs9_stage')
            ->selectRaw('SUM(weighted_discounted_shortfall) weighted_ecl, SUM(undiscounted_shortfall * scenario_weight) undiscounted_ecl, MAX(discount_exponent) horizon')
            ->groupBy('contract_id','ifrs9_stage')->orderBy('contract_id')->get():collect();
        return Inertia::render('ExpectedCreditLoss/Projections',['run'=>$run,'rows'=>$rows,'contracts'=>$contracts,
            'runs'=>DB::table('ecl_projection_runs')->where('status','COMPLETED')->latest('id')->limit(20)->get(),
            'scenarios'=>DB::table('ecl_scenario_assumptions')->where('status','APPROVED')->orderByDesc('weight')->get(),
            'filters'=>['run_id'=>$runId,'search'=>$search,'scenario'=>$scenario]]);
    }
}
