<?php

namespace App\Http\Controllers;

use App\Models\LoanProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoanProductCategoriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.products.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.products.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.products.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.products.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $categories = LoanProductCategory::filter(\request()->only('search'))
            ->paginate();
        return Inertia::render('LoanProductCategories/Index', [
            'filters' => \request()->all('search'),
            'categories' => $categories,
        ]);
    }

    public function create()
    {

        return Inertia::render('LoanProductCategories/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $category = new LoanProductCategory();
        $category->name = $request->name;
        $category->description = $request->description;
        $category->save();
        activity()
            ->performedOn($category)
            ->log('Create Loan Category');
        return redirect()->route('loan_products.categories.index')->with('success', 'Loan Category created successfully.');
    }

    public function show(LoanProductCategory $category)
    {

        return Inertia::render('LoanProductCategories/Show', [
            'category' => $category
        ]);
    }

    public function edit(LoanProductCategory $category)
    {
        return Inertia::render('LoanProductCategories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, LoanProductCategory $category)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $category->name = $request->name;
        $category->description = $request->description;
        $category->save();
        activity()
            ->performedOn($category)
            ->log('Update Loan Category');
        return redirect()->route('loan_products.categories.index')->with('success', 'Loan Category updated successfully.');
    }

    public function destroy(LoanProductCategory $category)
    {
        $category->delete();
        activity()
            ->performedOn($category)
            ->log('Delete Loan Category');
        return redirect()->route('loan_products.categories.index')->with('success', 'Loan Category deleted successfully.');
    }
}
