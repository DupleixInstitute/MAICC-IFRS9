import os, re
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')

def sub(path, pairs, regex=False):
    s = open(path, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (path, a[:80])
        s = s.replace(a, b)
    open(path, 'w', encoding='utf-8', newline='').write(s)

# EclBuildUpService: the constant becomes the engine's rule
sub('app/Services/Reports/EclBuildUpService.php', [
("""    /** The engine's EAD, as SQL. */
    public const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';""",
"""    /**
     * The engine's EAD, as SQL: EclExposure's one rule (floored at nil, nil for
     * a loan written off to nil). Until 14 October 2026 this was the gross,
     * unfloored figure, which counted a credit balance as a negative exposure
     * and the undrawn amount of a loan written off to nil as an exposure.
     */
    public static function eadSql(): string
    {
        return \\App\\Services\\Ecl\\EclExposure::sql();
    }"""),
])
sub('app/Http/Controllers/LoanBookController.php', [("""$ead = EclBuildUpService::EAD_SQL;""", """$ead = EclBuildUpService::eadSql();""")])
sub('app/Http/Controllers/ExpectedCreditLossController.php', [("""COALESCE(SUM(' . EclBuildUpService::EAD_SQL . '),0) as ead,""", """COALESCE(SUM(' . EclBuildUpService::eadSql() . '),0) as ead,""")])

# Ifrs9ReportsController
p = 'app/Http/Controllers/Reports/Ifrs9ReportsController.php'
s = open(p, encoding='utf-8').read()
a = """    private const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';"""
b = """    // The EAD every report shows is the engine's (EclExposure: floored at nil, nil for a loan
    // written off to nil); until 14 October 2026 the reports used the gross, unfloored figure.
    private static function ead(): string
    {
        return \\App\\Services\\Ecl\\EclExposure::sql();
    }"""
assert a in s
s = s.replace(a, b)
n = s.count('self::EAD_SQL')
s = s.replace('self::EAD_SQL', 'self::ead()')
open(p, 'w', encoding='utf-8', newline='').write(s)
print('reports replaced', n)

# StressTestingController: the unused copies of the stress service's SQL go
sub('app/Http/Controllers/Reports/StressTestingController.php', [
("""    private const EAD = '(COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1))';
    private const PD  = 'COALESCE(pd_post_fli, pd_prefli, 0)';
    private const LGD = 'COALESCE(lgd_value, 0)';
""", ""),
])

# SegmentPdReport
p = 'app/Services/Pd/SegmentPdReport.php'
s = open(p, encoding='utf-8').read()
a = """    private const EAD = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';"""
assert a in s
s = s.replace(a, """    /** The engine's EAD (EclExposure), as SQL. */
    private static function ead(): string
    {
        return \\App\\Services\\Ecl\\EclExposure::sql();
    }""")
s = s.replace("self::EAD . ')", "self::ead() . ')").replace("' . self::EAD . '", "' . self::ead() . '")
assert 'self::EAD' not in s, 'segment left'
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
