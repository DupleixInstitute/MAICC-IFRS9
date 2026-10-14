<?php

namespace App\Services\Ebanker;

use App\Services\Eir\GovernanceService;
use Throwable;

/**
 * Whether a feed route may land packs, under the governed route in force
 * (Governance Centre, ebanker_feed_route; spec v4 section 6.5).
 *
 * Until 14 October 2026 the setting was recorded but the feed-folder poller
 * (route 2) and the API intake (route 3) landed whatever they found whatever
 * route was in force, and the poller took "Route 1" when no value was
 * approved. Now each refuses, with the reason, unless the route in force is
 * its own, or the setting opens several routes together. A missing value
 * refuses (fail closed). The manual upload (route 1) stays open for a missed
 * month or a correction, as the specification keeps every route available.
 */
class FeedRoute
{
    public const KEY = 'ebanker_feed_route';
    /** The option that opens routes 1, 2 and 3 together. */
    public const SEVERAL = 'Routes 1, 2 and 3 together: upload, folder and API';

    private const NAMES = [1 => 'manual pack upload', 2 => 'the scheduled export to a folder (the feed-folder poller)', 3 => 'the push by API (the API inbox)', 4 => 'direct read over the VPN', 5 => 'vendor view or API'];

    /** @return array{allowed:bool,in_force:?string,reason:?string} */
    public static function check(int $route, ?GovernanceService $governance = null): array
    {
        $governance ??= app(GovernanceService::class);
        $label = GovernanceService::catalogue()[self::KEY]['label'];
        try {
            $value = $governance->get(self::KEY);
        } catch (Throwable) {
            return ['allowed' => false, 'in_force' => null, 'reason' => "No route is approved for '{$label}' (Governance Centre, " . self::KEY . '), so route ' . $route . ' (' . self::NAMES[$route] . ') lands nothing: approve the route in use first.'];
        }
        $allowed = $value === self::SEVERAL ? in_array($route, [1, 2, 3], true) : str_starts_with($value, "Route {$route}:");

        return ['allowed' => $allowed, 'in_force' => $value, 'reason' => $allowed ? null
            : "The feed route in force is '{$value}' (Governance Centre, '{$label}'), so route {$route} (" . self::NAMES[$route] . ') is refused: change the route in force, or choose \'' . self::SEVERAL . '\', to land packs this way.'];
    }
}
