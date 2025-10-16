<?php

namespace App\Http\Controllers\MemberPortal;

use App\Events\CourseCreated;
use App\Events\CourseStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FilesController;
use App\Models\Article;
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

class MemberPortalCoursesController extends Controller
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
        $courses = Course::with(['tutor', 'category'])
            ->where('approval_status', 'approved')
            ->where('status', 'publish')
            ->filter(\request()->only('search', 'tutor_id', 'course_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status', 'approval_status'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Courses/Index', [
            'filters' => \request()->all('search', 'tutor_id', 'course_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status', 'approval_status'),
            'courses' => $courses,
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }


    /**
     * Display the specified resource.
     *
     * @param Course $course
     * @return \Inertia\Response
     */
    public function show(Course $course)
    {
        $course->load(['tutor', 'category']);
        $course->registered_course= CourseRegistration::where('member_id', session('member_id'))->where('course_id', $course->id)->first();;
        return Inertia::render('MemberPortal/Courses/Show', [
            'course' => $course,
        ]);
    }


    public function search(Request $request)
    {
        $search = $request->s;
        $id = $request->id;
        $tutorID = $request->tutor_id;
        $courseCategoryID = $request->course_category_id;
        $data = Course::with(['tutor', 'category'])->where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%");
            $query->orWhere('id', 'like', "%$search%");
            $query->orWhere('description', 'like', "%$search%");
        })->when($id, function ($query) use ($id) {
            return $query->where('id', $id);
        })->when($tutorID, function ($query) use ($tutorID) {
            return $query->where('tutor_id', $tutorID);
        })->when($courseCategoryID, function ($query) use ($courseCategoryID) {
            return $query->where('course_category_id', $courseCategoryID);
        })->get();
        return response()->json($data);
    }

    public function registrations(Course $course)
    {
        $course->registered_course= CourseRegistration::where('member_id', session('member_id'))->where('course_id', $course->id)->first();;

        $registrations = CourseRegistration::with(['tutor', 'course', 'member'])
            ->where('course_id', $course->id)
            ->filter(\request()->only('search', 'tutor_id', 'course_id', 'member_id', 'course_category_id', 'status'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Courses/Registrations/Index', [
            'filters' => \request()->all('search', 'tutor_id', 'course_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status', 'approval_status'),
            'registrations' => $registrations,
            'course' => $course,
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }

    public function articles(Course $course)
    {
        $course->registered_course= CourseRegistration::where('member_id', session('member_id'))->where('course_id', $course->id)->first();;
        $articles = Article::filter(\request()->only('search', 'status'))->with(['category', 'createdBy', 'course'])
            ->where('course_id', $course->id)
            ->where('status', 'publish')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Courses/Articles/Index', [
            'filters' => \request()->all('search', 'tutor_id', 'article_category_id', 'province_id', 'branch_id', 'district_id', 'ward_id', 'date_range', 'village_id', 'staff_id', 'status', 'approval_status'),
            'articles' => $articles,
            'course' => $course,
            'categories' => CourseCategory::get()->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name
                ];
            }),
        ]);

    }
}
