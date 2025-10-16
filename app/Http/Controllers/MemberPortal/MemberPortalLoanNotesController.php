<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Models\LoanApplication;
use App\Models\ScoringAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberPortalLoanNotesController extends Controller
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
        $notes = ScoringAttribute::with(['createdBy'])
            ->where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Loans/Notes/Index', [
            'loan' => $loan,
            'notes' => $notes,
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
        return Inertia::render('MemberPortal/Loans/Notes/Create', [
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
        $request->validate([
            'description' => ['required'],
        ]);
        $note = new ScoringAttribute();
        $note->created_by_id = Auth::id();
        $note->loan_id = $loan->id;
        $note->description = $request->description;
        $note->save();

        return redirect()->route('loans.notes.index', [$loan->id])->with('success', 'Loan Note created successfully.');

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
    public function edit(ScoringAttribute $note)
    {
        $loan = $note->loan;
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        return Inertia::render('MemberPortal/Loans/Notes/Edit', [
            'loan' => $loan,
            'note' => $note,
        ]);
    }



}
