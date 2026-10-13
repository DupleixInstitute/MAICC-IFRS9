<?php

namespace App\Services\Fli;

use App\Services\Eir\GovernanceService;
use App\Support\Fli\AsAtSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The forward-looking month-end, step by step (FLI module specification v1,
 * section 5 and decision D-F12): what each step needs, whether it is done
 * for the month, and what to do next. The Overview screen shows it and the
 * fli:month-end command follows it; an official close is refused until every
 * step before it is done.
 *
 * Each step returns: key, title, state (done, todo, blocked, warn, optional),
 * a one-line status, the figures behind it, the screen that does it and, for
 * a step the system runs, the command.
 */
class FliMonthEnd
{
    public function __construct(private FliModelRegistry $registry, private FliCloseService $close, private FliProjectionService $projection, private GovernanceService $governance)
    {
    }

    /** @return list<array<string,mixed>> */
    public function steps(string $period): array
    {
        $ym = AsAtSeries::ym($period);
        $book = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("count(*) n, sum(pd_prefli is not null) with_pd, sum(coalesce(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) is not null) staged, sum(pd_post_fli is not null) post, max(ecl_calculated_at) ecl_at")
            ->first();
        $steps = [];

        // 1. the month's loan book, staged, with a PD before FLI
        $ok = $book && $book->n > 0 && (int) $book->with_pd === (int) $book->n;
        $steps[] = $this->step('book', 'Loan book staged, PD before FLI', $book && $book->n > 0 ? ($ok ? 'done' : 'blocked') : 'blocked',
            $book && $book->n > 0 ? number_format((int) $book->n) . ' loans, ' . number_format((int) $book->staged) . ' staged, ' . number_format((int) $book->with_pd) . ' with a PD before FLI' : 'No loan book for this month: build it on Imports & Feeds or Maintenance.',
            'maintenance.index', null);

        // 2. the economic data known at the month-end
        $series = AsAtSeries::macroRows(null, $ym, ['actual', 'estimate', 'forecast'], null);
        $actualSeries = 0; $forecasts = 0; $stale = [];
        foreach ($series as $code => $rows) {
            $actual = array_keys(array_filter($rows, fn ($r) => ($r['type'] ?? '') === 'actual'));
            $actualSeries += $actual === [] ? 0 : 1;
            $forecasts += count(array_filter($rows, fn ($r) => ($r['type'] ?? '') === 'forecast'));
            if ($actual !== [] && AsAtSeries::monthsBetween((string) max($actual), $ym) > 15) {
                $stale[] = $code;
            }
        }
        $steps[] = $this->step('macro', 'Economic data as at the month-end', $actualSeries === 0 ? 'blocked' : ($forecasts === 0 || $stale !== [] ? 'warn' : 'done'),
            $actualSeries === 0 ? 'No macro series is known at this month-end: import the series on Macro Statistics.'
                : "{$actualSeries} series with actuals, {$forecasts} forecast values knowable at the month-end" . ($forecasts === 0 ? '; no published forecast is dated on or before it, so the scenario paths carry the last actual forward' : '') . ($stale !== [] ? '; last actual more than 15 months old for ' . implode(', ', array_slice($stale, 0, 5)) : ''),
            'macro-statistics.index', null, ['series' => $actualSeries, 'forecasts' => $forecasts]);

        // 3. the credit-loss history the model is calibrated on
        $proxies = DB::table('credit_loss_series')->where('observation_period', '<=', $ym)->selectRaw('count(distinct proxy_code) p, count(*) n, max(observation_period) last')->first();
        $external = DB::table('credit_loss_series')->where('proxy_code', 'like', 'EXT\_%')->distinct()->count('proxy_code');
        $steps[] = $this->step('losses', 'Credit-loss history', ($proxies->n ?? 0) > 0 ? (($proxies->last ?? '') === $ym ? 'done' : 'warn') : 'todo',
            ($proxies->n ?? 0) > 0 ? "{$proxies->p} measures, last month " . substr((string) $proxies->last, 0, 4) . '-' . substr((string) $proxies->last, 4, 2) . ($external ? ", {$external} external series" : ', no external (industry) series yet') . (($proxies->last ?? '') !== $ym ? '; refresh it with the correlation sweep' : '')
                : 'No credit-loss measure yet: the correlation sweep derives them from the staged loan books.',
            'credit-loss-data.index', 'fli-correlate');

        // 4. the model version in force
        $version = $this->registry->inForce($period);
        $cadence = $this->setting('fli_model_cadence', $period) ?? '';
        $age = $version ? AsAtSeries::monthsBetween(AsAtSeries::ym($version->effective_from), $ym) : null;
        $monthly = str_starts_with($cadence, 'Every month');
        $vState = $version === null ? 'blocked' : (($monthly && $version->effective_from !== $period) ? 'blocked' : ($age !== null && $age >= 12 ? 'warn' : 'done'));
        $steps[] = $this->step('model', 'Model version in force', $vState,
            $version === null ? 'No model version is in force: run the correlation sweep, then propose and approve a fit on Regression; approval publishes it.'
                : "Version {$version->version}: {$version->statistic_code} to {$version->proxy_code}, lag {$version->lag_months} months, R-squared " . number_format((float) $version->r_squared, 3) . ", in force since {$version->effective_from}"
                    . ($monthly && $version->effective_from !== $period ? '; the governed cadence asks for a version every month' : '')
                    . ($age !== null && $age >= 12 ? '; more than twelve months old, due for its annual review' : ''),
            'fli-models.index', null, ['version_id' => $version?->id]);

        // 5. the scenario set approved for the month
        $set = DB::table('governed_scenario_sets')->where('reporting_period', $period)->orderByDesc('version')->first();
        $approved = DB::table('governed_scenario_sets')->where('reporting_period', $period)->whereIn('status', ['APPROVED', 'LOCKED'])->orderByDesc('version')->first();
        $weights = $approved ? DB::table('governed_scenarios')->where('set_id', $approved->id)->orderBy('order_position')->get(['name', 'weight'])->map(fn ($s) => $s->name . ' ' . rtrim(rtrim(number_format((float) $s->weight, 2), '0'), '.'))->implode(', ') : null;
        $steps[] = $this->step('set', 'Scenario set approved', $approved ? 'done' : ($set ? 'todo' : 'blocked'),
            $approved ? "{$approved->name}, version {$approved->version} ({$approved->status}): {$weights}"
                : ($set ? "Version {$set->version} is {$set->status}: a second person approves it on Scenario Sets." : 'No scenario set for this month: start one from a profile on Scenario Profiles, or on Scenario Sets.'),
            'scenario-sets.index', null, ['set_id' => $approved?->id, 'pending_set_id' => $approved ? null : $set?->id]);

        // 6. overlays reviewed
        $pending = DB::table('fli_overlays')->where('reporting_period', $period)->where('status', 'PROPOSED')->count();
        $inForce = DB::table('fli_overlays')->where('status', 'APPROVED')->where('reporting_period', '<=', $period)->where(fn ($q) => $q->whereNull('expiry_period')->orWhere('expiry_period', '>=', $period))->count();
        $steps[] = $this->step('overlays', 'Overlays reviewed', $pending > 0 ? 'todo' : 'done',
            ($pending > 0 ? "{$pending} overlay(s) proposed and waiting for a second person; " : 'Nothing waiting; ') . "{$inForce} approved overlay(s) in force.",
            'fli-overlays.index', null);

        // 7. the route applied to every loan under the version and the set in force
        $applied = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')
            ->selectRaw('count(*) n, sum(fli_set_id = ?) on_set, sum(fli_model_version_id = ?) on_version, sum(pd_post_fli is not null) post', [(int) ($approved->id ?? 0), (int) ($version->id ?? 0)])->first();
        $appliedAt = DB::table('audit_logs')->where('action', 'FLI Route Applied')->where('reporting_period', $period)->max('created_at');
        // done only when every loan carries a post-FLI PD under the set and version in force,
        // written after the latest PD run (a new PD run clears the post-FLI PD)
        $pdRunAt = DB::getSchemaBuilder()->hasTable('segment_parameter_runs')
            ? DB::table('segment_parameter_runs')->whereIn('run_id', DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_segment_run_id')->distinct()->pluck('pd_segment_run_id'))->max('created_at') : null;
        $applyDone = $approved && $version && $applied->n > 0 && (int) $applied->on_set === (int) $applied->n && (int) $applied->on_version === (int) $applied->n
            && (int) $applied->post === (int) $applied->n && ! ($pdRunAt && $appliedAt && $appliedAt < $pdRunAt);
        $steps[] = $this->step('apply', 'Forward-looking route applied', $applyDone ? 'done' : (($approved && $version) ? 'todo' : 'blocked'),
            $applyDone ? number_format((int) $applied->n) . ' loans carry the post-FLI PD under version ' . $version->version . ' and set ' . $approved->id . ($appliedAt ? ", applied {$appliedAt}" : '')
                : (($approved && $version) ? ((int) $applied->post < (int) $applied->n ? number_format((int) $applied->n - (int) $applied->post) . ' loans have no post-FLI PD (a new PD run clears it): apply the route.' : number_format((int) ($applied->on_set ?? 0)) . ' of ' . number_format((int) $applied->n) . ' loans are on the set and version in force: apply the route.') : 'Needs the model version and the scenario set first.'),
            'fli.external.index', 'fli-apply');

        // 8. the scenario projection (evidence; does not block the close)
        $proj = DB::table('ecl_projection_runs')->where('reporting_period', $period)->where('methodology_version', FliProjectionService::SOURCE)->where('status', 'COMPLETED')->orderByDesc('id')->first();
        $projCurrent = $proj && $appliedAt && $proj->completed_at >= $appliedAt;
        $steps[] = $this->step('project', 'Scenario projection (term structure)', $projCurrent ? 'done' : ($applyDone ? 'optional' : 'blocked'),
            $proj ? 'Projected ' . $proj->completed_at . ($projCurrent ? '' : ', before the route last ran: project again') : 'Not projected yet: the year-by-year PD of each portfolio under each scenario.',
            'forecasting.manual', 'fli-project');

        // 9. the ECL on the post-FLI PD, after the route
        $eclDone = $applyDone && $book->ecl_at && $appliedAt && CarbonImmutable::parse($book->ecl_at)->gte(CarbonImmutable::parse($appliedAt));
        $steps[] = $this->step('ecl', 'ECL calculated on the post-FLI PD', $eclDone ? 'done' : ($applyDone ? 'todo' : 'blocked'),
            $eclDone ? 'Calculated ' . $book->ecl_at : ($applyDone ? 'The route ran after the last ECL calculation: calculate the ECL.' : 'Needs the route applied first.'),
            'expected-credit-loss.index', 'ecl');

        // 10. recorded and signed off
        $snap = $this->close->latest($period);
        $snapCurrent = $snap && $eclDone && $snap['created_at'] >= $book->ecl_at;
        $state = ! $eclDone ? 'blocked' : (! $snapCurrent ? 'todo' : ($snap['status'] === 'SIGNED_OFF' ? 'done' : 'todo'));
        $steps[] = $this->step('close', 'Month recorded and signed off', $state,
            ! $snapCurrent ? ($eclDone ? 'Record the month: the ECL under each scenario, the reconciliation and what it ran under.' : 'Needs the ECL first.')
                : ($snap['status'] === 'SIGNED_OFF' ? 'Signed off by ' . ($snap['signer'] ?? $snap['approver_label'] ?? 'a second person') . ' on ' . $snap['signed_off_at']
                    : 'Recorded ' . $snap['created_at'] . ($snap['reconciles'] ? ', weighted ECL ties to the booked ECL' : ', the weighted ECL does NOT tie to the booked ECL') . ($snap['needs_commentary'] ? '; the movement needs a commentary' : '') . ': a second person signs it off.'),
            'fli.overview', 'fli-close', ['snapshot_id' => $snap['id'] ?? null]);

        return $steps;
    }

    /** The first step that is not done, ignoring the optional one. */
    public function next(array $steps): ?array
    {
        foreach ($steps as $s) {
            if (! in_array($s['state'], ['done', 'optional', 'warn'], true)) {
                return $s;
            }
        }

        return null;
    }

    private function step(string $key, string $title, string $state, string $status, ?string $screen, ?string $command, array $extra = []): array
    {
        return ['key' => $key, 'title' => $title, 'state' => $state, 'status' => $status, 'screen' => $screen, 'command' => $command] + $extra;
    }

    private function setting(string $key, string $period): ?string
    {
        try {
            return $this->governance->get($key, CarbonImmutable::parse($period . '-01')->endOfMonth());
        } catch (Throwable) {
            return null;
        }
    }
}
