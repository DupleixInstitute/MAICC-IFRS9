p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/Ebanker/InstalmentChartHistoryTest.php'
s = open(p, encoding='utf-8').read()
anchor = "    public function test_a_collapsed_definition_with_no_monthly_mode_is_reported_unreadable(): void"
new = r"""    /**
     * Two versions left in force together (Sycamore Credit: rows created on
     * 1 January and on 1 November 2025 both live) carry each instalment twice.
     * The later version is the chart; nothing is counted twice and the chart
     * is not mistaken for a collapsed one.
     */
    public function test_two_versions_in_force_together_keep_the_later_one(): void
    {
        foreach ([1, 2, 3] as $serial) {
            $due = date('Y-m-d', strtotime('2025-09-01 +' . ($serial - 1) . ' months'));
            $this->row("old{$serial}", $due, 18000000, '1/1/2025', null, '8740', $serial);
            $this->row("new{$serial}", $due, 3200000, '11/1/2025', null, '8740', $serial);
        }
        $c = (new InstalmentChartHistory(new LandingZoneReader()))->at($this->a, '2025-12-31');
        $this->assertFalse($c['collapsed']);
        $this->assertSame([['2025-09-01', 3200000.0], ['2025-10-01', 3200000.0], ['2025-11-01', 3200000.0]], $c['instalments']);
        $this->assertSame(18000000.0, (new InstalmentChartHistory(new LandingZoneReader()))->at($this->a, '2025-10-31')['instalments'][0][1], 'before November only the January version existed');
    }

"""
assert s.count(anchor) == 1
s = s.replace(anchor, new + anchor)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Ebanker/InstalmentChartHistory.php'
s = open(p, encoding='utf-8').read()
old = " * A collapsed chart. On two loans"
new = """ * Two versions in force together. Where the extracts leave two versions of
 * one instalment (the same definition and serial) in force on a date, the
 * later-created is the chart (Sycamore Credit, January and November 2025).
 *
 * A collapsed chart. On two loans"""
assert s.count(old) == 1
s = s.replace(old, new)
s = s.replace(""" * Technologies 000104460000059: serials 25 to 61 all on 1 February 2025),
 * so from that day""", """ * Technologies 000104460000059: serials 25 to 61 all on 1 February 2025;
 * Msusa Investments and Beaulaland Food Processors carry serials in pairs on
 * one date), so from that day""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
