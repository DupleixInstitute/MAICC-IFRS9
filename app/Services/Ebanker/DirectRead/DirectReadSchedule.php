<?php

namespace App\Services\Ebanker\DirectRead;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonInterface;
use Throwable;

/**
 * When the scheduler runs route 4, under three governed settings (Governance Centre, Data sources):
 * at 06:00 on the first working day of the month, or on the working day and at the time MAIIC sets.
 * Working days are Monday to Friday; public holidays are not known. A setting that cannot be read
 * means no scheduled read: the month can still be read by hand.
 */
class DirectReadSchedule
{
    public const TIMING = 'ebanker_direct_read_timing';
    public const WORKING_DAY = 'ebanker_direct_read_working_day';
    public const TIME = 'ebanker_direct_read_time';

    public const AT_SIX = 'At 06:00 on the first working day of the month';
    public const AS_SET = 'On the working day and at the time set below';

    public const WORKING_DAYS = ['1st working day', '2nd working day', '3rd working day', '4th working day', '5th working day'];

    /** @return list<string> every hour, 00:00 to 23:00 */
    public static function times(): array
    {
        return array_map(fn ($h) => sprintf('%02d:00', $h), range(0, 23));
    }

    /** @return array{working_day:int,time:string} */
    public static function inForce(?GovernanceService $governance = null): array
    {
        $governance ??= app(GovernanceService::class);
        if ($governance->get(self::TIMING) === self::AT_SIX) {
            return ['working_day' => 1, 'time' => '06:00'];
        }

        return ['working_day' => array_search($governance->get(self::WORKING_DAY), self::WORKING_DAYS, true) + 1, 'time' => $governance->get(self::TIME)];
    }

    public static function isDue(CarbonInterface $now, ?GovernanceService $governance = null): bool
    {
        try {
            $s = self::inForce($governance);
        } catch (Throwable) {
            return false;
        }

        return $now->format('H:i') === $s['time'] && $now->isSameDay(self::workingDay($now, $s['working_day']));
    }

    /** The nth working day (Monday to Friday) of the month of $date. */
    public static function workingDay(CarbonInterface $date, int $n): CarbonInterface
    {
        $day = $date->copy()->startOfMonth();
        $counted = 0;
        while (true) {
            if ($day->isWeekday() && ++$counted === $n) {
                return $day;
            }
            $day = $day->addDay();
        }
    }
}
