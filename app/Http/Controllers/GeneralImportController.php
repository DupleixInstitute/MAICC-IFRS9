<?php

namespace App\Http\Controllers;

use App\Models\GeneralImportTemplate;
use App\Models\GeneralImportConfiguration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GeneralImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
       
        $templates = GeneralImportTemplate::with('configurations')
            ->where('active_status', 1)
            ->get();

        return Inertia::render('Imports/Index', [
            'templates' => $templates
        ]);
    }

    public function create()
    {
        $tables = Schema::getAllTables();
        
        return Inertia::render('Imports/Create', [
            'tables' => $tables
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        DB::transaction(function () use ($request) {
            $template = GeneralImportTemplate::create([
                'template_name' => $request->template_name,
                'template_description' => $request->template_description,
                'source_table_name' => $request->source_table_name['Tables_in_laravel_db'],
                'column_count' => count($request->configurations),
                'updated_by' => auth()->id()
            ]);

            foreach ($request->configurations as $config) {
                $template->configurations()->create([
                    'is_reporting_period' => $config['is_reporting_period'],
                    'is_portfolio_group_id' => $config['is_portfolio_group_id'],
                    'template_column_position' => $config['position'],
                    'column_description' => $config['description'],
                    'column_data_type' => $config['data_type'],
                    'minimum_value' => $config['minimum_value'],
                    'maximum_value' => $config['maximum_value'],
                    'updated_by' => auth()->id()
                ]);
            }
        });

        return redirect()->route('custom_imports.index');
    }

    public function processImport(Request $request, GeneralImportTemplate $template)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv',
            'reporting_period' => $template->configurations()
                ->where('is_reporting_period', true)
                ->exists() ? 'required|date' : 'nullable',
            'portfolio_group_id' => $template->configurations()
                ->where('is_portfolio_group_id', true)
                ->exists() ? 'required|exists:portfolio_groups,id' : 'nullable'
        ]);

        // Process the import file
        $importData = $this->parseImportFile($request->file('file'));
        
        // Validate data against configuration rules
        $this->validateImportData($importData, $template->configurations);
        
        // Perform the import
        $this->performImport($importData, $template, $request->reporting_period, $request->portfolio_group_id);

        return redirect()->back()->with('success', 'Import completed successfully');
    }

    public function downloadSample(GeneralImportTemplate $template)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Get configurations sorted by position
        $configurations = $template->configurations()
            ->orderBy('template_column_position')
            ->get();

        // Set headers
        foreach ($configurations as $index => $config) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $config->column_description);
        }

        // Create sample data row
        foreach ($configurations as $index => $config) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sampleValue = $this->getSampleValue($config->column_data_type);
            $sheet->setCellValue($column . '2', $sampleValue);
        }

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Create the Excel file
        $writer = new Xlsx($spreadsheet);
        $filename = 'sample_' . \Str::slug($template->template_name) . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    private function getSampleValue($dataType)
    {
        switch ($dataType) {
            case 'string':
                return 'Sample Text';
            case 'integer':
                return 123;
            case 'decimal':
                return 123.45;
            case 'date':
                return date('Y-m-d');
            case 'datetime':
                return date('Y-m-d H:i:s');
            case 'boolean':
                return true;
            default:
                return 'Sample';
        }
    }

    private function parseImportFile($file)
    {
        // Implementation for file parsing
    }

    private function validateImportData($data, $configurations)
    {
        // Implementation for data validation
    }

    private function performImport($data, $template, $reportingPeriod, $portfolioGroupId)
    {
        // Implementation for import process
    }
}   