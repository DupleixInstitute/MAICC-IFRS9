<?php

namespace App\Http\Controllers;


use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:chart_of_accounts.index'])->only(['index', 'show']);
        $this->middleware(['permission:chart_of_accounts.create'])->only(['create', 'store']);
        $this->middleware(['permission:chart_of_accounts.update'])->only(['edit', 'update']);
        $this->middleware(['permission:chart_of_accounts.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $chartOfAccounts = ChartOfAccount::filter(\request()->only('search', 'account_type'))
            ->orderBy('account_type')
            ->paginate(20);
        return Inertia::render('ChartOfAccounts/Index', [
            'chartOfAccounts' => $chartOfAccounts,
            'filters' => \request()->all('search', 'account_type'),
        ]);
    }

    public function create()
    {

        return Inertia::render('ChartOfAccounts/Create', [
            'chartOfAccounts' => ChartOfAccount::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . '(' . $item->gl_code . ')'
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
       $request->validate([
          'name'=>['required'],
          'gl_code'=>['required'],
          'account_type'=>['required'],
       ]);
        $chartOfAccount = new ChartOfAccount();
        $chartOfAccount->created_by_id = Auth::id();
        $chartOfAccount->parent_id = $request->parent_id;
        $chartOfAccount->name = $request->name;
        $chartOfAccount->gl_code = $request->gl_code;
        $chartOfAccount->account_type = $request->account_type;
        $chartOfAccount->allow_manual = $request->allow_manual?1:0;
        $chartOfAccount->active = $request->active?1:0;
        $chartOfAccount->description = $request->description;
        $chartOfAccount->save();
        activity()
            ->performedOn($chartOfAccount)
            ->log('Create  Chart of Account');
        return redirect()->route('chart_of_accounts.index')->with('success', 'Chart of Account successfully.');

    }

    public function show(ChartOfAccount $chartOfAccount)
    {
        return Inertia::render('ChartOfAccounts/Show', [
            'chartOfAccount' => $chartOfAccount
        ]);
    }

    public function edit(ChartOfAccount $chartOfAccount)
    {
        return Inertia::render('ChartOfAccounts/Edit', [
            'chartOfAccount' => $chartOfAccount,
        ]);
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo chart of account is not allowed.');
        }
        $request->validate([
            'name'=>['required'],
            'gl_code'=>['required'],
            'account_type'=>['required'],
        ]);
        $chartOfAccount->parent_id = $request->parent_id;
        $chartOfAccount->name = $request->name;
        $chartOfAccount->gl_code = $request->gl_code;
        $chartOfAccount->account_type = $request->account_type;
        $chartOfAccount->allow_manual = $request->allow_manual?1:0;
        $chartOfAccount->active = $request->active?1:0;
        $chartOfAccount->description = $request->description;
        $chartOfAccount->save();
        $chartOfAccount->save();
        activity()
            ->performedOn($chartOfAccount)
            ->log('Update Chart of Account');
        return redirect()->route('chart_of_accounts.index')->with('success', 'Chart of Account updated successfully.');
    }


    public function destroy(ChartOfAccount $chartOfAccount)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo chart of account is not allowed.');
        }
        $chartOfAccount->delete();
        activity()
            ->performedOn($chartOfAccount)
            ->log('Delete Chart of Account');
        return redirect()->route('chart_of_accounts.index')->with('success', 'Chart of Account deleted successfully.');
    }

}
