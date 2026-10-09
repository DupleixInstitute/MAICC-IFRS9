<?php

namespace App\Http\Controllers;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\TakeonLandingService;
use App\Services\Eir\GovernanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/**
 * Data Foundation, Take-on Schedules (spec v4 section 6.9): upload a workbook
 * (the alternative to the committed copy), see each block with its mapping,
 * confidence, tick and fees, the gate results, and the Build with approval.
 * Finance may confirm a mapping or enter a fee here instead of in Excel; the
 * screen's entry is the one that counts and is audit-logged.
 */
class EirTakeonController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:eir.view']);
        $this->middleware('permission:eir.govern')->only(['upload', 'build', 'confirm', 'fees']);
    }

    public function index(GovernanceService $governance)
    {
        $load = DB::table('ebanker_loads')->where('route', TakeonLandingService::ROUTE)->orderByDesc('id')->first();
        $blocks = $load ? DB::table('takeon_blocks')->where('load_id', $load->id)->orderBy('block_no')->get()->map(fn ($b) => [
            'id' => $b->id, 'block_no' => $b->block_no, 'sheet_row' => $b->sheet_row, 'title' => $b->title, 'facility_name' => $b->facility_name, 'loan_book_row' => $b->loan_book_row,
            'principal' => (float) $b->principal, 'rate' => $b->rate !== null ? (float) $b->rate : null, 'value_date' => $b->value_date, 'maturity_date' => $b->maturity_date,
            'carrying_31_oct_2024' => $b->carrying_31_oct_2024 !== null ? (float) $b->carrying_31_oct_2024 : null, 'account' => $b->account, 'proposed_account' => $b->proposed_account,
            'confidence' => $b->confidence, 'confirmed' => $b->confirmed, 'corrected_account' => $b->corrected_account, 'comment' => $b->comment,
            'arrangement_fee' => $b->arrangement_fee !== null ? (float) $b->arrangement_fee : null, 'legal_fees' => $b->legal_fees !== null ? (float) $b->legal_fees : null,
            'other_fees' => $b->other_fees !== null ? (float) $b->other_fees : null, 'fee_date' => $b->fee_date, 'fee_deducted' => $b->fee_deducted, 'fee_source' => $b->fee_source,
            'total_fees' => $b->total_fees !== null ? (float) $b->total_fees : null, 'status' => $b->status, 'restructured' => (bool) $b->restructured,
            'gates' => json_decode($b->gates, true) ?? [], 'cells' => json_decode($b->cells, true) ?? [],
            'lines' => DB::table('takeon_schedule_lines')->where('block_id', $b->id)->count(),
        ]) : collect();
        $population = DB::table('contract_takeon')->orderBy('account')->get()->map(fn ($c) => [
            'account' => $c->account, 'contract_id' => $c->contract_id, 'basis' => $c->basis, 'block_id' => $c->block_id, 'origination_date' => $c->origination_date,
            'original_principal' => $c->original_principal !== null ? (float) $c->original_principal : null, 'contractual_rate' => $c->contractual_rate !== null ? (float) $c->contractual_rate : null,
            'fees_total' => $c->fees_total !== null ? (float) $c->fees_total : null, 'takeon_posting' => (float) $c->takeon_posting, 'takeon_opening_interest' => (float) $c->takeon_opening_interest,
            'takeon_opening_recovery' => (float) ($c->takeon_opening_recovery ?? 0), 'schedule_balance_at_takeon' => $c->schedule_balance_at_takeon !== null ? (float) $c->schedule_balance_at_takeon : null,
            'difference_at_takeon' => $c->difference_at_takeon !== null ? (float) $c->difference_at_takeon : null, 'flags' => json_decode($c->flags, true) ?? [], 'built_at' => $c->built_at,
            // the recompute from origination (system audit of 9 October 2026, finding M7)
            'takeon_balance' => isset($c->takeon_balance) ? (float) $c->takeon_balance : null,
            'recomputed_eir' => isset($c->recomputed_eir) ? (float) $c->recomputed_eir : null,
            'recomputed_amortised_cost' => isset($c->recomputed_amortised_cost) ? (float) $c->recomputed_amortised_cost : null,
            'recomputed_difference' => isset($c->recomputed_difference) ? (float) $c->recomputed_difference : null,
            'schedule_lines_written' => (int) ($c->schedule_lines_written ?? 0),
        ]);
        try {
            $basis = $governance->get('takeon_history_basis');
        } catch (Throwable) {
            $basis = 'Recompute from origination where the block and fees exist, else start at the take-on balance';
        }

        return Inertia::render('Eir/Takeon/Index', [
            'load' => $load ? ['id' => $load->id, 'pack' => $load->pack_name, 'status' => $load->status, 'loaded_at' => $load->loaded_at, 'manifest' => json_decode($load->manifest, true), 'summary' => (json_decode($load->gates, true) ?? [])['summary'] ?? []] : null,
            'blocks' => $blocks, 'population' => $population, 'basisInForce' => $basis,
            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
            // the view the section tab opened (config/menu.php) and the count on each tab
            'tab' => request('tab') === 'population' ? 'population' : 'blocks',
            'tabCounts' => ['blocks' => $blocks->count(), 'population' => $population->count()],
        ]);
    }

    public function upload(Request $request, TakeonLandingService $service)
    {
        $request->validate(['original' => ['required', 'file', 'mimes:xlsx'], 'mapping' => ['required', 'file', 'mimes:xlsx']]);
        $dir = storage_path('app/takeon/' . now()->format('Ymd-His'));
        mkdir($dir, 0775, true);
        $o = $request->file('original')->move($dir, $request->file('original')->getClientOriginalName());
        $m = $request->file('mapping')->move($dir, $request->file('mapping')->getClientOriginalName());
        try {
            $r = $service->land($o->getPathname(), $m->getPathname(), $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($r['status'] === 'QUARANTINED' ? 'error' : 'success', "Take-on workbook {$r['status']}: {$r['blocks']} blocks, {$r['lines']} schedule lines.");
    }

    /** Finance confirms or corrects a mapping on the screen; this entry counts over the workbook's and is logged. */
    public function confirm(Request $request, int $block)
    {
        $data = $request->validate(['confirmed' => ['required', 'in:Y,N'], 'corrected_account' => ['nullable', 'string', 'max:30'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $b = DB::table('takeon_blocks')->where('id', $block)->first();
        if ($b === null) {
            return back()->with('error', 'No such block.');
        }
        if ($data['confirmed'] === 'N' && empty($data['corrected_account'])) {
            return back()->with('error', 'Marking a mapping N needs the correct account number.');
        }
        $account = $data['confirmed'] === 'N' ? $data['corrected_account'] : ($b->proposed_account ?? $b->account);
        DB::table('takeon_blocks')->where('id', $block)->update(['confirmed' => $data['confirmed'], 'corrected_account' => $data['corrected_account'] ?? null, 'comment' => $data['comment'] ?? $b->comment, 'account' => $account, 'updated_at' => now()]);
        AuditLoggerService::log('Take-on Mapping Confirmed', 'takeon_blocks', $block, ['old_values' => ['confirmed' => $b->confirmed, 'account' => $b->account], 'new_values' => ['confirmed' => $data['confirmed'], 'account' => $account, 'comment' => $data['comment'] ?? null], 'meta' => ['user' => $request->user()->id]]);

        return back()->with('success', "Block {$b->block_no}: mapping " . ($data['confirmed'] === 'Y' ? 'confirmed' : "corrected to {$account}") . '.');
    }

    /** Finance enters a block's fees on the screen. A blank means no such fee; a zero means known to be nil. */
    public function fees(Request $request, int $block)
    {
        $data = $request->validate(['arrangement_fee' => ['nullable', 'numeric', 'min:0'], 'legal_fees' => ['nullable', 'numeric', 'min:0'], 'other_fees' => ['nullable', 'numeric', 'min:0'],
            'fee_date' => ['nullable', 'date_format:Y-m-d'], 'fee_deducted' => ['nullable', 'in:Y,N'], 'fee_source' => ['nullable', 'string', 'max:255']]);
        $b = DB::table('takeon_blocks')->where('id', $block)->first();
        if ($b === null) {
            return back()->with('error', 'No such block.');
        }
        $given = array_filter([$data['arrangement_fee'] ?? null, $data['legal_fees'] ?? null, $data['other_fees'] ?? null], fn ($x) => $x !== null && $x !== '');
        $total = $given === [] ? null : round(array_sum(array_map('floatval', $given)), 4);
        DB::table('takeon_blocks')->where('id', $block)->update($data + ['total_fees' => $total, 'updated_at' => now()]);
        AuditLoggerService::log('Take-on Fees Entered', 'takeon_blocks', $block, ['old_values' => ['total_fees' => $b->total_fees], 'new_values' => $data + ['total_fees' => $total], 'meta' => ['user' => $request->user()->id]]);

        return back()->with('success', "Block {$b->block_no}: fees recorded" . ($total !== null ? ' (total ' . number_format($total, 2) . ')' : ' (no fee row)') . '.');
    }

    public function build(Request $request, TakeonLandingService $service)
    {
        try {
            $c = $service->build($request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Take-on population built: {$c['accounts']} accounts, {$c['recomputed']} recomputed from origination, {$c['takeon_balance']} at the take-on balance, {$c['refused']} refused.");
    }
}
