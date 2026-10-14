<?php

namespace App\Http\Controllers;

use App\Support\Imports\ImportSpecs;
use App\Support\Imports\ImportSpecValidator;
use App\Support\Imports\ImportTemplateBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The downloads and the check of every file import, from the one registry
 * (App\Support\Imports\ImportSpecs, 13 October 2026):
 *
 *  - template: the sample file in exactly the format the import reads;
 *  - guide: the XLSX column guide (instructions, each column's type,
 *    required flag, format and allowed values, the sample rows);
 *  - spec: the same as JSON, for the import screens' instructions panel;
 *  - check: a chosen file tested against the spec, row by row. It writes
 *    nothing; the import itself runs the same check and refuses a file with
 *    any error.
 */
class ImportTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function template(string $key, ImportTemplateBuilder $builder): Response
    {
        abort_unless(ImportSpecs::has($key), 404);
        [$body, $name, $type] = $builder->template($key);

        return response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function guide(string $key, ImportTemplateBuilder $builder): Response
    {
        abort_unless(ImportSpecs::has($key), 404);
        $spec = ImportSpecs::get($key);

        return response($builder->guide($key), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . ImportTemplateBuilder::guideFileName($spec) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function spec(string $key): JsonResponse
    {
        abort_unless(ImportSpecs::has($key), 404);
        $s = ImportSpecs::forScreen($key);
        $s['template_url'] = $s['template'] === 'none' ? null : route('import-templates.download', $key);
        $s['guide_url'] = route('import-templates.guide', $key);
        $s['check_url'] = $s['checkable'] && ! in_array($key, ['takeon', 'ebanker-pack', 'sp-grades'], true) ? route('import-templates.check', $key) : null;

        return response()->json($s);
    }

    /** Test a file without writing anything. */
    public function check(Request $request, string $key): JsonResponse
    {
        abort_unless(ImportSpecs::has($key), 404);
        $spec = ImportSpecs::get($key);
        abort_if(! $spec['checkable'] || in_array($key, ['takeon', 'ebanker-pack', 'sp-grades'], true), 404);
        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'mapping' => ['nullable'],
            'period' => ['nullable', 'string', 'max:10'],
            'sheet' => ['nullable', 'string', 'max:100'],
        ]);
        $mapping = $request->input('mapping');
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?: [];
        }
        $file = $request->file('file');
        $check = (new ImportSpecValidator())->checkFile($key, $file->getRealPath(), $file->getClientOriginalName(), [
            'mapping' => is_array($mapping) ? $mapping : [],
            'field_aliases' => self::fieldAliases($key),
            'period' => $request->input('period'),
            'sheet' => $request->input('sheet'),
        ]);

        return response()->json($check);
    }

    /** The loan_books column names the Match columns screen offers, as the spec names them. */
    public static function fieldAliases(string $key): array
    {
        return match ($key) {
            'loan-book' => [
                'create_date' => 'value_date', 'due_date' => 'maturity_date', 'principal_balance' => 'principal', 'customer_name' => 'name',
                'disbursed_amount' => 'disbursed', 'loan_type' => 'type', 'product_group' => 'type', 'internal_grade_code' => 'internal_grade',
                'arrears_1_to_30' => '1-30 Days', 'arrears_30_to_90' => '31-90 Days', 'arrears_91_to_180' => '91-180 Days', 'arrears_180_to_270' => '181-270 Days',
            ],
            default => [],
        };
    }
}
