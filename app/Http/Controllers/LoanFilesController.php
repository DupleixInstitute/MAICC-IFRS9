<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;

class LoanFilesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.files.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.files.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.files.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.files.destroy'])->only(['destroy']);
    }
    public function index(LoanApplication $loan)
    {
        dd($loan);

        $files = File::where('record_id', $loan->id)
            ->where('category', 'loans')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Loans/Files/Index', [
            'loan' => $loan,
            'files' => $files,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create(LoanApplication $loan)
    {
        return Inertia::render('Loans/Files/Create', [
            'loan' => $loan,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, LoanApplication $loan)
    {

        $fileController = new FilesController();
        $file = $fileController->store([
            'file' => $request->file('file'),
            'name' => $request->name,
            'description' => $request->description,
            'category' => 'loans',
            'record_id' => $loan->id,
        ]);
        activity()
            ->performedOn($loan)
            ->log('Create Loan File');
        return redirect()->route('loans.files.index', [$loan->id])->with('success', 'Loan File created successfully.');

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
     * @param  File $file
     * @return \Inertia\Response
     */
    public function edit(File $file)
    {
        $loan = LoanApplication::find($file->record_id);

        return Inertia::render('Loans/Files/Edit', [
            'loan' => $loan,
            'file' => $file,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  File $file
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, File $file)
    {

        $loan = LoanApplication::find($file->record_id);
        $fileController = new FilesController();
        $file = $fileController->update($file->id,[
            'file' => $request->file('file'),
            'name' => $request->name,
            'description' => $request->description,
        ]);
        activity()
            ->performedOn($loan)
            ->log('Update Loan File');
        return redirect()->route('loans.files.index', [$loan->id])->with('success', 'Updated File successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  File $file
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(File $file)
    {

        $fileController = new FilesController();
        $fileController->destroy($file->id);
        activity()
            ->performedOn($file)
            ->log('Delete Loan File');
        return redirect()->route('loans.files.index', [$file->record_id])->with('success', 'File deleted successfully.');

    }
}
