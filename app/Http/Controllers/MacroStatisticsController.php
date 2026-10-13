<?php

namespace App\Http\Controllers;

use App\Services\Macro\ImfWeoParserService;
use App\Services\Macro\MacroImportService;
use App\Services\Macro\WorldBankFetcherService;
use App\Services\AuditLoggerService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Data Foundation, Macro Statistics (spec v4 section 13): five tabs
 * (Dashboard, Variables, Data Entry, Scenario Assumptions, Import / Export).
 * Every source has a preview the viewer may call and a commit for the
 * manager only; nothing reaches the table until a person has seen the
 * rows. Every commit is a batch with its provenance.
 */
class MacroStatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // macro.view and macro.manage are the screen's own permissions (spec v4 section 13;
        // system audit of 9 October 2026, finding M16); the EIR pair still opens the door
        // for a user who holds it, so nobody is locked out by the rename
        $this->middleware('permission:macro.view|eir.view')->only(['index', 'preview', 'export']);
        $this->middleware('permission:macro.manage|eir.govern')->only(['commit', 'manual', 'variableStore', 'variableUpdate', 'variableDestroy', 'observationUpdate', 'observationDestroy', 'csvPreview', 'csvCommit', 'assumptionSave', 'assumptionWeights', 'assumptionVersion']);
    }

    public function index(Request $request)
    {
        $series = DB::table('macro_statistics')->orderBy('statistic_code')->get()->map(function ($s) {
            $agg = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $s->id)->selectRaw('count(*) n, min(period) first, max(period) last, sum(is_forecast) forecasts')->first();
            $batch = DB::table('macro_statistics_data as d')->join('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')->where('d.macro_stat_definition_id', $s->id)->orderByDesc('b.id')->first(['b.source', 'b.fetched_at', 'b.address']);

            return ['id' => $s->id, 'code' => $s->statistic_code, 'name' => $s->statistic_name, 'unit' => $s->unit, 'frequency' => $s->frequency, 'source' => $s->data_source, 'codes' => json_decode($s->external_codes ?? '', true) ?: [], 'country' => $s->country ?? 'MWI',
                'description' => $s->statistic_description, 'website' => $s->website_link,
                'n' => (int) $agg->n, 'first' => $agg->first, 'last' => $agg->last, 'forecasts' => (int) $agg->forecasts, 'last_batch' => $batch ? ['source' => $batch->source, 'fetched_at' => $batch->fetched_at, 'address' => $batch->address] : null, 'why' => $s->statistic_description];
        });
        $selected = (int) $request->query('series', $series->first()['id'] ?? 0);
        $observations = $selected ? DB::table('macro_statistics_data as d')->leftJoin('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')->where('d.macro_stat_definition_id', $selected)->orderBy('d.period')
            ->get(['d.id', 'd.period', 'd.value', 'd.is_forecast', 'd.source', 'd.notes', 'd.published_at', 'b.source as batch_source', 'b.fetched_at']) : collect();
        $batches = DB::table('macro_source_import_batches as b')->leftJoin('users as u', 'u.id', '=', 'b.committed_by')->orderByDesc('b.id')->limit(30)->get(['b.*', 'u.name as committed_by_name']);
        $sets = DB::table('governed_scenario_sets')->orderByDesc('reporting_period')->orderByDesc('version')->limit(5)->get(['id', 'reporting_period', 'version', 'name', 'status']);

        return Inertia::render('Macro/Index', [
            'series' => $series, 'selected' => $selected, 'observations' => $observations, 'batches' => $batches, 'sets' => $sets,
            'assumptions' => $this->assumptions((string) $request->query('aperiod', '')),
            'canManage' => (bool) (auth()->user()?->canAny(['macro.manage', 'eir.govern']) ?? false), 'defaultCountry' => config('services.worldbank.country', 'MWI'),
            // Real counts for the section row's chips (config/menu.php, Macro Statistics).
            'tabCounts' => ['dashboard' => $series->count(), 'variables' => $series->count(), 'entry' => $observations ? count($observations) : 0, 'scenarios' => $sets->count(), 'import' => $batches->count()],
        ]);
    }

    /** A preview from a source: World Bank (live), the IMF WEO file, or the RBM policy-rate file. Writes nothing. */
    public function preview(Request $request, WorldBankFetcherService $wb, ImfWeoParserService $imf)
    {
        $data = $request->validate(['source' => ['required', 'in:world_bank,imf_weo,rbm_file'], 'series_code' => ['nullable', 'string'], 'country' => ['nullable', 'string', 'max:3'], 'from' => ['nullable', 'integer'], 'to' => ['nullable', 'integer'], 'file' => ['nullable', 'file']]);
        try {
            if ($data['source'] === 'rbm_file') {
                $request->validate(['file' => ['required', 'file']]);
                if ($refused = $this->specRefusal('rbm-policy-rate', $request)) {
                    return $refused;
                }

                return response()->json($this->rbmPreview($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName()));
            }
            $series = DB::table('macro_statistics')->where('statistic_code', $data['series_code'] ?? '')->first();
            if ($series === null) {
                return response()->json(['error' => 'Choose a series.'], 422);
            }
            if ($data['source'] === 'world_bank') {
                return response()->json($wb->fetch($series, $data['country'] ?? null, $data['from'] ?? null, $data['to'] ?? null));
            }
            $request->validate(['file' => ['required', 'file']]);
            if ($refused = $this->specRefusal('imf-weo', $request)) {
                return $refused;
            }

            return response()->json($imf->parse($request->file('file')->getRealPath(), $series, $data['country'] ?? null, $data['from'] ?? null, $data['to'] ?? null));
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Commit a preview the person has seen, as a batch. */
    public function commit(Request $request, MacroImportService $importer)
    {
        $data = $request->validate(['preview' => ['required', 'array'], 'source' => ['required', 'in:world_bank,imf_weo,rbm_file,manual'], 'note' => ['nullable', 'string', 'max:255']]);
        try {
            $r = $importer->commit($data['preview'], $request->user()->id, $data['source'], $data['preview']['file_sha256'] ?? null, $data['note'] ?? null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Batch {$r['batch_id']}: {$r['rows']} rows, {$r['new']} new, {$r['updated']} updated, {$r['unchanged']} unchanged.");
    }

    /** A manual entry overrides a source only with a reason, shown on the row and in the audit log (13.5). */
    public function manual(Request $request, MacroImportService $importer)
    {
        $data = $request->validate(['series_code' => ['required', 'string'], 'period' => ['required', 'date'], 'value' => ['required', 'numeric'], 'is_forecast' => ['nullable', 'boolean'], 'published_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'max:255']]);
        $preview = ['series_code' => $data['series_code'], 'indicator_code' => null, 'country' => 'MWI', 'source' => 'Manual entry: ' . $data['reason'], 'address' => null, 'fetched_at' => now()->toDateTimeString(),
            'rows' => [['period' => $data['period'], 'year' => (int) substr($data['period'], 0, 4), 'value' => (float) $data['value'], 'value_type' => ! empty($data['is_forecast']) ? 'forecast' : 'actual']]];
        try {
            $r = $importer->commit($preview, $request->user()->id, 'manual', null, $data['reason']);
            if (! empty($data['published_at'])) {
                DB::table('macro_statistics_data')->where('source_import_batch_id', $r['batch_id'])->update(['published_at' => $data['published_at']]);
            }
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Manual entry recorded as batch {$r['batch_id']} with its reason.");
    }

    /** The CSV template and the export of every observation. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['series_code', 'period', 'value', 'is_forecast', 'source', 'published_at']);
            foreach (DB::table('macro_statistics_data as d')->join('macro_statistics as s', 's.id', '=', 'd.macro_stat_definition_id')->orderBy('s.statistic_code')->orderBy('d.period')->get(['s.statistic_code', 'd.period', 'd.value', 'd.is_forecast', 'd.source', 'd.published_at']) as $r) {
                fputcsv($out, [$r->statistic_code, $r->period, $r->value, $r->is_forecast, $r->source, $r->published_at]);
            }
            fclose($out);
        }, 'macro statistics ' . now()->toDateString() . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** The RBM policy-rate file: a CSV of date,rate (monthly), as the Reserve Bank publishes the MPC decisions. */
    // ------------------------------------------------------------------ variables

    private function variableRules(?int $id = null): array
    {
        return [
            'statistic_code' => ['required', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:macro_statistics,statistic_code' . ($id ? ',' . $id : '')],
            'statistic_name' => ['required', 'string', 'max:199'],
            'unit' => ['nullable', 'string', 'max:60'],
            'frequency' => ['required', 'in:monthly,quarterly,yearly'],
            'data_source' => ['nullable', 'string', 'max:199'],
            'world_bank' => ['nullable', 'string', 'max:60'],
            'imf_weo' => ['nullable', 'string', 'max:60'],
            'other_code' => ['nullable', 'string', 'max:60'],
            'statistic_description' => ['nullable', 'string', 'max:199'],
            'website_link' => ['nullable', 'string', 'max:199'],
        ];
    }

    private function variableRow(array $d): array
    {
        $codes = array_filter(['world_bank' => $d['world_bank'] ?? null, 'imf_weo' => $d['imf_weo'] ?? null, 'rbm' => $d['other_code'] ?? null]);

        return ['statistic_code' => $d['statistic_code'], 'statistic_name' => $d['statistic_name'], 'unit' => $d['unit'] ?? null, 'frequency' => $d['frequency'],
            'data_source' => $d['data_source'] ?? null, 'statistic_description' => $d['statistic_description'] ?? null, 'website_link' => $d['website_link'] ?? null,
            'external_codes' => $codes === [] ? null : json_encode($codes), 'updated_at' => now()];
    }

    /** A new economic variable: a series the model can be calibrated on and the scenarios can shock. */
    public function variableStore(Request $request)
    {
        $d = $request->validate($this->variableRules(), ['statistic_code.regex' => 'The code is capital letters, digits and underscores, starting with a letter (for example MAIZE_PRICE).']);
        $id = DB::table('macro_statistics')->insertGetId($this->variableRow($d) + ['country' => 'MWI', 'is_active' => 1, 'created_at' => now()]);
        AuditLoggerService::log('Macro Variable Created', 'macro_statistics', $id, ['new_values' => $d, 'meta' => ['user' => $request->user()->id]]);

        return redirect()->route('macro-statistics.index', ['tab' => 'variables'])->with('success', "Variable {$d['statistic_code']} added. Enter its values on Data Entry, or import them.");
    }

    public function variableUpdate(Request $request, int $id)
    {
        $old = DB::table('macro_statistics')->where('id', $id)->first() ?? abort(404);
        $d = $request->validate($this->variableRules($id), ['statistic_code.regex' => 'The code is capital letters, digits and underscores, starting with a letter.']);
        if ($d['statistic_code'] !== $old->statistic_code && ($why = $this->variableInUse($old->statistic_code)) !== null) {
            return back()->with('error', "The code of {$old->statistic_code} cannot change: {$why}. Change its name or description instead.");
        }
        DB::table('macro_statistics')->where('id', $id)->update($this->variableRow($d));
        AuditLoggerService::log('Macro Variable Updated', 'macro_statistics', $id, ['old_values' => (array) $old, 'new_values' => $d, 'meta' => ['user' => $request->user()->id]]);

        return back()->with('success', "Variable {$d['statistic_code']} updated.");
    }

    /** A variable is deleted only when nothing depends on it; otherwise the message says what does. */
    public function variableDestroy(Request $request, int $id)
    {
        $v = DB::table('macro_statistics')->where('id', $id)->first() ?? abort(404);
        $n = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $id)->count();
        if ($n > 0) {
            return back()->with('error', "{$v->statistic_code} holds {$n} observations: delete them on Data Entry first, or keep the variable.");
        }
        if (($why = $this->variableInUse($v->statistic_code)) !== null) {
            return back()->with('error', "{$v->statistic_code} cannot be deleted: {$why}.");
        }
        DB::table('macro_statistics')->where('id', $id)->delete();
        DB::table('macro_series')->where('statistic_code', $v->statistic_code)->delete();
        AuditLoggerService::log('Macro Variable Deleted', 'macro_statistics', $id, ['old_values' => (array) $v, 'meta' => ['user' => $request->user()->id]]);

        return back()->with('success', "Variable {$v->statistic_code} deleted.");
    }

    private function variableInUse(string $code): ?string
    {
        if (DB::table('fli_model_versions')->where('statistic_code', $code)->exists()) {
            return 'a forward-looking model version uses it as its driver';
        }
        if (DB::table('scenario_shocks')->where('statistic_code', $code)->exists()) {
            return 'a scenario set shocks it';
        }

        return null;
    }

    // ------------------------------------------------------------------ observations

    /** Correct one observation, with the reason; the old value goes to the audit log. */
    public function observationUpdate(Request $request, int $id)
    {
        $o = DB::table('macro_statistics_data')->where('id', $id)->first() ?? abort(404);
        $d = $request->validate(['value' => ['required', 'numeric'], 'is_forecast' => ['nullable', 'boolean'], 'published_at' => ['nullable', 'date'], 'reason' => ['required', 'string', 'max:255']]);
        $new = ['value' => $d['value'], 'is_forecast' => ! empty($d['is_forecast']) ? 1 : 0, 'actual_value' => empty($d['is_forecast']) ? $d['value'] : null,
            'published_at' => $d['published_at'] ?? null, 'notes' => mb_substr(trim(($o->notes ? $o->notes . ' | ' : '') . 'Corrected: ' . $d['reason']), 0, 2000), 'updated_at' => now()];
        DB::table('macro_statistics_data')->where('id', $id)->update($new);
        AuditLoggerService::log('Macro Observation Corrected', 'macro_statistics_data', $id, ['old_values' => ['value' => $o->value, 'is_forecast' => $o->is_forecast, 'published_at' => $o->published_at],
            'new_values' => $new, 'meta' => ['user' => $request->user()->id, 'reason' => $d['reason']]]);

        return back()->with('success', 'Observation corrected; the old value is kept in the audit log. The model reads it at the next correlation sweep.');
    }

    public function observationDestroy(Request $request, int $id)
    {
        $o = DB::table('macro_statistics_data')->where('id', $id)->first() ?? abort(404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        DB::table('macro_statistics_data')->where('id', $id)->delete();
        AuditLoggerService::log('Macro Observation Deleted', 'macro_statistics_data', $id, ['old_values' => (array) $o, 'meta' => ['user' => $request->user()->id, 'reason' => $d['reason']]]);

        return back()->with('success', 'Observation deleted; the reason and the old value are in the audit log.');
    }

    // ------------------------------------------------------------------ your own CSV

    /**
     * A CSV of any variable the system holds, in the export's own layout:
     * series_code, period, value, is_forecast, source, published_at. Writes
     * nothing; the rows come back grouped by variable for the person to see.
     */
    public function csvPreview(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        if ($refused = $this->specRefusal('macro-series', $request)) {
            return $refused;
        }
        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $bom = "\xEF\xBB\xBF";
        $head = array_map(fn ($h) => strtolower(trim(str_replace($bom, '', (string) $h))), fgetcsv($fh) ?: []);
        foreach (['series_code', 'period', 'value'] as $need) {
            if (! in_array($need, $head, true)) {
                fclose($fh);

                return response()->json(['error' => "The file needs a column named {$need}. Download the sample for the layout."], 422);
            }
        }
        $codes = DB::table('macro_statistics')->pluck('statistic_code')->all();
        $groups = [];
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = [];
            foreach ($head as $i => $h) {
                $r[$h] = trim((string) ($row[$i] ?? ''));
            }
            $code = strtoupper($r['series_code']);
            $periodText = preg_match('/^\d{4}-\d{2}$/', $r['period']) ? $r['period'] . '-01' : (preg_match('/^\d{4}$/', $r['period']) ? $r['period'] . '-12-01' : $r['period']);
            $ts = strtotime($periodText);
            $value = str_replace([',', '%'], '', $r['value']);
            if (! in_array($code, $codes, true)) {
                $errors[] = "Line {$line}: {$code} is not a variable; add it on Variables first.";
                continue;
            }
            if ($ts === false || ! is_numeric($value)) {
                $errors[] = "Line {$line}: the period or the value cannot be read.";
                continue;
            }
            $forecast = in_array(strtolower($r['is_forecast'] ?? ''), ['1', 'yes', 'true', 'forecast', 'y'], true);
            $pub = $r['published_at'] ?? '';
            $groups[$code][] = ['period' => date('Y-m-t', $ts), 'year' => (int) date('Y', $ts), 'value' => (float) $value, 'value_type' => $forecast ? 'forecast' : 'actual',
                'published_at' => $pub !== '' && strtotime($pub) ? date('Y-m-d', strtotime($pub)) : null, 'source' => ($r['source'] ?? '') !== '' ? $r['source'] : null];
        }
        fclose($fh);
        $previews = [];
        foreach ($groups as $code => $rows) {
            usort($rows, fn ($a, $b) => strcmp($a['period'], $b['period']));
            $previews[] = ['series_code' => $code, 'indicator_code' => null, 'country' => 'MWI', 'source' => $rows[0]['source'] ?? 'CSV import',
                'address' => $request->file('file')->getClientOriginalName(), 'fetched_at' => now()->toDateTimeString(), 'rows' => $rows];
        }

        return response()->json(['previews' => $previews, 'errors' => array_slice($errors, 0, 50), 'error_count' => count($errors), 'rows' => array_sum(array_map(fn ($p) => count($p['rows']), $previews))]);
    }

    public function csvCommit(Request $request, MacroImportService $importer)
    {
        $data = $request->validate(['previews' => ['required', 'array'], 'note' => ['nullable', 'string', 'max:255']]);
        $done = [];
        try {
            foreach ($data['previews'] as $p) {
                $r = $importer->commit($p, $request->user()->id, 'manual', null, $data['note'] ?? 'CSV import');
                // the publisher's release date of each row (FLI module D-F10)
                $sid = DB::table('macro_statistics')->where('statistic_code', $p['series_code'])->value('id');
                foreach ($p['rows'] as $row) {
                    if (! empty($row['published_at'])) {
                        DB::table('macro_statistics_data')->where('macro_stat_definition_id', $sid)->where('period', $row['period'])->update(['published_at' => $row['published_at']]);
                    }
                }
                $done[] = "{$p['series_code']} batch {$r['batch_id']} ({$r['new']} new, {$r['updated']} updated)";
            }
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Imported: ' . implode('; ', $done) . '.');
    }

    // ------------------------------------------------------------------ scenario assumptions

    /**
     * The assumptions of a month's scenario sets: per scenario, the shock on
     * each variable in each forecast year and the path it gives, beside the
     * base path the sources give. A set that is a draft, or proposed by the
     * person looking, can be edited here; an approved set cannot (a change is
     * a new version with a reason, on Scenario Sets).
     */
    private function assumptions(string $period): array
    {
        $periods = DB::table('governed_scenario_sets')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period');
        // the latest month with a loan book opens first; a month ahead of the books only when asked for
        $latestBook = (string) DB::table('loan_books')->max('reporting_period');
        $period = preg_match('/^\d{4}-\d{2}$/', $period) ? $period : ($periods->contains($latestBook) ? $latestBook : (string) ($periods->first() ?? ''));
        $sets = [];
        $service = app(ScenarioSetService::class);
        foreach (DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->get() as $set) {
            try {
                $paths = $service->paths((int) $set->id);
            } catch (Throwable) {
                $paths = ['base' => [], 'scenarios' => [], 'base_source' => []];
            }
            $scenarios = DB::table('governed_scenarios')->where('set_id', $set->id)->orderBy('order_position')->get()->map(function ($sc) use ($paths) {
                $shocks = DB::table('scenario_shocks')->where('scenario_id', $sc->id)->orderBy('statistic_code')->orderBy('year_offset')->get(['statistic_code', 'year_offset', 'kind', 'value', 'note']);

                return ['id' => (int) $sc->id, 'name' => $sc->name, 'weight' => (float) $sc->weight, 'is_base' => (bool) $sc->is_base, 'anchored_to' => $sc->anchored_to,
                    'calibration_note' => $sc->calibration_note, 'shocks' => $shocks, 'path' => $paths['scenarios'][$sc->name]['path'] ?? []];
            });
            $sets[] = ['id' => (int) $set->id, 'name' => $set->name, 'version' => (int) $set->version, 'status' => $set->status, 'proposed_by' => $set->proposed_by,
                'editable' => in_array($set->status, ['DRAFT', 'PROPOSED'], true), 'base' => $paths['base'], 'base_source' => $paths['base_source'], 'scenarios' => $scenarios];
        }

        return ['period' => $period, 'periods' => $periods, 'year' => $period !== '' ? (int) substr($period, 0, 4) : null, 'sets' => $sets,
            'variables' => DB::table('macro_statistics')->orderBy('statistic_code')->get(['statistic_code', 'statistic_name', 'unit'])];
    }

    /** The weights of every scenario of a set not yet approved, in one go; the shocks are left as they are. */
    public function assumptionWeights(Request $request, int $set, ScenarioSetService $service)
    {
        $d = $request->validate(['weights' => ['required', 'array'], 'weights.*.id' => ['required', 'integer'], 'weights.*.weight' => ['required', 'numeric', 'min:0', 'max:100']]);
        try {
            $v = $service->updateProposed($set, ['scenarios' => array_map(fn ($w) => ['id' => (int) $w['id'], 'weight' => (float) $w['weight']], $d['weights'])], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($v['ok'] ? 'success' : 'warning', 'Weights saved.' . ($v['ok'] ? '' : ' The set cannot be proposed yet: ' . implode('; ', $v['problems'])));
    }

    /** An approved set is changed by a new version: a draft copy of it, with the reason. */
    public function assumptionVersion(Request $request, int $set, ScenarioSetService $service)
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $period = DB::table('governed_scenario_sets')->where('id', $set)->value('reporting_period');
        $open = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['DRAFT', 'PROPOSED'])->orderByDesc('version')->first();
        if ($open) {
            return back()->with('error', "Version {$open->version} of {$period} is already open ({$open->status}): change that one, or discard it first.");
        }
        try {
            $id = $service->newVersion($set, $d['reason'], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Draft version {$id} created from the approved set: change its weights and shocks, then propose it on Scenario Sets.");
    }

    /** Replace one scenario's shocks (and optionally its weight) on a set that is not approved yet. */
    public function assumptionSave(Request $request, int $set, ScenarioSetService $service)
    {
        $d = $request->validate([
            'scenario_id' => ['required', 'integer'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shocks' => ['present', 'array'],
            'shocks.*.statistic_code' => ['required', 'string', 'exists:macro_statistics,statistic_code'],
            'shocks.*.year_offset' => ['required', 'integer', 'min:0', 'max:2'],
            'shocks.*.kind' => ['required', 'in:abs,pct,replace'],
            'shocks.*.value' => ['required', 'numeric'],
            'shocks.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $scenario = ['id' => $d['scenario_id'], 'shocks' => array_values($d['shocks'])];
        if (isset($d['weight']) && $d['weight'] !== null) {
            $scenario['weight'] = $d['weight'];
        }
        try {
            $v = $service->updateProposed($set, ['scenarios' => [$scenario]], $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($v['ok'] ? 'success' : 'warning', 'Assumptions saved.' . ($v['ok'] ? '' : ' The set cannot be proposed yet: ' . implode('; ', $v['problems'])));
    }

    /**
     * The file against its column spec (App\Support\Imports\ImportSpecs) before
     * any preview is made: with an error on any row, the problems come back by
     * row and nothing can be committed.
     */
    private function specRefusal(string $key, Request $request): ?JsonResponse
    {
        $file = $request->file('file');
        $check = (new \App\Support\Imports\ImportSpecValidator())->checkFile($key, $file->getRealPath(), $file->getClientOriginalName());

        return $check['ok'] ? null : response()->json(['error' => \App\Support\Imports\ImportSpecValidator::summary($check), 'check' => $check], 422);
    }

    private function rbmPreview(string $path, string $name): array
    {
        $fh = fopen($path, 'r');
        $rows = [];
        $i = 0;
        while (($r = fgetcsv($fh)) !== false) {
            $i++;
            if ($i === 1 && ! is_numeric(str_replace(['%', ' '], '', (string) ($r[1] ?? '')))) {
                continue; // a header
            }
            if (array_filter($r, fn ($v) => trim((string) $v) !== '') === []) {
                continue; // a blank line, such as the one a spreadsheet leaves at the end
            }
            $date = trim((string) ($r[0] ?? ''));
            $rate = str_replace(['%', ' ', ','], '', (string) ($r[1] ?? ''));
            foreach (['Y-m-d', 'd/m/Y', 'Y-m'] as $f) {
                $d = \DateTime::createFromFormat('!' . $f, $date);
                if ($d && $d->format($f) === $date) {
                    $date = $d->format('Y-m-t');
                    break;
                }
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! is_numeric($rate)) {
                return ['series_code' => 'POLICY_RATE', 'rows' => [], 'note' => "row {$i}: '" . implode(',', $r) . "' is not date,rate"];
            }
            $rows[] = ['period' => $date, 'year' => (int) substr($date, 0, 4), 'value' => round((float) $rate, 6), 'value_type' => 'actual'];
        }
        fclose($fh);

        return ['series_code' => 'POLICY_RATE', 'indicator_code' => 'rbm:policy_rate', 'country' => 'MWI', 'source' => 'Reserve Bank of Malawi (file)', 'address' => $name, 'file_sha256' => hash_file('sha256', $path), 'fetched_at' => now()->toDateTimeString(), 'rows' => $rows, 'note' => $rows === [] ? 'no rows' : null];
    }
}
