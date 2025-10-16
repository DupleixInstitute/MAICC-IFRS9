<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TransitionProfileOption;
use App\Models\TransitionProfileDefinition;
use Inertia\Inertia;
use App\Http\Requests;
use Illuminate\Support\Facades\DB;
class TransitionProfileOptionController extends Controller
{
    public function index($profileId)
    {
        $profile = TransitionProfileDefinition::find($profileId);
    
        $startCategories = TransitionProfileOption::where('profile_id', $profileId)
            ->where('is_start_or_end', 'start')
            ->get();
    
        $endCategories = TransitionProfileOption::where('profile_id', $profileId)
            ->where('is_start_or_end', 'end')
            ->get();

        return Inertia::render('TransitionProfileDefinitions/Components/ConfigurationDataTables', [
            'profile' => $profile,
            'startCategories'=> $startCategories,
            'endCategories'=> $endCategories,
            'categories' => $startCategories->merge($endCategories),
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'profile_id' => 'required|exists:transition_profile_definitions,id',
            'ordering_index' => 'nullable|integer',
            'category_name' => 'required',
            'is_start_or_end' => 'required',
            'min_value' => 'required',
            'max_value' => 'required',
            'text_value' => 'required',
            'default_value' => 'nullable',
        ]);
    
        $validatedData['ordering_index'] = $request->ordering_index ?? null; // Ensure null if not provided
    
        TransitionProfileOption::create($validatedData);
    
        return redirect()->back()->with('message', 'Category created successfully');
    }
    
    public function categories($profileId)
    {
        $categories = TransitionProfileOption::where('profile_id', $profileId)
            ->select(['id', 'category_name', 'ordering_index', 'is_start_or_end'])
            ->orderBy('ordering_index','desc')
            ->get();
    
        return inertia('TransitionProfileOption/Components/CategoryReorder', [
            'categories' => $categories,
        ]);
    }
    
    public function sortingIndex(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|exists:transition_profile_definitions,id',
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:transition_profile_options,id',
            'categories.*.ordering_index' => 'required|integer',
            'categories.*.is_start_or_end' => 'required|in:start,end',
        ]);

        $profileId = $request->profile_id;
        $categories = $request->categories;

        $startCategories = array_values(array_filter($categories, fn ($c) => $c['is_start_or_end'] === 'start'));
        $endCategories = array_values(array_filter($categories, fn ($c) => $c['is_start_or_end'] === 'end'));
    
        $query = "UPDATE transition_profile_options SET ordering_index = CASE id ";
        $ids = [];

        foreach ($startCategories as $index => $category) {
            $query .= "WHEN {$category['id']} THEN " . ($index + 1) . " ";
            $ids[] = $category['id'];
        }

        foreach ($endCategories as $index => $category) {
            $query .= "WHEN {$category['id']} THEN " . ($index + 1) . " ";
            $ids[] = $category['id'];
        }
        
        $query .= "END WHERE id IN (" . implode(',', $ids) . ")";
    
        DB::statement($query);
    
        return redirect()->route('transition-profiles.config', $profileId)->with('message', 'Categories sorted successfully');
    }

    public function update(Request $request, $id)
    {
        $category = TransitionProfileOption::findOrFail($id);
        $category->update($request->only(['category_name', 'min_value', 'max_value','text_value','default_value']));
    
         return redirect()->back()->with('message', 'Category created successfully');
    }
    
    
    public function destroy($id)
    {
        $category = TransitionProfileOption::find($id);
        $category->delete();
    
        return redirect()->back()->with('message', 'Category deleted successfully');
    }
    
}
