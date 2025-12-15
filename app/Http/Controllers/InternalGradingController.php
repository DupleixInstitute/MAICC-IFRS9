<?php

namespace App\Http\Controllers;
use App\Models\InternalGradeProfile;
use App\Models\InternalGradeMapping;
use App\Models\LoanBook;
use App\Models\LoanPortfolio;
use App\Models\IndustryType;
use App\Models\ReportingPeriods;
use Carbon\Carbon;
use App\Models\GradeTenorPd;
use App\Services\AuditLoggerService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Http\Request;

class InternalGradingController extends Controller
{
    /**
     * List grades for a profile
     */
   public function profiles()
    {
        return Inertia::render('InternalGrading/Index', [
            'profiles' => InternalGradeProfile::withCount('mappings')
                ->orderBy('name')
                ->get(),
            'portfolios' => LoanPortfolio::select('id', 'name')->orderBy('name')->get(),
            'sectors' => IndustryType::select('code', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * List grades for a profile
     */
    public function index(InternalGradeProfile $profile)
    {
        return Inertia::render('InternalGrading/Grades', [
            'profile' => $profile,
            'grades' => $profile->mappings()
                ->with('tenorPds')
                ->orderBy('grade_code')
                ->get(),

        ]);
    }
    /**
     * Create a new grading profile
     */
    public function storeProfile(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'max_tenor_years' => 'required|integer|min:1',
        ]);

        InternalGradeProfile::create($validated);

        return back()->with('success', 'Profile created successfully');
    }

    /**
     * Store a grade + PD curve
     */
    public function storeGrade(Request $request, InternalGradeProfile $profile)
    {
        $request->validate([
            'grade_code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('internal_grade_mappings')
                    ->where('profile_id', $profile->id),
            ],
            'grade_name'  => 'required|string|max:255',
            'sp_rating'   => 'nullable|string|max:50',
            'rbm_class'   => 'nullable|string|max:50',
            'lower_bound' => 'nullable|numeric',
            'upper_bound' => 'nullable|numeric',

            'tenor_pds' => 'required|array|min:1',
            'tenor_pds.*.tenor_years'     => 'required|integer|min:1',
            'tenor_pds.*.pd_probability'  => 'required|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($request, $profile) {

            $mapping = $profile->mappings()->create(
                $request->only([
                    'grade_name',
                    'grade_code',
                    'sp_rating',
                    'rbm_class',
                    'lower_bound',
                    'upper_bound',
                ])
            );

            $mapping->tenorPds()->createMany(
                collect($request->tenor_pds)->map(fn ($pd) => [
                    'tenor_years'    => $pd['tenor_years'],
                    'pd_probability' => $pd['pd_probability'],
                ])->toArray()
            );
        });

        return redirect()
            ->route('internal-grading.grades', $profile)
            ->with('success', 'Grade and PD curve saved successfully');
    }



    public function updateGrade( Request $request, InternalGradeProfile $profile, InternalGradeMapping $grade)
     {
        // Safety: grade must belong to profile
        abort_unless($grade->profile_id === $profile->id, 403);

        $validated = $request->validate([
            'grade_code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('internal_grade_mappings')
                    ->where('profile_id', $profile->id)
                    ->ignore($grade->id),
            ],
            'grade_name'  => 'required|string|max:255',
            'sp_rating'   => 'nullable|string|max:50',
            'rbm_class'   => 'nullable|string|max:50',
            'lower_bound' => 'nullable|numeric',
            'upper_bound' => 'nullable|numeric',

            'tenor_pds' => 'required|array|min:1',
            'tenor_pds.*.tenor_years'    => 'required|integer|min:1',
            'tenor_pds.*.pd_probability' => 'required|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($validated, $grade) {

            // 1️⃣ Update grade metadata
            $grade->update(collect($validated)->except('tenor_pds')->toArray());

            // 2️⃣ Sync PD term structure
            $existing = $grade->tenorPds()->get()->keyBy('tenor_years');

            foreach ($validated['tenor_pds'] as $pd) {
                $existingPd = $existing->get($pd['tenor_years']);

                if ($existingPd) {
                    $existingPd->update([
                        'pd_probability' => $pd['pd_probability'],
                    ]);
                } else {
                    $grade->tenorPds()->create($pd);
                }
            }

            // 3️⃣ Remove PDs beyond profile max tenor (optional safety)
            $grade->tenorPds()
                ->where('tenor_years', '>', $grade->profile->max_tenor_years)
                ->delete();
        });

        return back()->with('success', 'Grade updated successfully');
    }

        public function toggle(InternalGradeProfile $profile)
        {
            DB::transaction(function () use ($profile) {

                if (! $profile->is_active) {
                    InternalGradeProfile::where('is_active', true)
                        ->update(['is_active' => false]);
                }

                $profile->update([
                    'is_active' => ! $profile->is_active
                ]);
            });

            return back();
        }



