<?php

namespace App\Http\Controllers;

use App\Models\GeneralImportTemplate;
use App\Models\GeneralImportConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GeneralImportConfigurationController extends Controller
{
    public function index($templateId)
    {
        dd($templateId);
        $template = GeneralImportTemplate::with(['configurations' => function ($query) {
            $query->orderBy('template_column_position');
        }])->findOrFail($templateId);

        $dataTypes = [
            'string' => 'Text',
            'integer' => 'Whole Number',
            'decimal' => 'Decimal Number',
            'date' => 'Date',
            'datetime' => 'Date and Time',
            'boolean' => 'Yes/No',
        ];

        return view('general-import.configurations.index', compact('template', 'dataTypes'));
    }

    public function store(Request $request, $templateId)
    {
        $template = GeneralImportTemplate::findOrFail($templateId);

        $validator = Validator::make($request->all(), [
            'template_column_position' => [
                'required',
                'integer',
                Rule::unique('general_import_configurations')
                    ->where('template_id', $templateId)
            ],
            'column_description' => 'required|string|max:255',
            'column_data_type' => 'required|string',
            'minimum_value' => 'nullable|numeric',
            'maximum_value' => 'nullable|numeric|gt:minimum_value',
            'is_reporting_period' => 'boolean',
            'is_portfolio_group_id' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $config = GeneralImportConfiguration::create([
                'template_id' => $templateId,
                'template_column_position' => $request->template_column_position,
                'column_description' => $request->column_description,
                'column_data_type' => $request->column_data_type,
                'minimum_value' => $request->minimum_value,
                'maximum_value' => $request->maximum_value,
                'is_reporting_period' => $request->is_reporting_period ?? false,
                'is_portfolio_group_id' => $request->is_portfolio_group_id ?? false,
                'updated_by' => auth()->id(),
            ]);

            $template->updateColumnCount();

            DB::commit();
            return response()->json($config, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add configuration'], 500);
        }
    }

    public function update(Request $request, $templateId, $configId)
    {
        $config = GeneralImportConfiguration::where('template_id', $templateId)
            ->findOrFail($configId);

        $validator = Validator::make($request->all(), [
            'template_column_position' => [
                'required',
                'integer',
                Rule::unique('general_import_configurations')
                    ->where('template_id', $templateId)
                    ->ignore($configId)
            ],
            'column_description' => 'required|string|max:255',
            'column_data_type' => 'required|string',
            'minimum_value' => 'nullable|numeric',
            'maximum_value' => 'nullable|numeric|gt:minimum_value',
            'is_reporting_period' => 'boolean',
            'is_portfolio_group_id' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $config->update([
                'template_column_position' => $request->template_column_position,
                'column_description' => $request->column_description,
                'column_data_type' => $request->column_data_type,
                'minimum_value' => $request->minimum_value,
                'maximum_value' => $request->maximum_value,
                'is_reporting_period' => $request->is_reporting_period ?? false,
                'is_portfolio_group_id' => $request->is_portfolio_group_id ?? false,
                'update_date' => now(),
                'updated_by' => auth()->id(),
            ]);

            DB::commit();
            return response()->json($config);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update configuration'], 500);
        }
    }

    public function delete($templateId, $configId)
    {
        $config = GeneralImportConfiguration::where('template_id', $templateId)
            ->findOrFail($configId);

        DB::beginTransaction();
        try {
            $config->delete();
            $config->template->updateColumnCount();

            DB::commit();
            return response()->json(['message' => 'Configuration deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete configuration'], 500);
        }
    }
}
