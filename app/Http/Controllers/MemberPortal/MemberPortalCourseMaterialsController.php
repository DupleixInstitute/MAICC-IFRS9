<?php

namespace App\Http\Controllers\MemberPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FilesController;
use App\Models\CourseMaterial;
use App\Models\Course;
use App\Models\CourseRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MemberPortalCourseMaterialsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Course $course)
    {
        $course->registered_course= CourseRegistration::where('member_id', session('member_id'))->where('course_id', $course->id)->first();;

        $materials = CourseMaterial::with(['file'])
            ->where('course_id', $course->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return Inertia::render('MemberPortal/Courses/Materials/Index', [
            'course' => $course,
            'materials' => $materials,
        ]);
    }


    /**
     * Display the specified resource.
     *
     * @param CourseMaterial $material
     * @return \Inertia\Response
     */
    public function show(CourseMaterial $material)
    {
        $course = $material->course;
        $course->registered_course= CourseRegistration::where('member_id', session('member_id'))->where('course_id', $course->id)->first();;
        $material->load(['file']);
        return Inertia::render('MemberPortal/Courses/Materials/Show', [
            'course' => $course,
            'material' => $material,
        ]);
    }



}
