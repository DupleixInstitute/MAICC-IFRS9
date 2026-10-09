import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:60])
        s = s.replace(old, new)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

sub('app/Services/Rbm/RbmReturnService.php', [
(""" * Bands, by the term of the facility (section 2: short-term 12 months or
 * less; medium and long term over 12):
 *   Pass              0 to 30 days                  provision  0 percent
 *   Special mention   31 to 90   | 31 to 180        provision  5 percent
 *   Substandard       91 to 180  | 181 to 360       provision 20 percent
 *   Doubtful          181 to 360 | 361 to 720       provision 50 percent
 *   Loss              over 360   | over 720         provision 100 percent
 */""",
""" * Bands, by the term of the facility (section 2: short-term 12 months or
 * less; medium and long term over 12), from section 10 of the Gazette
 * (docs/regulatory, pages 683 and 685):
 *                     short-term   | medium and long
 *   Pass (standard)   0 to 30      | 0 to 90          provision  0 percent
 *   Special mention   31 to 90     | 91 to 180        provision  5 percent
 *   Substandard       91 to 180    | 181 to 365       provision 20 percent
 *   Doubtful          181 to 365   | 366 to 746       provision 50 percent
 *   Loss              over 365     | over 746         provision 100 percent
 */"""),
("    private const BANDS = ['short' => [30, 90, 180, 360], 'long' => [30, 180, 360, 720]];",
 "    public const BANDS = ['short' => [30, 90, 180, 365], 'long' => [90, 180, 365, 746]];"),
])

sub('app/Http/Controllers/Reports/Ifrs9ReportsController.php', [
("""     *   class            short-term   medium/long   rate
     *   Pass             0 to 30      0 to 30       0 %
     *   Special Mention  31 to 90     31 to 180     5 %
     *   Substandard      91 to 180    181 to 360    20 %
     *   Doubtful         181 to 360   361 to 720    50 %
     *   Loss             over 360     over 720      100 %
     */
    private const RBM = [
        'Pass'            => ['short' => [0, 30],    'long' => [0, 30],    'rate' => 0.00],
        'Special Mention' => ['short' => [31, 90],   'long' => [31, 180],  'rate' => 0.05],
        'Substandard'     => ['short' => [91, 180],  'long' => [181, 360], 'rate' => 0.20],
        'Doubtful'        => ['short' => [181, 360], 'long' => [361, 720], 'rate' => 0.50],
        'Loss'            => ['short' => [361, null], 'long' => [721, null], 'rate' => 1.00],
    ];""",
"""     *   class            short-term   medium/long   rate   (Gazette section 10, pages 683 and 685)
     *   Pass             0 to 30      0 to 90       0 %
     *   Special Mention  31 to 90     91 to 180     5 %
     *   Substandard      91 to 180    181 to 365    20 %
     *   Doubtful         181 to 365   366 to 746    50 %
     *   Loss             over 365     over 746      100 %
     */
    private const RBM = [
        'Pass'            => ['short' => [0, 30],    'long' => [0, 90],    'rate' => 0.00],
        'Special Mention' => ['short' => [31, 90],   'long' => [91, 180],  'rate' => 0.05],
        'Substandard'     => ['short' => [91, 180],  'long' => [181, 365], 'rate' => 0.20],
        'Doubtful'        => ['short' => [181, 365], 'long' => [366, 746], 'rate' => 0.50],
        'Loss'            => ['short' => [366, null], 'long' => [747, null], 'rate' => 1.00],
    ];"""),
("""            WHEN COALESCE(tenor,0) <= 12 AND overdue_days <= 360 THEN 'Doubtful'
            WHEN COALESCE(tenor,0) <= 12                         THEN 'Loss'
            WHEN overdue_days <= 180 THEN 'Special Mention'
            WHEN overdue_days <= 360 THEN 'Substandard'
            WHEN overdue_days <= 720 THEN 'Doubtful'
            ELSE 'Loss' END";""",
"""            WHEN COALESCE(tenor,0) <= 12 AND overdue_days <= 365 THEN 'Doubtful'
            WHEN COALESCE(tenor,0) <= 12                         THEN 'Loss'
            WHEN overdue_days <= 90  THEN 'Pass'
            WHEN overdue_days <= 180 THEN 'Special Mention'
            WHEN overdue_days <= 365 THEN 'Substandard'
            WHEN overdue_days <= 746 THEN 'Doubtful'
            ELSE 'Loss' END";"""),
("""                    WHEN overdue_days <= 360 THEN '181-360'
                    WHEN overdue_days <= 720 THEN '361-720'
                    ELSE '721+' END bucket,""",
"""                    WHEN overdue_days <= 365 THEN '181-365'
                    WHEN overdue_days <= 746 THEN '366-746'
                    ELSE '747+' END bucket,"""),
("bands by term: 30/90/180/360 days for a facility of 12 months or less, 30/180/360/720 otherwise (RBM Credit Risk Management for DFIs Directive, 2018, sections 9 to 12); the same bands and rates as the RBM Return",
 "bands by term: 30/90/180/365 days for a facility of 12 months or less, 90/180/365/746 otherwise (RBM Credit Risk Management for DFIs Directive, 2018, section 10 and the Schedule); the same bands and rates as the RBM Return"),
])

