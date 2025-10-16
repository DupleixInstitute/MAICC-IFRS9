<?php

namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoanPortfoliosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // $this->middleware(['permission:portfolios.index'])->only(['index', 'show']);
        // $this->middleware(['permission:portfolios.create'])->only(['create', 'store']);
        // $this->middleware(['permission:portfolios.update'])->only(['edit', 'update']);
        // $this->middleware(['permission:portfolios.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $portfolios = LoanPortfolio::filter(request()->only('search', 'status'))
            ->with(['createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            // dd($portfolios);

        return Inertia::render('Portfolios/Index', [
            'filters' => request()->all('search', 'status'),
            'portfolios' => $portfolios
        ]);
    }

    public function create()
    {
        return Inertia::render('Portfolios/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        $portfolio = new LoanPortfolio();
        $portfolio->name = $request->name;
        $portfolio->description = $request->description;
        $portfolio->active = $request->active;
        $portfolio->created_by_id = Auth::id();
        $portfolio->save();

        return redirect()->route('portfolios.index')
            ->with('success', 'Portfolio created successfully.');
    }

    public function edit(LoanPortfolio $portfolio)
    {
        return Inertia::render('Portfolios/Edit', [
            'portfolio' => $portfolio
        ]);
    }

    public function update(Request $request, LoanPortfolio $portfolio)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ]);

        $portfolio->name = $request->name;
        $portfolio->description = $request->description;
        $portfolio->active = $request->active;
        $portfolio->save();

        return redirect()->route('portfolios.index')
            ->with('success', 'Portfolio updated successfully.');
    }

    public function destroy(Request $request, LoanPortfolio $portfolio)
    {
        if ($portfolio->loanBooks()->count() > 0) {
            return response()->json(['error' => 'Cannot delete portfolio with associated loan books.'], 422);
        }

        $portfolio->delete();

        return redirect()->route('portfolios.index')
            ->with('success', 'Portfolio deleted successfully.');
    }
}
