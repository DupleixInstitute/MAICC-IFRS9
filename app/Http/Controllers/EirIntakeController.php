<?php

namespace App\Http\Controllers;

use App\Models\ImportMapping;
use App\Services\AuditLoggerService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\ScheduleImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * EIR intake (docs/EIR_Build.md §4 Phase 2): upload a schedule or fee
 * file in whatever shape the client's system exports, map its columns
 * once, and import. The heavy lifting lives in MappedFileReader,
 * ScheduleImportService and FeeImportService — this controller stays thin
 * so the logic is testable without HTTP.
 */
class EirIntakeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index(ScheduleImportService $schedules)
    {
        return Inertia::render('Eir/Intake', [
            'coverage'  => $schedules->coverage(),
            'templates' => ImportMapping::orderBy('import_type')->orderBy('source_header')->get()
                ->groupBy('import_type'),
            'fieldSpec' => [
                'schedule' => [
                    'required' => MappedFileReader::REQUIRED_FIELDS['schedule'],
                    'optional' => MappedFileReader::OPTIONAL_FIELDS['schedule'],
                ],
                'fees' => [
                    'required' => MappedFileReader::REQUIRED_FIELDS['fees'],
                    'optional' => MappedFileReader::OPTIONAL_FIELDS['fees'],
                ],
            ],
        ]);
    }

    /**
     * Inspect an uploaded file: detected headers, preview rows, saved
     * template matches, missing required fields. JSON — feeds the mapping
     * screen.
     */
    public function analyze(Request $request, MappedFileReader $reader)
    {
        $request->validate([
            'file'        => ['required', 'file', 'mimes:csv,txt,xlsx,xls,ods', 'max:20480'],
            'import_type' => ['required', 'in:schedule,fees'],
        ]);

        try {
            return response()->json(
                $reader->analyze($request->file('file')->getPathname(), $request->input('import_type'))
            );
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Persist a header→field mapping as the reusable template for this
     * import type. Replaces previous mappings for the same headers.
     */
    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'import_type'             => ['required', 'in:schedule,fees'],
            'mappings'                => ['required', 'array', 'min:1'],
            'mappings.*.source_header' => ['required', 'string', 'max:255'],
            'mappings.*.target_field'  => ['required', 'string', 'max:255'],
            'mappings.*.transform'     => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data['mappings'] as $mapping) {
            ImportMapping::updateOrCreate(
                [
                    'import_type'   => $data['import_type'],
                    'source_header' => $mapping['source_header'],
                ],
                [
                    'target_field' => $mapping['target_field'],
                    'transform'    => $mapping['transform'] ?? null,
                ]
            );
        }

        return response()->json(['saved' => count($data['mappings'])]);
    }

    /**
     * Run the import: re-reads the uploaded file with the chosen mapping
     * and hands mapped rows to the type's import service. Per-contract
     * failures come back named; the file never fails wholesale.
     */
    public function import(
        Request $request,
        MappedFileReader $reader,
        ScheduleImportService $schedules,
        FeeImportService $fees
    ) {
        $request->validate([
            'file'        => ['required', 'file', 'mimes:csv,txt,xlsx,xls,ods', 'max:20480'],
            'import_type' => ['required', 'in:schedule,fees'],
            'mapping'     => ['required', 'json'],
            'transforms'  => ['nullable', 'json'],
        ]);

        $importType = $request->input('import_type');
        $mapping    = json_decode($request->input('mapping'), true) ?: [];
        $transforms = json_decode($request->input('transforms') ?? '{}', true) ?: [];

        try {
            $read = $reader->read(
                $request->file('file')->getPathname(),
                $importType,
                $mapping,
                $transforms
            );

            $result = $importType === 'schedule'
                ? $schedules->import($read['rows'])
                : $fees->import($read['rows']);

            AuditLoggerService::log(
                action: 'EIR Intake Import',
                entityType: 'ContractCashflowSchedule',
                entityId: 0,
                data: [
                    'meta' => [
                        'import_type' => $importType,
                        'file'        => $request->file('file')->getClientOriginalName(),
                        'result'      => $result,
                    ],
                ]
            );

            return response()->json([
                'result' => $result,
                'report' => $read['report'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