sub('resources/js/Pages/Reports/RbmReturn.vue', [
("Short-term: 12 months or less (pass to 30 days; special mention to 90; substandard to 180; doubtful to 360; loss beyond). Medium and long term: to 30; 180; 360; 720; beyond.",
 "Short-term: 12 months or less (standard to 30 days; special mention to 90; substandard to 180; doubtful to 365; loss beyond). Medium and long term: standard to 90; special mention to 180; substandard to 365; doubtful to 746; loss beyond (Gazette section 10)."),
])

sub('tests/Feature/Rbm/RbmReturnServiceTest.php', [
("""        $this->assertSame('Pass', RbmReturnService::classify(30, 12));
        $this->assertSame('Special mention', RbmReturnService::classify(31, 12));
        $this->assertSame('Substandard', RbmReturnService::classify(91, 12));
        $this->assertSame('Special mention', RbmReturnService::classify(91, 36));
        $this->assertSame('Substandard', RbmReturnService::classify(181, 36));
        $this->assertSame('Doubtful', RbmReturnService::classify(181, 12));
        $this->assertSame('Doubtful', RbmReturnService::classify(361, 36));
        $this->assertSame('Loss', RbmReturnService::classify(361, 12));
        $this->assertSame('Loss', RbmReturnService::classify(721, 36));""",
"""        // Gazette section 10 (pages 683 and 685): short-term 30/90/180/365; medium and long 90/180/365/746
        foreach ([[30, 'Pass'], [31, 'Special mention'], [90, 'Special mention'], [91, 'Substandard'], [180, 'Substandard'], [181, 'Doubtful'], [365, 'Doubtful'], [366, 'Loss']] as [$dpd, $class]) {
            $this->assertSame($class, RbmReturnService::classify($dpd, 12), "short-term {$dpd} days");
        }
        foreach ([[30, 'Pass'], [90, 'Pass'], [91, 'Special mention'], [180, 'Special mention'], [181, 'Substandard'], [365, 'Substandard'], [366, 'Doubtful'], [746, 'Doubtful'], [747, 'Loss']] as [$dpd, $class]) {
            $this->assertSame($class, RbmReturnService::classify($dpd, 36), "medium/long-term {$dpd} days");
        }"""),
("        foreach ([[12, 31], [12, 90], [12, 91], [12, 180], [12, 181], [12, 360], [12, 361], [36, 31], [36, 180], [36, 181], [36, 360], [36, 361], [36, 720], [36, 721]] as $i => [$tenor, $dpd]) {",
 "        foreach ([[12, 31], [12, 90], [12, 91], [12, 180], [12, 181], [12, 365], [12, 366], [36, 31], [36, 90], [36, 91], [36, 180], [36, 181], [36, 365], [36, 366], [36, 746], [36, 747]] as $i => [$tenor, $dpd]) {"),
("        $this->assertCount(19, $rows);", "        $this->assertCount(21, $rows);"),
])
print('ok')
