<?php

namespace App\Http\Controllers;

use App\Models\SicrGroup;
use App\Models\SicrItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use League\Csv\Reader;

class SicrItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index(Request $request)
    {
        $groups = SicrGroup::orderBy('name')->get(['id','name']);
        $items = SicrItem::with('group')
            ->when($request->group_id, fn($q) => $q->where('group_id', $request->group_id))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('StageingRules/Items', [
            'groups' => $groups,
            'items' => $items,
            'filters' => $request->only('group_id')
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'name' => ['required','string','max:255'],
            'active' => ['boolean'],
        ]);
        SicrItem::create($data);
        return back()->with('success', 'Item created.');
    }

    public function update(Request $request, SicrItem $item)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'name' => ['required','string','max:255'],
            'active' => ['boolean'],
        ]);
        $item->update($data);
        return back()->with('success', 'Item updated.');
    }

    public function toggle(SicrItem $item)
    {
        $item->active = !$item->active;
        $item->save();
        return back()->with('success', 'Item status updated.');
    }

    public function destroy(SicrItem $item)
    {
        $item->delete();
        return back()->with('success', 'Item deleted.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:csv,txt','max:10240'],
        ]);
        $csv = Reader::createFromPath($request->file('file')->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = array_map('strtolower', $csv->getHeader());
        $required = ['group','name','active'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            return back()->with('error', 'Missing headers: '.implode(', ', $missing));
        }
        $count = 0;
        foreach ($csv->getRecords() as $row) {
            $group = SicrGroup::firstOrCreate(['name' => $row['group']], ['description' => null]);
            if (!empty($row['name'])) {
                SicrItem::updateOrCreate([
                    'group_id' => $group->id,
                    'name' => $row['name'],
                ], [
                    'active' => filter_var($row['active'], FILTER_VALIDATE_BOOLEAN),
                ]);
                $count++;
            }
        }
        return back()->with('success', "Imported {$count} items.");
    }
}
