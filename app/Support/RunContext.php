<?php

namespace App\Support;

use App\Services\RunRecordService;
use Throwable;

/**
 * Which run each engine call belongs to (Staging Module Specification v1,
 * section 8.8, "Where this applies"). The month-end job and the bootstrap open
 * a run for a month; every engine that then works on that month records its
 * step in that run. An engine called with no run open for its month (from a
 * screen, by hand) gets a run of its own, marked SCREEN, which can never be
 * the run of record.
 *
 * Each engine's entry point starts with:
 *
 *   if (! RunContext::inside($period, 'Staging')) {
 *       return RunContext::engine($period, 'Staging', $userId, fn () => $this->stage(...));
 *   }
 *
 * engine() marks the step as running and calls the method again, which then
 * goes on to its work; so the engines need no other change.
 */
final class RunContext
{
    /** @var array<string, int> month => the run open for it */
    private static array $open = [];

    /** @var array<string, true> 'month|engine' => running now */
    private static array $active = [];

    public static function open(string $period, int $runId): void
    {
        self::$open[$period] = $runId;
    }

    public static function close(string $period): void
    {
        unset(self::$open[$period]);
    }

    public static function runFor(string $period): ?int
    {
        return self::$open[$period] ?? null;
    }

    public static function inside(string $period, string $engine): bool
    {
        return isset(self::$active[$period . '|' . $engine]);
    }

    /** Forget every open run (tests, and a long-lived worker between jobs). */
    public static function reset(): void
    {
        self::$open = [];
        self::$active = [];
    }

    public static function engine(string $period, string $engine, ?int $userId, callable $work): mixed
    {
        $key = $period . '|' . $engine;
        if (! RunRecordService::available()) {
            // a database without run records (before the migration, or a test's own schema): the work, unrecorded
            self::$active[$key] = true;
            try {
                return $work();
            } finally {
                unset(self::$active[$key]);
            }
        }
        $runs = app(RunRecordService::class);
        $runId = self::runFor($period);
        $own = $runId === null;
        if ($own) {
            $runId = $runs->start($period, RunRecordService::SCREEN, $userId, $engine . ' run on its own');
            self::open($period, $runId);
        }
        self::$active[$key] = true;
        $step = $runs->stepStarted($runId, $engine);
        try {
            $result = $work();
            $runs->stepFinished($runId, $step, true);
            if ($own) {
                self::close($period);
                $runs->finish($runId, true);
            }

            return $result;
        } catch (Throwable $e) {
            $runs->stepFinished($runId, $step, false, $e->getMessage());
            if ($own) {
                self::close($period);
                $runs->finish($runId, false, $e->getMessage());
            }
            throw $e;
        } finally {
            unset(self::$active[$key]);
        }
    }
}
