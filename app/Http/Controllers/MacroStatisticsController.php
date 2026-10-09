<?php

namespace App\Http\Controllers;

use App\Services\Macro\ImfWeoParserService;
use App\Services\Macro\MacroImportService;
use App\Services\Macro\WorldBankFetcherService;
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
        $this->middleware('permission:macro.manage|eir.govern')->only(['commit', 'manual']);
    }

    public function index(Request $request)
    {
        $series = DB::table('macro_statistics')->orderBy('statistic_code')->get()->map(function ($s) {
            $agg = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $s->id)->selectRaw('count(*) n, min(period) first, max(period) last, sum(is_forecast) forecasts')->first();
            $batch = DB::table('macro_statistics_data as d')->join('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')->where('d.macro_stat_definition_id', $s->id)->orderByDesc('b.id')->first(['b.source', 'b.fetched_at', 'b.address']);

            return ['id' => $s->id, 'code' => $s->statistic_code, 'name' => $s->statistic_name, 'unit' => $s->unit, 'frequency' => $s->frequency, 'source' => $s->data_source, 'codes' => json_decode($s->external_codes ?? '', true) ?: [], 'country' => $s->country ?? 'MWI',
                'n' => (int) $agg->n, 'first' => $agg->first, 'last' => $agg->last, 'forecasts' => (int) $agg->forecasts, 'last_batch' => $batch ? ['source' => $batch->source, 'fetched_at' => $batch->fetched_at, 'address' => $batch->address] : null, 'why' => $s->statistic_description];
        });
        $selected = (int) $request->query('series', $series->first()['id'] ?? 0);
        $observations = $selected ? DB::table('macro_statistics_data as d')->leftJoin('macro_source_import_batches as b', 'b.id', '=', 'd.source_import_batch_id')->where('d.macro_stat_definition_id', $selected)->orderBy('d.period')
            ->get(['d.id', 'd.period', 'd.value', 'd.is_forecast', 'd.source', 'd.notes', 'b.source as batch_source', 'b.fetched_at']) : collect();
        $batches = DB::table('macro_source_import_batches as b')->leftJoin('users as u', 'u.id', '=', 'b.committed_by')->orderByDesc('b.id')->limit(30)->get(['b.*', 'u.name as committed_by_name']);
        $sets = DB::table('governed_scenario_sets')->orderByDesc('reporting_period')->orderByDesc('version')->limit(5)->get(['id', 'reporting_period', 'version', 'name', 'status']);

        return Inertia::render('Macro/Index', [
            'series' => $series, 'selected' => $selected, 'observations' => $observations, 'batches' => $batches, 'sets' => $sets,
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
        $data = $request->validate(['series_code' => ['required', 'string'], 'period' => ['required', 'date'], 'value' => ['required', 'numeric'], 'is_forecast' => ['nullable', 'boolean'], 'reason' => ['required', 'string', 'max:255']]);
        $preview = ['series_code' => $data['series_code'], 'indicator_code' => null, 'country' => 'MWI', 'source' => 'Manual entry: ' . $data['reason'], 'address' => null, 'fetched_at' => now()->toDateTimeString(),
            'rows' => [['period' => $data['period'], 'year' => (int) substr($data['period'], 0, 4), 'value' => (float) $data['value'], 'value_type' => ! empty($data['is_forecast']) ? 'forecast' : 'actual']]];
        try {
            $r = $importer->commit($preview, $request->user()->id, 'manual', null, $data['reason']);
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
            fputcsv($out, ['series_code', 'period', 'value', 'is_forecast', 'source']);
            foreach (DB::table('macro_statistics_data as d')->join('macro_statistics as s', 's.id', '=', 'd.macro_stat_definition_id')->orderBy('s.statistic_code')->orderBy('d.period')->get(['s.statistic_code', 'd.period', 'd.value', 'd.is_forecast', 'd.source']) as $r) {
                fputcsv($out, [$r->statistic_code, $r->period, $r->value, $r->is_forecast, $r->source]);
            }
            fclose($out);
        }, 'macro statistics ' . now()->toDateString() . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** The RBM policy-rate file: a CSV of date,rate (monthly), as the Reserve Bank publishes the MPC decisions. */
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