        public function matrix(InternalGradeProfile $profile)
        {
            $profile->load([
                'mappings.tenorPds' => function ($q) {
                    $q->orderBy('tenor_years');
                }
            ]);

            $maxTenor = $profile->max_tenor_years ?? 5;

            $grades = $profile->mappings->map(function ($mapping) use ($maxTenor) {

                $pdMap = $mapping->tenorPds
                    ->keyBy('tenor_years')
                    ->map(fn ($pd) => $pd->pd_probability);

                return [
                    'id' => $mapping->id,
                    'grade_code' => $mapping->grade_code,
                    'grade_name' => $mapping->grade_name,
                    'pds' => collect(range(1, $maxTenor))->mapWithKeys(
                        fn ($year) => [$year => $pdMap[$year] ?? null]
                    ),
                ];
            });

            return response()->json([
                'profile' => [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'is_active' => $profile->is_active,
                    'max_tenor_years' => $maxTenor,
                ],
                'grades' => $grades,
                'maxTenor' => $maxTenor,
            ]);

        }

   public function updateLoanBookWithPD(Request $request)
        {
            ini_set('max_execution_time', 300);
            $startTime = microtime(true);

            // ✅ VALIDATION
            $validated = $request->validate([
                'reporting_period' => 'required|date_format:Y-m',
                'profile_id'      => 'required|exists:internal_grade_profiles,id',
                'scope' => 'required|in:portfolio,sector',
                'portfolio_id' => 'required_if:scope,portfolio',
                'sector_code'  => 'required_if:scope,sector',
            ]);

            $profile = InternalGradeProfile::with('mappings.tenorPds')->findOrFail($validated['profile_id']);
            $period  = Carbon::parse($validated['reporting_period'])->format('Y-m');
            $scope   = $validated['scope'];

            // Prevent active profile updates if needed
            if (! $profile->is_active) {
                return back()->with('error', 'Profile must be active before updating loan books.');
            }


            $rowsUpdated = 0;

            // ✅ FETCH LOANS scoped by portfolio or sector
            $loansQuery = LoanBook::whereRaw("LEFT(reporting_period,7) = ?", [$period]);

            if ($scope === 'sector') {
                    $loansQuery->where('industry_code', $validated['sector_code']);
                }
                elseif ($scope === 'portfolio') {
                    $loansQuery->where('loan_portfolio_id', $validated['portfolio_id']);
                }

            $loans = $loansQuery->get();

            DB::transaction(function () use ($loans, $profile, &$rowsUpdated) {

                foreach ($loans as $loan) {

                    $pd = 0;
                    $stage = trim((string) $loan->ifrs9stage_pre_qualitative);

                    Log::debug('PD CALC START', [
                        'loan_id' => $loan->id,
                        'stage_raw' => $loan->ifrs9stage_pre_qualitative,
                        'stage_normalized' => $stage,
                        'grade_code' => $loan->internal_grade_code,
                        'remaining_tenor' => $loan->remaining_tenor,
                    ]);

                    // 🔗 Map using internal_grade_code
                    $mapping = $profile->mappings
                        ->firstWhere('grade_code', $loan->internal_grade_code);

                    Log::debug('GRADE MAPPING CHECK', [
                        'loan_id' => $loan->id,
                        'mapping_found' => (bool) $mapping,
                        'mapping_grade' => $mapping?->grade_code,
                    ]);

                    // ✅ STAGE 3 — ALWAYS PD = 1
                    if ($stage === '3') {

                        $pd = 1.0;

                        Log::info('STAGE 3 PD SET', [
                            'loan_id' => $loan->id,
                            'pd' => $pd,
                        ]);
                    }

                    // Stage 1 & 2 require mapping
                    elseif ($mapping) {

                        if ($stage === '1') {

                            $pd = $mapping->tenorPds
                                ->firstWhere('tenor_years', 1)
                                ?->pd_probability ?? 0;

                            Log::info('STAGE 1 PD SET', [
                                'loan_id' => $loan->id,
                                'pd' => $pd,
                            ]);
                        }

                        elseif ($stage === '2') {

                            $pd = $mapping->tenorPds
                                ->firstWhere('tenor_years', (int)$loan->remaining_tenor)
                                ?->pd_probability ?? 0;

                            Log::info('STAGE 2 PD SET', [
                                'loan_id' => $loan->id,
                                'pd' => $pd,
                            ]);
                        }
                    }
                    else {
                        Log::warning('NO GRADE MAPPING FOR STAGE 1/2', [
                            'loan_id' => $loan->id,
                            'stage' => $stage,
                            'grade_code' => $loan->internal_grade_code,
                        ]);
                    }

                    $oldPd = $loan->pd_prefli;

                    $loan->update([
                        'pd_prefli' => $pd,
                        //'internal_grade_profile_id' => $profile->id,
                    ]);

                    Log::info('PD UPDATED', [
                        'loan_id' => $loan->id,
                        'old_pd' => $oldPd,
                        'new_pd' => $pd,
                    ]);

                    // 🚨 Hard guard for IFRS 9
                    if ($stage === '3' && $pd !== 1.0) {
                        Log::error('STAGE 3 PD VIOLATION', [
                            'loan_id' => $loan->id,
                            'pd' => $pd,
                        ]);
                    }

                    $rowsUpdated++;
                }
            });

            $timeTaken = round((microtime(true) - $startTime) / 60, 2);

            ReportingPeriods::updateOrCreate(
                ['period' => $period . '-01'],
                [
                    'reporting_year' => (int)substr($period, 0, 4),
                    'reporting_month' => (int)substr($period, 5, 2),
                    'internal_grade_profile_id' => $profile->id,
                    'internal_grade_calculation_time' => $timeTaken,
                ]
            );

            return back()->with(
                'success',
                "Loan books updated successfully in {$timeTaken} minutes. Rows updated: {$rowsUpdated}"
            );
        }

}
