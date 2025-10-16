<?php

namespace App\Http\Controllers;

use App\Models\LegalType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LegalTypesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:legal_types.index'])->only(['index', 'show']);
        $this->middleware(['permission:legal_types.create'])->only(['create', 'store']);
        $this->middleware(['permission:legal_types.update'])->only(['edit', 'update']);
        $this->middleware(['permission:legal_types.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $types = LegalType::filter(\request()->only('search'))
            ->paginate();
        return Inertia::render('LegalTypes/Index', [
            'filters' => \request()->all('search'),
            'types' => $types,
        ]);
    }

    public function create()
    {

        return Inertia::render('LegalTypes/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $type = new LegalType();
        $type->name = $request->name;
        $type->description = $request->description;
        $type->save();
        activity()
            ->performedOn($type)
            ->log('Create Legal Type');
        return redirect()->route('legal_types.index')->with('success', 'Legal Type created successfully.');
    }

    public function show(LegalType $type)
    {

        return Inertia::render('LegalTypes/Show', [
            'type' => $type
        ]);
    }

    public function edit(LegalType $type)
    {
        return Inertia::render('LegalTypes/Edit', [
            'type' => $type,
        ]);
    }

    public function update(Request $request, LegalType $type)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $type->name = $request->name;
        $type->description = $request->description;
        $type->save();
        activity()
            ->performedOn($type)
            ->log('Update Legal Type');
        return redirect()->route('legal_types.index')->with('success', 'Legal Type updated successfully.');
    }

    public function destroy(LegalType $type)
    {
        $type->delete();
        activity()
            ->performedOn($type)
            ->log('Delete Legal Type');
        return redirect()->route('legal_types.index')->with('success', 'Legal Type deleted successfully.');
    }
}
