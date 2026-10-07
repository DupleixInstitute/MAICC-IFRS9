<?php

namespace App\Http\Controllers;

use App\Models\MacroVariable;
use App\Models\ReferenceLookup;
use App\Models\Scenario;
use App\Models\ScenarioParameterLibrary;
use App\Models\ScenarioShock;
use App\Models\SegmentDimension;
use App\Models\User;
use App\Services\ScenarioDefinitionService;
use App\Services\ScenarioGovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioController extends Controller
{
    public function __construct(
        private readonly ScenarioDefinitionService $definitionService,
        private readonly ScenarioGovernanceService $governanceService,
    ) {
    }

    public function index(): Response
    {
        // Per-cycle reporting override context. The selected cycle drives the "in this
        // cycle's report" state: an override row wins, else the global report_selected
        // flag. Uncurated cycle -> the global flag everywhere (golden-safe).
        $selectedPeriod = request('period')
            ?: \App\Models\ReportingContext::query()->where('is_active_for_reporting', true)
                ->orderByDesc('id')->value('reporting_period')
            ?: \App\Models\ReportingContext::query()->orderByDesc('id')->value('reporting_period');
        $reportPeriods = \App\Models\ReportingContext::query()
            ->whereNotNull('reporting_period')->distinct()
            ->orderByDesc('reporting_period')->pluck('reporting_period')->values();
        $cycleOverrides = $selectedPeriod
            ? \App\Models\ScenarioReportCycleOverride::where('reporting_period', $selectedPeriod)
                ->pluck('is_selected', 'scenario_id')->all()
            : [];
        $cycleCurated = $cycleOverrides !== [];

        $scenarios = Scenario::query()
            ->where('scenario_role', '!=', 'whatif')   // hide the internal combined-stress holder
            ->with([
                'shocks.macroVariable:id,code,name,category,unit',
                'parameters:id,scenario_id,parameter_key,parameter_label,parameter_value,unit,category',
                'createdBy:id,name',
                'approvedBy:id,name',
                'owner:id,name',
            ])
            ->withCount('runs')
            ->orderByRaw("FIELD(status,'approved','draft','archived')")
            ->orderBy('scenario_family')
            ->orderBy('name')
            ->get()
            ->map(fn (Scenario $scenario) => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'scenario_type' => $scenario->scenario_type,
                // Overlay (journal-driven) scenarios have their own builder page; the
                // list uses this to surface an "Open overlay builder" link so an
                // existing overlay is reachable without knowing its URL.
                'input_mode' => $scenario->input_mode,
                'scenario_family' => $scenario->scenario_family,
                'family_label' => $scenario->family_label,
                'type_color' => $scenario->type_color,
                'description' => $scenario->description,
                'status' => $scenario->status,
                'version' => $scenario->version,
                'parent_scenario_id' => $scenario->parent_scenario_id,
                'base_year' => $scenario->base_year,
                'horizon_years' => $scenario->horizon_years,
                'is_icaap_scenario' => $scenario->is_icaap_scenario,
                'report_selected' => (bool) $scenario->report_selected,
                // Effective inclusion for the selected cycle: an override row wins,
                // otherwise the global report_selected flag. cycle_overridden flags
                // that this scenario deviates from the global default for this cycle.
                'report_included_cycle' => array_key_exists($scenario->id, $cycleOverrides)
                    ? (bool) $cycleOverrides[$scenario->id]
                    : (bool) $scenario->report_selected,
                'cycle_overridden' => array_key_exists($scenario->id, $cycleOverrides),
                'severity_index' => $scenario->severity_index,
                'probability_weight' => $scenario->probability_weight,
                'narrative' => $scenario->narrative,
                'notes' => $scenario->notes,
                'scenario_role' => $scenario->scenario_role,
                'calibration_source' => $scenario->calibration_source,
                'calibration_method' => $scenario->calibration_method,
                'plausibility_note' => $scenario->plausibility_note,
                'use_test_evidence' => $scenario->use_test_evidence,
                'owner_id' => $scenario->owner_id,
                'owner' => $scenario->owner ? ['name' => $scenario->owner->name] : null,
                'risk_categories' => $scenario->risk_categories ?? [],
                'extended_params' => $scenario->extended_params ?? [],
                'segment_filter_json' => $scenario->segment_filter_json,
                'management_actions' => $scenario->management_actions ?? [],
                'combination_scenario_ids' => $scenario->combination_scenario_ids ?? [],
                'combination_mode' => $scenario->combination_mode,
                'is_reverse_stress' => $scenario->is_reverse_stress,
                'solver_target' => $scenario->solver_target,
                'solver_threshold' => $scenario->solver_threshold,
                'interest_timing' => $scenario->interest_timing,
                'interest_timing_approved' => (bool) $scenario->interest_timing_approved,
                'parameters' => $scenario->parameters->map(fn ($parameter) => [
                    'id' => $parameter->id,
                    'parameter_key' => $parameter->parameter_key,
                    'parameter_label' => $parameter->parameter_label,
                    'parameter_value' => $parameter->parameter_value,
                    'unit' => $parameter->unit,
                    'category' => $parameter->category,
                    // Whether an engine actually reads this key. Non-drivers (e.g. the
                    // Climate/ESG calibration inputs) are narrative/documentation only
                    // and are badged as such in the UI so they are not mistaken for
                    // transmitting drivers.
                    'drives_engine' => in_array($parameter->parameter_key, \App\Models\Scenario::ENGINE_DRIVER_PARAM_KEYS, true),
                ])->values(),
                'shocks' => $scenario->shocks->map(fn (ScenarioShock $shock) => [
                    'id' => $shock->id,
                    'scenario_id' => $shock->scenario_id,
                    'shock_family' => $shock->shock_family,
                    'macro_variable_id' => $shock->macro_variable_id,
                    'shock_label' => $shock->shock_label,
                    'shock_target' => $shock->shock_target,
                    'target_type' => $shock->target_type,
                    'shock_type' => $shock->shock_type,
                    'shock_value' => $shock->shock_value,
                    'segment_filter_json' => $shock->segment_filter_json,
                    'transmission_path' => $shock->transmission_path,
                    'elasticity' => $shock->elasticity,
                    'lag_years' => $shock->lag_years,
                    'floor_value' => $shock->floor_value,
                    'cap_value' => $shock->cap_value,
                    'year_offset' => $shock->year_offset,
                    'injection_mode' => $shock->injection_mode,
                    'start_year' => $shock->start_year,
                    'end_year' => $shock->end_year,
                    'notes' => $shock->notes,
                    'macro_variable' => $shock->macroVariable ? [
                        'id' => $shock->macroVariable->id,
                        'code' => $shock->macroVariable->code,
                        'name' => $shock->macroVariable->name,
                        'category' => $shock->macroVariable->category,
                        'unit' => $shock->macroVariable->unit,
                    ] : null,
                ])->values(),
                'runs_count' => $scenario->runs_count,
                'created_by' => $scenario->createdBy ? ['name' => $scenario->createdBy->name] : null,
                'approved_by' => $scenario->approvedBy ? ['name' => $scenario->approvedBy->name] : null,
                'approved_at' => $scenario->approved_at?->toDateTimeString(),
                'created_at' => $scenario->created_at->toDateTimeString(),
            ])
            ->values();

        $macroVariables = MacroVariable::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'category',
                'unit',
                'shock_direction',
                'default_mild_shock',
                'default_severe_shock',
                'shock_unit',
            ]);

        $segmentDimensions = SegmentDimension::query()
            ->where('active_flag', true)
            ->with(['nodes' => fn ($query) => $query->select('id', 'dimension_id', 'code', 'name', 'level_no', 'path_string', 'active_flag')])
            ->orderBy('name')
            ->get()
            ->map(fn ($dimension) => [
                'id' => $dimension->id,
                'code' => $dimension->code,
                'name' => $dimension->name,
                'is_hierarchical' => $dimension->is_hierarchical,
                'nodes' => $dimension->nodes
                    ->where('active_flag', true)
                    ->map(fn ($node) => [
                        'id' => $node->id,
                        'code' => $node->code,
                        'name' => $node->name,
                        'level_no' => $node->level_no,
                        'path_string' => $node->path_string,
                    ])
                    ->values(),
            ])
            ->values();

        $stats = [
            'total' => $scenarios->count(),
            'approved' => $scenarios->where('status', 'approved')->count(),
            'draft' => $scenarios->where('status', 'draft')->count(),
            'icaap' => $scenarios->where('is_icaap_scenario', true)->count(),
        ];

        return Inertia::render('Scenarios/Index', [
            'scenarios' => $scenarios,
            'macroVariables' => $macroVariables,
            'segmentDimensions' => $segmentDimensions,
            'shockTypes' => Scenario::SHOCK_TYPES,
            'scenarioTypes' => ReferenceLookup::map('scenario_type'),
            'scenarioFamilies' => ReferenceLookup::map('scenario_family'),
            'targetTypes' => Scenario::TARGET_TYPES,
            'combinationModes' => Scenario::COMBINATION_MODES,
            'managementActionTypes' => Scenario::MANAGEMENT_ACTION_TYPES,
            'parameterLibrary' => ScenarioParameterLibrary::libraryByFamily() ?: Scenario::PARAMETER_LIBRARY,
            'scenarioRoles' => ReferenceLookup::map('scenario_role'),
            'calibrationMethods' => ReferenceLookup::map('calibration_method'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'stats' => $stats,
            'reportPeriods' => $reportPeriods,
            'selectedPeriod' => $selectedPeriod,
            'cycleCurated' => $cycleCurated,
            // The default base year for a NEW scenario, derived from the SAME backend
            // rule the engine uses to pick the stress base (latest actual balance-sheet
            // year - see ScenarioSeeder + IcaapBootstrapCommand::runScenarioReviewChains).
            // Passed to the builder so the UI default never contradicts the engine
            // (was a frontend `new Date().getFullYear() - 1` guess).
            'defaultBaseYear' => (int) (\App\Models\FinancialStatement::query()
                ->where('statement_type', 'balance_sheet')
                ->where('status', 'actual')
                ->max('year') ?: (now()->year - 1)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateScenarioRequest($request);
        // interest_timing_approved is the APPROVER-only flag that gates
        // severe/adverse/reverse official runs (spec 4.4). A maker with only
        // 'manage scenarios' must not self-approve their own timing convention,
        // so a new scenario is always born unapproved unless the actor can
        // 'approve scenarios' (maker-checker split, authoritative server-side).
        $validated['interest_timing_approved'] = Gate::allows('approve scenarios')
            && (bool) ($validated['interest_timing_approved'] ?? false);
        $scenario = $this->definitionService->upsert($validated, null, Auth::id());

        return redirect()->route('scenarios.index')->with('success', "Scenario '{$scenario->name}' created.");
    }

    public function update(Request $request, Scenario $scenario): RedirectResponse
    {
        if ($redirect = $this->lockGuard($scenario)) {
            return $redirect;
        }
        $validated = $this->validateScenarioRequest($request, $scenario);
        // Approver-only flag (see store()): a maker cannot flip interest_timing_approved,
        // so preserve the stored value unless the actor can 'approve scenarios'.
        if (! Gate::allows('approve scenarios')) {
            $validated['interest_timing_approved'] = (bool) $scenario->interest_timing_approved;
        }
        $scenario = $this->definitionService->upsert($validated, $scenario, Auth::id());

        return redirect()->route('scenarios.index')->with('success', "Scenario '{$scenario->name}' updated.");
    }

    public function rename(Request $request, Scenario $scenario): RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $scenario->update(['name' => trim($request->input('name'))]);

        return redirect()->route('scenarios.index')->with('success', "Scenario renamed.");
    }

    public function approve(Scenario $scenario): RedirectResponse
    {
        try {
            $this->governanceService->approve($scenario, Auth::id());
        } catch (\RuntimeException $e) {
            return redirect()->route('scenarios.index')
                ->withErrors(['approve' => $e->getMessage()])
                ->with('error', $e->getMessage());
        }

        // Notify the scenario owner / author that it was approved.
        $owner = \App\Models\User::find($scenario->owner_id ?? $scenario->created_by);
        if ($owner) {
            app(\App\Services\Notifications\ApprovalNotificationService::class)->approvalCompleted($owner, [
                'title'       => 'Approved: scenario',
                'message'     => "Scenario \"{$scenario->name}\" was approved.",
                'event_label' => 'Approval completed',
                'module'      => 'scenario',
                'url'         => route('scenarios.index'),
                'source_type' => Scenario::class,
                'source_id'   => $scenario->id,
            ], $scenario);
        }

        return redirect()->route('scenarios.index')->with('success', "Scenario '{$scenario->name}' approved.");
    }

    public function archive(Scenario $scenario): RedirectResponse
    {
        $scenario->update(['status' => 'archived']);

        return redirect()->route('scenarios.index')->with('success', "Scenario '{$scenario->name}' archived.");
    }

    /**
     * Toggle whether a scenario is in the ICAAP REPORTING SET (report_selected).
     *
     * Distinct from approval: an approved scenario is always CALCULATED (it runs the
     * full engine chain at bootstrap), but only report-selected scenarios flow into
     * the ICAAP pack, the stress-results tables, Table 17 and the consolidated
     * report. The reporting set changes each cycle and differs bank to bank, so it is
     * user-selectable here. Only an approved scenario can be added (you cannot report
     * a draft); it can always be removed.
     */
    public function toggleReporting(Scenario $scenario): RedirectResponse
    {
        if ($scenario->status !== 'approved' && ! $scenario->report_selected) {
            return redirect()->route('scenarios.index')
                ->with('error', "Only an approved scenario can be added to the reporting set - approve \"{$scenario->name}\" first.");
        }

        $scenario->update(['report_selected' => ! $scenario->report_selected]);
        $state = $scenario->report_selected ? 'added to' : 'removed from';

        return redirect()->route('scenarios.index')
            ->with('success', "Scenario '{$scenario->name}' {$state} the ICAAP reporting set.");
    }

    /**
     * Per-cycle override of the reporting set: include/exclude a scenario for ONE
     * reporting cycle only, on top of the global report_selected default. Writing the
     * first override row for a cycle switches that cycle to the override set; a cycle
     * with no overrides keeps using the global flag (golden-safe). Distinct from
     * toggleReporting(), which sets the global default for every cycle.
     */
    public function toggleReportingForCycle(Request $request, Scenario $scenario): RedirectResponse
    {
        $validated = $request->validate([
            'period'   => ['required', 'string', 'max:10'],
            'selected' => ['required', 'boolean'],
        ]);

        if ($scenario->status !== 'approved') {
            return back()->with('error', "Only an approved scenario can be reported - approve \"{$scenario->name}\" first.");
        }

        \App\Models\ScenarioReportCycleOverride::updateOrCreate(
            ['reporting_period' => $validated['period'], 'scenario_id' => $scenario->id],
            ['is_selected' => (bool) $validated['selected'], 'created_by' => Auth::id()],
        );

        $state = $validated['selected'] ? 'included in' : 'excluded from';

        return back()->with('success', "'{$scenario->name}' {$state} the {$validated['period']} reporting set (this cycle only).");
    }

    /**
     * Batch G: explicitly unlock an approved scenario for editing. Reverts it to
     * draft + clears the approval (audited), so the maker-checker cycle is visible
     * and intentional - the analyst unlocks, edits, then re-approves. An official
     * run requires the re-approved status (ValidatesRunPrerequisites).
     */
    public function revertToDraft(Scenario $scenario): RedirectResponse
    {
        if ($scenario->status !== 'approved') {
            return back()->with('error', 'Only an approved scenario can be unlocked for editing.');
        }

        $scenario->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
        \App\Services\ActivityLogger::governance(
            $scenario,
            'scenario_unlocked_for_edit',
            'scenario',
            "Scenario '{$scenario->name}' unlocked for editing (reverted to draft); re-approval required before use in an official run."
        );

        return back()->with('success', "Scenario '{$scenario->name}' unlocked - edit the shocks, then re-approve before using it in an official run.");
    }

    public function clone(Scenario $scenario): RedirectResponse
    {
        $clone = $this->definitionService->clone(
            $scenario->load(['shocks', 'parameters', 'segmentStressRules']),
            Auth::id()
        );

        return redirect()->route('scenarios.index')->with('success', "Scenario cloned as '{$clone->name}'.");
    }

    public function destroy(Scenario $scenario): RedirectResponse
    {
        // An approved (locked) scenario is a sealed audit record; deleting it
        // cascades away its shocks, parameters and entire stress-run history.
        // Guard it exactly like every other mutating scenario action does.
        if ($redirect = $this->lockGuard($scenario)) {
            return $redirect;
        }

        $name = $scenario->name;
        $scenario->delete();

        return redirect()->route('scenarios.index')->with('success', "Scenario '{$name}' deleted.");
    }

    /**
     * The approved-shock lock: an approved scenario is a sealed audit record, so its
     * definition (shocks, parameters, segment rules) cannot be edited in place. The
     * analyst picks one of two explicit paths instead - "Create new version" (this
     * approved record stays immutable, edits happen on a fresh draft) or "Unlock /
     * revert to draft" (amend in place, approval cleared). Returns a redirect when
     * the scenario is locked, or null when the edit may proceed.
     */
    private function lockGuard(Scenario $scenario): ?RedirectResponse
    {
        if (! $this->governanceService->isLocked($scenario)) {
            return null;
        }

        return back()->with('error',
            "Scenario '{$scenario->name}' is approved and locked. Create a new version to edit it "
            . '(the approved version is kept), or unlock it to amend in place.');
    }

    /**
     * Create a new DRAFT version of an approved scenario - a deep copy that is the
     * editable working record, while the approved one stays immutable. Definition-
     * side only (ScenarioDefinitionService); execution is untouched.
     */
    public function newVersion(Scenario $scenario): RedirectResponse
    {
        $version = $this->definitionService->cloneAsNewVersion(
            $scenario->load(['shocks', 'parameters', 'segmentStressRules']),
            Auth::id()
        );
        \App\Services\ActivityLogger::governance(
            $version,
            'scenario_new_version',
            'scenario',
            "New draft version '{$version->name}' (v{$version->version}) forked from approved scenario '{$scenario->name}'; the approved version is unchanged."
        );

        return redirect()->route('scenarios.index')
            ->with('success', "Created '{$version->name}' (v{$version->version}) - edit and approve it; the approved version is unchanged.");
    }

    public function storeShock(Request $request, Scenario $scenario): RedirectResponse
    {
        if ($redirect = $this->lockGuard($scenario)) {
            return $redirect;
        }
        $validated = $this->validateShockRequest($request);
        $this->definitionService->createShock($scenario, $validated);

        return redirect()->route('scenarios.index')->with('success', 'Shock added.');
    }

    public function updateShock(Request $request, Scenario $scenario, ScenarioShock $shock): RedirectResponse
    {
        abort_if($shock->scenario_id !== $scenario->id, 403);
        if ($redirect = $this->lockGuard($scenario)) {
            return $redirect;
        }
        $validated = $this->validateShockRequest($request);
        $shock->update($validated);

        return redirect()->route('scenarios.index')->with('success', 'Shock updated.');
    }

    public function destroyShock(Scenario $scenario, ScenarioShock $shock): RedirectResponse
    {
        abort_if($shock->scenario_id !== $scenario->id, 403);
        if ($redirect = $this->lockGuard($scenario)) {
            return $redirect;
        }
        $shock->delete();

        return redirect()->route('scenarios.index')->with('success', 'Shock removed.');
    }

    public function updateParameterDefault(Request $request, string $family, string $key): RedirectResponse
    {
        $families = array_keys(ReferenceLookup::map('scenario_family'));
        abort_unless(in_array($family, $families, true), 404);

        $validated = $request->validate([
            'default_value' => ['nullable', 'numeric'],
            'label' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:30'],
        ]);

        ScenarioParameterLibrary::updateOrCreate(
            ['family' => $family, 'parameter_key' => $key],
            array_filter($validated, fn ($v) => $v !== null)
        );

        return redirect()->route('scenarios.index')->with('success', 'Parameter default updated.');
    }

    public function export(Scenario $scenario)
    {
        $scenario->load(['shocks.macroVariable', 'parameters']);

        return response()->streamDownload(function () use ($scenario): void {
            $out = fopen('php://output', 'wb');

            fputcsv($out, ['Scenario Export', $scenario->name]);
            fputcsv($out, ['Type', $scenario->scenario_type]);
            fputcsv($out, ['Family', $scenario->scenario_family]);
            fputcsv($out, ['Status', $scenario->status]);
            fputcsv($out, ['Base Year', $scenario->base_year]);
            fputcsv($out, ['Horizon (Years)', $scenario->horizon_years]);
            fputcsv($out, ['Severity', $scenario->severity_index]);
            fputcsv($out, ['ICAAP Scenario', $scenario->is_icaap_scenario ? 'Yes' : 'No']);
            fputcsv($out, ['Risk Categories', implode(', ', $scenario->risk_categories ?? [])]);
            fputcsv($out, ['Combination Mode', $scenario->combination_mode ?: '-']);
            fputcsv($out, ['Reverse Stress', $scenario->is_reverse_stress ? 'Yes' : 'No']);
            fputcsv($out, ['Narrative', $scenario->narrative ?: '-']);
            fputcsv($out, []);

            if ($scenario->parameters->isNotEmpty()) {
                fputcsv($out, ['Parameters']);
                fputcsv($out, ['Category', 'Key', 'Label', 'Value', 'Unit']);
                foreach ($scenario->parameters as $parameter) {
                    fputcsv($out, [
                        $parameter->category,
                        $parameter->parameter_key,
                        $parameter->parameter_label,
                        $parameter->parameter_value,
                        $parameter->unit,
                    ]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Shocks']);
            fputcsv($out, ['Family', 'Label', 'Target', 'Target Type', 'Shock Type', 'Shock Value', 'Year', 'Lag', 'Injection Mode', 'Start Year', 'End Year', 'Macro Variable']);
            foreach ($scenario->shocks as $shock) {
                fputcsv($out, [
                    $shock->shock_family,
                    $shock->shock_label,
                    $shock->shock_target,
                    $shock->target_type,
                    $shock->shock_type,
                    $shock->shock_value,
                    $shock->year_offset,
                    $shock->lag_years,
                    $shock->injection_mode,
                    $shock->start_year ?? '',
                    $shock->end_year ?? '',
                    $shock->macroVariable?->name ?? '-',
                ]);
            }

            fclose($out);
        }, 'scenario_' . $scenario->id . '_' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Phase 2 — canonical shock-targeting preview. For each shock in the
     * scenario, shows how it would resolve against the canonical exposure
     * layer for a chosen cycle: a read-only validation of the targeting
     * before the engine is switched onto the canonical path.
     */
    public function targetingPreview(Request $request, Scenario $scenario): Response
    {
        $cycleId  = $request->integer('cycle') ?: null;
        $resolver = app(\App\Services\CanonicalShockTargetResolver::class);

        $shocks = $scenario->shocks()->get()->map(function (ScenarioShock $shock) use ($resolver, $cycleId): array {
            return [
                'id'           => $shock->id,
                'label'        => $shock->shock_label ?: ('Shock #' . $shock->id),
                'target_type'  => $shock->target_type,
                'shock_target' => $shock->shock_target,
                'shock_type'   => $shock->shock_type,
                'shock_value'  => (float) $shock->shock_value,
                'resolution'   => $resolver->resolve($shock, $cycleId),
            ];
        });

        return Inertia::render('Scenarios/TargetingPreview', [
            'scenario' => [
                'id'     => $scenario->id,
                'name'   => $scenario->name,
                'family' => $scenario->scenario_family,
            ],
            'shocks'        => $shocks,
            'selectedCycle' => $cycleId,
            'cycles'        => \App\Models\RegulatoryIntakeCycle::query()
                ->orderByDesc('as_of_date')->orderByDesc('id')
                ->get(['id', 'label', 'period_code', 'pack_type'])
                ->map(fn ($c): array => [
                    'id'    => $c->id,
                    'label' => "{$c->label} · {$c->period_code} ({$c->pack_type})",
                ]),
            'summary' => [
                'total'     => $shocks->count(),
                'canonical' => $shocks->filter(fn ($s): bool => (bool) $s['resolution']['targetable'])->count(),
                'matched'   => $shocks->filter(fn ($s): bool => ($s['resolution']['matched_count'] ?? 0) > 0)->count(),
            ],
        ]);
    }

    private function validateScenarioRequest(Request $request, ?Scenario $scenario = null): array
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:120', Rule::unique('scenarios', 'name')->ignore($scenario?->id)],
            'scenario_type' => ['required', Rule::in(array_keys(ReferenceLookup::map('scenario_type')))],
            'scenario_role' => ['nullable', Rule::in(array_keys(ReferenceLookup::map('scenario_role')))],
            'scenario_family' => ['nullable', Rule::in(array_keys(ReferenceLookup::map('scenario_family')))],
            'description' => ['nullable', 'string', 'max:2000'],
            'calibration_source' => ['nullable', 'string', 'max:250'],
            'calibration_method' => ['nullable', Rule::in(array_keys(ReferenceLookup::map('calibration_method')))],
            'plausibility_note' => ['nullable', 'string', 'max:2000'],
            'use_test_evidence' => ['nullable', 'string', 'max:2000'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'base_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            // The base period end month (spec E4.3); derived from the base year's
            // latest actual balance sheet when not posted (ScenarioDefinitionService).
            'base_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'horizon_years' => ['required', 'integer', 'min:1', 'max:10'],
            'is_icaap_scenario' => ['boolean'],
            'severity_index' => ['nullable', 'integer', 'min:1', 'max:10'],
            'probability_weight' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'narrative' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'risk_categories' => ['nullable', 'array'],
            'risk_categories.*' => ['string', Rule::in(array_keys(ReferenceLookup::map('scenario_family')))],
            'segment_filter_json' => ['nullable', 'array'],
            'segment_filter_json.dimension_id' => ['nullable', 'integer', 'exists:segment_dimensions,id'],
            'segment_filter_json.node_id' => ['nullable', 'integer', 'exists:segment_nodes,id'],
            'segment_filter_json.dimension_code' => ['nullable', 'string', 'max:60'],
            'segment_filter_json.node_code' => ['nullable', 'string', 'max:80'],
            'combination_scenario_ids' => ['nullable', 'array'],
            'combination_scenario_ids.*' => ['integer', 'exists:scenarios,id'],
            'combination_mode' => ['nullable', Rule::in(array_keys(Scenario::COMBINATION_MODES))],
            'is_reverse_stress' => ['boolean'],
            'solver_target' => ['nullable', 'string', 'max:60'],
            'solver_threshold' => ['nullable', 'numeric'],
            // Interest-impact channel timing (spec 4): eop default; mid/bop are
            // governed options; severe/adverse/reverse official runs need approval.
            'interest_timing' => ['nullable', Rule::in(['bop', 'mid', 'eop'])],
            'interest_timing_approved' => ['boolean'],
            'parameters' => ['nullable', 'array'],
            'parameters.*.parameter_key' => ['required_with:parameters.*.parameter_value', 'string', 'max:80'],
            'parameters.*.parameter_value' => ['nullable', 'numeric'],
            'parameters.*.parameter_label' => ['nullable', 'string', 'max:120'],
            'parameters.*.unit' => ['nullable', 'string', 'max:30'],
            'parameters.*.category' => ['nullable', 'string', 'max:30'],
            'parameters.*.calibration_source' => ['nullable', 'string', 'max:250'],
            'parameters.*.evidence_reference' => ['nullable', 'string', 'max:500'],
            'management_actions' => ['nullable', 'array'],
            'management_actions.*.type' => ['nullable', Rule::in(array_keys(Scenario::MANAGEMENT_ACTION_TYPES))],
            'management_actions.*.description' => ['nullable', 'string', 'max:200'],
            'management_actions.*.year_offset' => ['nullable', 'integer', 'min:1', 'max:10'],
            'management_actions.*.impact_target' => ['nullable', Rule::in(array_keys(Scenario::TARGET_TYPES))],
            'management_actions.*.impact_value' => ['nullable', 'numeric'],
            'management_actions.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function (Validator $validator) use ($request, $scenario): void {
            $type = (string) $request->input('scenario_type');
            $combinationIds = collect($request->input('combination_scenario_ids', []))->filter()->values();

            if ($type === 'combined_scenario' && $combinationIds->isEmpty()) {
                $validator->errors()->add('combination_scenario_ids', 'Combined scenarios need at least one linked scenario.');
            }

            if (($type === 'reverse_stress' || $request->boolean('is_reverse_stress')) && ! $request->filled('solver_target')) {
                $validator->errors()->add('solver_target', 'Reverse stress scenarios require a solver target.');
            }

            if ($scenario && $combinationIds->contains($scenario->id)) {
                $validator->errors()->add('combination_scenario_ids', 'A scenario cannot include itself as a combination layer.');
            }
        });

        return $validator->validate();
    }

    private function validateShockRequest(Request $request): array
    {
        // shock_target must be one the engine subscribes/handles (the fixed engine
        // vocabulary + the governed macro-variable codes). Anything else never bites -
        // reject it with a clear message rather than silently save a no-op target.
        $recognizedTargets = array_values(array_unique(array_merge(
            ScenarioShock::ENGINE_SHOCK_TARGETS,
            \App\Models\MacroVariable::query()->pluck('code')->all(),
        )));

        $validator = validator($request->all(), [
            'macro_variable_id' => ['nullable', 'integer', 'exists:macro_variables,id'],
            'shock_family' => ['nullable', Rule::in(array_keys(ReferenceLookup::map('scenario_family')))],
            'shock_label' => ['required', 'string', 'max:120'],
            'shock_target' => ['required', 'string', 'max:80', Rule::in($recognizedTargets)],
            'target_type' => ['nullable', Rule::in(array_keys(Scenario::TARGET_TYPES))],
            'shock_type' => ['required', Rule::in(array_keys(Scenario::SHOCK_TYPES))],
            'shock_value' => ['required', 'numeric'],
            'segment_filter_json' => ['nullable', 'array'],
            'segment_filter_json.dimension_id' => ['nullable', 'integer', 'exists:segment_dimensions,id'],
            'segment_filter_json.node_id' => ['nullable', 'integer', 'exists:segment_nodes,id'],
            'segment_filter_json.dimension_code' => ['nullable', 'string', 'max:60'],
            'segment_filter_json.node_code' => ['nullable', 'string', 'max:80'],
            'transmission_path' => ['nullable', 'array'],
            'transmission_path.*' => ['nullable', 'string', 'max:80'],
            'elasticity' => ['nullable', 'numeric', 'min:-20', 'max:20'],
            'lag_years' => ['nullable', 'integer', 'min:0', 'max:10'],
            'floor_value' => ['nullable', 'numeric'],
            'cap_value' => ['nullable', 'numeric'],
            'year_offset' => ['required', 'integer', 'min:1', 'max:10'],
            'injection_mode' => ['nullable', Rule::in(['one_off', 'sustained', 'compounding'])],
            'start_year' => ['nullable', 'integer', 'min:1', 'max:10'],
            'end_year' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:255'],
            'calibration_source' => ['nullable', 'string', 'max:250'],
            'calibration_basis' => ['nullable', 'string', 'max:40'],
        ]);

        $validator->after(function (Validator $validator) use ($request): void {
            $targetType = (string) $request->input('target_type');
            $segmentScopedTypes = ['sector', 'geography', 'segment', 'customer_group', 'top_exposure_group', 'currency', 'funding_source'];
            $segmentFilter = $request->input('segment_filter_json');

            if (in_array($targetType, $segmentScopedTypes, true) && empty($segmentFilter)) {
                $validator->errors()->add('segment_filter_json', 'This target type requires a segment scope.');
            }

            if ($request->filled('floor_value') && $request->filled('cap_value') && (float) $request->input('floor_value') > (float) $request->input('cap_value')) {
                $validator->errors()->add('floor_value', 'Floor value cannot be above the cap value.');
            }

            if ($request->filled('start_year') && $request->filled('end_year') && (int) $request->input('start_year') > (int) $request->input('end_year')) {
                $validator->errors()->add('end_year', 'End year cannot be before the start year.');
            }
        });

        return $validator->validate();
    }
}
