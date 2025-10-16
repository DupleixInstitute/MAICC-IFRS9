<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\LoanProductScoringAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MemberPortalLoanGuarantorsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index(LoanApplication $loan)
    {
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        $loan->load(['member']);
        $guarantors = LoanProductScoringAttribute::with(['member'])
            ->where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Loans/Guarantors/Index', [
            'loan' => $loan,
            'guarantors' => $guarantors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create(LoanApplication $loan)
    {
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        return Inertia::render('MemberPortal/Loans/Guarantors/Create', [
            'loan' => $loan,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, LoanApplication $loan)
    {
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        $notIn=LoanProductScoringAttribute::where('loan_id',$loan->id)->get()->pluck('member_id')->all();
        array_push($notIn,$loan->member_id);
        $notIn=array_unique($notIn);
        $request->validate([
            'member_id' => ['required',Rule::notIn(array_values($notIn))],
        ],[
            'member_id' => 'Member already selected'
        ]);
        $guarantor = new LoanProductScoringAttribute();
        $guarantor->created_by_id = Auth::id();
        $guarantor->loan_id = $loan->id;
        $guarantor->member_id = $request->member_id;
        $guarantor->amount = $request->amount;
        $guarantor->description = $request->description;
        $guarantor->save();
        activity()
            ->performedOn($loan)
            ->log('Create Loan Guarantor');
        return redirect()->route('portal.loans.guarantors.index', [$loan->id])->with('success', 'Loan Guarantor created successfully.');

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Inertia\Response
     */
    public function edit(LoanProductScoringAttribute $guarantor)
    {
        if ($guarantor->loan->member_id != session('member_id')) {
            abort(403);
        }
        $loan = $guarantor->loan;

        return Inertia::render('MemberPortal/Loans/Guarantors/Edit', [
            'loan' => $loan,
            'guarantor' => $guarantor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, LoanProductScoringAttribute $guarantor)
    {
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        $loan = $guarantor->loan;
        $request->validate([
            'amount' => ['required'],
        ]);
        $guarantor->amount = $request->amount;
        $guarantor->description = $request->description;
        $guarantor->save();

        activity()
            ->performedOn($loan)
            ->log('Update Loan Guarantor');
        return redirect()->route('portal.loans.guarantors.index', [$loan->id])->with('success', 'Loan Guarantor updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(LoanProductScoringAttribute $guarantor)
    {
        if ($guarantor->loan->member_id != session('member_id')) {
            abort(403);
        }
        $guarantor->delete();
        activity()
            ->performedOn($guarantor)
            ->log('Delete Loan Guarantor');
        return redirect()->route('portal.loans.guarantors.index',[$guarantor->loan_id])->with('success', 'Loan deleted successfully.');

    }
}
