<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientFilesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.files.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.files.create'])->only(['create', 'store']);
        $this->middleware(['permission:clients.files.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.files.destroy'])->only(['destroy']);
    }
    public function index(Client $client)
    {

        $files = File::where('record_id', $client->id)
            ->where('category', 'clients')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Clients/Files/Index', [
            'client' => $client,
            'files' => $files,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Client $client)
    {
        return Inertia::render('Clients/Files/Create', [
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

        $fileController = new FilesController();
        $file = $fileController->store([
            'file' => $request->file('file'),
            'name' => $request->name,
            'description' => $request->description,
            'category' => 'clients',
            'record_id' => $client->id,
        ]);
        activity()
            ->performedOn($client)
            ->log('Create Client File');
        return redirect()->route('clients.files.index', [$client->id])->with('success', 'Client File created successfully.');

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
    public function edit(File $file)
    {
        $client = Client::find($file->record_id);

        return Inertia::render('Clients/Files/Edit', [
            'client' => $client,
            'file' => $file,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, File $file)
    {

        $client = Client::find($file->record_id);
        $fileController = new FilesController();
        $file = $fileController->update($file->id,[
            'file' => $request->file('file'),
            'name' => $request->name,
            'description' => $request->description,
        ]);
        activity()
            ->performedOn($client)
            ->log('Update Client File');
        return redirect()->route('clients.files.index', [$client->id])->with('success', 'Updated File successfully.');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(File $file)
    {

        $fileController = new FilesController();
        $fileController->destroy($file->id);
        activity()
            ->performedOn($file)
            ->log('Delete Client File');
        return redirect()->route('clients.files.index', [$file->record_id])->with('success', 'File deleted successfully.');

    }
}
