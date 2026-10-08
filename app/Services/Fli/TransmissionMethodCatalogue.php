<?php

namespace App\Services\Fli;

use App\Services\Eir\GovernanceService;
use App\Support\Fli\AdjustmentMethods;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * How a forward-looking adjustment reaches a loan's PD (spec v4 section
 * 14.7): the governed transmission methods, each with a method card the
 * system explains in the screen, in the Governance Centre, in the help
 * centre and in the audit workbook, in the same words.
 *
 * A card has five parts: what the method does in plain language; the
 * formula with its symbols named; what it implies; its preconditions,
 * checked live against MAIIC's data with the figure that decides each; and
 * a worked example on one real loan of the book. A method whose
 * preconditions are not met cannot be selected, and the card says what has
 * to be true before it can be. Whichever method is in force: Stage 3 is
 * 100 percent, Stage 1 takes the 12-month window and Stage 2 the lifetime
 * window, and the result is floored at 0 and capped at 100 percent.
 */
class TransmissionMethodCatalogue
{
    public const SCALAR = 'Multiplicative scalar on the proxy ratio';
    public const SEGMENT = 'Segment-specific scalar';
    public const REFERENCE = 'One of the nine reference methods';
    public const LOGIT = 'Logit-linear PD model';
    public const VASICEK = 'Vasicek single-factor Z-shift';

    public function __construct(private GovernanceService $governance)
    {
    }

    /** The method in force, from the Governance Centre. */
    public function inForce(): string
    {
        try {
            return $this->governance->get('fli_transmission_method');
        } catch (Throwable) {
            return self::SCALAR;
        }
    }

    /** @return list<array{key:string,title:string,seeded:bool,what:string,formula:string,symbols:array,implies:list<string>,preconditions:list<array{name:string,met:bool,figure:string}>,available:bool,example:?array,in_force:bool}> */
    public function cards(?string $period = null): array
    {
        $period ??= (string) (DB::table('loan_books')->whereNotNull('pd_prefli')->max('reporting_period') ?? DB::table('loan_books')->max('reporting_period'));
        $facts = $this->facts($period);
        $inForce = $this->inForce();
        $out = [];
        foreach ($this->texts() as $key => $t) {
            $pre = $this->preconditions($key, $facts);
            $available = array_reduce($pre, fn ($ok, $p) => $ok && $p['met'], true);
            $out[] = $t + [
                'key' => $key, 'seeded' => $key === self::SCALAR, 'preconditions' => $pre, 'available' => $available,
                'example' => $available && $facts['example_loan'] ? $this->example($key, $facts) : null, 'in_force' => $key === $inForce, 'period' => $period,
            ];
        }

        return $out;
    }

    /**
     * Apply a method to one loan's pre-FLI PD. Returns the post-FLI PD, floored
     * at 0 and capped at 1, or null when an input the method needs is
     * undefined, never a fabricated figure.
     *
     * @param  array{adjustment?:float,segment_adjustment?:float,reference_method?:string,reference_ctx?:array,alpha?:float,beta?:float,macro?:float,rho?:float,z?:float}  $ctx
     */
    public function apply(string $method, float $pdPre, array $ctx): ?float
    {
        $post = match ($method) {
            self::SCALAR => isset($ctx['adjustment']) ? $pdPre * (1 + (float) $ctx['adjustment']) : null,
            self::SEGMENT => isset($ctx['segment_adjustment']) ? $pdPre * (1 + (float) $ctx['segment_adjustment']) : null,
            self::REFERENCE => $this->reference($pdPre, (string) ($ctx['reference_method'] ?? ''), (array) ($ctx['reference_ctx'] ?? [])),
            self::LOGIT => isset($ctx['alpha'], $ctx['beta'], $ctx['macro']) ? 1 / (1 + exp(-((float) $ctx['alpha'] + (float) $ctx['beta'] * (float) $ctx['macro']))) : null,
            self::VASICEK => isset($ctx['rho'], $ctx['z']) && $pdPre > 0 && $pdPre < 1 && (float) $ctx['rho'] > 0 && (float) $ctx['rho'] < 1
                ? self::phi((self::phiInv($pdPre) - sqrt((float) $ctx['rho']) * (float) $ctx['z']) / sqrt(1 - (float) $ctx['rho'])) : null,
            default => null,
        };

        return $post === null ? null : max(0.0, min(1.0, $post));
    }

