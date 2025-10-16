<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LoanProduct;
use App\Models\ClientUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class ClientLoginDetailsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.create'])->only(['index', 'show']);
        $this->middleware(['permission:clients.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.destroy'])->only(['destroy']);
    }

    public function index(Client $client)
    {
        $client->load(['user']);
        return Inertia::render('Clients/LoginDetails/Index', [
            'client' => $client,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Client $client)
    {
        return Inertia::render('Clients/LoginDetails/Create', [
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
            'existing_user' => ['required'],
            'user_id' => ['required_if:existing_user,true'],
            'name' => ['required_if:existing_user,false', 'string', 'max:255'],
            'email' => ['required_if:existing_user,false', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required_if:existing_user,false', 'confirmed'],
        ]);
        if ($request->existing_user) {
           $user=User::find($request->user_id);
            $user->client_id = $client->id;
            $user->save();
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'client_id' => $client->id,
                'password' => Hash::make($request->password),
            ]);
            $user->assignRole(Role::findByName('client'));
        }
        activity()
            ->performedOn($client)
            ->log('Create Client User');
        return redirect()->route('clients.login_details.index', [$client->id])->with('success', 'Client user created successfully.');

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
    public function destroy(ClientUser $clientUser)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo client note is not allowed.');
        }
        $clientUser->delete();
        activity()
            ->performedOn($clientUser)
            ->log('Delete Client User');
        return redirect()->route('clients.login_details.index', [$clientUser->client_id])->with('success', 'Deleted successfully.');

    }
}
