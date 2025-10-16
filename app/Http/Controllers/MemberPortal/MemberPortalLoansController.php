<?php

namespace App\Http\Controllers\MemberPortal;

use App\Events\LoanCreated;
use App\Events\LoanStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\LoanApplication;

use App\Models\LoanProductCategory;
use App\Models\LoanProductScoringAttribute;
use App\Models\Client;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberPortalLoansController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $staffID = null;
        $nurseID = null;
        $receptionistID = null;

        $loans = LoanApplication::with(['staff', 'member', 'category'])
            ->filter(\request()->only('search', 'member_id', 'loan_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->where('member_id', session('member_id'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Loans/Index', [
            'filters' => \request()->all('search', 'member_id', 'loan_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'),
            'loans' => $loans,
            'categories' => LoanProductCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('MemberPortal/Loans/Create', [
            'categories' => LoanProductCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'loan_category_id' => ['required'],
            'amount' => ['required'],
            'guarantors' => ['required','array'],
        ]);
        $member = Client::find(session('member_id'));
        $loan = new LoanApplication();
        $loan->created_by_id = Auth::id();
        $loan->loan_category_id = $request->loan_category_id;
        $loan->currency_id = Setting::where('setting_key', 'currency')->first()->setting_value;
        $loan->member_id = $member->id;
        $loan->province_id = $member->province_id;
        $loan->branch_id = $member->branch_id;
        $loan->district_id = $member->district_id;
        $loan->ward_id = $member->ward_id;
        $loan->village_id = $member->village_id;
        $loan->date =date('Y-m-d');
        $loan->amount = $request->amount;
        $loan->applied_amount = $request->amount;
        $loan->description = $request->description;
        $loan->save();
        if(!empty($request->guarantors)){
            foreach ($request->guarantors as $key){
                $guarantor = new LoanProductScoringAttribute();
                $guarantor->created_by_id = Auth::id();
                $guarantor->loan_id = $loan->id;
                $guarantor->member_id = $key;
                //$guarantor->amount = $request->amount;
                $guarantor->description = $request->description;
                $guarantor->save();
            }
        }
        event(new LoanCreated($loan));
        return redirect()->route('portal.loans.show', $loan->id)->with('success', 'Loan created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param LoanApplication $loan
     * @return \Inertia\Response
     */
    public function show(LoanApplication $loan)
    {
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        $loan->load(['member','member.province', 'member.branch','member.district','member.ward','member.village','category','staff']);
        return Inertia::render('MemberPortal/Loans/Show', [
            'loan' => $loan,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param LoanApplication $loan
     * @return \Inertia\Response
     */
    public function edit(LoanApplication $loan)
    {
        return Inertia::render('MemberPortal/Loans/Edit', [
            'loan' => $loan,
            'categories' => LoanProductCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param LoanApplication $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, LoanApplication $loan)
    {
        $request->validate([
            'amount' => ['required'],
            'date' => ['required'],
        ]);
        $loan->loan_category_id = $request->loan_category_id;
        $loan->staff_id = $request->staff_id;
        $loan->date = $request->date;
        $loan->amount = $request->amount;
        $loan->description = $request->description;
        $loan->save();
        return redirect()->route('portal.loans.show', $loan->id)->with('success', 'Loan updated successfully.');
    }

}
