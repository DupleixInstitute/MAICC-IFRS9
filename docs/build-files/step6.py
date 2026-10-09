import io, os
os.chdir(r'C:\xampp\htdocs\MAICC-IFRS9')

def sub(path, pairs):
    s = io.open(path, encoding='utf-8').read()
    for old, new in pairs:
        assert old in s, (path, old[:70])
        s = s.replace(old, new, 1)
    io.open(path, 'w', encoding='utf-8', newline='\n').write(s)

LOCK = "\\App\\Support\\ReportingPeriodLock::assertOpen"

# H4: every writer checks the lock
sub('app/Services/Eir/EirRevenueService.php', [
("""        $period = $this->normalisePeriod($period);
        if ($recalculate && trim((string) $reason) === '') {""",
"""        $period = $this->normalisePeriod($period);
        try {
            %s($period, 'the EIR roll-forward');
        } catch (\\App\\Support\\LockedPeriodException $e) {
            return ['contract_id' => $contractId, 'reporting_period' => $period, 'status' => 'BLOCKED', 'error' => $e->getMessage()];
        }
        if ($recalculate && trim((string) $reason) === '') {""" % LOCK),
])
sub('app/Services/Eir/StagingService.php', [
("    public function stage(string $period, ?int $userId = null, bool $dryRun = false): array\n    {\n",
 "    public function stage(string $period, ?int $userId = null, bool $dryRun = false): array\n    {\n        if (! $dryRun) {\n            %s($period, 'staging');\n        }\n" % LOCK),
])
sub('app/Services/Pd/PdEngineService.php', [
("    {\n        $end = CarbonImmutable::parse($period . '-01');",
 "    {\n        %s($period, 'the PD engine');\n        $end = CarbonImmutable::parse($period . '-01');" % LOCK),
])
sub('app/Services/Lgd/LgdEngineService.php', [
("    {\n        $end = CarbonImmutable::parse($period . '-01');",
 "    {\n        %s($period, 'the LGD engine');\n        $end = CarbonImmutable::parse($period . '-01');" % LOCK),
])
sub('app/Services/Fli/FliRouteService.php', [
("    public function apply(string $period, ?int $userId = null): array\n    {\n",
 "    public function apply(string $period, ?int $userId = null): array\n    {\n        %s($period, 'the forward-looking route');\n" % LOCK),
])
sub('app/Http/Controllers/ExpectedCreditLossController.php', [
("""                $periodDate = Carbon::parse($validated['reporting_period']);
                $period     = $periodDate->format('Y-m');
""",
"""                $periodDate = Carbon::parse($validated['reporting_period']);
                $period     = $periodDate->format('Y-m');
                try {
                    %s($period, 'the ECL');
                } catch (\\App\\Support\\LockedPeriodException $e) {
                    return redirect()->route('expected-credit-loss.index')->with('error', $e->getMessage());
                }
""" % LOCK),
])

# H11: thresholds resolved at the period end, and no silent default
sub('app/Models/StagingThreshold.php', [
("""    public static function forFacility(?string $facilityClass, int $tenorMonths): ?self
    {
        return static::query()
            ->whereIn('facility_class', array_filter([$facilityClass, 'DEFAULT']))
            ->where('min_tenor_months', '<=', $tenorMonths)
            ->whereDate('effective_from', '<=', now())""",
"""    public static function forFacility(?string $facilityClass, int $tenorMonths, ?string $asOf = null): ?self
    {
        // as at the period end, not today: a re-run of a past month keeps the
        // rules that governed it (D21; system audit of 9 October 2026, H11)
        return static::query()
            ->whereIn('facility_class', array_filter([$facilityClass, 'DEFAULT']))
            ->where('min_tenor_months', '<=', $tenorMonths)
            ->whereDate('effective_from', '<=', $asOf ?? now()->toDateString())"""),
])
sub('app/Services/Eir/StagingService.php', [
("""    private function thresholds(string $class, int $tenorMonths): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths);

        return $t ? [(int) $t->stage2_dpd, (int) $t->stage3_dpd] : [31, 181];
    }""",
"""    private function thresholds(string $class, int $tenorMonths, ?CarbonImmutable $asOf = null): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths, $asOf?->toDateString());
        if ($t === null) {
            // no silent default (D21): a month without a rule in force is an error to fix, not a 31/181 to assume
            throw new RuntimeException("No staging threshold is in force for facility class '{$class}' (tenor {$tenorMonths} months)" . ($asOf ? " at {$asOf->toDateString()}" : '') . '. Seed or approve one on the Staging & SICR Rules screen.');
        }

        return [(int) $t->stage2_dpd, (int) $t->stage3_dpd];
    }"""),
("""    private function missedTrigger(CarbonImmutable $asOf): int
    {
        try {
            $v = $this->governance->get('stage3_missed_instalments', $asOf);
        } catch (Throwable) {
            return 4;
        }
""",
"""    private function missedTrigger(CarbonImmutable $asOf): int
    {
        // no default in code (D21): the governed value or a named error
        $v = $this->governance->get('stage3_missed_instalments', $asOf);
"""),
])
print('ok')
