<?php

namespace App\Http\Controllers;


use App\Models\FinancialPeriod;

use App\Models\CourseMaterial;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FinancialPeriodController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:accounting.financial_periods.index'])->only(['index', 'show']);
        $this->middleware(['permission:accounting.financial_periods.create'])->only(['create', 'store']);
        $this->middleware(['permission:accounting.financial_periods.update'])->only(['edit', 'update']);
        $this->middleware(['permission:accounting.financial_periods.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $financialPeriods = FinancialPeriod::with(['closedBy'])
            ->filter(\request()->only('search'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('FinancialPeriods/Index', [
            'financialPeriods' => $financialPeriods,
            'filters' => \request()->all('search'),
        ]);
    }

    public function create()
    {

        return Inertia::render('FinancialPeriods/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'start_date' => ['required'],
        ]);
        $financialPeriod = new FinancialPeriod();
        $financialPeriod->created_by_id = Auth::id();
        $financialPeriod->name = $request->name;
        $financialPeriod->start_date = $request->start_date;
        $financialPeriod->end_date = $request->end_date;
        $financialPeriod->description = $request->description;
        $financialPeriod->save();
        FinancialPeriod::where('id', '!=', $financialPeriod->id)
            ->where('closed', 0)
            ->update([
                'closed_by_id' => Auth::id(),
                'closed' => 1,
            ]);
        activity()
            ->performedOn($financialPeriod)
            ->log('Create  Financial Period');
        return redirect()->route('accounting.financial_periods.index')->with('success', 'Financial Period successfully.');

    }

    public function show(FinancialPeriod $financialPeriod)
    {
        $financialPeriod->load(['closedBy']);
        return Inertia::render('FinancialPeriods/Show', [
            'financialPeriod' => $financialPeriod
        ]);
    }

    public function edit(FinancialPeriod $financialPeriod)
    {
        return Inertia::render('FinancialPeriods/Edit', [
            'financialPeriod' => $financialPeriod,
        ]);
    }

    public function update(Request $request, FinancialPeriod $financialPeriod)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo period is not allowed.');
        }
        $request->validate([
            'start_date' => ['required'],
        ]);
        $financialPeriod->name = $request->name;
        $financialPeriod->start_date = $request->start_date;
        $financialPeriod->end_date = $request->end_date;
        $financialPeriod->description = $request->description;
        $financialPeriod->save();
        activity()
            ->performedOn($financialPeriod)
            ->log('Update Financial Period');
        return redirect()->route('accounting.financial_periods.index')->with('success', 'Financial Period updated successfully.');
    }

    public function close(Request $request, FinancialPeriod $financialPeriod)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo period is not allowed.');
        }

        $financialPeriod->closed = 1;
        $financialPeriod->closed_by_id = Auth::id();
        $financialPeriod->end_date = date('Y-m-d');
        $financialPeriod->save();
        //open new period
        $newFinancialPeriod = new FinancialPeriod();
        $newFinancialPeriod->created_by_id = Auth::id();
        $newFinancialPeriod->start_date = $financialPeriod->end_date;
        $newFinancialPeriod->branch_id = $financialPeriod->branch_id;
        $newFinancialPeriod->save();
        activity()
            ->performedOn($financialPeriod)
            ->log('Close Financial Period');
        return redirect()->route('accounting.financial_periods.index')->with('success', 'Financial Period updated successfully.');
    }


    public function destroy(FinancialPeriod $financialPeriod)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo period is not allowed.');
        }
        $financialPeriod->delete();
        activity()
            ->performedOn($financialPeriod)
            ->log('Delete Financial Period');
        return redirect()->route('accounting.financial_periods.index')->with('success', 'Financial Period deleted successfully.');
    }

}
