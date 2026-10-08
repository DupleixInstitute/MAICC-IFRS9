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
    
        // One update per row with bound integers. The earlier CASE statement
        // interpolated the request ids into raw SQL; the exists: rule did not
        // stop it, because MySQL coerces "5 OR 1=1" to 5 (system audit of
        // 9 October 2026, finding C8).
        DB::transaction(function () use ($startCategories, $endCategories) {
            foreach ([$startCategories, $endCategories] as $group) {
                foreach ($group as $index => $category) {
                    DB::table('transition_profile_options')->where('id', (int) $category['id'])->update(['ordering_index' => $index + 1]);
                }
            }
        });
    
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
