<?php

namespace App\Http\Controllers;


use App\Models\CommunicationTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


class CommunicationTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
        $this->middleware(['permission:communication.templates.index'])->only(['index', 'show', 'get_templates']);
        $this->middleware(['permission:communication.templates.create'])->only(['create', 'store']);
        $this->middleware(['permission:communication.templates.update'])->only(['edit', 'update']);
        $this->middleware(['permission:communication.templates.destroy'])->only(['destroy']);

    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $templates = CommunicationTemplate::filter(\request()->only('search', 'type'))
            ->orderBy('id','desc')
            ->paginate(20);
        return Inertia::render('CommunicationTemplates/Index', [
            'filters' => \request()->all('search', 'type'),
            'templates' => $templates,
        ]);
    }


    /**
     * Show the template for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('CommunicationTemplates/Create', [

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
            'type' => ['required'],
            'subject' => ['required_if:type,email'],
            'active' => ['required'],
        ]);
        $template = new CommunicationTemplate();
        $template->created_by_id = Auth::id();
        $template->name = $request->name;
        $template->type = $request->type;
        $template->subject = $request->subject;
        $template->active = $request->active;
        $template->description = $request->description;
        $template->save();
        activity()->on($template)
            ->withProperties(['id' => $template->id])
            ->log('Create Communication Template');
        return redirect()->route('communication.templates.index')->with('success', 'Communication Template saved successfully.');

    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(CommunicationTemplate $template)
    {
        $template->load(['tariff', 'fields']);
        return Inertia::render('CommunicationTemplates/Show', [
            'template' => $template,

        ]);
    }

    /**
     * Show the template for editing the specified resource.
     * @param int $id
     * @return Response
     */
    public function edit(CommunicationTemplate $template)
    {
        return Inertia::render('CommunicationTemplates/Edit', [
            'template' => $template,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param CommunicationTemplate $template
     * @return Response
     */
    public function update(Request $request, CommunicationTemplate $template)
    {
        $request->validate([
            'name' => ['required'],
            'active' => ['required'],
        ]);
        if (!$template->is_system) {
            $template->name = $request->name;
            $template->type = $request->type;
            $template->active = $request->active;
        }
        $template->subject = $request->subject;
        $template->description = $request->description;
        $template->save();
        activity()->on($template)
            ->withProperties(['id' => $template->id])
            ->log('Update Communication Template');
        return redirect()->route('communication.templates.index')->with('success', 'Communication Template  updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(CommunicationTemplate $template)
    {
        if ($template->is_system) {
            return redirect()->back()->with('error', 'Cannot delete system template');
        }
        $template->delete();
        activity()->on($template)
            ->withProperties(['id' => $template->id])
            ->log('Delete CommunicationTemplate');
        return redirect()->route('communication.templates.index')->with('success', 'Communication Template deleted successfully.');

    }
}
