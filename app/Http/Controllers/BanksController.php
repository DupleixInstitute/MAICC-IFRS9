<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BanksController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:banks.index'])->only(['index', 'show']);
        $this->middleware(['permission:banks.create'])->only(['create', 'store']);
        $this->middleware(['permission:banks.update'])->only(['edit', 'update']);
        $this->middleware(['permission:banks.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $banks = Bank::filter(\request()->only('search'))
            ->paginate();
        return Inertia::render('Banks/Index', [
            'filters' => \request()->all('search'),
            'banks' => $banks,
        ]);
    }

    public function create()
    {

        return Inertia::render('Banks/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $bank = new Bank();
        $bank->name = $request->name;
        $bank->description = $request->description;
        $bank->save();
        activity()
            ->performedOn($bank)
            ->log('Create Bank');
        return redirect()->route('banks.index')->with('success', 'Bank created successfully.');
    }

    public function show(Bank $bank)
    {

        return Inertia::render('Banks/Show', [
            'bank' => $bank
        ]);
    }

    public function edit(Bank $bank)
    {
        return Inertia::render('Banks/Edit', [
            'bank' => $bank,
        ]);
    }

    public function update(Request $request, Bank $bank)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $bank->name = $request->name;
        $bank->description = $request->description;
        $bank->save();
        activity()
            ->performedOn($bank)
            ->log('Update Bank');
        return redirect()->route('banks.index')->with('success', 'Bank updated successfully.');
    }

    public function destroy(Bank $bank)
    {
        $bank->delete();
        activity()
            ->performedOn($bank)
            ->log('Delete Bank');
        return redirect()->route('banks.index')->with('success', 'Bank deleted successfully.');
    }
}
