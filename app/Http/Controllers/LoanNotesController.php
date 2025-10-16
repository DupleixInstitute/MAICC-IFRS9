<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\ScoringAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoanNotesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.notes.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.notes.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.notes.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.notes.destroy'])->only(['destroy']);
    }
    public function index(LoanApplication $loan)
    {
        $notes = ScoringAttribute::with(['createdBy'])
            ->where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Loans/Notes/Index', [
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
        return Inertia::render('Loans/Notes/Create', [
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
        $request->validate([
            'description' => ['required'],
        ]);
        $note = new ScoringAttribute();
        $note->created_by_id = Auth::id();
        $note->loan_id = $loan->id;
        $note->description = $request->description;
        $note->save();
        activity()
            ->performedOn($loan)
            ->log('Create Loan Note');
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

        return Inertia::render('Loans/Notes/Edit', [
            'loan' => $loan,
            'note' => $note,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ScoringAttribute $note)
    {

        $loan = $note->loan;
        $request->validate([
            'description' => ['required'],
        ]);
        $note->description = $request->description;
        $note->save();

        activity()
            ->performedOn($loan)
            ->log('Update Loan Note');
        return redirect()->route('loans.notes.index', [$loan->id])->with('success', 'Loan Note updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ScoringAttribute $note)
    {

        $note->delete();
        activity()
            ->performedOn($note)
            ->log('Delete Loan Note');
        return redirect()->route('loans.notes.index',[$note->loan_id])->with('success', 'Loan deleted successfully.');

    }
}