    private function reference(float $pdPre, string $method, array $ctx): ?float
    {
        if (! in_array($method, AdjustmentMethods::METHODS, true)) {
            return null;
        }
        $r = AdjustmentMethods::compute($method, $ctx);
        if ($r['value'] === null) {
            return null;
        }

        return match ($r['kind']) {
            'factor' => $pdPre * (1 + $r['value']),
            'pd' => $r['value'],
            'change', 'difference', 'statistic' => $pdPre * (1 + $r['value']), // a driver movement used as the factor, as the legacy calculator did
            default => null,
        };
    }

    // ----- the facts the preconditions are checked against ----------------

    private function facts(?string $period): array
    {
        $f = ['period' => $period, 'approved_fit' => false, 'fit' => null, 'overlay' => false, 'segments_with_proxy' => 0, 'segments' => 0,
            'default_series_months' => 0, 'min_observations' => 36, 'asset_correlation' => null, 'z_series' => false, 'grade_default_series' => false, 'example_loan' => null];
        try {
            $f['min_observations'] = (int) (preg_match('/(\d+)/', $this->governance->get('fli_min_observations'), $m) ? $m[1] : 36);
        } catch (Throwable) {
        }
        try {
            $rho = $this->governance->get('fli_asset_correlation');
            $f['asset_correlation'] = preg_match('/(\d+(?:\.\d+)?)\s*%/', $rho, $m) ? (float) $m[1] / 100 : (is_numeric($rho) ? (float) $rho : null);
        } catch (Throwable) {
        }
        // an approved fit: the regression definitions the Weighted Forecast reads
        try {
            if (DB::getSchemaBuilder()->hasTable('macro_credit_loss_definitions')) {
                $fit = DB::table('macro_credit_loss_definitions')->whereNotNull('slope')->orderByDesc('id')->first();
                if ($fit) {
                    $f['approved_fit'] = true;
                    $f['fit'] = ['slope' => (float) $fit->slope, 'intercept' => (float) ($fit->intercept ?? 0), 'r' => (float) ($fit->correlation ?? $fit->r_squared ?? 0)];
                }
            }
        } catch (Throwable) {
        }
        try {
            $f['overlay'] = DB::getSchemaBuilder()->hasTable('fli_adj') && DB::table('fli_adj')->exists();
        } catch (Throwable) {
        }
        // a default-rate history: the months of staged loan books held
        try {
            $f['default_series_months'] = (int) DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->distinct()->count('reporting_period');
            $f['segments'] = (int) DB::table('loan_books')->where('reporting_period', $period)->distinct()->count('product_group');
            $f['segments_with_proxy'] = (int) DB::table('loan_books')->whereNotNull('ifrs9stage_post_qualitative')->selectRaw('product_group, count(distinct reporting_period) n')->groupBy('product_group')->havingRaw('count(distinct reporting_period) >= 2')->get()->count();
            $f['grade_default_series'] = DB::getSchemaBuilder()->hasTable('transition_matrices') && DB::table('transition_matrices')->count() >= $f['min_observations'];
            $loan = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('pd_prefli')->where('pd_prefli', '>', 0)->where('pd_prefli', '<', 1)->orderBy('contract_id')->first(['contract_id', 'customer_name', 'product_group', 'ifrs9stage_post_qualitative', 'pd_prefli', 'fli_adj', 'pd_post_fli']);
            $f['example_loan'] = $loan ? (array) $loan : null;
        } catch (Throwable) {
        }

        return $f;
    }

