<?php

namespace App\Http\Controllers;

use App\Models\LoanProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LoanProductCategoriesController extends Controller
{

    public function index()
    {
        $groups = LoanProductCategory::filter(\request()->only('search'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        // the loans each group carries in the latest loan book, and the groups the book uses that are not listed here
        $latest = DB::table('loan_books')->max('reporting_period');
        $inBook = DB::table('loan_books')->where('reporting_period', $latest)->whereNotNull('product_group')
            ->selectRaw('product_group, count(*) n')->groupBy('product_group')->pluck('n', 'product_group');
        $listed = LoanProductCategory::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();

        return Inertia::render('LoanProductGroups/Index', [
            'filters' => \request()->all('search'),
            'groups' => $groups,
            'loansByGroup' => $inBook,
            'latestPeriod' => $latest,
            'unlisted' => $inBook->keys()->filter(fn ($g) => ! in_array(mb_strtolower(trim($g)), $listed, true))->values(),
        ]);
    }

    /** Add every product group the loan book uses that is not listed yet. */
    public function sync()
    {
        $listed = LoanProductCategory::pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $added = 0;
        foreach (DB::table('loan_books')->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group') as $g) {
            if (trim((string) $g) === '' || in_array(mb_strtolower(trim($g)), $listed, true)) {
                continue;
            }
            $c = new LoanProductCategory();
            $c->name = trim($g);
            $c->description = 'From the loan book (E-Banker product group)';
            $c->save();
            $listed[] = mb_strtolower(trim($g));
            $added++;
        }
        activity()->log("Product groups taken from the loan book: {$added}");

        return redirect()->route('groups.index')->with('success', $added ? "{$added} product group(s) added from the loan book." : 'Every product group in the loan book is already listed.');
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
        return redirect()->route('groups.index')->with('success', 'Product group created.');
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
        return redirect()->route('groups.index')->with('success', 'Product group updated.');
    }

    public function destroy(LoanProductCategory $group)
    {
        $group->delete();
        activity()
            ->performedOn($group)
            ->log('Delete Loan Category');
        return redirect()->route('groups.index')->with('success', 'Product group deleted.');
    }
}
