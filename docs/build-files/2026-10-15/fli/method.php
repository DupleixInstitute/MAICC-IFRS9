    /**
     * The regression adjustment per scenario for period P, under the fitted lag.
     *
     * The approved fit is proxy(t) = slope x driver(t - L) + intercept: the
     * proxy at month t responds to the driver L months earlier (the lag the
     * finder and the regression chose, SeriesAligner). The route follows spec
     * 14.3 step 3 and 14.7: the adjustment is the predicted proxy of the
     * twelve-month window over the predicted proxy of the base window, less
     * one. Applied with the lag:
     *
     *  - base window, t = P: driver(P - L), the actual known at P. With no
     *    such actual the route fails closed for the period.
     *  - twelve-month window, t = P + 12: driver(P + 12 - L).
     *      L >= 12: that month is on or before P, so the driver is an actual
     *      already known at P and every scenario takes it (the lag means the
     *      next year's proxy is already determined by observed data; the
     *      scenarios cannot differ there).
     *      L < 12: the month is P + (12 - L) ahead, inside forecast year
     *      floor((12 - L - 1) / 12) of the scenario path (year 0 for every lag
     *      on the grid), and each scenario takes its own path value for that
     *      year. With no path value the route fails closed.
     *
     * Every value is read as at P (AsAtSeries): nothing after the end of P
     * enters, which is what B5.5.49 to B5.5.54 allow (information reasonably
     * available at the reporting date, forecasts included only as published by
     * then). One adjustment per scenario is applied to the twelve-month PD,
     * from which the ECL engine takes the Stage 2 lifetime PD.
     *
     * @return array{0:array<string,mixed>,1:array<string,array<string,mixed>>}
     */
    private function regressionAdjustments(string $period, object $fit, array $paths): array
    {
        $p = AsAtSeries::ym($period);
        $code = (string) $fit->statistic_code;
        $lag = (int) $fit->lag_months;
        $actuals = AsAtSeries::macro(null, $p, ['actual'], [$code])[$code] ?? [];

        $basePeriod = SeriesAligner::shiftPeriod($p, $lag);
        if (! array_key_exists($basePeriod, $actuals)) {
            throw new RuntimeException("The forward-looking route cannot run for {$period}: fit {$fit->id} ({$code}, lag {$lag} months) needs the {$code} actual for {$basePeriod}, and none is known at the end of {$period}. The PD is not adjusted; load the series or approve a fit the data supports.");
        }
        $base = (float) $actuals[$basePeriod];

        $windowPeriod = SeriesAligner::shiftPeriod($p, $lag - 12);
        $ahead = AsAtSeries::monthsBetween($p, $windowPeriod);
        $observed = null;
        $offset = null;
        if ($ahead <= 0) {
            if (! array_key_exists($windowPeriod, $actuals)) {
                throw new RuntimeException("The forward-looking route cannot run for {$period}: with a lag of {$lag} months the twelve-month window reads the {$code} actual for {$windowPeriod}, and none is known at the end of {$period}.");
            }
            $observed = (float) $actuals[$windowPeriod];
            $source = "actual {$windowPeriod}, known at {$p} (with a lag of {$lag} months the window's driver is on or before the reporting date)";
        } else {
            $offset = intdiv($ahead - 1, 12);
            $source = "scenario path, forecast year {$offset}: " . ($paths['base_source'][$code][$offset] ?? 'no base value');
        }

        $predBase = (float) $fit->slope * $base + (float) $fit->intercept;
        if (abs($predBase) <= 1e-12) {
            throw new RuntimeException("The forward-looking route cannot run for {$period}: fit {$fit->id} predicts a base proxy of zero at {$code} = {$base}, so no ratio to the base exists.");
        }
        $scenarios = [];
        foreach ($paths['scenarios'] as $name => $sc) {
            $driver = $observed ?? ($sc['path'][$code][$offset] ?? null);
            if ($driver === null) {
                throw new RuntimeException("The forward-looking route cannot run for {$period}: scenario '{$name}' has no {$code} value for forecast year {$offset} known at the end of {$period}.");
            }
            $pred = (float) $fit->slope * (float) $driver + (float) $fit->intercept;
            $scenarios[$name] = ['weight' => $sc['weight'], 'driver' => round((float) $driver, 6), 'driver_period' => $windowPeriod, 'predicted_proxy' => round($pred, 6), 'adjustment' => round($pred / $predBase - 1, 6)];
        }

        return [['statistic_code' => $code, 'lag_months' => $lag, 'base_period' => $basePeriod, 'base_value' => round($base, 6), 'predicted_base_proxy' => round($predBase, 6), 'window_period' => $windowPeriod, 'window_driver_source' => $source], $scenarios];
    }

