<?php

namespace App\Http\Controllers;

use App\Models\SmsGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SmsGatewaysController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:communication.sms_gateways.index'])->only(['index', 'show']);
        $this->middleware(['permission:communication.sms_gateways.create'])->only(['create', 'store']);
        $this->middleware(['permission:communication.sms_gateways.update'])->only(['edit', 'update']);
        $this->middleware(['permission:communication.sms_gateways.destroy'])->only(['destroy']);
    }

    public function index()
    {
        $smsGateways = SmsGateway::filter(\request()->only('search'))
            ->paginate(20);
        return Inertia::render('SmsGateways/Index', [
            'filters' => \request()->all('search'),
            'smsGateways' => $smsGateways,
        ]);
    }

    public function create()
    {

        return Inertia::render('SmsGateways/Create', [

        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
        ]);
        $smsGateway = new SmsGateway();
        $smsGateway->name = $request->name;
        $smsGateway->url = $request->url;
        $smsGateway->to_name = $request->to_name;
        $smsGateway->msg_name = $request->msg_name;
        $smsGateway->active = $request->active ? 1 : 0;
        $smsGateway->description = $request->description;
        $smsGateway->save();
        activity()
            ->performedOn($smsGateway)
            ->log('Create Sms Gateway');
        return redirect()->route('communication.sms_gateways.index')->with('success', 'SmsGateway created successfully.');
    }

    public function show(SmsGateway $smsGateway)
    {

        return Inertia::render('SmsGateways/Show', [
            'smsGateway' => $smsGateway
        ]);
    }

    public function edit(SmsGateway $smsGateway)
    {
        return Inertia::render('SmsGateways/Edit', [
            'smsGateway' => $smsGateway,
        ]);
    }

    public function update(Request $request, SmsGateway $smsGateway)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Updating the demo SMS Gateway is not allowed.');
        }
        $request->validate([
            'name' => ['required'],
        ]);
        $smsGateway->name = $request->name;
        if (!$smsGateway->is_system) {
            $smsGateway->url = $request->url;
            $smsGateway->to_name = $request->to_name;
            $smsGateway->msg_name = $request->msg_name;
        }
        $smsGateway->active = $request->active ? 1 : 0;
        $smsGateway->description = $request->description;
        $smsGateway->save();
        activity()
            ->performedOn($smsGateway)
            ->log('Update Sms Gateway');
        return redirect()->route('communication.sms_gateways.index')->with('success', 'Sms Gateway updated successfully.');
    }

    public function destroy(SmsGateway $smsGateway)
    {
        if (App::environment('demo')) {
            return redirect()->back()->with('error', 'Deleting the demo SMS Gateway is not allowed.');
        }
        if ($smsGateway->is_system) {
            return redirect()->back()->with('error', 'Deleting system SMS Gateway is not allowed.');
        }
        $smsGateway->delete();
        activity()
            ->performedOn($smsGateway)
            ->log('Delete SmsGateway');
        return redirect()->route('communication.sms_gateways.index')->with('success', 'Sms Gateway deleted successfully.');
    }
}
