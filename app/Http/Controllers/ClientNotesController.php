<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientNotesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.notes.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.notes.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.notes.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.notes.destroy'])->only(['destroy']);
    }
    public function index(Client $client)
    {
        $notes = LoanProduct::with(['createdBy'])
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Clients/Notes/Index', [
            'client' => $client,
            'notes' => $notes,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Client $client)
    {
        return Inertia::render('Clients/Notes/Create', [
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
            'description' => ['required'],
        ]);
        $clientNote = new LoanProduct();
        $clientNote->created_by_id = Auth::id();
        $clientNote->client_id = $client->id;
        $clientNote->description = $request->description;
        $clientNote->visible_to_client = $request->visible_to_client ? 1 : 0;
        $clientNote->save();
        activity()
            ->performedOn($client)
            ->log('Create Client Note');
        return redirect()->route('clients.notes.index', [$client->id])->with('success', 'Client Note created successfully.');

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
    public function edit(LoanProduct $clientNote)
    {
        $client = $clientNote->client;

        return Inertia::render('Clients/Notes/Edit', [
            'client' => $client,
            'clientNote' => $clientNote,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, LoanProduct $clientNote)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo client note is not allowed.');
        }
        $client = $clientNote->client;
        $request->validate([
            'description' => ['required'],
        ]);
        $clientNote->description = $request->description;
        $clientNote->visible_to_client = $request->visible_to_client ? 1 : 0;
        $clientNote->save();

        activity()
            ->performedOn($client)
            ->log('Update Client Note');
        return redirect()->route('clients.notes.index', [$client->id])->with('success', 'Client Note updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(LoanProduct $clientNote)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo client note is not allowed.');
        }
        $clientNote->delete();
        activity()
            ->performedOn($clientNote)
            ->log('Delete Client Note');
        return redirect()->route('clients.notes.index',[$clientNote->client_id])->with('success', 'Client deleted successfully.');

    }
}
