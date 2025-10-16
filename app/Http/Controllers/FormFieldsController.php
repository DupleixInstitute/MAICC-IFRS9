<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Course;
use App\Models\FormField;
use App\Models\FormType;
use App\Models\JournalEntry;
use App\Models\PaymentDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


class FormFieldsController extends Controller
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
    public function store(Request $request, Course $form)
    {
        $request->validate([
            'name' => ['required'],
            'type' => ['required'],
            'active' => ['required'],
        ]);
        $field = new FormField();
        $field->created_by_id = Auth::id();
        $field->form_id = $form->id;
        $field->name = $request->name;
        $field->type = $request->type;
        $field->options = $request->options;
        $field->normal_range = $request->normal_range;
        $field->unit = $request->unit;
        $field->field_position = $request->field_position;
        $field->active = $request->active;
        $field->required = $request->required;
        $field->rules = $request->rules;
        $field->classes = $request->classes;
        $field->default_values = $request->default_values;
        $field->description = $request->description;
        $field->save();
        activity()->on($field)
            ->withProperties(['id' => $field->id])
            ->log('Create Form');
        return redirect()->back()->with('success', 'Form  updated successfully.');

    }

    /**
     * Show the specified resource.
     * @param FormField $field
     * @return Response
     */
    public function show(FormField $field)
    {
        $field->load(['tariff', 'fields']);
        return Inertia::render('Forms/Show', [
            'form' => $field,

        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param FormField $field
     * @return Response
     */
    public function edit(FormField $field)
    {
        return Inertia::render('Forms/Edit', [
            'field' => $field,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param FormField $field
     * @return Response
     */
    public function update(Request $request, FormField $field)
    {
        $request->validate([
            'name' => ['required'],
            'active' => ['required'],
        ]);

        $field->name = $request->name;
        $field->type = $request->type;
        $field->options = $request->options;
        $field->normal_range = $request->normal_range;
        $field->unit = $request->unit;
        $field->field_position = $request->field_position;
        $field->active = $request->active;
        $field->required = $request->required;
        $field->rules = $request->rules;
        $field->classes = $request->classes;
        $field->default_values = $request->default_values;
        $field->description = $request->description;
        $field->save();
        activity()->on($field)
            ->withProperties(['id' => $field->id])
            ->log('Update Field');
        return redirect()->back()->with('success', 'Field  updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     * @param FormField $field
     * @return Response
     */
    public function destroy(FormField $field)
    {
        $field->delete();
        activity()->on($field)
            ->withProperties(['id' => $field->id])
            ->log('Delete Field');
        return redirect()->back()->with('success', 'Field deleted successfully.');

    }
}
