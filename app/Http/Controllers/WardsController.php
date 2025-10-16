<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\District;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WardsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:locations.index'])->only(['index', 'show']);
        $this->middleware(['permission:locations.create'])->only(['create', 'store']);
        $this->middleware(['permission:locations.update'])->only(['edit', 'update']);
        $this->middleware(['permission:locations.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $wards = Ward::filter(\request()->only('search'))
            ->with(['district'])
            ->paginate(20);
        return Inertia::render('Wards/Index', [
            'filters' => \request()->all('search'),
            'wards' => $wards,
        ]);
    }

    public function create()
    {

        return Inertia::render('Wards/Create', [
            'district_id' => \request('district_id'),
            'districts' => District::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'district_id' => ['required'],
        ]);
        $ward = new Ward();
        $ward->district_id = $request->district_id;
        $ward->name = $request->name;
        $ward->save();
        activity()
            ->performedOn($ward)
            ->log('Create Ward');
        return redirect()->route('locations.wards.index')->with('success', 'Ward created successfully.');
    }

    public function show(Ward $ward)
    {
        $ward->load(['villages', 'district']);
        return Inertia::render('Wards/Show', [
            'ward' => $ward,

        ]);
    }

    public function edit(Ward $ward)
    {
        return Inertia::render('Wards/Edit', [
            'ward' => $ward,
            'districts' => District::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function update(Request $request, Ward $ward)
    {

        $request->validate([
            'name' => ['required'],
            'district_id' => ['required'],
        ]);
        $ward->district_id = $request->district_id;
        $ward->name = $request->name;
        $ward->save();
        activity()
            ->performedOn($ward)
            ->log('Update Ward');
        return redirect()->route('locations.wards.index')->with('success', 'Ward updated successfully.');
    }

    public function destroy(Ward $ward)
    {
        $ward->delete();
        activity()
            ->performedOn($ward)
            ->log('Delete Ward');
        return redirect()->route('locations.wards.index')->with('success', 'Ward deleted successfully.');
    }
}
