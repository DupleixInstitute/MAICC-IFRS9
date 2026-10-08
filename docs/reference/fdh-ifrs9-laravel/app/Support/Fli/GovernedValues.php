<?php

declare(strict_types=1);

namespace App\Support\Fli;

use Illuminate\Support\Facades\DB;

/**
 * Fail-closed resolver for every governed value the FLI engine needs. It reads
 * ONLY approved, effective-dated rows from governed_parameters and THROWS when
 * a required key is absent - there is deliberately no default branch, so an
 * auditor can confirm no cutoff/alpha/method is hardcoded in engine logic.
 *
 * The seed defaults (section 15.1 / 13.5 of FLI_AND_PD_METHODOLOGY.md) are
 * governed REFERENCE definitions, not code constants: ensureDefaults() inserts
 * them into governed_parameters only when the key is not already present, so an
 * operator's approved value always wins and the resolver always reads the store,
 * never a literal.
 */
final class GovernedValues
{
    /** @var array<string,string> */
    private array $params = [];

    private string $period;

    private string $connection;

    /**
     * The seeded FLI governance defaults (key => value). Sourced from
     * FLI_AND_PD_METHODOLOGY.md: r2 cutoff + transmission (section 8/10),
     * the 9-method default (5A), the lag grid + Malawi tuning (12.5), and the
     * distribution-diagnostic thresholds (13.5). Inserted only if absent.
     *
     * @var array<string,string>
     */
    public const SEED_DEFAULTS = [
        'fli.r2_cutoff.default' => '0.60',
        'fli.methodology.default' => 'manual_ass2',   // working default stays the manual ASS2 path
        'fli.adjustment.method' => 'pd_forecast',      // default of the 9 methods when the regression path is selected
        'fli.transmission.style' => 'multiplicative',
        'fli.lag_grid' => '0,3,6,9,12',
        // Historical horizon: the correlation window is capped to the most recent
        // N years of MATCHING (overlapping) data (FLI_AND_PD_METHODOLOGY.md 12.5).
        // Bivariate search plays over lags within this governed window. Shipped
        // default 5; FDH operates at 15 (a governed operator value) so short annual
        // series use ALL their overlapping history - bounded by the shorter series.
        'fli.max_history_years' => '5',
        'fli.vif.max' => '5.0',
        'fli.pvalue.max' => '0.05',
        'stats.alpha' => '0.05',
        // Minimum matched observations for a trustworthy fit. Shipped default 24
        // (calibrated for monthly data). FDH operates at 8 for its ANNUAL series
        // (~8-9 points) - a governed operator value in governed_parameters
        // ('operator value wins'); the seed stays conservative.
        'stats.min_obs' => '24',
        'stats.skew_limit' => '1.0',
        'stats.kurtosis_limit' => '2.0',
        'stats.shapiro_max_n' => '50',
        'stats.default_method' => 'pearson',
        'ecl.scenario_method' => 'probability_weighted_outcomes',
        'pd.derivation.method' => 'balance_sum_cumulative',
    ];

    public function __construct(string $period, ?string $connection = null)
    {
        $this->period = $period;
        $this->connection = $connection ?? config('database.default');
        $this->load();
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    private function load(): void
    {
        $rows = $this->db()->table('governed_parameters')
            ->where('status', 'approved')
            ->where('effective_from', '<=', $this->period)
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $this->period);
            })
            ->orderBy('param_key')
            ->orderBy('effective_from')
            ->get(['param_key', 'param_value']);

        foreach ($rows as $r) {
            // later effective_from wins (ascending order overwrites)
            $this->params[$r->param_key] = (string) $r->param_value;
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->params);
    }

    public function string(string $key): string
    {
        if (! array_key_exists($key, $this->params)) {
            throw FliParameterException::missing($key, $this->period);
        }

        return $this->params[$key];
    }

    public function int(string $key): int
    {
        $v = $this->string($key);
        if (! is_numeric($v)) {
            throw new FliParameterException("FLI parameter [{$key}] value [{$v}] is not an integer.");
        }

        return (int) $v;
    }

    public function float(string $key): float
    {
        $v = $this->string($key);
        if (! is_numeric($v)) {
            throw new FliParameterException("FLI parameter [{$key}] value [{$v}] is not numeric.");
        }

        return (float) $v;
    }

    /**
     * Governed lag grid (months). Fail-closed if fli.lag_grid is absent.
     *
     * @return array<int,int>
     */
    public function lagGrid(): array
    {
        $raw = $this->string('fli.lag_grid');
        $out = [];
        foreach (explode(',', $raw) as $tok) {
            $tok = trim($tok);
            if ($tok === '' || ! is_numeric($tok)) {
                continue;
            }
            $out[] = (int) $tok;
        }
        if ($out === []) {
            throw new FliParameterException("FLI parameter [fli.lag_grid] value [{$raw}] has no valid lags.");
        }

        return array_values(array_unique($out));
    }

    public function period(): string
    {
        return $this->period;
    }

    public function connection(): string
    {
        return $this->connection;
    }

    /**
     * Insert any missing FLI/stats governed defaults (idempotent; operator
     * values are never overwritten). Returns the list of keys actually seeded.
     *
     * @return array<int,string>
     */
    public static function ensureDefaults(?string $connection = null, string $effectiveFrom = '201501'): array
    {
        $conn = $connection ?? config('database.default');
        $db = DB::connection($conn);
        $seeded = [];
        $now = now();
        foreach (self::SEED_DEFAULTS as $key => $value) {
            $exists = $db->table('governed_parameters')->where('param_key', $key)->exists();
            if ($exists) {
                continue;
            }
            $db->table('governed_parameters')->insert([
                'param_key' => $key,
                'param_value' => $value,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => 'approved',
                'note' => 'FLI engine seed default (FLI_AND_PD_METHODOLOGY.md 13.5/15.1); insert-if-absent, operator value wins',
                'changed_at' => $now,
            ]);
            $seeded[] = $key;
        }

        return $seeded;
    }
}
