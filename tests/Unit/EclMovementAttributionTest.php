<?php

namespace Tests\Unit;

use App\Services\Reports\EclMovementAttribution as A;
use PHPUnit\Framework\TestCase;

/**
 * Pure tests for the IFRS 7 movement attribution (no database). Values are
 * in cents. The synthetic book has one contract of each kind:
 *
 *  C1 Stage 1 -> Stage 1, ECL 100.00 -> 80.00     remeasurement
 *  C2 Stage 1 -> Stage 3, ECL 50.00 -> 400.00     transfer to Stage 3
 *  C3 Stage 2 -> Stage 3, ECL 200.00 -> 300.00    transfer to Stage 3
 *  C4 Stage 3 -> Stage 1, ECL 900.00 -> 10.00     transfer to Stage 1 (cure)
 *  C5 new in Stage 2, ECL 70.00                   originated
 *  C6 Stage 3 gone, ECL 600.00                    derecognised
 *  C7 Stage 2 gone and written off, ECL 30.00     written off
 */
class EclMovementAttributionTest extends TestCase
{
    private function book(): array
    {
        return [
            ['from' => 1, 'to' => 1, 'contracts' => 1, 'opening' => 10000, 'closing' => 8000],
            ['from' => 1, 'to' => 3, 'contracts' => 1, 'opening' => 5000, 'closing' => 40000],
            ['from' => 2, 'to' => 3, 'contracts' => 1, 'opening' => 20000, 'closing' => 30000],
            ['from' => 3, 'to' => 1, 'contracts' => 1, 'opening' => 90000, 'closing' => 1000],
            ['from' => null, 'to' => 2, 'contracts' => 1, 'opening' => 0, 'closing' => 7000],
            ['from' => 3, 'to' => null, 'contracts' => 1, 'opening' => 60000, 'closing' => 0],
            ['from' => 2, 'to' => null, 'contracts' => 1, 'opening' => 3000, 'closing' => 0, 'written_off' => true],
        ];
    }

    public function test_opening_and_closing_by_stage(): void
    {
        $r = A::attribute($this->book());
        $this->assertSame([1 => 15000, 2 => 23000, 3 => 150000, 'total' => 188000], $r['lines']['opening']);
        $this->assertSame([1 => 9000, 2 => 7000, 3 => 70000, 'total' => 86000], $r['lines']['closing']);
    }

