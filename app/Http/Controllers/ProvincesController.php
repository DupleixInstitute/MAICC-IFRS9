<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Province;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProvincesController extends Controller
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
        $provinces = Province::filter(\request()->only('search'))
            ->with(['country'])
            ->paginate(20);
        return Inertia::render('Provinces/Index', [
            'filters' => \request()->all('search'),
            'provinces' => $provinces,
        ]);
    }

    public function create()
    {

        return Inertia::render('Provinces/Create', [
            'countries' => Country::get()->map(function ($item) {
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
            'country_id' => ['required'],
        ]);
        $province = new Province();
        $province->country_id = $request->country_id;
        $province->name = $request->name;
        $province->save();
        activity()
            ->performedOn($province)
            ->log('Create Province');
        return redirect()->route('locations.provinces.index')->with('success', 'Province created successfully.');
    }

    public function show(Province $province)
    {
        $province->load(['districts']);
        return Inertia::render('Provinces/Show', [
            'province' => $province
        ]);
    }

    public function edit(Province $province)
    {
        return Inertia::render('Provinces/Edit', [
            'province' => $province,
            'countries' => Country::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    public function update(Request $request, Province $province)
    {

        $request->validate([
            'name' => ['required'],
            'country_id' => ['required'],
        ]);
        $province->country_id = $request->country_id;
        $province->name = $request->name;
        $province->save();
        activity()
            ->performedOn($province)
            ->log('Update Province');
        return redirect()->route('locations.provinces.index')->with('success', 'Province updated successfully.');
    }

    public function destroy(Province $province)
    {
        $province->delete();
        activity()
            ->performedOn($province)
            ->log('Delete Province');
        return redirect()->route('locations.provinces.index')->with('success', 'Province deleted successfully.');
    }
}