    /** @return list<array{name:string,met:bool,figure:string}> */
    private function preconditions(string $key, array $f): array
    {
        $fit = ['name' => 'An approved fit (slope, intercept, correlation), or an overlay', 'met' => $f['approved_fit'] || $f['overlay'], 'figure' => $f['approved_fit'] ? 'fit held: slope ' . number_format($f['fit']['slope'], 6) . ', R ' . number_format($f['fit']['r'], 4) : ($f['overlay'] ? 'manual overlay held' : 'no approved fit and no overlay')];

        return match ($key) {
            self::SCALAR => [$fit],
            self::SEGMENT => [$fit, ['name' => 'A derivable proxy per segment (two or more staged periods each)', 'met' => $f['segments'] > 0 && $f['segments_with_proxy'] >= $f['segments'], 'figure' => "{$f['segments_with_proxy']} of {$f['segments']} segments have two or more periods"]],
            self::REFERENCE => [['name' => 'An approved fit with slope, intercept and correlation', 'met' => $f['approved_fit'], 'figure' => $fit['figure']], ['name' => 'The scenario-weighted driver (an approved scenario set)', 'met' => DB::getSchemaBuilder()->hasTable('scenario_sets') && DB::table('scenario_sets')->where('is_active', 1)->exists(), 'figure' => 'active scenario set ' . (DB::table('scenario_sets')->where('is_active', 1)->exists() ? 'held' : 'absent')]],
            self::LOGIT => [['name' => 'A grade-level or loan-level default series long enough to fit', 'met' => $f['default_series_months'] >= $f['min_observations'] && $f['grade_default_series'], 'figure' => "default-rate series: {$f['default_series_months']} months held, {$f['min_observations']} needed" . ($f['grade_default_series'] ? '' : '; no grade-level matrices yet')]],
            self::VASICEK => [['name' => 'A governed asset correlation ρ per portfolio', 'met' => $f['asset_correlation'] !== null && $f['asset_correlation'] > 0, 'figure' => $f['asset_correlation'] !== null ? 'ρ = ' . number_format($f['asset_correlation'] * 100, 2) . '%' : 'not yet governed'], ['name' => 'A Z series calibrated on a default-rate history', 'met' => $f['default_series_months'] >= $f['min_observations'], 'figure' => "default-rate series: {$f['default_series_months']} months held, {$f['min_observations']} needed"]],
            default => [],
        };
    }

    private function example(string $key, array $f): ?array
    {
        $loan = $f['example_loan'];
        $pre = (float) $loan['pd_prefli'];
        $adj = $loan['fli_adj'] !== null ? (float) $loan['fli_adj'] : 0.0;
        $steps = [];
        $post = null;
        switch ($key) {
            case self::SCALAR:
            case self::SEGMENT:
                $post = $this->apply($key, $pre, ['adjustment' => $adj, 'segment_adjustment' => $adj]);
                $steps = ['pre-FLI PD ' . $this->pct($pre), 'adjustment ' . number_format($adj, 6) . ($key === self::SEGMENT ? ' (the segment\'s own)' : ''), 'post-FLI PD = ' . $this->pct($pre) . ' × (1 + ' . number_format($adj, 6) . ') = ' . $this->pct($post)];
                break;
            case self::REFERENCE:
                $fit = $f['fit'];
                $ctx = ['slope' => $fit['slope'], 'intercept' => $fit['intercept'], 'correlation_r' => $fit['r'], 'macro' => 1.0, 'base_pd' => $pre, 'macro_forecast' => 1.0, 'macro_base' => 1.0];
                $r = AdjustmentMethods::compute('fli_adj_FDH_ByChange', $ctx);
                $post = $this->apply($key, $pre, ['reference_method' => 'fli_adj_FDH_ByChange', 'reference_ctx' => $ctx]);
                $steps = ['method fli_adj_FDH_ByChange: ' . $r['note'], 'with the driver forecast equal to its base the factor is ' . ($r['value'] === null ? 'undefined' : number_format($r['value'], 6)), 'post-FLI PD = ' . $this->pct($post)];
                break;
            case self::LOGIT:
                $alpha = log($pre / (1 - $pre)); $beta = 0.0;
                $post = $this->apply($key, $pre, ['alpha' => $alpha, 'beta' => $beta, 'macro' => 0.0]);
                $steps = ['logit(PD) = α + β × macro; with α = logit(pre-FLI PD) = ' . number_format($alpha, 4) . ' and β = 0 until fitted', 'post-FLI PD = 1 / (1 + e^−(α + β × macro)) = ' . $this->pct($post)];
                break;
            case self::VASICEK:
                $rho = $f['asset_correlation'] ?? 0.12; $z = 0.0;
                $post = $this->apply($key, $pre, ['rho' => $rho, 'z' => $z]);
                $steps = ['TTC PD ' . $this->pct($pre) . ', ρ = ' . number_format($rho * 100, 2) . '%, Z = 0 (the average state of the economy until Z is calibrated; the TTC PD is the average of the conditional PD over Z, so the figure at Z = 0 sits below it for a PD under 50 percent)', 'PIT PD = Φ[(Φ⁻¹(PD) − √ρ × Z) / √(1 − ρ)] = ' . $this->pct($post)];
                break;
        }

        return ['contract_id' => $loan['contract_id'], 'customer_name' => $loan['customer_name'], 'product_group' => $loan['product_group'], 'stage' => $loan['ifrs9stage_post_qualitative'], 'pd_pre' => $pre, 'pd_post' => $post, 'steps' => $steps];
    }

