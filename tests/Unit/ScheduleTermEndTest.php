<?php

namespace Tests\Unit;

use App\Services\Eir\ScheduleWorkflowService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The number of instalments a generated version 1 schedule has (16 October
 * 2026). E-Banker writes the maturity as the day before the anniversary of the
 * start, and the term runs to the end of that day: the instalment due the day
 * after the maturity is the last one. The four loans found one instalment short.
 */
class ScheduleTermEndTest extends TestCase
{
    private static function n(string $first, string $maturity, int $interval): int
    {
        return ScheduleWorkflowService::instalmentsTo(Carbon::parse($first), Carbon::parse($maturity), $interval);
    }

    public function test_annual_maturity_one_day_before_the_anniversary_nature_s_gift(): void
    {
        // 22 Apr 2022 for three years, maturity 21 Apr 2025: E-Banker's chart has three annual instalments
        $this->assertSame(3, self::n('2023-04-22', '2025-04-21', 12));
    }

    public function test_monthly_maturity_one_day_before_kadava(): void
    {
        // 2 Aug 2022 for two years, maturity 1 Aug 2024: 24 monthly instalments, the last on 2 Aug 2024
        $this->assertSame(24, self::n('2022-09-02', '2024-08-01', 1));
    }

    public function test_monthly_maturity_one_day_before_udon_business(): void
    {
        // maturity 1 Feb 2029: the instalment of 2 Feb 2029 is the 60th (the locked schedule stops at 59, 2 Jan 2029)
        $this->assertSame(60, self::n('2024-03-02', '2029-02-01', 1));
    }

    public function test_monthly_maturity_one_day_before_mtengo_wa_kumunda(): void
    {
        // maturity 4 Jul 2024: the instalment of 5 Jul 2024 is the 21st (the locked schedule stops at 20, 5 Jun 2024)
        $this->assertSame(21, self::n('2022-11-05', '2024-07-04', 1));
    }

    public function test_the_bounds_of_the_term(): void
    {
        // a maturity on the payment date counts it, as before
        $this->assertSame(48, self::n('2024-09-24', '2028-08-24', 1));
        // a payment date two days after the maturity is after the term: not counted
        $this->assertSame(23, self::n('2022-09-03', '2024-08-01', 1));
        // a single bullet due on the anniversary of a one-year loan is its one instalment (it was refused)
        $this->assertSame(1, self::n('2026-01-01', '2025-12-31', 12));
        // a first instalment after the term: none
        $this->assertSame(0, self::n('2026-01-03', '2025-12-31', 12));
        // the dates counted are the dates written (first plus whole intervals), so a month-end start does not drift
        $this->assertSame(12, self::n('2024-01-31', '2024-12-30', 1));
    }
}
