<?php

namespace App\Http\Controllers;

use App\Models\StageingRule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StageingRulesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:settings');
    }

    public function index()
    {
        $rule = StageingRule::firstOrCreate(['institution_type' => 'default']);
        return Inertia::render('StageingRules/Thresholds', [
            'rule' => $rule,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'institution_type' => ['required','string','max:255'],
            'stage_1_threshold' => ['required','numeric','min:0'],
            'stage_3_threshold' => ['required','numeric','min:0'],
        ]);

        $rule = StageingRule::updateOrCreate(
            ['institution_type' => $data['institution_type']],
            ['stage_1_threshold' => $data['stage_1_threshold'], 'stage_3_threshold' => $data['stage_3_threshold']]
        );

        return redirect()->route('stageing-rules.index')->with('success', 'Thresholds saved.');
    }
}
