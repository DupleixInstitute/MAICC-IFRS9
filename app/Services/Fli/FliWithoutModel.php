<?php

namespace App\Services\Fli;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * A month-end with no forward-looking model version in force (FLI module
 * specification D-F1, note of 14 October 2026).
 *
 * Until 14 October 2026 the screens and the bootstrap disagreed: the
 * Maintenance month-end (fli:month-end --all) stopped at "No model version is
 * in force" and the Overview hid its buttons, while the bootstrap applied the
 * route with no fit ("the PD holds") and booked the ECL. One governed setting
 * now decides for all of them (Governance Centre, fli_without_model):
 *
 *  - PROCEED (the seeded technical default, what the bootstrap did): every step
 *    runs; the route writes each scenario of the set at the PD before FLI, the
 *    projection is skipped, the ECL is booked, and the month record carries
 *    "no forward-looking model in force", is disclosed as such, and needs a
 *    commentary to be signed off;
 *  - REFUSE: the month-end, the route, the record and the bootstrap stop for
 *    the month with the reason.
 *
 * A month with no approved value of the setting, or a value that is neither
 * option, stops (fail closed), naming the setting.
 */
class FliWithoutModel
{
    public const KEY = 'fli_without_model';
    public const PROCEED = 'Proceed: the PD holds with no forward-looking adjustment, recorded and disclosed; sign-off needs a commentary';
    public const REFUSE = 'Refuse the month-end until a model version is in force';
    /** What the month record, the screens and the reports say. */
    public const NOTE = 'No forward-looking model in force';
    public const PROJECTION_SKIPPED = 'no model in force: skipped, the PD holds';

    private FliModelRegistry $registry;

    public function __construct(private GovernanceService $governance, ?FliModelRegistry $registry = null)
    {
        $this->registry = $registry ?? new FliModelRegistry();
    }

    /**
     * Whether the month-end may run for a month, and what to say.
     *
     * @return array{model:?object,in_force:bool,proceed:bool,setting:?string,message:?string}
     */
    public function decide(string $period): array
    {
        $model = $this->registry->inForce($period);
        if ($model !== null) {
            return ['model' => $model, 'in_force' => true, 'proceed' => true, 'setting' => null, 'message' => null];
        }
        try {
            $value = $this->governance->get(self::KEY, CarbonImmutable::parse($period . '-01')->endOfMonth());
        } catch (Throwable) {
            $value = null;
        }
        $label = GovernanceService::catalogue()[self::KEY]['label'];
        $message = match ($value) {
            self::PROCEED => "No forward-looking model version is in force for {$period}. Under the governed setting ({$label}: Proceed) the PD holds with no forward-looking adjustment: the month-end runs, the record says \"" . self::NOTE . '", the IFRS 9 note discloses it, and the sign-off needs a commentary saying so. Approving a fit on Regression publishes a version.',
            self::REFUSE => "No forward-looking model version is in force for {$period}, and the governed setting ({$label}, fli_without_model) refuses the month-end until one is: approve a fit on Regression, which publishes a version, then run the month-end again.",
            default => "No forward-looking model version is in force for {$period}, and no value of the setting '{$label}' (fli_without_model) is approved for the month" . ($value !== null ? " ('{$value}' is not one of its options)" : '') . ', so the month-end stops: approve the setting on the Governance Centre, or publish a model version.',
        };

        return ['model' => null, 'in_force' => false, 'proceed' => $value === self::PROCEED, 'setting' => $value, 'message' => $message];
    }

    /**
     * Stop when the month has no model in force and the setting does not let it proceed.
     *
     * @return array{model:?object,in_force:bool,proceed:bool,setting:?string,message:?string}
     * @throws RuntimeException naming the reason
     */
    public function assertMayRun(string $period): array
    {
        $d = $this->decide($period);
        if (! $d['proceed']) {
            throw new RuntimeException($d['message']);
        }

        return $d;
    }

    /**
     * The disclosure for a month whose PD held for want of a model: from the
     * month record when it says so, else from the loans (the route applied
     * under the regression route with no model version on any loan). Null when
     * a model was in force or the route has not run.
     */
    public static function disclosure(string $period): ?string
    {
        $without = false;
        if (Schema::hasTable('fli_run_snapshots')) {
            $inputs = DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->value('inputs');
            $without = ! empty((json_decode((string) $inputs, true) ?: [])['without_model']);
        }
        if (! $without && Schema::hasTable('loan_books') && Schema::hasColumn('loan_books', 'fli_model_version_id') && Schema::hasColumn('loan_books', 'fli_route')) {
            $r = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_post_fli')
                ->selectRaw("count(*) n, sum(case when fli_model_version_id is null and fli_route like 'Regression%' then 1 else 0 end) held")->first();
            $without = $r && (int) $r->n > 0 && (int) $r->held === (int) $r->n;
        }

        return $without
            ? self::NOTE . ' at ' . $period . ': no forward-looking model version was in force, so under the governed setting the PD of each loan held at its value before forward-looking information, in every scenario of the approved set; the allowance carries no macro-economic adjustment (IFRS 9 5.5.17(c)).'
            : null;
    }
}