    public function test_transfers_move_the_opening_value_and_net_to_nil(): void
    {
        $r = A::attribute($this->book());
        // C2 and C3 move into Stage 3 with their opening ECL.
        $this->assertSame([1 => -5000, 2 => -20000, 3 => 25000, 'total' => 0], $r['lines']['transfer_3']);
        // C4 cures from Stage 3 into Stage 1.
        $this->assertSame([1 => 90000, 2 => 0, 3 => -90000, 'total' => 0], $r['lines']['transfer_1']);
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 'total' => 0], $r['lines']['transfer_2']);
        $this->assertSame(2, $r['contracts']['transfer_3'][3]);
    }

    public function test_remeasurement_sits_in_the_closing_stage(): void
    {
        $r = A::attribute($this->book());
        // Stage 1: C1 (-20.00) and C4 (10.00 - 900.00). Stage 3: C2 (+350.00) and C3 (+100.00).
        $this->assertSame([1 => -2000 - 89000, 2 => 0, 3 => 35000 + 10000, 'total' => -46000], $r['lines']['remeasurement']);
    }

    public function test_new_derecognised_and_written_off(): void
    {
        $r = A::attribute($this->book());
        $this->assertSame([1 => 0, 2 => 7000, 3 => 0, 'total' => 7000], $r['lines']['originated']);
        $this->assertSame([1 => 0, 2 => 0, 3 => -60000, 'total' => -60000], $r['lines']['derecognised']);
        $this->assertSame([1 => 0, 2 => -3000, 3 => 0, 'total' => -3000], $r['lines']['written_off']);
    }

    public function test_every_column_ties(): void
    {
        $r = A::attribute($this->book());
        $this->assertTrue($r['check']['ok'], implode(' ', $r['check']['messages']));
        $this->assertSame([1 => 0, 2 => 0, 3 => 0, 'total' => 0], $r['check']['differences']);
        $this->assertSame(0, $r['check']['transfers_total']);
    }

    public function test_the_check_fails_loudly_when_a_column_is_broken(): void
    {
        $r = A::attribute($this->book());
        $lines = $r['lines'];
        $lines['closing'][2] += 1;
        $lines['closing']['total'] += 1;
        $check = A::check($lines);
        $this->assertFalse($check['ok']);
        $this->assertSame(-1, $check['differences'][2]);
        $this->assertStringContainsString('Stage 2 does not tie', implode(' ', $check['messages']));
    }

    public function test_empty_book_ties_at_nil(): void
    {
        $r = A::attribute([]);
        $this->assertTrue($r['check']['ok']);
        $this->assertSame(0, $r['lines']['closing']['total']);
    }

    public function test_rounded_note_casts_and_shows_the_rounding(): void
    {
        // Values chosen so the rounded figures do not cast on their own.
        $groups = [
            ['from' => 1, 'to' => 1, 'contracts' => 1, 'opening' => 140000, 'closing' => 140000], // 1,400.00 -> 1,400.00
            ['from' => 1, 'to' => null, 'contracts' => 1, 'opening' => 140000, 'closing' => 0],     // 1,400.00 gone
            ['from' => null, 'to' => 1, 'contracts' => 1, 'opening' => 0, 'closing' => 140000],     // 1,400.00 new
        ];
        $r = A::attribute($groups);
        $rounded = A::rounded($r['lines'], 100000); // MWK '000
        $l = $rounded['lines'];
        // Opening 2,800.00 -> 3; closing 2,800.00 -> 3; derecognised -1,400 -> -1; new 1,400 -> 1.
        $this->assertSame(3, $l['opening'][1]);
        $this->assertSame(3, $l['closing'][1]);
        foreach ([1, 2, 3, 'total'] as $col) {
            $sum = $l['opening'][$col];
            foreach (A::MOVEMENTS as $m) {
                $sum += $l[$m][$col];
            }
            $this->assertSame($l['closing'][$col], $sum, "column {$col} casts");
        }
        $this->assertSame(0, $rounded['absorbed'][1]);
        $this->assertSame(0, $rounded['recon']['closing']['difference']['total']);
    }

    public function test_rounding_left_in_a_column_goes_to_remeasurement(): void
    {
        $groups = [
            ['from' => 1, 'to' => 1, 'contracts' => 1, 'opening' => 60000, 'closing' => 60000],  // 600.00
            ['from' => 2, 'to' => 2, 'contracts' => 1, 'opening' => 60000, 'closing' => 60000],  // 600.00
            ['from' => 1, 'to' => null, 'contracts' => 1, 'opening' => 60000, 'closing' => 0],   // 600.00 gone
        ];
        $rounded = A::rounded(A::attribute($groups)['lines'], 100000);
        // Stage 1: opening 1,200 -> 1; derecognised -600 -> -1; closing 600 -> 1; plug +1.
        $this->assertSame(1, $rounded['absorbed'][1]);
        $this->assertSame(1, $rounded['lines']['remeasurement'][1]);
        // Total opening: stages 1 + 1 = 2 against 1,800 rounded = 2; closing 1 + 1 = 2 against 1,200 = 1.
        $this->assertSame(1, $rounded['recon']['closing']['difference']['total']);
    }

    public function test_rounded_transfers_net_to_nil(): void
    {
        // 95,671,977.39 and 106,634,514.05 move into Stage 3: rounded alone they give 202,307 against 95,672 + 106,635.
        $groups = [
            ['from' => 1, 'to' => 3, 'contracts' => 1, 'opening' => 9567197739, 'closing' => 9567197739],
            ['from' => 2, 'to' => 3, 'contracts' => 1, 'opening' => 10663451405, 'closing' => 10663451405],
        ];
        $rounded = A::rounded(A::attribute($groups)['lines'], 100000);
        $this->assertSame([1 => -95672, 2 => -106635, 3 => 202307, 'total' => 0], $rounded['lines']['transfer_3']);
        $this->assertSame(0, $rounded['lines']['transfer_3']['total']);
    }

    public function test_cents_parses_sql_decimals_exactly(): void
    {
        $this->assertSame(92537503097, A::cents('925375030.97'));
        $this->assertSame(-1205, A::cents('-12.05'));
        $this->assertSame(1235, A::cents('12.345'));
        $this->assertSame(0, A::cents(null));
        $this->assertSame(50, A::cents('.5'));
        $this->assertSame(1000, A::roundTo(999500, 1000) * 1000 / 1000);
        $this->assertSame(-1, A::roundTo(-50000, 100000));
    }
}
