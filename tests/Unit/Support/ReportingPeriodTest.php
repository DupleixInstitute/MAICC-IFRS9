<?php

namespace Tests\Unit\Support;

use App\Support\ReportingPeriod;
use PHPUnit\Framework\TestCase;

/**
 * loan_books.reporting_period is a free string written three ways over the
 * table's life. One helper reads all of them, so the shapes are pinned here.
 */
class ReportingPeriodTest extends TestCase
{
    public function test_every_shape_the_loan_book_uses_normalises_to_year_month(): void
    {
        $this->assertSame('2026-03', ReportingPeriod::normalise('202603'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('2026-03'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('2026-3'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('2026/03'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('2026-03-31'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('20260331'));
        $this->assertSame('2026-03', ReportingPeriod::normalise('2026-03-31 00:00:00'));
        $this->assertSame('2026-03', ReportingPeriod::normalise(' 2026-03 '));
        $this->assertSame('2026-03', ReportingPeriod::normalise(202603));
    }

    public function test_anything_else_is_not_a_period(): void
    {
        $this->assertNull(ReportingPeriod::normalise(null));
        $this->assertNull(ReportingPeriod::normalise(''));
        $this->assertNull(ReportingPeriod::normalise('2026-13'));
        $this->assertNull(ReportingPeriod::normalise('202613'));
        $this->assertNull(ReportingPeriod::normalise('03/2026'));
        $this->assertNull(ReportingPeriod::normalise('March 2026'));
    }

    public function test_month_end_is_the_last_calendar_day(): void
    {
        $this->assertSame('2026-02-28', ReportingPeriod::monthEnd('202602')->toDateString());
        $this->assertSame('2024-02-29', ReportingPeriod::monthEnd('2024-02')->toDateString());
        $this->assertSame('2026-08-31', ReportingPeriod::monthEnd('2026-08-01')->toDateString());
        $this->assertNull(ReportingPeriod::monthEnd('not a period'));
    }

    public function test_same_compares_across_shapes(): void
    {
        $this->assertTrue(ReportingPeriod::same('202603', '2026-03'));
        $this->assertTrue(ReportingPeriod::same('2026-03-31', '2026-03'));
        $this->assertFalse(ReportingPeriod::same('202604', '2026-03'));
        $this->assertFalse(ReportingPeriod::same(null, '2026-03'));
    }
}
