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
        $groups = SicrGroup::orderBy('sort_order')->orderBy('name')->get(['id','name']);
        $items = SicrItem::with('group')
            ->join('finance_sicr_groups as g', 'g.id', '=', 'finance_sicr_items.group_id')
            ->select('finance_sicr_items.*')
            ->when($request->group_id, fn($q) => $q->where('finance_sicr_items.group_id', $request->group_id))
            ->when(in_array((string) $request->stage, ['2', '3'], true), fn($q) => $q->where('finance_sicr_items.stage_implied', (int) $request->stage))
            ->orderBy('g.sort_order')->orderBy('g.name')->orderBy('finance_sicr_items.stage_implied')->orderBy('finance_sicr_items.name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('StageingRules/Items', [
            'groups' => $groups,
            'items' => $items,
            'filters' => $request->only('group_id', 'stage'),
            'counts' => [
                'all' => SicrItem::count(),
                'stage2' => SicrItem::where('stage_implied', 2)->count(),
                'stage3' => SicrItem::where('stage_implied', 3)->count(),
                'inactive' => SicrItem::where('active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'name' => ['required','string','max:199'],
            'description' => ['nullable','string','max:2000'],
            'stage_implied' => ['required','integer','in:2,3'],
            'evidence_required' => ['boolean'],
            'standard_reference' => ['nullable','string','max:120'],
            'active' => ['boolean'],
        ]);
        SicrItem::create($data);
        return back()->with('success', 'Item created.');
    }

    public function update(Request $request, SicrItem $item)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'name' => ['required','string','max:199'],
            'description' => ['nullable','string','max:2000'],
            'stage_implied' => ['required','integer','in:2,3'],
            'evidence_required' => ['boolean'],
            'standard_reference' => ['nullable','string','max:120'],
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
        // a trigger raised on the item keeps it: the register must still say what was raised
        if (\App\Models\SicrTrigger::where('item_id', $item->id)->exists()) {
            return back()->with('error', 'This item has triggers raised on it, so it cannot be deleted. Switch it off instead: no new triggers can then be raised on it.');
        }
        $item->delete();
        return back()->with('success', 'Item deleted.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:csv,txt','max:10240'],
        ]);
        // the file against the column spec before anything is written (key sicr-items)
        $check = (new \App\Support\Imports\ImportSpecValidator())->checkFile('sicr-items', $request->file('file')->getPathname(), $request->file('file')->getClientOriginalName());
        if (! $check['ok']) {
            return \App\Support\Imports\ImportSpecValidator::refuse($check);
        }
        $csv = Reader::createFromPath($request->file('file')->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = array_map('strtolower', $csv->getHeader());
        // stage (2 or 3), description, evidence_required and reference are optional columns
        $required = ['group','name','active'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            return back()->with('error', 'Missing headers: '.implode(', ', $missing));
        }
        $count = 0;
        $skipped = [];
        foreach ($csv->getRecords() as $n => $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            if (empty($row['name']) || empty($row['group'])) {
                continue;
            }
            // a blank stage is 2, as the screen and the sample say
            $stage = trim((string) ($row['stage'] ?? '')) ?: '2';
            if (! in_array($stage, ['2', '3'], true)) {
                $skipped[] = 'row ' . ($n + 1) . ' (stage must be 2 or 3)';
                continue;
            }
            $group = SicrGroup::firstOrCreate(['name' => $row['group']], ['description' => null]);
            $values = ['active' => filter_var($row['active'], FILTER_VALIDATE_BOOLEAN), 'stage_implied' => (int) $stage];
            foreach (['description', 'standard_reference'] as $col) {
                if (isset($row[$col]) && trim((string) $row[$col]) !== '') {
                    $values[$col] = trim((string) $row[$col]);
                }
            }
            if (isset($row['evidence_required']) && trim((string) $row['evidence_required']) !== '') {
                $values['evidence_required'] = filter_var($row['evidence_required'], FILTER_VALIDATE_BOOLEAN);
            }
            SicrItem::updateOrCreate(['group_id' => $group->id, 'name' => $row['name']], $values);
            $count++;
        }
        if ($skipped !== []) {
            return back()->with('warning', "Imported {$count} items. Skipped " . implode(', ', $skipped) . '.');
        }
        return back()->with('success', "Imported {$count} items.");
    }
}
