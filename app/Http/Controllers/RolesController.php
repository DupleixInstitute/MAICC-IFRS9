<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Consultation;
use App\Models\InvoicePayment;
use App\Models\Client;
use App\Models\User;
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

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:users.roles.index'])->only(['index', 'show']);
        $this->middleware(['permission:users.roles.create'])->only(['create', 'store']);
        $this->middleware(['permission:users.roles.update'])->only(['edit', 'update']);
        $this->middleware(['permission:users.roles.destroy'])->only(['destroy']);
    }


    public function index()
    {

        return Inertia::render('Roles/Index', [
            'roles' => Role::paginate(),
        ]);
    }

    public function create()
    {

        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all()->groupBy('module'),
        ]);
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'send_email_to_role_members' => 'nullable|boolean',
            'group_email' => 'nullable|email',
            'can_reassign_role_members' => 'nullable|boolean',
        ]);
        $role = Role::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'guard_name' => 'web',
            'send_email_to_role_members' => $request->send_email_to_role_members,
            'group_email' => $request->group_email,
            'can_reassign' => $request->can_reassign_role_members
        ]);
        $role->syncPermissions($request->permissions);

        return redirect()->route('users.roles.index')->with('success', 'Role created successfully.');
    }

    public function show(Role $role)
    {
        $role->permissions->transform(function ($item) {
            return $item->name;
        });
        return Inertia::render('Roles/Show', [
            'role' => $role,
            'permissions' => Permission::all()->groupBy('module'),
        ]);
    }
    public function edit(Role $role)
    {

        $role->permissions->transform(function ($item) {
            return $item->name;
        });
        return Inertia::render('Roles/Edit', [
            'role' => $role,
            'permissions' => Permission::all()->groupBy('module'),
        ]);
    }

    public function update(Request $request, Role $role)
    {


        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo role is not allowed.');
        }
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'send_email_to_role_members' => 'nullable|boolean',
            'group_email' => 'nullable|email',
            'can_reassign' => 'boolean|required'
        ]);
        $data = [
            'display_name' => $request->display_name,
            'send_email_to_role_members' => $request->send_email_to_role_members,
            'group_email' => $request->group_email,
            'can_reassign' =>$request->can_reassign
        ];
        if ($role->is_system === 0) {
            $data['name'] = $request->name;
        }
        $role->update($data);
        $role->syncPermissions($request->permissions);

        return redirect()->route('users.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo user is not allowed.');
        }
        if ($role->is_system == 1) {
            return redirect()->back()->with('error', 'Cannot delete system role.');
        }
        $role->delete();
        return redirect()->route('users.roles.index')->with('success', 'Role deleted successfully.');
    }

}
