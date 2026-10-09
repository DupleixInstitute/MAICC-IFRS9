<?php

namespace App\Http\Controllers;

use App\Services\Eir\EirAsAtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Report Hub, EIR as at a date (spec v4 section 6.11): the book at any date
 * the user names, by product, by GL and in total, with one contract's full
 * computation on request, and the download. The Contract Profile's date
 * picker reads the same service, so the screen and the download agree.
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
        $last = (new \App\Services\Ebanker\LandingZoneReader())->lastLedgerDate();
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

    public function export(Request $request, EirAsAtService $service): StreamedResponse
    {
        $date = (string) $request->query('date');
        $book = $service->book($date);

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
        }, 'EIR as at ' . $date . '.csv', ['Content-Type' => 'text/csv']);
    }
}
