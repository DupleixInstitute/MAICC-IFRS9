<?php

namespace App\Http\Controllers;

use App\Exports\Ifrs9ReportExport;
use App\Models\Setting;
use App\Services\AuditLoggerService;
use App\Services\Eir\EirAsAtService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Report Hub, EIR as at a date (spec v4 section 6.11): the book at any date
 * the user names, by product, by GL and in total, with one contract's full
 * computation on request, and the downloads. The Contract Profile's date
 * picker reads the same service, so the screen and the download agree.
 *
 * The download comes as CSV, Excel or PDF (the format query parameter);
 * the specification asks for Excel and PDF and the system audit of
 * 9 October 2026 (finding M13) found only the CSV. All three are built from
 * one payload, so the figures cannot differ between them.
 */
class EirAsAtController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
    }

    public function index(Request $request, EirAsAtService $service)
    {
        $date = (string) $request->query('date', '');
        $contract = (string) $request->query('contract', '');
        $last = DB::table('ebanker_raw_rows')->whereIn('query_id', \App\Services\Ebanker\LandingZoneReader::LEDGER)->max('row_date');
        if ($date === '' && $last !== null) {
            $date = $last;
        }
        $book = null; $one = null; $error = null;
        if ($date !== '') {
            try {
                $book = $service->book($date);
                if ($contract !== '') {
                    $one = $service->contract($contract, $date);
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return Inertia::render('Eir/AsAt/Index', [
            'date' => $date, 'contract' => $contract, 'lastLedgerDate' => $last, 'book' => $book, 'one' => $one, 'error' => $error,
            'contracts' => DB::table('contract_eir')->whereNotNull('locked_at')->orderBy('contract_id')->get(['contract_id', 'customer_name'])->map(fn ($c) => ['id' => $c->contract_id, 'name' => $c->customer_name]),
            'yearEnds' => ['2024-12-31', '2025-12-31', '2026-12-31'],
        ]);
    }

    /** The book at the date as CSV (the default), Excel or PDF. */
    public function export(Request $request, EirAsAtService $service)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])]]);
        $date = $data['date'];
        $format = $data['format'] ?? 'csv';
        $book = $service->book($date);
        $filename = 'EIR as at ' . $date;

        AuditLoggerService::log('EIR As At Exported', 'contract_eir', null, ['reporting_period' => $book['period'],
            'new_values' => ['format' => $format, 'as_at' => $date, 'contracts' => $book['total']['contracts'], 'difference_ytd' => $book['total']['difference']]]);

        if ($format === 'csv') {
            return $this->csv($book, $filename);
        }
        $report = $this->reportPayload($book);
        if ($format === 'pdf') {
            return Pdf::loadView('reports.ifrs9.report', ['report' => $report])->setPaper('a4', 'landscape')->download($filename . '.pdf');
        }

        return Excel::download(new Ifrs9ReportExport($report), $filename . '.xlsx');
    }

    private function csv(array $book, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['EIR as at ' . $book['as_at']]);
            fputcsv($out, ['Contract', 'Customer', 'Product', 'GL', 'EIR interest YTD', 'Contractual interest YTD', 'Difference', 'Amortised cost', 'Gross carrying amount']);
            foreach ($book['contracts'] as $l) {
                fputcsv($out, [$l['contract_id'], $l['customer_name'], $l['product'], $l['gl'], $l['eir_ytd'], $l['contractual_ytd'], $l['difference'], $l['amortised_cost'], $l['gross']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['By product']);
            foreach ($book['by_product'] as $g) {
                fputcsv($out, [$g['key'], '', '', '', $g['eir_ytd'], $g['contractual_ytd'], $g['difference'], $g['amortised_cost'], $g['gross']]);
            }
            fputcsv($out, ['By GL']);
            foreach ($book['by_gl'] as $g) {
                fputcsv($out, [$g['key'], '', '', '', $g['eir_ytd'], $g['contractual_ytd'], $g['difference'], $g['amortised_cost'], $g['gross']]);
            }
            $t = $book['total'];
            fputcsv($out, ['Total', '', '', '', $t['eir_ytd'], $t['contractual_ytd'], $t['difference'], $t['amortised_cost'], $t['gross']]);
            fclose($out);
        }, $filename . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The Excel and PDF content in the shape every hub report uses (company,
     * title, KPIs, sections of columns and rows), so the branded workbook and
     * the A4 landscape PDF carry the system's heading without report-specific
     * templates.
     */
    public function reportPayload(array $book): array
    {
        $n = fn ($v) => $v === null ? '-' : number_format((float) $v, 2);
        $groupRows = fn (array $groups) => array_map(fn ($g) => [$g['key'], (string) $g['contracts'], $n($g['eir_ytd']), $n($g['contractual_ytd']), $n($g['difference']), $n($g['amortised_cost']), $n($g['gross'])], $groups);
        $groupColumns = ['', 'Contracts', 'EIR interest YTD', 'Contractual interest YTD', 'Difference', 'Amortised cost', 'Gross carrying amount'];
        $groupAlign = ['l', 'r', 'r', 'r', 'r', 'r', 'r'];
        $t = $book['total'];
        $totalRow = ['Total', (string) $t['contracts'], $n($t['eir_ytd']), $n($t['contractual_ytd']), $n($t['difference']), $n($t['amortised_cost']), $n($t['gross'])];

        return [
            'company' => $this->company(),
            'title' => 'EIR as at ' . $book['as_at'],
            'subtitle' => 'The EIR interest, the contractual interest posted and the difference (the revenue shift) for the year to the date, by product, by GL and by contract, with the amortised cost and the gross carrying amount at the date (spec v4 section 6.11)',
            'period' => $book['period'],
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'kpis' => [
                ['label' => 'Contracts with a locked EIR', 'value' => (string) $t['contracts'], 'tone' => 'maiic'],
                ['label' => 'EIR interest, year to ' . $book['as_at'], 'value' => $n($t['eir_ytd']), 'tone' => 'emerald'],
                ['label' => 'Contractual interest, year to date', 'value' => $n($t['contractual_ytd']), 'tone' => 'amber'],
                ['label' => 'The revenue shift', 'value' => $n($t['difference']), 'tone' => $t['difference'] >= 0 ? 'emerald' : 'rose'],
            ],
            'sections' => [
                ['heading' => 'By product', 'columns' => array_replace($groupColumns, [0 => 'Product']), 'align' => $groupAlign, 'rows' => array_merge($groupRows($book['by_product']), [$totalRow])],
                ['heading' => 'By GL', 'columns' => array_replace($groupColumns, [0 => 'GL']), 'align' => $groupAlign, 'rows' => array_merge($groupRows($book['by_gl']), [$totalRow])],
                ['heading' => 'By contract', 'columns' => ['Contract', 'Customer', 'Product', 'GL', 'EIR interest YTD', 'Contractual interest YTD', 'Difference', 'Amortised cost', 'Gross carrying amount'],
                    'align' => ['l', 'l', 'l', 'l', 'r', 'r', 'r', 'r', 'r'],
                    'rows' => array_map(fn ($l) => [$l['contract_id'], (string) ($l['customer_name'] ?? ''), (string) $l['product'], (string) $l['gl'], $n($l['eir_ytd']), $n($l['contractual_ytd']), $n($l['difference']), $n($l['amortised_cost']), $n($l['gross'])], $book['contracts'])],
                ['heading' => 'How the figures are worked out', 'columns' => ['Figure', 'Basis'], 'align' => ['l', 'l'], 'rows' => [
                    ['Month-end', 'the roll-forward row the revenue run locked for the month'],
                    ['Date inside a month', 'the prior month-end closing amortised cost, plus simple interest at the contractual rate for the actual days over 365 (section 3), less the cash received in the month'],
                    ['Months without a roll-forward row', 'rolled from the last closing at the monthly EIR, less the cash received in each month'],
                    ['Take-on loans', 'opened at the take-on basis of section 6.9: the recomputed amortised cost at 31 July 2024, or the take-on balance'],
                    ['Gross carrying amount', 'the ledger\'s running balance of every posting on or before the date'],
                    ['Contractual interest', 'what the ledger posted (types 303 and 120)'],
                    ['Scope', $book['note']],
                ]],
            ],
        ];
    }

    private function company(): string
    {
        try {
            return optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: config('app.name');
        } catch (Throwable) {
            return config('app.name');
        }
    }
}
