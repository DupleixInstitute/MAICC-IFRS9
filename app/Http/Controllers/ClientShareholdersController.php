<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Shareholder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientShareholdersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.shareholders.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.shareholders.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.shareholders.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.shareholders.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {
        $shareholders = Shareholder::where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Clients/Shareholders/Index', [
            'client' => $client,
            'shareholders' => $shareholders,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Client $client)
    {
        return Inertia::render('Clients/Shareholders/Create', [
            'client' => $client,

        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Client $client)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $shareholder = new Shareholder();
        $shareholder->created_by_id = Auth::id();
        $shareholder->client_id = $client->id;
        $shareholder->name = $request->name;
        $shareholder->gender = $request->gender;
        $shareholder->itc_date = $request->itc_date;
        $shareholder->dob = $request->dob;
        $shareholder->shares = $request->shares;
        $shareholder->itc_ref_no = $request->itc_ref_no;
        $shareholder->itc_ref_date = $request->itc_ref_date;
        $shareholder->number_of_paid_debts = $request->number_of_paid_debts;
        $shareholder->number_of_defaulted_debts = $request->number_of_defaulted_debts;
        $shareholder->number_of_judgements = $request->number_of_judgements;
        $shareholder->number_of_trace_alerts = $request->number_of_trace_alerts;
        $shareholder->blacklisted = $request->blacklisted ? 1 : 0;
        $shareholder->fraud_alert = $request->fraud_alert ? 1 : 0;
        $shareholder->description = $request->description;
        $shareholder->save();
        activity()
            ->performedOn($client)
            ->log('Create Shareholder');
        return redirect()->route('clients.shareholders.index', [$client->id])->with('success', 'Shareholder created successfully.');

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
     * @return \Illuminate\Http\Response
     */
    public function edit(Shareholder $shareholder)
    {
        $client = $shareholder->client;

        return Inertia::render('Clients/Shareholders/Edit', [
            'client' => $client,
            'shareholder' => $shareholder,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Shareholder $shareholder)
    {

        $client = $shareholder->client;
        $request->validate([
            'name' => ['required'],
        ]);
        $shareholder->name = $request->name;
        $shareholder->gender = $request->gender;
        $shareholder->itc_date = $request->itc_date;
        $shareholder->dob = $request->dob;
        $shareholder->shares = $request->shares;
        $shareholder->itc_ref_no = $request->itc_ref_no;
        $shareholder->itc_ref_date = $request->itc_ref_date;
        $shareholder->number_of_paid_debts = $request->number_of_paid_debts;
        $shareholder->number_of_defaulted_debts = $request->number_of_defaulted_debts;
        $shareholder->number_of_judgements = $request->number_of_judgements;
        $shareholder->number_of_trace_alerts = $request->number_of_trace_alerts;
        $shareholder->blacklisted = $request->blacklisted ? 1 : 0;
        $shareholder->fraud_alert = $request->fraud_alert ? 1 : 0;
        $shareholder->description = $request->description;
        $shareholder->save();

        activity()
            ->performedOn($client)
            ->log('Update Shareholder');
        return redirect()->route('clients.shareholders.index', [$client->id])->with('success', 'Shareholder updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Shareholder $shareholder)
    {

        $shareholder->delete();
        activity()
            ->performedOn($shareholder)
            ->log('Delete Shareholder');
        return redirect()->route('clients.shareholders.index', [$shareholder->client_id])->with('success', 'Client deleted successfully.');

    }
}
