<?php

namespace App\Http\Controllers;

use App\Models\InternalGradeProfile;
use App\Services\Ifrs9\SpBasisEclService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

/** The S&P basis screen: the December 2025 model's PD method beside the system's ECL, for comparison only (spec v4 section 17). */
class SpBasisController extends Controller
{
    public function index(Request $request, SpBasisEclService $service)
    {
        $periods = DB::table('loan_books')->whereNotNull('lgd_value')->where('ead', '>', 0)->distinct()->orderByDesc('reporting_period')->pluck('reporting_period')->all();
        $period = $request->input('period') ?: (DB::table('sp_basis_runs')->orderByDesc('id')->value('reporting_period') ?: ($periods[0] ?? null));
        $run = $period ? DB::table('sp_basis_runs')->where('reporting_period', $period)->orderByDesc('id')->first() : null;
        try {
            $settings = $service->settings();
            $settingsError = null;
        } catch (Throwable $e) {
            $settings = null;
            $settingsError = $e->getMessage();
        }

        return Inertia::render('InternalGrading/SpBasis', [
            'periods' => $periods,
            'period' => $period,
            'run' => $run ? [
                'id' => $run->id, 'reporting_period' => $run->reporting_period, 'profile_name' => $run->profile_name, 'settings' => json_decode($run->settings, true),
                'loans' => $run->loans, 'ead' => (float) $run->ead, 'ecl' => (float) $run->ecl, 'system_ecl' => (float) $run->system_ecl,
                'by_stage' => json_decode($run->by_stage, true), 'notes' => json_decode($run->notes ?? '[]', true), 'created_at' => (string) $run->created_at,
            ] : null,
            'results' => $run ? DB::table('sp_basis_results')->where('run_id', $run->id)->orderByDesc('ecl')->get() : [],
            'settings' => $settings,
            'settingsError' => $settingsError,
            'activeProfile' => InternalGradeProfile::where('is_active', true)->value('name'),
            'canRun' => (bool) $request->user()?->can('settings'),
        ]);
    }

    public function run(Request $request, SpBasisEclService $service)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        try {
            $r = $service->run($data['period'], (int) $request->user()->id);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('internal-grading.sp-basis', ['period' => $data['period']])
            ->with('success', "S&P basis for {$data['period']}: {$r['loans']} loans, ECL " . number_format($r['ecl'], 2) . ' against the system\'s ' . number_format($r['system_ecl'], 2) . ' (undiscounted). No booked figure changed.');
    }
}
