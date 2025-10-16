<?php

namespace App\Http\Controllers\MemberPortal;

use App\Events\CourseCreated;
use App\Events\CourseRegistrationCreated;
use App\Events\CourseRegistrationStatusChanged;
use App\Events\CourseStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CourseCategory;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Tariff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class MemberPortalCourseRegistrationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $tutorID = null;
        $nurseID = null;
        $receptionistID = null;

        $registrations = CourseRegistration::with(['tutor', 'course', 'member'])
            ->where('member_id',session('member_id'))
            ->filter(\request()->only('search', 'tutor_id', 'course_id', 'member_id', 'course_category_id', 'status'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/CourseRegistrations/Index', [
            'filters' => \request()->all('search', 'tutor_id', 'course_id', 'member_id', 'course_category_id', 'status'),
            'registrations' => $registrations,
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('MemberPortal/CourseRegistrations/Create', [
            'member_id'=>\request('member_id'),
            'course_id'=>\request('course_id'),
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => ['required'],
        ]);
        $member = Client::find(session('member_id'));
        $course = Course::find($request->course_id);
        $registration = new CourseRegistration();
        $registration->created_by_id = Auth::id();
        $registration->tutor_id = $course->tutor_id;
        $registration->course_id = $request->course_id;
        $registration->member_id = $member->id;
        $registration->registration_date = date('Y-m-d');
        $registration->start_date = date('Y-m-d');
        $registration->status = 'pending';
        $registration->member_name = $request->member_name??$member->name;
        $registration->channel = $request->channel;
        $registration->description = $request->description;
        $registration->save();
        event(new CourseRegistrationCreated($registration));

        return redirect()->route('portal.registrations.show', $registration->id)->with('success', 'Course registration created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param CourseRegistration $registration
     * @return \Inertia\Response
     */
    public function show(CourseRegistration $registration)
    {
        if ($registration->member_id != session('member_id')) {
            abort(403);
        }
        $registration->load(['tutor', 'course', 'member']);
        return Inertia::render('MemberPortal/CourseRegistrations/Show', [
            'registration' => $registration,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param CourseRegistration $registration
     * @return \Inertia\Response
     */
    public function edit(CourseRegistration $registration)
    {
        if ($registration->member_id != session('member_id')) {
            abort(403);
        }
        return Inertia::render('MemberPortal/CourseRegistrations/Edit', [
            'registration' => $registration,
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param CourseRegistration $registration
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, CourseRegistration $registration)
    {
        if ($registration->member_id != session('member_id')) {
            abort(403);
        }
        $request->validate([

        ]);
        $registration->registration_date = $request->registration_date??date('Y-m-d');
        $registration->start_date = $request->start_date??date('Y-m-d');
        $registration->status = $request->status;
        $registration->member_name = $request->member_name??$registration->member->name;
        $registration->channel = $request->channel;
        $registration->description = $request->description;
        $registration->save();
        if ($registration->wasChanged('status')) {
            event(new CourseRegistrationStatusChanged($registration));
        }
        return redirect()->route('portal.registrations.show', $registration->id)->with('success', 'Course Registration  updated successfully.');
    }

    public function changeStatus(Request $request, Course $registration)
    {
        if ($registration->member_id != session('member_id')) {
            abort(403);
        }
        $registration->status = $request->status;
        $registration->approval_status_notes = $request->approval_status_notes;
        $registration->approved_by_id = Auth::id();
        $registration->save();

        if ($registration->wasChanged('status')) {
            event(new CourseRegistrationStatusChanged($registration));
        }
        return redirect()->route('portal.registrations.show', $registration->id)->with('success', 'Course  Registrationupdated successfully.');
    }
}
