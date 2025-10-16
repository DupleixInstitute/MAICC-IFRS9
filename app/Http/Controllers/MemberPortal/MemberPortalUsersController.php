<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Models\CourseRegistration;
use App\Models\Event;
use App\Models\Consultation;
use App\Models\Currency;
use App\Models\InvoicePayment;
use App\Models\LoanApplication;
use App\Models\Client;
use App\Models\MemberUser;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Jenssegers\Agent\Agent;

class MemberPortalUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {

        $loans = LoanApplication::where('member_id', session('member_id'))->count();
        $loansApproved = LoanApplication::where('member_id', session('member_id'))->where('status','approved')->count();
        $loansRejected = LoanApplication::where('member_id', session('member_id'))->where('status','rejected')->count();
        $loansApprovedAmount = LoanApplication::where('member_id', session('member_id'))->where('status','approved')->sum('amount');
        $courses = CourseRegistration::where('member_id', session('member_id'))->count();

        return Inertia::render('MemberPortal/Dashboard', [
            'loans' => number_format($loans),
            'loansApproved' => number_format($loansApproved),
            'loansRejected' => number_format($loansRejected),
            'loansApprovedAmount' => number_format($loansApprovedAmount, 2),
            'courses' => number_format($courses),
        ]);
    }

    public function index()
    {
        $users = User::with('roles')
            ->filter(\request()->only('search', 'role', 'gender'))
            ->paginate(20);
        return Inertia::render('Users/Index', [
            'filters' => \request()->all('search', 'role', 'gender'),
            'users' => $users,
            'roles' => Role::all(),
        ]);
    }

    public function profile(Request $request, $show)
    {
        return Inertia::render('MemberPortal/Profile/Show', [
            'sessions' => $this->sessions($request)->all(),
        ]);
    }

    public function linkedAccounts()
    {
        $accounts = MemberUser::with('member')
            ->where('user_id', Auth::id())
            ->get();
        return Inertia::render('MemberPortal/User/LinkedAccounts', [
            'accounts' => $accounts,
            'selectMemberID' => session('member_id'),
        ]);
    }
    public function selectLinkedAccount(Request $request)
    {
        session(['member_id'=>$request->member_id]);
        return redirect()->back()->with('success', 'Member selected successfully');

    }

    /**
     * Get the current sessions.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Support\Collection
     */
    public function sessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->orderBy('last_activity', 'desc')
                ->get()
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session);

            return (object)[
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });
    }

    /**
     * Create a new agent instance from the given session.
     *
     * @param mixed $session
     * @return \Jenssegers\Agent\Agent
     */
    protected function createAgent($session)
    {
        return tap(new Agent, function ($agent) use ($session) {
            $agent->setUserAgent($session->user_agent);
        });
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
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required'],
            'active' => ['required'],
            'roles' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:1024'],
        ]);
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'country_id' => $request->country_id,
            'title_id' => $request->title_id,
            'gender' => $request->gender,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'tel' => $request->tel,
            'zip' => $request->zip,
            'external_id' => $request->external_id,
            'practice_number' => $request->practice_number,
            'address' => $request->address,
            'active' => $request->active,
            'password' => Hash::make($request->password),
        ]);
        foreach ($request->roles as $key) {
            $user->assignRole(Role::findById($key));
        }
        if ($request->file('photo')) {
            $user->updateProfilePhoto($request->file('photo'));
        }
        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $user->selected_roles = $user->roles->map(function ($role) {
            return $role->id;
        });
        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => Role::all()->transform(function ($role) {
                return [
                    'value' => $role->id,
                    'label' => $role->display_name,
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required'],
            'roles' => ['required'],
            'active' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed'],
            'photo' => ['nullable', 'image', 'max:1024'],
        ]);
        $user->update([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'country_id' => $request->country_id,
            'title_id' => $request->title_id,
            'gender' => $request->gender,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'tel' => $request->tel,
            'zip' => $request->zip,
            'external_id' => $request->external_id,
            'practice_number' => $request->practice_number,
            'address' => $request->address,
            'active' => $request->active,
        ]);
        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }
        if ($request->file('photo')) {
            $user->updateProfilePhoto($request->file('photo'));
        }
        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if (App::environment('demo') && $user->isDemoUser()) {
            return redirect()->back()->with('error', 'Deleting the demo user is not allowed.');
        }
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->s;
        $type = $request->type ?: 'doctor';
        $id = $request->id;
        $data = User::where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%$search%");
            $query->orWhere('last_name', 'like', "%$search%");
            $query->orWhere('middle_name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('email', 'like', "%$search%");
            $query->orWhere('external_id', 'like', "%$search%");
            $query->orWhere('practice_number', 'like', "%$search%");
        })->when($type, function ($query) use ($type) {
            return $query->whereHas('roles', function ($query) use ($type) {
                $query->where('name', $type);
            });
        })->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->get();
        return response()->json($data);
    }
    public function searchMembers(Request $request)
    {
        $member=Client::find(session('member_id'));
        $search = $request->s;
        $id = $request->id;
        $branchID = $request->branch_id;
        $data = Client::with(['province', 'branch', 'district', 'ward', 'village'])->where(function ($query) use ($search) {
            $query->where('first_name', 'like', "%$search%");
            $query->orWhere('last_name', 'like', "%$search%");
            $query->orWhere('middle_name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('id_number', 'like', "%$search%");
            $query->orWhere('external_id', 'like', "%$search%");
        })->where('branch_id',$member->id)->where('id','!=',$member->id)->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->when($branchID, function ($query) use ($branchID) {
            return $query->where('branch_id', $branchID);
        })->get();
        return response()->json($data);
    }
}
