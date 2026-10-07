<?php

declare(strict_types=1);

namespace App\Services\Fli;

use App\Support\Fli\AdjustmentMethods;
use App\Support\Fli\GovernedValues;
use Illuminate\Support\Facades\DB;

/**
 * Produces fli_pd_adjustments rows (source='regression') from APPLIED fits,
 * using one of the nine governed adjustment methods (AdjustmentMethods). The
 * governed default of the nine is fli.adjustment.method; the governed default
 * FLI *methodology* stays the manual ASS2 path (fli.methodology.default =
 * manual_ass2), so this engine only runs when the automated regression path is
 * selected - it never displaces the working manual default.
 *
 * HARD RULE: only a fit with verdict='applied' may feed a factor. A declined
 * (quarantined) fit returns allowed=false and the PD holds at its pre-FLI value.
 *
 * FLI_AND_PD_METHODOLOGY.md section 5A + REVISED_SCHEMA.md section 4.
 */
final class AdjustmentMethodEngine
{
    private string $connection;

    private GovernedValues $gov;

    public function __construct(string $connection, GovernedValues $gov)
    {
        $this->connection = $connection;
        $this->gov = $gov;
    }

    private function db()
    {
        return DB::connection($this->connection);
    }

    /** The governed default method of the nine (when the regression path is selected). */
    public function defaultMethod(): string
    {
        return $this->gov->string('fli.adjustment.method');
    }

    /**
     * Compute the FLI factor for a fit, ENFORCING that only applied fits feed a
     * factor. $ctx supplies the method inputs (see AdjustmentMethods::compute).
     *
     * @param array{verdict:string,declined_reason?:?string,slope?:?float,intercept?:?float,correlation_r?:?float} $fit
     * @param array<string,mixed> $ctx
     * @return array{allowed:bool,method:string,kind:?string,pd12_factor:?float,pdlife_factor:?float,reason:string}
     */
    public function factorFromFit(array $fit, array $ctx, ?string $method = null): array
    {
        $method ??= $this->defaultMethod();

        if (($fit['verdict'] ?? 'declined') !== 'applied') {
            return [
                'allowed' => false,
                'method' => $method,
                'kind' => null,
                'pd12_factor' => null,
                'pdlife_factor' => null,
                'reason' => 'quarantined: fit is ' . ($fit['verdict'] ?? 'declined')
                    . (isset($fit['declined_reason']) ? ' (' . $fit['declined_reason'] . ')' : '')
                    . ' - PD holds at pre-FLI value; no regression factor produced.',
            ];
        }

        // Feed the fit coefficients into the method context.
        $ctx += [
            'slope' => $fit['slope'] ?? 0.0,
            'intercept' => $fit['intercept'] ?? 0.0,
            'correlation_r' => $fit['correlation_r'] ?? 0.0,
        ];

        $out = AdjustmentMethods::compute($method, $ctx);
        $value = $out['value'];

        // Translate the method output into a multiplicative FLI factor (fli_adj).
        $factor = null;
        if ($value !== null) {
            if ($out['kind'] === 'factor') {
                $factor = $value;
            } elseif ($out['kind'] === 'pd' && isset($ctx['base_pd']) && (float) $ctx['base_pd'] !== 0.0) {
                $factor = $value / (float) $ctx['base_pd'] - 1.0;
            }
        }

        $reason = $factor === null
            ? $method . ' produced a ' . $out['kind'] . ' (' . $out['note'] . '); not a terminal PD factor on its own.'
            : $method . ': fli_adj=' . number_format($factor, 6) . ' (' . $out['note'] . ')';

        return [
            'allowed' => true,
            'method' => $method,
            'kind' => $out['kind'],
            'pd12_factor' => $factor,
            'pdlife_factor' => $factor,
            'reason' => $reason,
        ];
    }

    /**
     * Persist a regression-sourced PD adjustment (upsert on the natural key).
     * Refuses to persist a factor that was not allowed (declined fit).
     *
     * @param array{allowed:bool,method:string,pd12_factor:?float,pdlife_factor:?float} $factor
     */
    public function persist(int $forecastSetId, int $businessUnitId, string $statisticCode, array $factor): bool
    {
        if (! $factor['allowed'] || $factor['pd12_factor'] === null) {
            return false;
        }
        $this->db()->table('fli_pd_adjustments')->upsert(
            [[
                'forecast_set_id' => $forecastSetId,
                'business_unit_id' => $businessUnitId,
                'statistic_code' => $statisticCode,
                'adjustment_method' => $factor['method'],
                'pd12_factor' => $factor['pd12_factor'],
                'pdlife_factor' => $factor['pdlife_factor'],
                'source' => 'regression',
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['forecast_set_id', 'business_unit_id', 'statistic_code', 'adjustment_method'],
            ['pd12_factor', 'pdlife_factor', 'source', 'updated_at']
        );

        return true;
    }
}
