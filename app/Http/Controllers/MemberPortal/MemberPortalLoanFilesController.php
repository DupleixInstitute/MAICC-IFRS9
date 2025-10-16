<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FilesController;
use App\Models\File;
use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;

class MemberPortalLoanFilesController extends Controller
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
        $files = File::where('record_id', $loan->id)
            ->where('category', 'loans')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Loans/Files/Index', [
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
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        return Inertia::render('MemberPortal/Loans/Files/Create', [
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
        if ($loan->member_id != session('member_id')) {
            abort(403);
        }
        $fileController = new FilesController();
        $file = $fileController->store([
            'file' => $request->file('file'),
            'name' => $request->name,
            'description' => $request->description,
            'category' => 'loans',
            'record_id' => $loan->id,
        ]);
        return redirect()->route('portal.loans.files.index', [$loan->id])->with('success', 'Loan File created successfully.');

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

}
