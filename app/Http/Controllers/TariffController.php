<?php

namespace App\Http\Controllers;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TariffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:tariffs.index'])->only(['index', 'show']);
        $this->middleware(['permission:tariffs.create'])->only(['create', 'store']);
        $this->middleware(['permission:tariffs.update'])->only(['edit', 'update']);
        $this->middleware(['permission:tariffs.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $tariffs = Tariff::filter(\request()->only('search'))
            ->paginate(20);
        return Inertia::render('Tariffs/Index', [
            'filters' => \request()->all('search', 'role', 'gender'),
            'tariffs' => $tariffs,
        ]);
    }

    public function create()
    {

        return Inertia::render('Tariffs/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'amount' => ['required'],
        ]);
        $tariff = new Tariff();
        $tariff->created_by_id = Auth::id();
        $tariff->name = $request->name;
        $tariff->code = $request->code;
        $tariff->film_code = $request->film_code;
        $tariff->cash_amount = $request->cash_amount;
        $tariff->co_payer_amount = $request->co_payer_amount;
        $tariff->co_payer_percent = $request->co_payer_percent;
        $tariff->amount = $request->amount;
        $tariff->maximum_quantity = $request->maximum_quantity;
        $tariff->description = $request->description;
        $tariff->type = $request->type;
        $tariff->is_claimable = $request->is_claimable ? 1 : 0;
        $tariff->needs_approval = $request->needs_approval ? 1 : 0;
        $tariff->auto_select = $request->auto_select ? 1 : 0;
        $tariff->save();
        activity()
            ->performedOn($tariff)
            ->log('Create Tariff');
        return redirect()->route('tariffs.index')->with('success', 'Tariff created successfully.');
    }

    public function show(Tariff $tariff)
    {

        return Inertia::render('Tariffs/Show', [
            'tariff' => $tariff
        ]);
    }

    public function edit(Tariff $tariff)
    {
        return Inertia::render('Tariffs/Edit', [
            'tariff' => $tariff,
        ]);
    }

    public function update(Request $request, Tariff $tariff)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo tariff is not allowed.');
        }
        $request->validate([
            'name' => ['required'],
            'amount' => ['required'],
        ]);
        $tariff->name = $request->name;
        $tariff->code = $request->code;
        $tariff->film_code = $request->film_code;
        $tariff->cash_amount = $request->cash_amount;
        $tariff->co_payer_amount = $request->co_payer_amount;
        $tariff->co_payer_percent = $request->co_payer_percent;
        $tariff->amount = $request->amount;
        $tariff->maximum_quantity = $request->maximum_quantity;
        $tariff->description = $request->description;
        $tariff->type = $request->type;
        $tariff->is_claimable = $request->is_claimable ? 1 : 0;
        $tariff->needs_approval = $request->needs_approval ? 1 : 0;
        $tariff->auto_select = $request->auto_select ? 1 : 0;
        $tariff->save();
        activity()
            ->performedOn($tariff)
            ->log('Update Tariff');
        return redirect()->route('tariffs.index')->with('success', 'Tariff updated successfully.');
    }

    public function destroy(Tariff $tariff)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo tariff is not allowed.');
        }
        $tariff->delete();
        activity()
            ->performedOn($tariff)
            ->log('Delete Tariff');
        return redirect()->route('tariffs.index')->with('success', 'Tariff deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->s;
        $data = Tariff::where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('code', 'like', "%$search%");
            $query->orWhere('film_code', 'like', "%$search%");
            $query->orWhere('description', 'like', "%$search%");
        })->get();
        return response()->json($data);
    }

    public function getTariff($id)
    {
        return response()->json(Tariff::find($id));
    }
}
