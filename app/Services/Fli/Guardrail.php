<?php

declare(strict_types=1);

namespace App\Services\Fli;

/**
 * The guardrail-that-declines - the FLI cockpit's differentiator.
 *
 * A fit is only 'applied' (allowed to feed an FLI factor) when ALL of:
 *   - enough observations           (n >= governed stats.min_obs)
 *   - the realised sign matches the expected sign (hard economic gate)
 *   - it is strong enough           (R2 >= governed fli.r2_cutoff)
 *   - it is statistically significant (p_value <= governed stats.alpha)
 *
 * Otherwise the verdict is 'declined' with a declined_reason and the
 * relationship is QUARANTINED - the PD falls back to its pre-FLI value. This is
 * the judgement the earlier calculator only coloured a comment for, now made
 * to actually block. Pure (no DB) so it is exhaustively unit-testable.
 *
 * The sign gate is governed (fli_expected_sign_test in the Governance
 * Centre, bridged as fli.sign_test.mode; system audit of 9 October 2026,
 * finding M12). Under the gating mode a wrong sign declines the fit as above.
 * Under the advisory mode the wrong sign is recorded as a warning on the fit
 * and the verdict is decided by the other three tests, so a reviewer sees
 * the sign and decides.
 *
 * FLI_AND_PD_METHODOLOGY.md sections 5 (common guardrail) and 12.2 (precedence).
 */
final class Guardrail
{
    public const REASON_INSUFFICIENT_N = 'insufficient_n';
    public const REASON_WRONG_SIGN = 'wrong_sign';
    public const REASON_R2_BELOW_CUTOFF = 'r2<cutoff';
    public const REASON_INSIGNIFICANT = 'insignificant';

    /** The two modes of the governed sign test, as the bridge writes them. */
    public const SIGN_TEST_GATING = 'gating';
    public const SIGN_TEST_ADVISORY = 'advisory';

    /**
     * @param array{n:int,r2:?float,p_value:?float,slope:?float} $fit
     * @param string|null $expectedSign 'positive'|'negative'|null (null => sign gate not applied)
     * @param string $signTestMode 'gating' (a wrong sign declines) or 'advisory' (a wrong sign is a warning on the fit)
     * @return array{verdict:string,declined_reason:?string,sign_ok:?bool,realised_sign:?string,sign_warning:?string,reasons:array<int,string>,checks:array{sufficient_n:bool,sign_ok:?bool,r2_ok:bool,significant:bool}}
     */
    public function evaluate(array $fit, ?string $expectedSign, float $r2Cutoff, int $minObs, float $alpha, string $signTestMode = self::SIGN_TEST_GATING): array
    {
        $advisory = strtolower(trim($signTestMode)) === self::SIGN_TEST_ADVISORY;
        // Accept a cutoff given either as a fraction (0.60) or a percent (60).
        $cutoff = $r2Cutoff > 1.0 ? $r2Cutoff / 100.0 : $r2Cutoff;

        $n = (int) $fit['n'];
        $r2 = $fit['r2'];
        $p = $fit['p_value'];
        $slope = $fit['slope'];

        $realisedSign = null;
        if ($slope !== null) {
            $realisedSign = $slope > 0.0 ? 'positive' : ($slope < 0.0 ? 'negative' : null);
        }

        $expected = $expectedSign !== null ? strtolower($expectedSign) : null;
        $signOk = ($expected === null || $realisedSign === null) ? null : ($realisedSign === $expected);

        $sufficientN = $n >= $minObs;
        $r2Ok = $r2 !== null && $r2 >= $cutoff;
        $significant = $p !== null && $p <= $alpha;

        $reasons = [];
        $reasons[] = $sufficientN
            ? sprintf('n=%d meets min_obs=%d', $n, $minObs)
            : sprintf('n=%d below min_obs=%d', $n, $minObs);
        if ($signOk === null) {
            $reasons[] = $expected === null
                ? 'expected sign not defined (context-dependent driver) - sign gate not applied'
                : 'realised sign indeterminate (zero/undefined slope)';
        } else {
            $reasons[] = $signOk
                ? sprintf('correct sign (%s)', $realisedSign)
                : sprintf('counter-intuitive: realised %s vs expected %s - possible spurious correlation%s', $realisedSign, $expected, $advisory ? ' (sign test advisory: recorded, not enforced)' : '');
        }
        $signWarning = ($signOk === false && $advisory)
            ? sprintf('wrong sign: realised %s against expected %s; the sign test is advisory (fli_expected_sign_test), so the fit is not declined for it', $realisedSign, $expected)
            : null;
        $reasons[] = $r2 === null
            ? 'R2 undefined'
            : sprintf('R2 %.4f %s cutoff %.4f', $r2, $r2Ok ? '>=' : '<', $cutoff);
        $reasons[] = $p === null
            ? 'p-value undefined'
            : sprintf('p=%.4f %s alpha=%.4f', $p, $significant ? '<=' : '>', $alpha);

        // Decline precedence: fundamental first (n), then the economic sign gate,
        // then strength, then significance.
        $declinedReason = null;
        if (! $sufficientN) {
            $declinedReason = self::REASON_INSUFFICIENT_N;
        } elseif ($signOk === false && ! $advisory) {
            $declinedReason = self::REASON_WRONG_SIGN;
        } elseif (! $r2Ok) {
            $declinedReason = self::REASON_R2_BELOW_CUTOFF;
        } elseif (! $significant) {
            $declinedReason = self::REASON_INSIGNIFICANT;
        }

        $verdict = $declinedReason === null ? 'applied' : 'declined';

        return [
            'verdict' => $verdict,
            'declined_reason' => $declinedReason,
            'sign_ok' => $signOk,
            'realised_sign' => $realisedSign,
            'sign_warning' => $signWarning,
            'reasons' => $reasons,
            'checks' => [
                'sufficient_n' => $sufficientN,
                'sign_ok' => $signOk,
                'r2_ok' => $r2Ok,
                'significant' => $significant,
            ],
        ];
    }
}
