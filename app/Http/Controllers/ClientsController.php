<?php

namespace App\Http\Controllers;

use App\Events\ClientCreated;
use App\Imports\ClientsImport;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Country;
use App\Models\District;
use App\Models\Import;
use App\Models\IndustryType;
use App\Models\LegalType;
use App\Models\LoanApplication;
use App\Models\Client;
use App\Models\Province;
use App\Models\Village;
use App\Models\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class ClientsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:clients.index'])->only(['index', 'show']);
        $this->middleware(['permission:clients.create'])->only(['create', 'store', 'import']);
        $this->middleware(['permission:clients.update'])->only(['edit', 'update']);
        $this->middleware(['permission:clients.destroy'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        return Inertia::render('Clients/Index', [
            'filters' => $request->all('search', 'trashed'),
            'can' => [
                'create' => Auth::user()->can('clients.create'),
                'edit' => Auth::user()->can('clients.update'),
                'delete' => Auth::user()->can('clients.destroy'),
            ],
            'clients' => Client::query()
                ->when($request->input('search'), function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('external_id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
                })
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($client) => [
                    'id' => $client->id,
                    'external_id' => $client->external_id,
                    'name' => $client->name,
                    'mobile' => $client->mobile,
                    'updated_at' => $client->updated_at->format('Y-m-d H:i'),
                    'deleted_at' => $client->deleted_at,
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'external_id' => ['required', 'string'],
            'mobile' => ['required', 'string', 'regex:/^07\d{8}$/'],
        ], [
            'mobile.regex' => 'Phone number must be 10 digits starting with 07',
        ]);

        $client = new Client();
        $client->created_by_id = Auth::id();
        $client->name = $request->name;
        $client->external_id = $request->external_id;
        $client->mobile = $request->mobile;
        $client->type = 'individual';
        $client->status = 'active';
        $client->save();

        event(new ClientCreated($client));

        activity()
            ->performedOn($client)
            ->log('Create Client');

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function show(Client $client)
    {
        $client->load(['country', 'province', 'district', 'ward', 'village', 'branch','industryType','legalType','mainBank','secondBank','thirdBank','registrationCountry']);
        return Inertia::render('Clients/Show', [
            'client' => $client
        ]);
    }

    public function edit(Client $client)
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'external_id' => ['required', 'string'],
            'mobile' => ['required', 'string', 'regex:/^07\d{8}$/'],
        ]);

        $client->name = $request->name;
        $client->external_id = $request->external_id;
        $client->mobile = $request->mobile;
        $client->type = 'individual';
        $client->status = 'active';
        $client->save();

        activity()
            ->performedOn($client)
            ->log('Update Client');

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {

        $client->delete();
        activity()
            ->performedOn($client)
            ->log('Delete Client');
        return redirect()->route('clients.index')->with('success', 'Client deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->s;
        $id = $request->id;
        $branchID = $request->branch_id;
        $data = Client::with(['country', 'province', 'branch', 'district', 'ward', 'village'])->where(function ($query) use ($search) {
            $query->where('external_id', 'like', "%$search%")
                ->orWhere('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('id_number', 'like', "%$search%")
                ->orWhere('mobile', 'like', "%$search%");
        })->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->when($branchID, function ($query) use ($branchID) {
            return $query->where('branch_id', $branchID);
        })->get();
        return response()->json($data);
    }

    public function loanApplication(Client $client)
    {

        $applications = LoanApplication::with(['staff', 'client', 'product'])
            ->filter(\request()->only('search', 'client_id', 'loan_product_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status'))
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Clients/LoanApplications/Index', [
            'client' => $client,
            'applications' => $applications,
        ]);
    }

    public function courses(Client $client)
    {

        $registrations = CourseRegistration::with(['tutor', 'course', 'client'])
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Clients/Courses/Index', [
            'client' => $client,
            'registrations' => $registrations,
        ]);
    }

   /* public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,csv', 'max:10240'],
        ]);

        $file = $request->file('file');
        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($lines as $line) {
            try {
                if (preg_match('/^(\S+)\s+(\S+)-(.+)$/', $line, $matches)) {
                    $customerId = $matches[1];
                    $phone = $matches[2];
                    $publicName = trim($matches[3]);

                    Client::updateOrCreate(
                        ['external_id' => $customerId],
                        [
                            'name' => $publicName,
                            'mobile' => $phone,
                            'created_by_id' => Auth::id(),
                        ]
                    );

                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Invalid format in line: {$line}";
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Error processing line: {$line}. Error: {$e->getMessage()}";
            }
        }

        return back()->with([
            'success' => "{$successCount} clients imported successfully.",
            'error' => $errorCount > 0 ? "{$errorCount} errors occurred during import." : null,
            'errors' => $errors,
        ]);
    }*/
    public function createImport(): Response
    {

        return Inertia::render('Clients/Import', [
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,csv'],
        ]);

        $import = Import::create([
            'name' => $request->file('file')->getClientOriginalName(),
            'status' => 'pending',
        ]);
        Excel::queueImport(new ClientsImport($import), $request->file('file'));

        return redirect()->route('clients.index')->with('success', 'Import started! You will be notified once it completes.');

    }
}
