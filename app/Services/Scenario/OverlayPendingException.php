<?php

namespace App\Services\Scenario;

use RuntimeException;

/**
 * A scenario set cannot be locked while an overlay against its period is
 * still proposed (spec v4 section 15.7; system audit of 9 October 2026,
 * finding M4). The overlay has to be approved or rejected first, so that
 * the locked period's ECL carries only judgement a second person has seen.
 */
class OverlayPendingException extends RuntimeException
{
    /** @param list<int> $overlayIds */
    public static function forSet(int $setId, string $period, array $overlayIds): self
    {
        return new self(sprintf(
            'Set %d cannot be locked: overlay%s %s for %s %s still proposed; approve or reject %s first.',
            $setId, count($overlayIds) === 1 ? '' : 's', implode(', ', $overlayIds), $period,
            count($overlayIds) === 1 ? 'is' : 'are', count($overlayIds) === 1 ? 'it' : 'them'
        ));
    }
}
