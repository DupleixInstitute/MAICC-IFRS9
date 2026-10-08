<?php

declare(strict_types=1);

namespace App\Support\Fli;

/**
 * Resolves the EXPECTED sign of a macro driver X against a credit-loss proxy Y
 * (an NPL / PD-type series where higher == worse). The sign gate is the hard,
 * univariate screen the Auto-Correlate sweep and the guardrail apply
 * (FLI_AND_PD_METHODOLOGY.md sections 5, 12.2, 12.6).
 *
 * Resolution order:
 *   1. An explicit governed regression_definitions row for the exact pair
 *      (statistic_code, proxy_code) - the bank's own approved policy.
 *   2. The seeded Malawi / SSA driver-level policy (section 12.6), grounded in
 *      Phiri (2019), Mpofu & Nikolaidou, Nkusu (2011) etc. This is governed
 *      REFERENCE policy shipped with the module - not a fabricated fit.
 *
 * A driver whose sign is genuinely context-dependent (FX / exchange rate)
 * returns null: the pair is not hard-rejected on sign, it is flagged
 * "usable_with_caveat" (section 12.6). Nothing is silently defaulted.
 */
final class ExpectedSignPolicy
{
    /**
     * Driver-level expected sign vs an NPL/PD proxy (higher == worse).
     * 'positive' => driver up -> proxy (default risk) up.
     * 'negative' => driver up -> proxy (default risk) down.
     * null       => context-dependent; do not gate on sign.
     *
     * Keyed by a normalized substring test applied to the statistic code.
     *
     * @var array<int,array{match:array<int,string>,sign:?string,note:string}>
     */
    private const POLICY = [
        ['match' => ['gdp'], 'sign' => 'negative', 'note' => 'real GDP growth: higher growth -> lower NPLs (Phiri 2019; SSA panels)'],
        ['match' => ['inflation', 'cpi'], 'sign' => 'positive', 'note' => 'CPI inflation: lagged inflation raises NPLs (SSA panels)'],
        ['match' => ['policy_rate', 'lending_rate', 'rbm_policy', 'rbm_rate', 'interest'], 'sign' => 'positive', 'note' => 'policy/lending rate: higher rates raise default risk (adverse selection)'],
        ['match' => ['credit_growth', 'private_sector_credit', 'credit_to_gdp'], 'sign' => 'positive', 'note' => 'private-sector credit growth: boom-bust raises later NPLs'],
        ['match' => ['unemp', 'un_emp', 'employ'], 'sign' => 'positive', 'note' => 'unemployment: higher unemployment raises default risk'],
        ['match' => ['usd', 'exchange', 'kwacha', 'fx'], 'sign' => null, 'note' => 'exchange rate: context-dependent (transmits via inflation / FX-lending), not gated on sign'],
    ];

    /**
     * @param array<int,array{statistic_code:string,proxy_code:string,expected_sign:string}> $definitions
     *   rows harvested from regression_definitions (bank-approved overrides)
     * @return array{sign:?string,note:string,source:string}
     */
    public static function resolve(string $statisticCode, string $proxyCode, array $definitions = []): array
    {
        foreach ($definitions as $d) {
            if (strcasecmp($d['statistic_code'], $statisticCode) === 0
                && strcasecmp($d['proxy_code'], $proxyCode) === 0) {
                return [
                    'sign' => strtolower($d['expected_sign']),
                    'note' => 'governed regression_definitions policy for this exact pair',
                    'source' => 'regression_definitions',
                ];
            }
        }

        $code = strtolower($statisticCode);
        foreach (self::POLICY as $rule) {
            foreach ($rule['match'] as $needle) {
                if (str_contains($code, $needle)) {
                    return [
                        'sign' => $rule['sign'],
                        'note' => $rule['note'],
                        'source' => 'seeded_ssa_policy',
                    ];
                }
            }
        }

        return [
            'sign' => null,
            'note' => 'no expected-sign policy for this driver; sign gate not applied',
            'source' => 'none',
        ];
    }
}
