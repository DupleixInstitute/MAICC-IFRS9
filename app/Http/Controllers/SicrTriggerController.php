<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\LoanBook;
use App\Models\SicrItem;
use App\Models\SicrGroup;
use App\Models\SicrTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SicrTriggerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index()
    {
        $groups = SicrGroup::orderBy('name')->get(['id','name']);
        $items = SicrItem::orderBy('name')->get(['id','name','group_id']);
        $triggers = SicrTrigger::with(['group','item','user'])->orderByDesc('created_at')->paginate(20);
        return Inertia::render('StageingRules/Triggers', [
            'groups' => $groups,
            'items' => $items,
            'triggers' => $triggers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required','exists:finance_sicr_groups,id'],
            'item_id' => ['required','exists:finance_sicr_items,id'],
            'account_number' => ['required','string','max:255'],
            'reason' => ['required','string'],
            'attachment' => ['nullable','file','max:5120'],
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('sicr_attachments', 'public');
        }

        $trigger = SicrTrigger::create([
            'group_id' => $data['group_id'],
            'item_id' => $data['item_id'],
            'account_number' => $data['account_number'],
            'reason' => $data['reason'],
            'attachment_path' => $path,
            'triggered_by' => Auth::id(),
        ]);

        // Update loan staging: mark SICR and move to next stage (post-qualitative)
        $loan = LoanBook::where('contract_id', $data['account_number'])
            ->orWhere('external_identity_id', $data['account_number'])
            ->orderByDesc('reporting_period')
            ->first();
        if ($loan) {
            $loan->sicr_trigger = 1;
            // Move to next stage (max 3)
            $pre = (int)($loan->ifrs9_stage_prequalitative ?? $loan->ifrs9_stage ?? 1);
            $loan->ifrs9_stage_postqualitative = min(3, max((int)$loan->ifrs9_stage_postqualitative, $pre + 1));
            $loan->save();
        }

        return back()->with('success', 'Trigger recorded.');
    }
}
