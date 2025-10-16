<?php

namespace App\Http\Controllers;

use App\Models\TransitionProfileDefinition;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use App\DataTables\TransitionProfileDefinitionDataTable;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransitionProfileDefinitionController extends Controller
{

    public function index(Request $request)
    {
        return Inertia::render('TransitionProfileDefinitions/Index');
    }

    public function getProfiles(Request $request)
    {
        if ($request->ajax()) {
            $profiles = TransitionProfileDefinition::query();
            return DataTables::of($profiles)
                ->addColumn('action', function ($profile) {
                    return '
                        <button onclick="handleEdit('.$profile->id.')" class="text-blue-600 hover:text-blue-900">Edit</button>
                        <button onclick="navigateToConfig('.$profile->id.')" class="text-orange-600 hover:text-orange-900">Configure</button>
                        <button onclick="handleDelete('.$profile->id.')" class="fa fa trash-o"></button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    
        return response()->json(['message' => 'Invalid request'], 400);
    }

    /**
     * Store a new transition profile.
     */
    public function store(Request $request)
    {
        $request->validate([
            'profile_code' => 'required|unique:transition_profile_definitions,profile_code',
            'short_name' => 'required',
            'start_table' => 'required',
            'end_table' => 'required',
            'start_grading_col' => 'required',
            'end_grading_col' => 'required',
            'start_client_id_col' => 'required',
            'end_client_id_col' => 'required',
            'start_value_type' => 'required',
            'end_value_type' => 'required',
            'aggregation_criteria' => 'required',
        ]);

        TransitionProfileDefinition::create($request->all());

      return redirect()->route('transition-profiles.index')->with('success', 'Profile created successfully.');

    }


    public function create(Request $request)
    {
        $tables = Schema::getConnection()->getSchemaBuilder()->getAllTables();
        
        // Check if a profile ID is provided for editing
        $profile = null;
        if ($request->has('profile_id')) {
            $profile = TransitionProfileDefinition::find($request->input('profile_id'));
        }

        return inertia('TransitionProfileDefinitions/Create', [
            'tables' => $tables,
            'profile' => $profile,
        ]);
    }

    /**
     * Update an existing transition profile.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'profile_code' => 'required|unique:transition_profile_definitions,profile_code,' . $id,
            'short_name' => 'required',
            'start_table' => 'required',
            'end_table' => 'required',
            'start_grading_col' => 'required',
            'end_grading_col' => 'required',
            'start_client_id_col' => 'required',
            'end_client_id_col' => 'required',
            'start_value_type' => 'required',
            'end_value_type' => 'required',
             'aggregation' => 'null',
        ]);

        $profile = TransitionProfileDefinition::findOrFail($id);
        $profile->update($request->all());
        //return Inertia::location(route('transition-profiles.index'))->with('success', 'Profile updated successfully.');
        //return redirect()->route('transition-profiles.index')->with('success', 'Profile updated successfully.');
        return Inertia::render('TransitionProfiles/Index', ['success' => 'Profile updated successfully.']);

    }

    public function edit($id)
    {
        $profile = TransitionProfileDefinition::findOrFail($id);
        $tables = Schema::getConnection()->getSchemaBuilder()->getAllTables();
        return inertia('TransitionProfileDefinitions/Create', [
            'profile' => $profile,
            'tables' => $tables,
        ]);
    }


    /**
     * Delete a transition profile.
     */
    public function destroy($id)
    {
        $profile = TransitionProfileDefinition::find($id);

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $profile->delete();

        return response()->json(['message' => 'Profile deleted successfully'], 200);
    }

    /**
     * Fetch all available tables from the database schema.
     */
    public function getTables()
    {
        try {
            // Fetch all table names
            $tables = DB::select('SHOW TABLES');

            if (empty($tables)) {
                return response()->json(['message' => 'No tables found in the database'], 404);
            }

            // Extract table names from the result set
            $tableNames = array_map(fn($table) => array_values((array) $table)[0], $tables);

            return response()->json(['tables' => $tableNames]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Fetch columns of a specific table.
     */

     public function getColumns($table)
     {
         try {
             // Check if the table exists in the database
             if (!Schema::hasTable($table)) {
                 return response()->json(['error' => 'Table not found'], 404);
             }
 
             // Retrieve and return the columns of the specified table
             $columns = Schema::getColumnListing($table);
             return response()->json($columns);
         } catch (\Exception $e) {
             return response()->json(['error' => $e->getMessage()], 500);
         }
     }
    }
