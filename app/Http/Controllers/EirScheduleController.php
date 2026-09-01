<?php
namespace App\Http\Controllers;

use App\Models\ContractEir;
use App\Services\Eir\ScheduleWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EirScheduleController extends Controller
{
    public function __construct() { $this->middleware(['auth','permission:settings']); }

    public function dryRun(ScheduleWorkflowService $workflow)
    {
        $r=$workflow->dryRun();
        return back()->with('success',"Schedule dry run: {$r['eligible']} eligible; {$r['blocked']} blocked.");
    }
    public function show(ContractEir $contractEir, ScheduleWorkflowService $workflow)
    {
        $generated=DB::table('contract_cashflow_schedule')->where('contract_id',$contractEir->contract_id)
            ->where('schedule_version',1)->orderBy('due_date')->get();
        $remaining=DB::table('contract_remaining_cashflow_schedule')->where('contract_id',$contractEir->contract_id)
            ->orderBy('due_date')->orderBy('id')->get();
        $totals=fn($rows)=>['rows'=>$rows->count(),'principal'=>(float)$rows->sum('principal_due'),
            'interest'=>(float)$rows->sum('interest_due'),'fees'=>(float)$rows->sum('fee_due'),
            'total'=>(float)$rows->sum(fn($r)=>(float)$r->principal_due+(float)$r->interest_due+(float)$r->fee_due)];

        return Inertia::render('Eir/ScheduleShow',[
            'contract'=>$contractEir,
            'generated'=>$generated,
            'remaining'=>$remaining,
            'generatedTotals'=>$totals($generated),
            'remainingTotals'=>$totals($remaining),
            'comparison'=>$workflow->comparison($contractEir),
        ]);
    }
    public function generateAll(ScheduleWorkflowService $workflow)
    {
        $r=$workflow->generateEligible();
        return back()->with('success',"Generated {$r['generated']} draft schedule(s); {$r['skipped']} skipped.");
    }
    public function generate(ContractEir $contractEir, ScheduleWorkflowService $workflow)
    {
        try { $r=$workflow->generate($contractEir); return back()->with('success',"Generated {$r['rows']} draft rows for {$contractEir->contract_id}."); }
        catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }
    public function approve(Request $request, ContractEir $contractEir, ScheduleWorkflowService $workflow)
    {
        $data=$request->validate(['notes'=>'nullable|string|max:2000']);
        try { $workflow->approve($contractEir,(int)$request->user()->id,$data['notes']??null); return back()->with('success',"Approved the version-1 schedule for {$contractEir->contract_id}."); }
        catch (\Throwable $e) { return back()->with('error',$e->getMessage()); }
    }
}
