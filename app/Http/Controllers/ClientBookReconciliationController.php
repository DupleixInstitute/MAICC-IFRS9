<?php

namespace App\Http\Controllers;

use App\Services\Ebanker\ClientBookReader;
use App\Services\Ebanker\ClientBookReconciliationService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\LoanAgeingOverlayService;
use App\Services\Eir\LoanGrossAmountOverlayService;
use App\Services\Eir\LoanGrossAmountOverrideService;
use App\Services\Eir\LoanStagingBasisOverrideService;
use App\Support\SupportingDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Throwable;

/**
 * Client Book Reconciliation (Customer & Loan Data): the client's own loan books against the book
 * the system rebuilt for the same month-end, the differences, and the governed overrides that take
 * the client's figure where the client's book is the right one (Client Book Reconciliation
 * Specification v1). Viewing needs eir.view; landing, comparing and deciding need eir.govern, as
 * on the other registers.
 */
class ClientBookReconciliationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->except(['index', 'differencesCsv', 'bookFile', 'template']);
    }

    public function index(Request $request)
    {
        $users = DB::table('users')->pluck('name', 'id');
        $name = fn ($id, $label = null) => $id !== null ? ($users[$id] ?? "user {$id}") : $label;
        // the decoded summary replaces the stored JSON string (an array union would keep the string)
        $books = DB::table('client_loan_books')->orderByDesc('reporting_period')->orderByDesc('id')->get()->map(fn ($b) => array_merge((array) $b, [
            'kind_label' => ClientBookReader::KINDS[$b->kind] ?? $b->kind,
            'landed_by_name' => $b->landed_label !== null ? 'the bootstrap' : $name($b->landed_by),
            'summary' => $b->summary ? json_decode($b->summary, true) : null,
            'open' => DB::table('client_book_differences')->where('client_book_id', $b->id)->where('status', ClientBookReconciliationService::STATUS_OPEN)->count(),
        ]));
        $bookId = $request->integer('book') ?: ($books->firstWhere('status', 'COMPARED')['id'] ?? null);
        $status = (string) $request->query('status', '');
        $diffs = $bookId ? DB::table('client_book_differences')->where('client_book_id', $bookId)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'OPEN' then 0 when 'OVERRIDE_PROPOSED' then 1 when 'KEPT' then 2 when 'RECONCILED' then 3 else 4 end")->orderBy('contract_id')->get()
            ->map(fn ($d) => (array) $d + ['decided_by_name' => $name($d->decided_by)]) : collect();

        $overrides = collect();
        foreach (['staging' => 'loan_staging_basis_overrides', 'gross' => 'loan_gross_amount_overrides'] as $type => $table) {
            foreach (DB::table($table)->orderByRaw("case status when 'PROPOSED' then 0 when 'APPROVED' then 1 when 'REJECTED' then 2 else 3 end")->orderByDesc('reporting_period')->orderBy('contract_id')->get() as $o) {
                $overrides->push((array) $o + [
                    'type' => $type, 'workflow' => $type === 'staging' ? 'staging-override' : 'gross-override',
                    'customer_name' => DB::table('loan_books')->where('contract_id', $o->contract_id)->where('reporting_period', $o->reporting_period)->value('customer_name'),
                    'proposed_by_name' => $name($o->proposed_by), 'approved_by_name' => $name($o->approved_by),
                    'documents' => SupportingDocuments::count($table, (int) $o->id),
                ]);
            }
        }

        return Inertia::render('Eir/ClientBooks/Index', [
            'books' => $books->values(),
            'bookId' => $bookId,
            'status' => $status,
            'differences' => $diffs->values(),
            'overrides' => $overrides->values(),
            'rebuilds' => $this->rebuildsDue(),
            'overlays' => [
                'ageing' => DB::table('loan_ageing_overlays')->orderByDesc('reporting_period')->orderBy('contract_id')->get(),
                'gross' => DB::table('loan_gross_amount_overlays')->orderByDesc('reporting_period')->orderBy('contract_id')->get(),
            ],
            'kinds' => ClientBookReader::KINDS,
            'periods' => DB::table('loan_books')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            'locked' => DB::table('reporting_period_locks')->pluck('reporting_period'),
            'me' => auth()->id(),
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    public function land(Request $request, ClientBookReconciliationService $service)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'kind' => ['required', 'in:' . implode(',', array_keys(ClientBookReader::KINDS))],
            'label' => ['required', 'string', 'min:5', 'max:200'],
            'file' => ['required', 'file', 'max:20480', 'mimes:xls,xlsx,csv,txt'],
            'compare' => ['nullable', 'boolean'],
        ]);
        try {
            $file = $data['file'];
            $r = $service->land($file->getRealPath(), $data['period'], $data['kind'], $data['label'], (int) $request->user()->id, null, $file->getClientOriginalName());
            $c = ($data['compare'] ?? true) ? $service->compare($r['book_id']) : null;
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $landed = $r['status'] === 'ALREADY_LANDED' ? "This file was already landed for {$data['period']} (book {$r['book_id']}); it was compared again." : "Client book {$r['book_id']} landed: {$r['rows']} rows.";

        return redirect()->route('client-books.index', ['book' => $r['book_id'], 'tab' => 'differences'])
            ->with('success', $landed . ($c ? " Compared with the system's book: {$c['compared']} loans matched, {$c['same']} the same, {$c['differences']} to decide." : ''));
    }

    public function compare(int $id, ClientBookReconciliationService $service)
    {
        try {
            $c = $service->compare($id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client-books.index', ['book' => $id, 'tab' => 'differences'])
            ->with('success', "Book {$id} ({$c['period']}) compared: {$c['compared']} loans matched, {$c['same']} the same, {$c['differences']} to decide. The differences CSV is written again.");
    }

    public function withdrawBook(Request $request, int $id, ClientBookReconciliationService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $service->withdraw($id, (int) $request->user()->id, $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Client book {$id} withdrawn. Overrides already proposed from it stay on the Overrides tab.");
    }

    public function propose(Request $request, int $id, ClientBookReconciliationService $service)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
            'days' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'amount' => ['nullable', 'numeric'],
        ]);
        try {
            $r = $service->propose($id, (int) $request->user()->id, $data['reason'], isset($data['days']) ? (int) $data['days'] : null, isset($data['amount']) ? (float) $data['amount'] : null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $what = implode(' and ', array_filter([$r['staging_override_id'] ? "staging override #{$r['staging_override_id']}" : null, $r['gross_override_id'] ? "gross amount override #{$r['gross_override_id']}" : null]));

        return back()->with('success', "Proposed {$what}, with the client's book linked as its evidence. A different person approves it on the Overrides tab; it applies when the month is rebuilt.");
    }

    public function proposeMany(Request $request, int $book, ClientBookReconciliationService $service)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer'], 'reason' => ['required', 'string', 'min:10', 'max:1500']]);
        try {
            $r = $service->proposeMany($book, $data['ids'], (int) $request->user()->id, $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $skipped = $r['skipped'] === [] ? '' : ' Not proposed: ' . implode(' | ', array_slice($r['skipped'], 0, 5)) . (count($r['skipped']) > 5 ? ' | and ' . (count($r['skipped']) - 5) . ' more.' : '');

        return back()->with($r['proposed'] > 0 ? 'success' : 'error', "{$r['proposed']} difference(s) proposed as overrides with the client's figures.{$skipped}");
    }

    public function keep(Request $request, int $id, ClientBookReconciliationService $service)
    {
        $data = $request->validate(['action' => ['required', 'in:keep,reopen'], 'reason' => ['required', 'string', 'min:10', 'max:1500']]);
        try {
            $data['action'] === 'keep' ? $service->keep($id, (int) $request->user()->id, $data['reason']) : $service->reopen($id, (int) $request->user()->id, $data['reason']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['action'] === 'keep' ? "Difference {$id}: the system's figure stands, with the reason recorded." : "Difference {$id} is open again.");
    }

    /** A person proposes an override directly (not from a client book); its evidence is attached on its record before approval. */
    public function storeOverride(Request $request, LoanStagingBasisOverrideService $staging, LoanGrossAmountOverrideService $gross)
    {
        $data = $request->validate([
            'type' => ['required', 'in:staging,gross'],
            'contract_id' => ['required', 'string', 'max:30'],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'days' => ['required_if:type,staging', 'nullable', 'integer', 'min:0', 'max:20000'],
            'amount' => ['required_if:type,gross', 'nullable', 'numeric'],
            'source' => ['nullable', 'in:audited_workbook,client_book'],
            'reason' => ['required', 'string', 'min:10', 'max:1500'],
        ]);
        if (! DB::table('loan_books')->where('contract_id', $data['contract_id'])->where('reporting_period', $data['period'])->exists()) {
            return back()->with('error', "Contract {$data['contract_id']} is not in the loan book of {$data['period']}: an override changes a loan the system builds for that month.");
        }
        try {
            $id = $data['type'] === 'staging'
                ? $staging->propose($data['contract_id'], $data['period'], $data['reason'], (int) $request->user()->id, (int) $data['days'], $data['source'] ?? LoanStagingBasisOverrideService::SOURCE_AUDITED_WORKBOOK)
                : $gross->propose($data['contract_id'], $data['period'], $data['reason'], (int) $request->user()->id, (float) $data['amount']);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client-books.index', ['tab' => 'overrides'])
            ->with('success', ($data['type'] === 'staging' ? 'Staging' : 'Gross amount') . " override #{$id} proposed. Attach the evidence on its record (the paperclip); a different person approves it.");
    }

    public function decideOverride(Request $request, string $type, int $id, LoanStagingBasisOverrideService $staging, LoanGrossAmountOverrideService $gross)
    {
        abort_unless(in_array($type, ['staging', 'gross'], true), 404);
        $data = $request->validate(['action' => ['required', 'in:approve,reject,withdraw'], 'reason' => ['nullable', 'string', 'max:1500']]);
        $service = $type === 'staging' ? $staging : $gross;
        $user = (int) $request->user()->id;
        try {
            match ($data['action']) {
                'approve' => $service->approve($id, $user),
                'reject' => mb_strlen(trim((string) ($data['reason'] ?? ''))) >= 10 ? $service->reject($id, $user, (string) $data['reason']) : throw new \RuntimeException('A rejection needs the reason (at least 10 characters).'),
                'withdraw' => $service->withdraw($id, $user, $data['reason'] ?? null),
            };
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $label = ($type === 'staging' ? 'Staging' : 'Gross amount') . " override #{$id}";

        return back()->with('success', match ($data['action']) {
            'approve' => "{$label} approved. It applies when the month is rebuilt: propose the rebuild from \"Rebuilds due\" above the table.",
            'reject' => "{$label} rejected.",
            'withdraw' => "{$label} withdrawn.",
        });
    }

    public function importOverlay(Request $request, LoanAgeingOverlayService $ageing, LoanGrossAmountOverlayService $gross)
    {
        $data = $request->validate(['type' => ['required', 'in:ageing,gross'], 'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']]);
        $path = $data['file']->getRealPath();
        $head = array_map('trim', (array) fgetcsv(fopen($path, 'r')));
        $want = $data['type'] === 'ageing' ? LoanAgeingOverlayService::HEADER : LoanGrossAmountOverlayService::HEADER;
        if (array_diff($want, $head) !== []) {
            return back()->with('error', 'The file is not a ' . ($data['type'] === 'ageing' ? 'Finance ageing' : 'Finance gross amount') . ' overlay: its header lacks ' . implode(', ', array_diff($want, $head)) . '.');
        }
        try {
            $r = $data['type'] === 'ageing' ? $ageing->import($path, (int) $request->user()->id) : $gross->import($path, (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client-books.index', ['tab' => 'overlays'])->with('success', "{$r['imported']} overlay row(s) recorded beside the system's figures. An overlay changes nothing: Finance's figure applies only through an approved override.");
    }

    public function differencesCsv(int $id)
    {
        $book = DB::table('client_loan_books')->where('id', $id)->first();
        abort_if($book === null || $book->csv_path === null, 404, 'This book has not been compared yet.');
        $full = storage_path('app/' . $book->csv_path);
        abort_unless(is_file($full), 404, 'The differences CSV is not on this server; compare the book again to write it.');

        return response()->download($full, basename($full), ['Content-Type' => 'text/csv']);
    }

    public function bookFile(int $id)
    {
        $doc = DB::table('supporting_documents')->where('documentable_type', 'client_loan_books')->where('documentable_id', $id)->whereNull('deleted_at')->first();
        abort_if($doc === null, 404);
        $disk = Storage::disk($doc->disk);
        abort_unless($disk->exists($doc->path), 404, 'The file is not on this server.');

        return response()->download($disk->path($doc->path), $doc->original_name);
    }

    /** The reconciliation template of a month, backfilled with the system's own figures. */
    public function template(Request $request, ClientBookReconciliationService $service)
    {
        $period = (string) $request->query('period');
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1, 422, 'Choose the month-end.');
        $rows = $service->templateRows($period);

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($f, $r);
            }
            fclose($f);
        }, "Client book reconciliation template {$period}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * The months with an approved override the book has not been rebuilt with yet: the last build of
     * the month ran before the override was approved. A rebuild already proposed is named.
     *
     * @return list<array{period:string, overrides:int, last_built:?string, proposed_build:?int, locked:bool}>
     */
    private function rebuildsDue(): array
    {
        $approved = collect();
        foreach (['loan_staging_basis_overrides', 'loan_gross_amount_overrides'] as $t) {
            $approved = $approved->concat(DB::table($t)->where('status', 'APPROVED')->get(['reporting_period', 'approved_at']));
        }
        $out = [];
        foreach ($approved->groupBy('reporting_period') as $period => $rows) {
            $builds = DB::table('loan_book_builds')->where('period_from', '<=', $period)->where('period_to', '>=', $period);
            $lastBuilt = (clone $builds)->where('status', 'BUILT')->max('built_at');
            $latest = $rows->max('approved_at');
            if ($lastBuilt !== null && $lastBuilt >= $latest) {
                continue;
            }
            $out[] = ['period' => $period, 'overrides' => $rows->count(), 'last_built' => $lastBuilt, 'proposed_build' => (clone $builds)->where('status', 'PROPOSED')->max('id'),
                'locked' => DB::table('reporting_period_locks')->where('reporting_period', $period)->exists()];
        }

        return collect($out)->sortBy('period')->values()->all();
    }
}
