<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\MacroStatsDefinition;

class MacroStatsController extends Controller
{
    public function index()
        {
            $stats = MacroStatsDefinition::orderBy('statistic_name')->get();

            return Inertia::render('FLI/MacroStats/Index', [
                'statistics' => $stats
            ]);
        }

    // ✅ Store a new macro statistic
    public function store(Request $request)
    {
        $validated = $request->validate([
            'statistic_code' => 'required|string|unique:macro_statistics,statistic_code',
            'statistic_name' => 'required|string',
            'statistic_description' => 'nullable|string',
            'unit' => 'nullable|string',
            'frequency' => 'nullable|in:monthly,quarterly,yearly',
            'periodic_interval' => 'nullable|string',
            'historical_periods' => 'nullable|integer',
            'forecasting_periods' => 'nullable|integer',
            'comments' => 'nullable|string',
            'data_source' => 'nullable|string',
            'website_link' => 'nullable|string',
        ]);

        $stat = MacroStatsDefinition::create($validated);
       return redirect()->route('macro-statistics.index')->with('success', 'Economic Option created successfully');
    }

    // ✅ Update an existing macro statistic
    public function update(Request $request, $id)
    {
        $stat = MacroStatsDefinition::findOrFail($id);

        $validated = $request->validate([
            'statistic_code' => 'required|string|unique:macro_statistics,statistic_code,' . $stat->id,
            'statistic_name' => 'required|string',
            'statistic_description' => 'nullable|string',
            'unit' => 'nullable|string',
            'frequency' => 'nullable|in:monthly,quarterly,yearly',
            'periodic_interval' => 'nullable|string',
            'historical_periods' => 'nullable|integer',
            'forecasting_periods' => 'nullable|integer',
            'comments' => 'nullable|string',
            'data_source' => 'nullable|string',
            'website_link' => 'nullable|string',
        ]);

        $stat->update($validated);
        return redirect()->route('macro-statistics.index')->with('success', 'Economic Option updated successfully');
    }

    // ✅ Delete (or deactivate) a macro statistic
    public function destroy($id)
    {
        $stat = MacroStatsDefinition::findOrFail($id);
        $stat->delete();

        return redirect()->route('macro-statistics.index')->with('success', 'Economic Option deleted successfully');
 }
}