    private function pct(?float $v): string
    {
        return $v === null ? 'undefined' : number_format($v * 100, 4) . '%';
    }

    /** The method cards' texts: seeded content when the table holds them, else these. */
    private function texts(): array
    {
        $defaults = [
            self::SCALAR => ['title' => 'Multiplicative scalar on the proxy ratio', 'what' => 'The adjustment is the ratio of the predicted credit-loss proxy in the forecast window to the predicted proxy today, less one. Every loan\'s PD is multiplied by one plus that ratio. It is MAIIC\'s method today and the seeded default: with two years of core-banking history it is the honest choice, because the richer methods need a default-rate series the institution does not yet have.',
                'formula' => 'post-FLI PD = pre-FLI PD × (1 + a), where a = proxy(window) / proxy(base) − 1', 'symbols' => ['a' => 'the adjustment', 'proxy(window)' => 'the proxy the fit predicts over the 12-month or lifetime window', 'proxy(base)' => 'the proxy the fit predicts for the base period'],
                'implies' => ['Proportional: the same percentage move for every loan whatever its starting PD', 'Linear in the macro variable', 'Not bounded by construction: the cap at 100 percent does the bounding', 'Moves weak and strong borrowers alike, which real downturns do not']],
            self::SEGMENT => ['title' => 'Segment-specific scalar', 'what' => 'The same arithmetic as the scalar, with one fit and one adjustment per portfolio or product group, from the segment proxies the deriver produces. A downturn then moves the agricultural book by its own history and the industrial book by its own.',
                'formula' => 'post-FLI PD = pre-FLI PD × (1 + a_s), where a_s = proxy_s(window) / proxy_s(base) − 1 for the loan\'s segment s', 'symbols' => ['a_s' => 'the segment\'s adjustment', 's' => 'the portfolio or product group'],
                'implies' => ['Proportional within a segment, different between segments', 'Needs a derivable proxy per segment: two or more staged periods each', 'The natural next step once the deriver of section 14.4 has segment history']],
            self::REFERENCE => ['title' => 'One of the nine reference methods', 'what' => 'The suite\'s governed producers of a factor from a fit crossed with the scenario-weighted driver: the weighted statistic; the annual difference and the annual change in the driver; both scaled by the correlation; the absolute PD forecast from the equation; the forecast-over-base ratio (MAIIC\'s scalar is this one); and the two correlation-scaled difference and change variants. Selectable one at a time; each returns nothing rather than a fabricated figure when an input is undefined.',
                'formula' => 'factor = f(slope, intercept, R, driver); post-FLI PD = pre-FLI PD × (1 + factor), or the absolute PD forecast = slope × macro + intercept', 'symbols' => ['R' => 'the correlation of the approved fit', 'driver' => 'the scenario-weighted macro forecast'],
                'implies' => ['Nine named arithmetics, ported as they are from the suite', 'A method whose input is undefined (zero base, missing prior year) declines rather than invents', 'Linear in the driver; bounded only by the floor and cap']],
            self::LOGIT => ['title' => 'Logit-linear PD model', 'what' => 'The PD is modelled in log-odds: logit(PD) = α + β × macro. A move in the macro variable moves the odds proportionally rather than the probability, so a weak borrower moves more in probability than a strong one, and the result is bounded between 0 and 1 by construction.',
                'formula' => 'PD = 1 / (1 + e^−(α + β × macro))', 'symbols' => ['α' => 'the intercept of the fit in log-odds', 'β' => 'the sensitivity of the log-odds to the macro variable', 'macro' => 'the scenario-weighted driver'],
                'implies' => ['Not proportional: mid-range PDs move most in probability', 'Bounded by construction', 'Needs a grade-level or loan-level default series long enough to fit; declined until the history allows']],
            self::VASICEK => ['title' => 'Vasicek single-factor Z-shift', 'what' => 'The through-the-cycle PD is moved through a latent systematic factor Z, the state of the economy, with ρ the share of a borrower\'s risk that is systematic. The shift is largest for mid-range PDs and bounded by construction; it is the arithmetic behind the Basel capital formula.',
                'formula' => 'PIT PD = Φ[(Φ⁻¹(TTC PD) − √ρ × Z) / √(1 − ρ)]', 'symbols' => ['Φ' => 'the standard normal distribution function', 'Φ⁻¹' => 'its inverse', 'ρ' => 'the governed asset correlation of the portfolio', 'Z' => 'the systematic factor from the macro fit; negative in a downturn'],
                'implies' => ['Not proportional: the shift is largest for mid-range PDs', 'Bounded by construction', 'Needs a governed ρ per portfolio and a Z series calibrated on a default-rate history; declined until then']],
        ];
        try {
            if (DB::getSchemaBuilder()->hasTable('fli_method_cards')) {
                foreach (DB::table('fli_method_cards')->get() as $row) {
                    if (isset($defaults[$row->method_key])) {
                        $defaults[$row->method_key] = ['title' => $row->title, 'what' => $row->what, 'formula' => $row->formula, 'symbols' => json_decode($row->symbols, true) ?? [], 'implies' => json_decode($row->implies, true) ?? []];
                    }
                }
            }
        } catch (Throwable) {
        }

        return $defaults;
    }

