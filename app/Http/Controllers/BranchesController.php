<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BranchesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:branches.index'])->only(['index', 'show']);
        $this->middleware(['permission:branches.create'])->only(['create','store']);
        $this->middleware(['permission:branches.update'])->only(['edit','update']);
        $this->middleware(['permission:branches.destroy'])->only(['destroy']);
    }
    public function index()
    {
        $branches = Branch::filter(\request()->only('search'))
            ->paginate(20);
        return Inertia::render('Branches/Index', [
            'filters' => \request()->all('search'),
            'branches' => $branches,
        ]);
    }

    public function create()
    {

        return Inertia::render('Branches/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'open_date' => ['required'],
        ]);
        $branch = new Branch();
        $branch->created_by_id = Auth::id();
        $branch->name = $request->name;
        $branch->open_date = $request->open_date;
        $branch->description = $request->description;
        $branch->active = $request->active ? 1 : 0;
        $branch->save();
        activity()
            ->performedOn($branch)
            ->log('Create Branch');
        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function show(Branch $branch)
    {

        return Inertia::render('Branches/Show', [
            'branch' => $branch
        ]);
    }

    public function edit(Branch $branch)
    {
        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo branch is not allowed.');
        }
        $request->validate([
            'name' => ['required'],
            'open_date' => ['required'],
        ]);
        $branch->name = $request->name;
        $branch->open_date = $request->open_date;
        $branch->description = $request->description;
        $branch->active = $request->active ? 1 : 0;
        $branch->save();
        activity()
            ->performedOn($branch)
            ->log('Update Branch');
        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo branch is not allowed.');
        }
        $branch->delete();
        activity()
            ->performedOn($branch)
            ->log('Delete Branch');
        return redirect()->route('branches.index')->with('success', 'Branch deleted successfully.');
    }
}
