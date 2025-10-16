<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Course;
use App\Models\FormType;
use App\Models\JournalEntry;
use App\Models\PaymentDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


class FormsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware(['permission:forms.index'])->only(['index', 'show', 'get_forms']);
        $this->middleware(['permission:forms.create'])->only(['create', 'store']);
        $this->middleware(['permission:forms.update'])->only(['edit', 'update']);
        $this->middleware(['permission:forms.destroy'])->only(['destroy']);

    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $forms = Course::with(['tariff'])
            ->filter(\request()->only('search', 'tariff_id', 'type'))
            ->paginate(20);
        return Inertia::render('Forms/Index', [
            'filters' => \request()->all('search', 'tariff_id', 'type'),
            'forms' => $forms,
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Forms/Create', [

        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required'],
            'active' => ['required'],
        ]);
        $form = new Course();
        $form->created_by_id = Auth::id();
        $form->name = $request->name;
        $form->type = $request->type;
        $form->tariff_id = $request->tariff_id;
        $form->active = $request->active;
        $form->description = $request->description;
        $form->save();
        activity()->on($form)
            ->withProperties(['id' => $form->id])
            ->log('Create Form');
        return redirect()->route('forms.index')->with('success', 'Form saved successfully.');

    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Course $form)
    {
        $form->load(['tariff','fields']);
        return Inertia::render('Forms/Show', [
            'form' => $form,

        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(Course $form)
    {
        return Inertia::render('Forms/Edit', [
            'currentForm' => $form,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param Course $form
     * @return Response
     */
    public function update(Request $request, Course $form)
    {
        $request->validate([
            'name' => ['required'],
            'active' => ['required'],
        ]);

        $form->name = $request->name;
        $form->type = $request->type;
        $form->tariff_id = $request->tariff_id;
        $form->active = $request->active;
        $form->description = $request->description;
        $form->save();
        activity()->on($form)
            ->withProperties(['id' => $form->id])
            ->log('Update Form');
        return redirect()->route('forms.index')->with('success', 'Form  updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Course $form)
    {
        $form->fields()->delete();
        $form->delete();
        activity()->on($form)
            ->withProperties(['id' => $form->id])
            ->log('Delete Form');
        return redirect()->route('forms.index')->with('success', 'Form deleted successfully.');

    }
}
