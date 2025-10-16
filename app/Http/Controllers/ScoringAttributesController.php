<?php

namespace App\Http\Controllers;

use App\Models\LoanProductScoringAttribute;
use App\Models\ScoringAttribute;
use App\Models\ScoringAttributeGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ScoringAttributesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:loans.scoring_attributes.index'])->only(['index', 'show']);
        $this->middleware(['permission:loans.scoring_attributes.create'])->only(['create', 'store']);
        $this->middleware(['permission:loans.scoring_attributes.update'])->only(['edit', 'update']);
        $this->middleware(['permission:loans.scoring_attributes.destroy'])->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = ScoringAttributeGroup::query()
            ->withCount('scoringAttributes as total_attributes')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest();

        return Inertia::render('ScoringAttributeGroups/Index', [
            'filters' => $request->all('search'),
            'attributes' => $query->paginate(10)
                ->through(function ($attribute) {
                    return [
                        'id' => $attribute->id,
                        'name' => $attribute->name,
                        'description' => $attribute->description,
                        'field_type' => $attribute->field_type,
                        'db_table_name' => $attribute->db_table_name,
                        'db_table_column_name' => $attribute->db_table_column_name,
                        'is_default' => $attribute->is_default,
                        'total_attributes' => $attribute->total_attributes
                    ];
                }),
        ]);
    }

    public function create()
    {
        // Get all tables from the database
        $tables = \DB::select('SHOW TABLES');
        $dbName = \DB::getDatabaseName();
        $tables = array_map(function($table) use ($dbName) {
            return $table->{'Tables_in_' . $dbName};
        }, $tables);

        return Inertia::render('ScoringAttributeGroups/Create', [
            'tables' => $tables,
            'tableColumns' => [] // Initially empty, will be populated via AJAX
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'field_type' => ['required', 'string', 'in:number,range,text'],
            'db_table_name' => ['required', 'string'],
            'db_table_column_name' => ['required', 'string'],
            'is_default' => ['boolean'],
        ]);

        $group = new ScoringAttributeGroup();
        $group->name = $validated['name'];
        $group->description = $validated['description'];
        $group->field_type = $validated['field_type'];
        $group->db_table_name = $validated['db_table_name'];
        $group->db_table_column_name = $validated['db_table_column_name'];
        $group->is_default = $validated['is_default'] ?? false;
        $group->created_by_id = auth()->id();
        $group->save();

        return redirect()->route('scoring_attributes.index')
            ->with('success', 'Transition Profile Column created successfully.');
    }

    public function storeItem(Request $request, ScoringAttributeGroup $group)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'field_type' => ['required'],
        ]);
        if (!empty($request->id)) {
            $attribute = ScoringAttribute::find($request->id);
        } else {
            $attribute = new ScoringAttribute();
            $attribute->created_by_id = Auth::id();
            $attribute->scoring_attribute_group_id = $request->scoring_attribute_group_id;
        }
        $attribute->name = $request->name;
        $attribute->field_type = $request->field_type;
        $attribute->condition = $request->condition;
        $attribute->default_values = $request->default_values;
        if ($request->field_type === 'dropdown' || $request->field_type === 'radio' || $request->field_type === 'checkbox') {
            $attribute->options = json_encode($request->options);
        } else {
            $attribute->options = $request->options;
        }
        $attribute->rules = $request->rules;
        $attribute->class = $request->class;
        $attribute->description = $request->description;
        $attribute->required = $request->required ? 1 : 0;
        $attribute->active = $request->active ? 1 : 0;
        $attribute->save();
        activity()
            ->performedOn($attribute)
            ->log('Create Scoring Attribute');
        return redirect()->back()->with('success', 'Scoring Attribute created successfully.');
    }

    public function getTableColumns(Request $request)
    {
        $tableName = $request->input('table');
        $columns = \Schema::getColumnListing($tableName);
        return response()->json($columns);
    }

    public function show(ScoringAttributeGroup $attribute)
    {
        $attribute->load(['scoringAttributes', 'createdBy']);
        $attribute->scoringAttributes->transform(function ($item) {
            if ($item->field_type === 'dropdown' || $item->field_type === 'radio' || $item->field_type === 'checkbox') {
                $item->options = json_decode($item->options);
            }
            return $item;
        });
        return Inertia::render('ScoringAttributeGroups/Show', [
            'attribute' => $attribute
        ]);
    }

    public function edit(ScoringAttributeGroup $attribute)
    {
        return Inertia::render('ScoringAttributes/Edit', [
            'attribute' => $attribute,
        ]);
    }

    public function update(Request $request, ScoringAttributeGroup $attribute)
    {

        $request->validate([
            'name' => ['required', 'string'],
        ]);
        $attribute->parent_id = $request->parent_id;
        $attribute->name = $request->name;
        $attribute->description = $request->description;
        $attribute->active = $request->active ? 1 : 0;
        $attribute->save();
        activity()
            ->performedOn($attribute)
            ->log('Update Scoring Attribute');

        return redirect()->route('scoring_attributes.index')->with('success', 'Scoring Attribute updated successfully.');
    }

    public function destroy(ScoringAttributeGroup $attribute)
    {
        $count = LoanProductScoringAttribute::where('scoring_attribute_group_id', $attribute->id)->count();
        if ($count > 0) {
            return redirect()->back()->with('error', 'You cannot delete this attribute, its being used in ' . $count . ' places.');
        }
        $attribute->delete();
        activity()
            ->performedOn($attribute)
            ->log('Delete Scoring Attribute');
        return redirect()->route('scoring_attributes.index')->with('success', 'Scoring Attribute deleted successfully.');
    }

    public function destroyItem(ScoringAttribute $attribute)
    {
        $count = LoanProductScoringAttribute::where('scoring_attribute_id', $attribute->id)->count();
        if ($count > 0) {
            return redirect()->back()->with('error', 'You cannot delete this attribute, its being used in ' . $count . ' places.');
        }
        $attribute->delete();
        activity()
            ->performedOn($attribute)
            ->log('Delete Scoring Attribute');
        return redirect()->back()->with('success', 'Scoring Attribute deleted successfully.');
    }
}