    // ----- the normal distribution ----------------------------------------

    public static function phi(float $x): float
    {
        return 0.5 * (1 + self::erf($x / sqrt(2)));
    }

    public static function phiInv(float $p): float
    {
        // Acklam's rational approximation, refined by one Newton step
        $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02, 1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
        $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02, 6.680131188771972e+01, -1.328068155288572e+01];
        $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00, -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
        $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00, 3.754408661907416e+00];
        $pl = 0.02425; $ph = 1 - $pl;
        if ($p < $pl) {
            $q = sqrt(-2 * log($p));
            $x = ((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        } elseif ($p <= $ph) {
            $q = $p - 0.5; $r = $q * $q;
            $x = ((((($a[0] * $r + $a[1]) * $r + $a[2]) * $r + $a[3]) * $r + $a[4]) * $r + $a[5]) * $q / ((((($b[0] * $r + $b[1]) * $r + $b[2]) * $r + $b[3]) * $r + $b[4]) * $r + 1);
        } else {
            $q = sqrt(-2 * log(1 - $p));
            $x = -((((($c[0] * $q + $c[1]) * $q + $c[2]) * $q + $c[3]) * $q + $c[4]) * $q + $c[5]) / (((($d[0] * $q + $d[1]) * $q + $d[2]) * $q + $d[3]) * $q + 1);
        }
        $e = self::phi($x) - $p;
        $u = $e * sqrt(2 * M_PI) * exp($x * $x / 2);

        return $x - $u / (1 + $x * $u / 2);
    }

    private static function erf(float $x): float
    {
        // Abramowitz and Stegun 7.1.26, error below 1.5e-7
        $t = 1 / (1 + 0.3275911 * abs($x));
        $y = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return $x >= 0 ? $y : -$y;
    }
}
