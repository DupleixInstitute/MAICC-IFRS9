<?php

namespace App\Http\Controllers;

use App\Models\MacroObservation;
use App\Models\MacroVariable;
use App\Services\Macro\ImfWeoParserService;
use App\Services\Macro\WorldBankFetcherService;
use App\Support\CsvTemplateBuilder;
use App\Support\MacroPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MacroController extends Controller
{
    public function index(): Response
    {
        $variables = MacroVariable::query()
            ->with(['latestObservation'])
            ->orderBy('sort_order')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // For each variable, load its last 12 observations (for sparklines / history)
        $observations = MacroObservation::query()
            ->whereIn('macro_variable_id', $variables->pluck('id'))
            ->orderBy('period_date')
            ->get()
            ->groupBy('macro_variable_id');

        $categories = $variables->pluck('category')->unique()->sort()->values();

        return Inertia::render('Macro/Index', [
            'variables'    => $variables,
            'observations' => $observations,
            'categories'   => $categories,
            'frequencies'  => $this->frequencies(),
            'shockUnits'   => $this->shockUnits(),
            // Table 9 - ICAAP Macro Assumptions (governed variables + base/mild/severe
            // paths + approval lineage). Same payload the export serialises (UI==export).
            'table9'       => app(\App\Services\Macro\MacroScenarioReconciliationService::class)->assumptionsTable(),
            // Viewing needs `view macro`; every create / edit / toggle / import path
            // needs `manage macro`. Share it so a view-only user is not offered
            // controls that 403 on click.
            'can'          => ['manage' => (bool) auth()->user()?->can('manage macro')],
        ]);
    }

    /**
     * Export Table 9 (ICAAP Macro Assumptions) as CSV - the SAME payload the page
     * renders, so the export and the on-screen table are identical.
     */
    public function exportAssumptions(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $table = app(\App\Services\Macro\MacroScenarioReconciliationService::class)->assumptionsTable();

        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $table['columns']);
            foreach ($table['rows'] as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c] ?? '', $table['columns']));
            }
            fclose($out);
        }, 'icaap-table9-macro-assumptions.csv', ['Content-Type' => 'text/csv']);
    }

    // ── Variables ─────────────────────────────────────────────────────────────

    public function storeVariable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'                 => ['required', 'string', 'max:60', 'unique:macro_variables,code', 'regex:/^[A-Z0-9_]+$/'],
            'name'                 => ['required', 'string', 'max:255'],
            'category'             => ['required', 'string', 'max:80'],
            'unit'                 => ['required', 'string', 'max:60'],
            'frequency'            => ['required', 'in:monthly,quarterly,annual'],
            'source'               => ['nullable', 'string', 'max:120'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'shock_direction'      => ['required', 'in:up,down,both'],
            'default_mild_shock'   => ['nullable', 'numeric'],
            'default_severe_shock' => ['nullable', 'numeric'],
            'shock_unit'           => ['required', 'in:pp,pct,abs'],
            'sort_order'           => ['integer', 'min:0'],
            'wb_code'              => ['nullable', 'string', 'max:60'],
            'imf_code'             => ['nullable', 'string', 'max:60'],
        ]);

        $externalCodes = array_filter([
            'world_bank' => $validated['wb_code']  ?? null,
            'imf_weo'    => $validated['imf_code'] ?? null,
        ]);

        $variable = MacroVariable::create([
            ...$validated,
            'code'           => strtoupper(trim($validated['code'])),
            'is_active'      => true,
            'sort_order'     => $validated['sort_order'] ?? 0,
            'external_codes' => $externalCodes ?: null,
        ]);
        \App\Services\ActivityLogger::governance($variable, 'created', 'Macro', "Macro variable {$variable->code} created", [], $validated);

        return redirect()->route('macro.index')->with('success', 'Variable added.');
    }

    public function updateVariable(Request $request, MacroVariable $variable): RedirectResponse
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'category'             => ['required', 'string', 'max:80'],
            'unit'                 => ['required', 'string', 'max:60'],
            'frequency'            => ['required', 'in:monthly,quarterly,annual'],
            'source'               => ['nullable', 'string', 'max:120'],
            'description'          => ['nullable', 'string', 'max:2000'],
            'shock_direction'      => ['required', 'in:up,down,both'],
            'default_mild_shock'   => ['nullable', 'numeric'],
            'default_severe_shock' => ['nullable', 'numeric'],
            'shock_unit'           => ['required', 'in:pp,pct,abs'],
            'sort_order'           => ['integer', 'min:0'],
            'wb_code'              => ['nullable', 'string', 'max:60'],
            'imf_code'             => ['nullable', 'string', 'max:60'],
        ]);

        $externalCodes = array_filter([
            'world_bank' => $validated['wb_code']  ?? null,
            'imf_weo'    => $validated['imf_code'] ?? null,
        ]);
        unset($validated['wb_code'], $validated['imf_code']);

        $before = $variable->only(array_keys($validated));
        $variable->update([
            ...$validated,
            'external_codes' => $externalCodes ?: null,
        ]);
        \App\Services\ActivityLogger::governance($variable, 'updated', 'Macro', "Macro variable {$variable->code} updated", $before, $validated);

        return redirect()->route('macro.index')->with('success', 'Variable updated.');
    }

    // ── View modal — return one variable + its observations as JSON ───────────

    public function showVariable(MacroVariable $variable): JsonResponse
    {
        $observations = MacroObservation::where('macro_variable_id', $variable->id)
            ->orderBy('period_date')
            ->get(['id', 'period_date', 'period_label', 'period_type', 'value', 'value_stressed_mild', 'value_stressed_severe', 'source', 'value_type', 'notes']);

        return response()->json([
            'variable'     => $variable,
            'observations' => $observations,
            'summary'      => [
                'count'         => $observations->count(),
                'actual_count'  => $observations->where('value_type', 'actual')->count(),
                'forecast_count'=> $observations->where('value_type', 'forecast')->count(),
                'first_period'  => optional($observations->first())->period_label,
                'last_period'   => optional($observations->last())->period_label,
                'min_value'     => $observations->min('value'),
                'max_value'     => $observations->max('value'),
            ],
        ]);
    }

    // ── External-source import: preview + commit ─────────────────────────────

    /**
     * Optional country (ISO3) + year range for a World Bank / IMF import. Omitted
     * values fall through to the service defaults (ZMB, full history), so the
     * existing behaviour is unchanged.
     *
     * @return array{country: ?string, yearFrom: ?int, yearTo: ?int}
     */
    private function externalImportParams(Request $request): array
    {
        $v = $request->validate([
            'country'   => ['nullable', 'string', 'regex:/^[A-Za-z]{2,3}$/'],
            'year_from' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'year_to'   => ['nullable', 'integer', 'min:1950', 'max:2100'],
        ]);

        return [
            'country'  => isset($v['country']) ? strtoupper($v['country']) : null,
            'yearFrom' => isset($v['year_from']) ? (int) $v['year_from'] : null,
            'yearTo'   => isset($v['year_to']) ? (int) $v['year_to'] : null,
        ];
    }

    public function previewWorldBank(Request $request, MacroVariable $variable, WorldBankFetcherService $fetcher): JsonResponse
    {
        try {
            $p = $this->externalImportParams($request);
            return response()->json($fetcher->fetch($variable, $p['country'], $p['yearFrom'], $p['yearTo']));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function commitWorldBank(Request $request, MacroVariable $variable, WorldBankFetcherService $fetcher): RedirectResponse
    {
        try {
            $p = $this->externalImportParams($request);
            $payload = $fetcher->fetch($variable, $p['country'], $p['yearFrom'], $p['yearTo']);
            $written = $this->writePreviewedRows($variable, $payload['rows'], $payload['source']);
            return redirect()->route('macro.index')->with('success',
                "World Bank import ({$payload['country']}): {$variable->code} - {$written['added']} added, {$written['updated']} updated"
                . ($written['rejected'] ? ", {$written['rejected']} rejected (see macro import log)" : '') . '.'
            );
        } catch (\Throwable $e) {
            report($e); // log the technical detail; never show it to the user
            \App\Support\ActionResult::flashImport([
                'status'      => \App\Support\ActionResult::FAILED,
                'module'      => 'Macro data',
                'import_type' => 'World Bank macro series',
                'message'     => 'The World Bank series could not be imported. Please check the selected country/indicator and try again. The technical detail has been logged for the team.',
            ]);
            return redirect()->route('macro.index');
        }
    }

    public function previewImfWeo(Request $request, MacroVariable $variable, ImfWeoParserService $parser): JsonResponse
    {
        $request->validate(['weo_file' => ['required', 'file', 'mimetypes:text/plain,text/tab-separated-values,application/vnd.ms-excel,text/csv', 'max:51200']]);
        try {
            $p = $this->externalImportParams($request);
            $path = $request->file('weo_file')->getRealPath();
            return response()->json($parser->parse($path, $variable, $p['country'], $p['yearFrom'], $p['yearTo']));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function commitImfWeo(Request $request, MacroVariable $variable, ImfWeoParserService $parser): RedirectResponse
    {
        $request->validate(['weo_file' => ['required', 'file', 'mimetypes:text/plain,text/tab-separated-values,application/vnd.ms-excel,text/csv', 'max:51200']]);
        try {
            $p = $this->externalImportParams($request);
            $path = $request->file('weo_file')->getRealPath();
            $payload = $parser->parse($path, $variable, $p['country'], $p['yearFrom'], $p['yearTo']);
            $written = $this->writePreviewedRows($variable, $payload['rows'], $payload['source']);
            return redirect()->route('macro.index')->with('success',
                "IMF WEO import ({$payload['country']}): {$variable->code} - {$written['added']} added, {$written['updated']} updated "
                . "({$written['actual']} historical + {$written['forecast']} forecast)"
                . ($written['rejected'] ? ", {$written['rejected']} rejected (see macro import log)" : '') . '.'
            );
        } catch (\Throwable $e) {
            report($e); // log the technical detail; never show it to the user
            \App\Support\ActionResult::flashImport([
                'status'      => \App\Support\ActionResult::FAILED,
                'module'      => 'Macro data',
                'import_type' => 'IMF WEO macro series',
                'message'     => 'The IMF WEO file could not be imported. Please check the file format and the selected indicator, then re-upload. The technical detail has been logged for the team.',
            ]);
            return redirect()->route('macro.index');
        }
    }

    /**
     * @param  list<array{period_date:string,period_label:string,period_type:string,value:float,value_type:string}>  $rows
     * @return array{added:int,updated:int,actual:int,forecast:int}
     */
    private function writePreviewedRows(MacroVariable $variable, array $rows, string $source): array
    {
        $added = $updated = $actual = $forecast = 0;
        $userId = Auth::id();

        // ── Row-level validation BEFORE upsert (parity with the CSV importer) ──
        // period_date parseable, value numeric/finite, value_type + period_type
        // in the governed enums, variable mapping present. Bad rows are rejected
        // and recorded in the import log - never silently upserted.
        $valid  = [];
        $errors = [];
        foreach ($rows as $i => $row) {
            $rowNum = $i + 1;

            $period = trim((string) ($row['period_date'] ?? ''));
            $date   = null;
            if ($period !== '') {
                try { $date = \Illuminate\Support\Carbon::parse($period)->toDateString(); } catch (\Throwable) {}
            }
            if ($date === null) {
                $errors[] = ['row' => $rowNum, 'reason' => "period_date '{$period}' is missing or not a valid date"];
                continue;
            }

            $value = $row['value'] ?? null;
            if ($value === null || $value === '' || ! is_numeric($value) || ! is_finite((float) $value)) {
                $shown = is_scalar($value) ? (string) $value : '';
                $errors[] = ['row' => $rowNum, 'reason' => "value '{$shown}' is not numeric"];
                continue;
            }

            $valueType = strtolower(trim((string) ($row['value_type'] ?? '')));
            if (! in_array($valueType, \App\Services\Macro\MacroImportService::VALUE_TYPES, true)) {
                $errors[] = ['row' => $rowNum, 'reason' => "invalid value_type '{$valueType}' (actual/estimate/forecast)"];
                continue;
            }

            $periodType = strtolower(trim((string) ($row['period_type'] ?? '')));
            if (! in_array($periodType, \App\Services\Macro\MacroImportService::PERIOD_TYPES, true)) {
                $errors[] = ['row' => $rowNum, 'reason' => "invalid period_type '{$periodType}' (monthly/quarterly/annual)"];
                continue;
            }

            if (! $variable->exists) {
                $errors[] = ['row' => $rowNum, 'reason' => 'macro variable mapping is missing'];
                continue;
            }

            $valid[] = [
                'period_date'  => $date,
                // Derived server-side (never the raw external label) so every
                // ingestion path produces the same label for the same period.
                'period_label' => MacroPeriod::deriveLabel($date, $periodType),
                'period_type'  => $periodType,
                'value'        => (float) $value,
                'value_type'   => $valueType,
            ];
        }

        DB::transaction(function () use ($variable, $valid, $source, $userId, &$added, &$updated, &$actual, &$forecast): void {
            foreach ($valid as $row) {
                $obs = MacroObservation::where('macro_variable_id', $variable->id)
                    ->where('period_date', $row['period_date'])
                    ->where('period_type', $row['period_type'])
                    ->first();

                $payload = [
                    'macro_variable_id' => $variable->id,
                    'period_date'       => $row['period_date'],
                    'period_label'      => $row['period_label'],
                    'period_type'       => $row['period_type'],
                    'value'             => $row['value'],
                    'value_type'        => $row['value_type'],
                    'source'            => $source,
                    'created_by'        => $userId,
                ];

                if ($obs) { $obs->update($payload); $updated++; }
                else      { MacroObservation::create($payload); $added++; }

                if ($row['value_type'] === 'forecast') $forecast++; else $actual++;
            }
        });

        // Log + audit the external import (World Bank / IMF) like the CSV path,
        // now carrying the rejected count + the row-level errors (never lost).
        $srcType = stripos($source, 'imf') !== false ? 'imf' : (stripos($source, 'world') !== false ? 'worldbank' : 'manual');
        $log = \App\Models\MacroImportLog::create([
            'batch_ref'    => 'MAC-' . now()->format('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
            'source'       => $srcType,
            'import_mode'  => 'import',
            'filename'     => $source,
            'total_rows'   => count($rows),
            'valid_rows'   => count($valid),
            'created_rows' => $added,
            'updated_rows' => $updated,
            'rejected_rows' => count($errors),
            'errors'       => $errors ?: null,
            'error_summary' => $errors ? implode(' | ', array_map(fn ($e) => "row {$e['row']}: {$e['reason']}", array_slice($errors, 0, 8))) : null,
            'status'       => $valid === [] && $errors !== [] ? 'failed' : 'completed',
            'imported_by'  => $userId,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);
        \App\Services\ActivityLogger::log('macro_external_import', $log, [
            'module'     => 'Macro',
            'notes'      => "{$srcType} commit for {$variable->code}: {$added} added, {$updated} updated, " . count($errors) . " rejected ({$actual} actual / {$forecast} forecast).",
            'properties' => ['variable' => $variable->code, 'source' => $source, 'added' => $added, 'updated' => $updated, 'rejected' => count($errors)],
        ]);

        return compact('added', 'updated', 'actual', 'forecast') + ['rejected' => count($errors)];
    }

    public function toggleVariable(MacroVariable $variable): RedirectResponse
    {
        $variable->update(['is_active' => ! $variable->is_active]);
        $state = $variable->is_active ? 'activated' : 'deactivated';
        \App\Services\ActivityLogger::governance($variable, $state, 'Macro', "Variable {$variable->code} {$state}.");
        return redirect()->route('macro.index')->with('success', "Variable {$variable->code} {$state}.");
    }

    /**
     * Toggle whether a macro indicator is in the ICAAP macro "house view"
     * (icaap_selected) - the governed set whose latest imported values drive the
     * ICAAP macro tables. Distinct from is_active (whether the variable is tracked
     * at all): a variable can be actively imported yet not reported in the ICAAP.
     * The set changes each cycle, so it is user-selectable here.
     */
    public function toggleIcaapSelected(MacroVariable $variable): RedirectResponse
    {
        $variable->update(['icaap_selected' => ! $variable->icaap_selected]);
        $state = $variable->icaap_selected ? 'added to' : 'removed from';
        \App\Services\ActivityLogger::governance($variable, 'icaap_macro_' . ($variable->icaap_selected ? 'selected' : 'deselected'), 'Macro', "Variable {$variable->code} {$state} the ICAAP macro house-view.");
        return redirect()->route('macro.index')->with('success', "Variable {$variable->code} {$state} the ICAAP macro house-view.");
    }

    /**
     * Delete a macro variable (its mild/severe shock definition). BLOCKED when an
     * APPROVED scenario uses it as a shock - a governed macro shock cannot be pulled
     * out from under an approved scenario. Draft scenario shocks that reference it
     * have their link nulled by the FK (nullOnDelete); the variable's observations
     * are removed explicitly. Audited.
     */
    public function destroyVariable(MacroVariable $variable): RedirectResponse
    {
        $approved = \Illuminate\Support\Facades\DB::table('scenario_shocks')
            ->join('scenarios', 'scenarios.id', '=', 'scenario_shocks.scenario_id')
            ->where('scenario_shocks.macro_variable_id', $variable->id)
            ->where('scenarios.status', 'approved')
            ->distinct()
            ->count('scenarios.id');
        if ($approved > 0) {
            return redirect()->route('macro.index')->with('error',
                "Cannot delete \"{$variable->name}\" ({$variable->code}): it is used as a shock by {$approved} approved scenario(s). Remove it from those scenarios first.");
        }

        $code = $variable->code;
        $name = $variable->name;
        $obs  = (int) \Illuminate\Support\Facades\DB::table('macro_observations')->where('macro_variable_id', $variable->id)->count();
        $draftShocks = (int) \Illuminate\Support\Facades\DB::table('scenario_shocks')->where('macro_variable_id', $variable->id)->count();
        \Illuminate\Support\Facades\DB::table('macro_observations')->where('macro_variable_id', $variable->id)->delete();
        $variable->delete();

        \App\Services\ActivityLogger::governance($variable, 'deleted', 'Macro',
            "Variable {$code} deleted ({$obs} observation(s) removed" . ($draftShocks > 0 ? ", {$draftShocks} draft scenario shock(s) unlinked" : '') . ').');

        return redirect()->route('macro.index')->with('success',
            "Macro variable \"{$name}\" deleted." . ($draftShocks > 0 ? " It was unlinked from {$draftShocks} draft scenario shock(s)." : ''));
    }

    // ── Observations ──────────────────────────────────────────────────────────

    public function storeObservation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'macro_variable_id'     => ['required', 'integer', 'exists:macro_variables,id'],
            'period_date'           => ['required', 'date'],
            // period_label is DERIVED server-side from period_date + frequency
            // (period_type); any client value is only a preview and is ignored.
            'period_label'          => ['nullable', 'string', 'max:20'],
            'period_type'           => ['required', Rule::in(MacroPeriod::FREQUENCIES)],
            'value'                 => ['required', 'numeric'],
            'value_type'            => ['nullable', Rule::in(MacroPeriod::VALUE_TYPES)],
            'value_stressed_mild'   => ['nullable', 'numeric'],
            'value_stressed_severe' => ['nullable', 'numeric'],
            'source'                => ['nullable', 'string', 'max:120'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ]);

        // value_type governance: a future/projection period must be Estimate or
        // Forecast - never 'actual', never a silent default.
        $isFuture       = \Carbon\Carbon::parse($validated['period_date'])->gt(now());
        $valueType      = $validated['value_type'] ?? null;
        $blankDefaulted = false;

        if ($isFuture) {
            if (empty($valueType)) {
                return redirect()->route('macro.index')->with('error',
                    "The future period {$validated['period_date']} must be marked Estimate or Forecast (a projection cannot be Actual or left blank).");
            }
            if (! MacroPeriod::isProjection($valueType)) {
                return redirect()->route('macro.index')->with('error',
                    "The future period {$validated['period_date']} cannot be saved as Actual - mark it Estimate or Forecast.");
            }
        } elseif (empty($valueType)) {
            // Past/present blank -> default to actual, recorded as a warning below.
            $valueType      = 'actual';
            $blankDefaulted = true;
        }
        $validated['value_type'] = $valueType;

        // Single source of truth for the label: derived from date + frequency.
        $derivedLabel = MacroPeriod::deriveLabel($validated['period_date'], $validated['period_type']);
        $validated['period_label'] = $derivedLabel;

        $obs = MacroObservation::updateOrCreate(
            [
                'macro_variable_id' => $validated['macro_variable_id'],
                'period_date'       => $validated['period_date'],
                'period_type'       => $validated['period_type'],
            ],
            [
                ...$validated,
                'created_by' => Auth::id(),
            ]
        );

        \App\Services\ActivityLogger::log($obs->wasRecentlyCreated ? 'created' : 'updated', $obs, [
            'module' => 'Macro',
            'notes'  => "Manual observation {$derivedLabel} - {$valueType} ({$validated['period_type']})"
                . ($blankDefaulted ? ' [value_type blank -> defaulted to actual]' : '')
                . (! empty($validated['source']) ? " from {$validated['source']}" : '') . '.',
            'properties' => [
                'value_type'   => $valueType,
                'period_type'  => $validated['period_type'],
                'period_label' => $derivedLabel,
                'source'       => $validated['source'] ?? null,
                'defaulted'    => $blankDefaulted,
            ],
        ]);

        return redirect()->route('macro.index')->with('success', "Observation {$derivedLabel} saved ({$valueType}).");
    }

    public function destroyObservation(MacroObservation $observation): RedirectResponse
    {
        \App\Services\ActivityLogger::log('deleted', $observation, [
            'module' => 'Macro',
            'notes'  => "Observation {$observation->period_label} deleted.",
        ]);
        $observation->delete();
        return redirect()->route('macro.index')->with('success', 'Observation deleted.');
    }

    public function importObservations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'mode'     => ['nullable', 'in:validate_only,import,replace,append,partial'],
        ]);

        $file = $request->file('csv_file');
        $mode = $validated['mode'] ?? 'import';

        // Validate the WHOLE file first, log the outcome, and only commit valid
        // rows in a transaction (never row-by-row; never silently default a
        // projection to actual). See MacroImportService.
        $result  = app(\App\Services\Macro\MacroImportService::class)
            ->process($file->getPathname(), $file->getClientOriginalName(), $mode, Auth::id());
        $log     = $result['log'];
        $preview = $result['preview'];

        // Persist the full validation preview for the page (errors never lost).
        session()->flash('macro_import_preview', [
            'log_id'     => $log->id,
            'batch_ref'  => $log->batch_ref,
            'mode'       => $mode,
            'status'     => $log->status,
            'saved'      => $result['saved'],
            'blocked'    => $result['blocked'],
            'total_rows' => $preview['total_rows'],
            'valid_rows' => $preview['valid_rows'],
            'counts'     => $preview['counts'] ?? [],
            'issues'     => array_slice($preview['issues'] ?? [], 0, 200),
        ]);

        if ($result['blocked']) {
            return redirect()->route('macro.index')->with(
                'error',
                "Import blocked: {$log->rejected_rows} row(s) have errors - nothing was saved. Fix the file, or choose Partial import to save only the valid rows.",
            );
        }
        if ($mode === 'validate_only') {
            return redirect()->route('macro.index')->with(
                'success',
                "Validation only: {$preview['valid_rows']} of {$preview['total_rows']} rows valid. Nothing was saved.",
            );
        }

        return redirect()->route('macro.index')->with(
            'success',
            "Import {$log->status}: {$log->created_rows} added, {$log->updated_rows} updated, {$log->rejected_rows} rejected.",
        );
    }

    // ── Exports ───────────────────────────────────────────────────────────────

    public function exportTemplate()
    {
        $cols = \App\Services\Macro\MacroImportService::TEMPLATE_COLUMNS;

        // ONE clearly-illustrative example row. variable_code 'EXAMPLE_CODE' does
        // not exist, so if the template is imported unchanged the importer REJECTS
        // it (unknown variable_code) - it can never become approved production data.
        // value_type must be actual/estimate/forecast; a future/projection period
        // must NOT be left blank (it would be blocked, never defaulted to actual).
        return CsvTemplateBuilder::download('macro_import_template.csv', $cols, [[
            'variable_code' => 'EXAMPLE_CODE',
            'period_date' => '2024-01-01',
            'period_label' => 'EXAMPLE - replace this row',
            'period_type' => 'quarterly (monthly/quarterly/annual)',
            'value' => '3.2',
            'value_type' => 'actual (actual/estimate/forecast)',
            'value_stressed_mild' => '2.0',
            'value_stressed_severe' => '-1.5',
            'source' => 'EXAMPLE ONLY - delete before importing real data',
        ]]);
    }

    public function exportData()
    {
        $obs = MacroObservation::query()
            ->with('variable')
            ->orderBy('macro_variable_id')
            ->orderBy('period_date')
            ->get();

        // value_type and approval_status are included so an export round-trips
        // without losing the actual/estimate/forecast distinction or the governance
        // state (the importer keys on value_type; dropping it silently degraded a
        // forecast to an actual on re-import).
        $cols = ['variable_code', 'variable_name', 'category', 'unit', 'period_date', 'period_label', 'period_type', 'value', 'value_type', 'value_stressed_mild', 'value_stressed_severe', 'approval_status', 'source'];

        return response()->streamDownload(function () use ($obs, $cols): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $cols);
            foreach ($obs as $o) {
                fputcsv($out, [
                    $o->variable?->code,
                    $o->variable?->name,
                    $o->variable?->category,
                    $o->variable?->unit,
                    $o->period_date?->toDateString(),
                    $o->period_label,
                    $o->period_type,
                    $o->value,
                    $o->value_type,
                    $o->value_stressed_mild,
                    $o->value_stressed_severe,
                    $o->approval_status,
                    $o->source,
                ]);
            }
            fclose($out);
        }, 'macro_data_' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function frequencies(): array
    {
        return ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
    }

    private function shockUnits(): array
    {
        return ['pp' => 'Percentage points (pp)', 'pct' => 'Percent change (%)', 'abs' => 'Absolute value'];
    }
}
