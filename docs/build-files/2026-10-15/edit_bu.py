import io
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Reports\EclBuildUpService.php'
s = io.open(p, encoding='utf-8', newline='').read()
def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:80], s.count(old))
    s = s.replace(old, new)
rep("use App\Services\Fli\OverlayService;\n", "use App\Services\Ecl\EclExposure;\nuse App\Services\Fli\OverlayService;\n")
rep("""    /** Above this many groups the rest are shown as one "Other" line. */""",
"""    /** The lineage columns, with the build basis the engine reads a write-off from where the book carries it. */
    public static function columns(): array
    {
        return EclExposure::hasBasis() ? array_merge(self::COLUMNS, ['build_basis']) : self::COLUMNS;
    }

    /** Above this many groups the rest are shown as one "Other" line. */""")
rep("""        $utilisation = $l->facility_utilisation_rate === null ? 1.0 : (float) $l->facility_utilisation_rate;
        // a credit balance is owed to the customer, not an exposure: the engine floors the EAD at nil (spec 3.8)
        $ead = max(0.0, $carrying + $undrawn * $utilisation);""",
"""        $utilisation = $l->facility_utilisation_rate === null ? 1.0 : (float) $l->facility_utilisation_rate;
        // the engine's EAD (EclExposure): a credit balance is owed to the customer, not an exposure, so it
        // is floored at nil (spec 3.8), and a loan written off to nil has no exposure, undrawn included
        $ead = EclExposure::of($l);""")
rep("""            ->get(self::COLUMNS);""", """            ->get(self::columns());""")
io.open(p, 'w', encoding='utf-8', newline='').write(s)
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\ExpectedCreditLossController.php'
s = io.open(p, encoding='utf-8', newline='').read()
rep("EclBuildUpService::COLUMNS));", "EclBuildUpService::columns()));")
io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
