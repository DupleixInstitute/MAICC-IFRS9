<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Branch;
use App\Models\Consultation;
use App\Models\LoanApplication;
use App\Models\InvoicePayment;
use App\Models\Client;
use App\Models\User;
use App\Notifications\SendLoginDetails;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:users.index'])->only(['index', 'show']);
        $this->middleware(['permission:users.create'])->only(['create', 'store']);
        $this->middleware(['permission:users.update'])->only(['edit', 'update']);
        $this->middleware(['permission:users.destroy'])->only(['destroy']);
    }

    public function dashboard()
    {

        $members = Client::count();
        $membersLastMonth = Client::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $membersThisMonth = Client::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $membersChange = 0;
        $membersChangeClass = 'text-green-500';
        if ($membersLastMonth > 0) {
            $membersChange = abs(($membersThisMonth - $membersLastMonth) * 100 / $membersLastMonth);
            if ($membersThisMonth < $membersLastMonth) {
                $membersChangeClass = 'text-red-500';
            }
        }
        if ($membersLastMonth === 0 && $membersThisMonth > 0) {
            $membersChange = 100;
        }
        $consultations = Consultation::count();
        $consultationsLastMonth = Consultation::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $consultationsThisMonth = Consultation::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $consultationsChange = 0;
        $consultationsChangeClass = 'text-green-500';
        if ($consultationsLastMonth > 0) {
            $consultationsChange = abs(($consultationsThisMonth - $consultationsLastMonth) * 100 / $consultationsLastMonth);
            if ($consultationsThisMonth < $consultationsLastMonth) {
                $consultationsChangeClass = 'text-red-500';
            }
        }
        if ($consultationsLastMonth === 0 && $consultationsThisMonth > 0) {
            $consultationsChange = 100;
        }
        $appointments = Event::count();
        $appointmentsLastMonth = Event::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $appointmentsThisMonth = Event::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->count();
        $appointmentsChange = 0;
        $appointmentsChangeClass = 'text-green-500';
        if ($appointmentsLastMonth > 0) {
            $appointmentsChange = abs(($appointmentsThisMonth - $appointmentsLastMonth) * 100 / $appointmentsLastMonth);
            if ($appointmentsThisMonth < $appointmentsLastMonth) {
                $appointmentsChangeClass = 'text-red-500';
            }
        }
        if ($appointmentsLastMonth === 0 && $appointmentsThisMonth > 0) {
            $appointmentsChange = 100;
        }
        $payments = InvoicePayment::sum('amount');
        $paymentsLastMonth = InvoicePayment::whereBetween('created_at', [Carbon::today()->subMonth()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->subMonth()->endOfMonth()->format('Y-m-d H:i:s')])->sum('amount');
        $paymentsThisMonth = InvoicePayment::whereBetween('created_at', [Carbon::today()->startOfMonth()->format('Y-m-d H:i:s'), Carbon::today()->endOfMonth()->format('Y-m-d H:i:s')])->sum('amount');
        $paymentsChange = 0;
        $paymentsChangeClass = 'text-green-500';
        if ($paymentsLastMonth > 0) {
            $paymentsChange = abs(($paymentsThisMonth - $paymentsLastMonth) * 100 / $paymentsLastMonth);
            if ($paymentsThisMonth < $paymentsLastMonth) {
                $paymentsChangeClass = 'text-red-500';
            }
        }
        if ($paymentsLastMonth === 0 && $paymentsThisMonth > 0) {
            $paymentsChange = 100;
        }

        return Inertia::render('Dashboard', [
            'members' => number_format($members),
            'membersChange' => number_format($membersChange, 2),
            'membersChangeClass' => $membersChangeClass,
            'consultations' => number_format($consultations),
            'consultationsChange' => number_format($consultationsChange, 2),
            'consultationsChangeClass' => $consultationsChangeClass,
            'appointments' => number_format($appointments),
            'appointmentsChange' => number_format($appointmentsChange, 2),
            'appointmentsChangeClass' => $appointmentsChangeClass,
            'payments' => number_format($payments),
            'paymentsChange' => number_format($paymentsChange, 2),
            'paymentsChangeClass' => $paymentsChangeClass,
        ]);
    }

    public function index()
    {
        $users = User::with('roles')
            ->filter(\request()->only('search', 'role', 'gender'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('Users/Index', [
            'filters' => \request()->all('search', 'role', 'gender'),
            'users' => $users,
            'roles' => Role::all(),
        ]);
    }

    public function create()
    {

        return Inertia::render('Users/Create', [
            'roles' => Role::all()->transform(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->display_name,
                ];
            }),
            'branches' => Branch::all()->transform(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->name,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {

        // dd($request->all());

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required'],
            'active' => ['required'],
            'roles' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:1024'],
            'group_email' => ['nullable', 'string', 'email', 'max:255'],
            'can_reassign' =>['boolean','required']
        ]);

        $user = User::create([
            'name' => $request->name,
            'branch_id' => $request->branch_id,
            'country_id' => $request->country_id,
            'title_id' => $request->title_id,
            'gender' => $request->gender,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'tel' => $request->tel,
            'zip' => $request->zip,
            'external_id' => $request->external_id,
            'description' => $request->description,
            'address' => $request->address,
            'active' => $request->active,
            'password' => Hash::make($request->password),
            'group_email' => $request->group_email,
            'can_reassign' => $request->can_reassign
        ]);

        foreach ($request->roles as $key) {
            $user->assignRole(Role::findById($key));
        }
        if ($request->file('photo')) {
            $user->updateProfilePhoto($request->file('photo'));
        }
        if ($request->send_login_details) {
            $user->notify(new SendLoginDetails($request->password));
        }
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $user->load(['branch']);
        return Inertia::render('Users/Show', [
            'profile' => $user,
        ]);
    }

    public function availabilitySchedule(User $user)
    {
        $schedule = LoanApplication::where('doctor_id', $user->id)
            ->orderBy('start_time')
            ->get();
        return Inertia::render('Users/AvailabilitySchedule', [
            'profile' => $user,
            'monday' => $schedule->where('day', 'monday'),
            'tuesday' => $schedule->where('day', 'tuesday'),
            'wednesday' => $schedule->where('day', 'wednesday'),
            'thursday' => $schedule->where('day', 'thursday'),
            'friday' => $schedule->where('day', 'friday'),
            'saturday' => $schedule->where('day', 'saturday'),
            'sunday' => $schedule->where('day', 'sunday'),
        ]);
    }

    public function storeAvailabilitySchedule(Request $request, User $user)
    {
        $request->validate([
            'day' => ['required'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required'],
        ]);
        if (Carbon::parse($request->start_time)->greaterThan(Carbon::parse($request->end_time))) {
            return redirect()->back()->withErrors([
                    'end_time' => 'End time must be greater than start time']
            );
        }
        if (LoanApplication::where('doctor_id', $user->id)->where('day', $request->day)->where('start_time', '<=', $request->start_time)->where('end_time', '>=', $request->start_time)->count() > 0) {
            return redirect()->back()->withErrors([
                    'start_time' => 'There is already a schedule at this time']
            );
        }
        $schedule = new LoanApplication();
        $schedule->doctor_id = $user->id;
        $schedule->day = $request->day;
        $schedule->start_time = $request->start_time;
        $schedule->end_time = $request->end_time;
        $schedule->save();

        return redirect()->back()->with('success', 'Saved successfully.');
    }

    public function destroyAvailabilitySchedule(Request $request, LoanApplication $schedule)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo user is not allowed.');
        }
        $schedule->delete();

        return redirect()->back()->with('success', 'Deleted successfully.');
    }

    public function edit(User $user)
    {
        $user->selected_roles = $user->roles->map(function ($role) {
            return $role->id;
        })->toArray();

        return Inertia::render('Users/Edit', [
            'profile' => $user,
            'roles' => Role::all()->transform(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->display_name,
                ];
            }),
            'branches' => Branch::all()->transform(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->name,
                ];
            }),
        ]);
    }

    public function update(Request $request, User $user)
    {

        if (App::environment('demo') && $user->isDemoUser()) {
            return redirect()->back()->with('error', 'Updating the demo user is not allowed.');
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required'],
            'roles' => ['required'],
            'active' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:1024'],
            'group_email' => ['nullable', 'string', 'email', 'max:255'],
            'can_reassign' => ['required', 'boolean']
        ]);
        $user->update([
            'name' => $request->name,
            'branch_id' => $request->branch_id,
            'country_id' => $request->country_id,
            'title_id' => $request->title_id,
            'gender' => $request->gender,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'tel' => $request->tel,
            'zip' => $request->zip,
            'external_id' => $request->external_id,
            'description' => $request->description,
            'address' => $request->address,
            'active' => $request->active,
            'group_email' => $request->group_email,
            'can_reassign' => $request->can_reassign,
        ]);
        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }
        if ($request->file('photo')) {
            $user->updateProfilePhoto($request->file('photo'));
        }
        $user->syncRoles($request->roles);
        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->s;
        $type = $request->type;
        $roleID = $request->role_id;
        $id = $request->id;
        $data = User::where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('email', 'like', "%$search%");
            $query->orWhere('external_id', 'like', "%$search%");
        })->when($type, function ($query) use ($type) {
            return $query->whereHas('roles', function ($query) use ($type) {
                $query->where('name', $type);
            });
        })->when($roleID, function ($query) use ($roleID) {
            return $query->whereHas('roles', function ($query) use ($roleID) {
                $query->where('id', $roleID);
            });
        })->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->get();
        return response()->json($data);
    }
}
