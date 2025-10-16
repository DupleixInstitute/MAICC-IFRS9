<?php

namespace App\Http\Controllers;

use App\Models\LoanApprovalStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class LoanApprovalStagesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.approval_stages.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.approval_stages.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.approval_stages.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.approval_stages.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $stages = LoanApprovalStage::filter(\request()->only('search'))
            ->with(['role'])
            ->paginate(20);
        return Inertia::render('LoanApprovalStages/Index', [
            'filters' => \request()->all('search'),
            'stages' => $stages,
        ]);
    }

    public function create()
    {

        return Inertia::render('LoanApprovalStages/Create', [
            'roles' => Role::get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'role_id' => ['required'],
        ]);
        $stage = new LoanApprovalStage();
        $stage->created_by_id = Auth::id();
        $stage->name = $request->name;
        $stage->role_id = $request->role_id;
        $stage->description = $request->description;
        $stage->stage_position = $request->stage_position;
        $stage->save();
        return redirect()->route('loan_approval_stages.index')->with('success', 'Stage created successfully.');
    }

    public function show(LoanApprovalStage $stage)
    {

        return Inertia::render('LoanApprovalStages/Show', [
            'stage' => $stage
        ]);
    }

    public function edit(LoanApprovalStage $stage)
    {
        return Inertia::render('LoanApprovalStages/Edit', [
            'stage' => $stage,
            'roles' => Role::get()
        ]);
    }

    public function update(Request $request, LoanApprovalStage $stage)
    {

        $request->validate([
            'name' => ['required'],
            'role_id' => ['required'],
        ]);
        $stage->name = $request->name;
        $stage->role_id = $request->role_id;
        $stage->description = $request->description;
        $stage->stage_position = $request->stage_position;
        $stage->save();

        return redirect()->route('loan_approval_stages.index')->with('success', 'Stage updated successfully.');
    }

    public function destroy(LoanApprovalStage $stage)
    {

        $stage->delete();
        return redirect()->route('loan_approval_stages.index')->with('success', 'Stage deleted successfully.');
    }
}
