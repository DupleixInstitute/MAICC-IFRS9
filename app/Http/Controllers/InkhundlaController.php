<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InkhundlaController extends Controller
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
        $districts = District::filter(\request()->only('search'))
            ->with(['province'])
            ->paginate(20);
        return Inertia::render('Inkhundla/Index', [
            'filters' => \request()->all('search'),
            'districts' => $districts,
        ]);
    }

    public function create()
    {

        return Inertia::render('Inkhundla/Create', [
            'province_id' => \request('province_id'),
            'provinces' => Province::get()->map(function ($item) {
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
            'province_id' => ['required'],
        ]);
        $district = new District();
        $district->province_id = $request->province_id;
        $district->name = $request->name;
        $district->save();
        activity()
            ->performedOn($district)
            ->log('Create Inkhundla');
        return redirect()->route('locations.regions.show',['province'=>$district->province_id])->with('success', 'Inkhundla created successfully.');
    }

    public function show(District $district)
    {
        $district->load(['wards', 'province']);
        return Inertia::render('Inkhundla/Show', [
            'district' => $district,

        ]);
    }

    public function edit(District $district)
    {
        return Inertia::render('Inkhundla/Edit', [
            'district' => $district,
            'provinces' => Province::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function update(Request $request, District $district)
    {

        $request->validate([
            'name' => ['required'],
            'province_id' => ['required'],
        ]);
        $district->province_id = $request->province_id;
        $district->name = $request->name;
        $district->save();
        activity()
            ->performedOn($district)
            ->log('Update Inkhundla');
        return redirect()->route('locations.regions.show',['province'=>$district->province_id])->with('success', 'Inkhundla updated successfully.');
    }

    public function destroy(District $district)
    {
        $district->delete();
        activity()
            ->performedOn($district)
            ->log('Delete Inkhundla');
        return redirect()->route('locations.inkhundla.index')->with('success', 'Inkhundla deleted successfully.');
    }
}
