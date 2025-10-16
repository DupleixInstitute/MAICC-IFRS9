<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberPortalEventsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {

        $events = Event::filter(\request()->only('search', 'status', 'type'))->with(['category', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Events/Index', [
            'filters' => \request()->all('search', 'status', 'type'),
            'events' => $events,
            'categories' => EventCategory::all()->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->id,
                ];
            }),
        ]);
    }

    public function calendar(Request $request)
    {
        $events = Event::filter(\request()->only('search', 'status', 'type'))->with(['category', 'createdBy'])
            ->where('start_date', '>=', Carbon::today()->format('Y-m-d'))
            ->orderBy('created_at', 'desc')
            ->get();
        return Inertia::render('MemberPortal/Events/Calendar', [
            'events' => $events,
            'categories' => EventCategory::all()->map(function ($item) {
                return [
                    'label' => $item->name,
                    'value' => $item->id,
                ];
            }),
            'filters' => \request()->all('search', 'branch_id', 'status', 'created_by_type', 'patient_id', 'doctor_id', 'appointment_type', 'date_range'),

        ]);
    }

    public function getEvents(Request $request)
    {
        $events = Event::filter(\request()->only('search', 'status', 'type'))->with(['category', 'createdBy'])
            ->where('start_date', '>=', Carbon::today()->format('Y-m-d'))
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($events);
    }

}
