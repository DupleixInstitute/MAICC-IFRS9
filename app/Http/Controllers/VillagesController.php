<?php

namespace App\Http\Controllers;

use App\Models\Village;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class VillagesController extends Controller
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
        $villages = Village::filter(\request()->only('search'))
            ->with(['ward'])
            ->paginate(20);
        return Inertia::render('Villages/Index', [
            'filters' => \request()->all('search'),
            'villages' => $villages,
        ]);
    }

    public function create()
    {

        return Inertia::render('Villages/Create', [
            'ward_id' => \request('ward_id'),
            'wards' => Ward::get()->map(function ($item) {
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
            'ward_id' => ['required'],
        ]);
        $village = new Village();
        $village->ward_id = $request->ward_id;
        $village->name = $request->name;
        $village->save();
        activity()
            ->performedOn($village)
            ->log('Create Village');
        return redirect()->route('locations.villages.index')->with('success', 'Village created successfully.');
    }

    public function show(Village $village)
    {
        $village->load(['ward']);
        return Inertia::render('Villages/Show', [
            'village' => $village,

        ]);
    }

    public function edit(Village $village)
    {
        return Inertia::render('Villages/Edit', [
            'village' => $village,
            'wards' => Ward::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function update(Request $request, Village $village)
    {

        $request->validate([
            'name' => ['required'],
            'ward_id' => ['required'],
        ]);
        $village->ward_id = $request->ward_id;
        $village->name = $request->name;
        $village->save();
        activity()
            ->performedOn($village)
            ->log('Update Village');
        return redirect()->route('locations.villages.index')->with('success', 'Village updated successfully.');
    }

    public function destroy(Village $village)
    {
        $village->delete();
        activity()
            ->performedOn($village)
            ->log('Delete Village');
        return redirect()->route('locations.villages.index')->with('success', 'Village deleted successfully.');
    }
}
