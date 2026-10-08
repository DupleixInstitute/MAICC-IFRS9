<?php

declare(strict_types=1);

namespace App\Support\Fli;

use Illuminate\Support\Facades\Cache;

/**
 * Live progress feed for the Auto-Correlate run - the FLI cockpit polls this
 * while the queued job iterates the (X driver x Y proxy x lag) grid, so the
 * user SEES the sweep working through the lags instead of a silent job.
 *
 * One cache slot (database cache store), overwritten per iteration: cheap,
 * crash-safe (TTL expires stale feeds) and honest - the payload carries the
 * exact pair + lag under evaluation, the running verdict tallies and the best
 * find so far. States: queued -> running -> fitting -> complete | failed.
 */
final class AutoCorrelateProgress
{
    private const KEY = 'fli.autocorrelate.progress';

    private const TTL_SECONDS = 3600;

    /** @param array<string,mixed> $extra */
    public static function put(string $state, array $extra = []): void
    {
        Cache::put(self::KEY, array_merge([
            'state' => $state,
            'updated_at' => now()->toDateTimeString(),
        ], $extra), self::TTL_SECONDS);
    }

    public static function queued(?string $period): void
    {
        self::put('queued', ['period' => $period, 'message' => 'Auto-Correlate queued; waiting for a worker.']);
    }

    /**
     * Per-iteration heartbeat from the sweep loop.
     *
     * @param array<string,mixed> $best
     * @param array<string,int> $verdicts
     */
    public static function iteration(int $done, int $total, string $xCode, string $yCode, int $lag, array $verdicts, ?array $best): void
    {
        self::put('running', [
            'done' => $done,
            'total' => $total,
            'pct' => $total > 0 ? (int) floor($done * 100 / $total) : 0,
            'current' => ['x' => $xCode, 'y' => $yCode, 'lag' => $lag],
            'verdicts' => $verdicts,
            'best' => $best,
            'message' => sprintf('Evaluating %s x %s at lag %dm (%d/%d)', $xCode, $yCode, $lag, $done, $total),
        ]);
    }

    public static function fitting(string $message): void
    {
        self::put('fitting', ['message' => $message]);
    }

    /** @param array<string,mixed> $summary */
    public static function complete(array $summary): void
    {
        self::put('complete', $summary);
    }

    public static function failed(string $message): void
    {
        self::put('failed', ['message' => $message]);
    }

    /** @return array<string,mixed>|null */
    public static function get(): ?array
    {
        $v = Cache::get(self::KEY);

        return is_array($v) ? $v : null;
    }
}
