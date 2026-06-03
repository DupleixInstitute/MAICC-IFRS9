<?php

namespace App\Http\Controllers;

use App\Models\LoanProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanProductCategoriesController extends Controller
{

    public function index()
    {
        $groups = LoanProductCategory::filter(\request()->only('search'))
            ->paginate();
        return Inertia::render('LoanProductGroups/Index', [
            'filters' => \request()->all('search'),
            'groups' => $groups,
        ]);
    }

    public function create()
    {

        return Inertia::render('LoanProductGroups/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $group = new LoanProductCategory();
        $group->name = $request->name;
        $group->description = $request->description;
        $group->save();
        activity()
            ->performedOn($group)
            ->log('Create Loan Category');
        return redirect()->route('groups.index')->with('success', 'Loan Category created successfully.');
    }

    public function show(LoanProductCategory $group)
    {

        return Inertia::render('LoanProductGroups/Show', [
            'group' => $group,
        ]);
    }

    public function edit(LoanProductCategory $group)
    {
        return Inertia::render('LoanProductGroups/Edit', [
            'group' => $group,
        ]);
    }

    public function update(Request $request, LoanProductCategory $group)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $group->name = $request->name;
        $group->description = $request->description;
        $group->save();
        activity()
            ->performedOn($group)
            ->log('Update Loan Category');
        return redirect()->route('groups.index')->with('success', 'Loan Category updated successfully.');
    }

    public function destroy(LoanProductCategory $group)
    {
        $group->delete();
        activity()
            ->performedOn($group)
            ->log('Delete Loan Category');
        return redirect()->route('groups.index')->with('success', 'Loan Category deleted successfully.');
    }
}
