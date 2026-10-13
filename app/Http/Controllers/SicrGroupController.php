<?php

namespace App\Http\Controllers;

use App\Models\SicrGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use League\Csv\Reader;

class SicrGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index()
    {
        // each group with how many of its items imply Stage 2 and Stage 3
        $groups = SicrGroup::withCount([
            'items',
            'items as stage2_count' => fn ($q) => $q->where('stage_implied', 2),
            'items as stage3_count' => fn ($q) => $q->where('stage_implied', 3),
            'items as active_count' => fn ($q) => $q->where('active', true),
        ])->orderBy('sort_order')->orderBy('name')->paginate(15);
        return Inertia::render('StageingRules/Groups', [
            'groups' => $groups,
            'totals' => [
                'groups' => SicrGroup::count(),
                'items' => \App\Models\SicrItem::count(),
                'stage2' => \App\Models\SicrItem::where('stage_implied', 2)->count(),
                'stage3' => \App\Models\SicrItem::where('stage_implied', 3)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:199'],
            'description' => ['nullable','string'],
            'sort_order' => ['nullable','integer','min:0','max:999'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        SicrGroup::create($data);
        return back()->with('success', 'Group created.');
    }

    public function update(Request $request, SicrGroup $group)
    {
        $data = $request->validate([
            'name' => ['required','string','max:199'],
            'description' => ['nullable','string'],
            'sort_order' => ['nullable','integer','min:0','max:999'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $group->update($data);
        return back()->with('success', 'Group updated.');
    }

    public function destroy(SicrGroup $group)
    {
        if ($group->items()->count() > 0) {
            return back()->with('error', 'Cannot delete group with items.');
        }
        $group->delete();
        return back()->with('success', 'Group deleted.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:csv,txt','max:10240'],
        ]);
        $csv = Reader::createFromPath($request->file('file')->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = array_map('strtolower', $csv->getHeader());
        $required = ['name','description'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            return back()->with('error', 'Missing headers: '.implode(', ', $missing));
        }
        $count = 0;
        foreach ($csv->getRecords() as $row) {
            if (!empty($row['name'])) {
                SicrGroup::firstOrCreate(['name' => $row['name']], ['description' => $row['description'] ?? null]);
                $count++;
            }
        }
        return back()->with('success', "Imported {$count} groups.");
    }
}
