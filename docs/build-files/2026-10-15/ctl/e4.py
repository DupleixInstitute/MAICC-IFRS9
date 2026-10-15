from ed import edit
p='app/Services/Eir/StagingClassifier.php'
edit(p, r""" * The seeded DEFAULT rule (stage 2 at 31 DPD, stage 3 at 181 DPD)
 * reproduces the previously hardcoded ladder exactly, so behaviour does
 * not change until a differently-configured rule — e.g. the RBM-based
 * 90-day backstop for long-tenor facilities (2025 review finding 1) —
 * is signed off and activated. When no configuration exists at all
 * (fresh install, mid-migration), the same hardcoded ladder applies.
 */""", r""" * The rule is the approved staging_thresholds row in force at the end of
 * the reporting period the import is for, not at today's date, so a re-run
 * of a past month keeps the rules that governed it (D21; system audit of
 * 9 October 2026, H11). With no rule in force the import stops and names
 * what is missing: until 14 October 2026 it fell back to a ladder written
 * in code (stage 2 at 31 days, stage 3 at 181).
 */""")
edit(p, r"""    /** The pre-config ladder: stage 2 from 31 DPD, stage 3 from 181 DPD. */
    private const FALLBACK_STAGE2_DPD = 31;
    private const FALLBACK_STAGE3_DPD = 181;

    private ?StagingThreshold $cached = null;
    private bool $resolved = false;
""", r"""    /** @var array<string, array{0:int,1:int}> the rule per class and tenor, read once per import */
    private array $cached = [];

    /** @param string $asOf the reporting period end the rows are staged at (Y-m-d) */
    public function __construct(private readonly string $asOf)
    {
    }
""")
edit(p, r"""    /** @return array{0:int,1:int} [stage2_dpd, stage3_dpd] */
    private function thresholds(?string $facilityClass, int $tenorMonths): array
    {
        // One lookup per import run; the rule set does not change mid-file.
        if (! $this->resolved) {
            try {
                $this->cached = StagingThreshold::forFacility($facilityClass, $tenorMonths);
            } catch (Throwable) {
                // Table absent (fresh install, tests without migrations):
                // fall through to the hardcoded ladder.
                $this->cached = null;
            }
            $this->resolved = true;
        }

        return $this->cached
            ? [(int) $this->cached->stage2_dpd, (int) $this->cached->stage3_dpd]
            : [self::FALLBACK_STAGE2_DPD, self::FALLBACK_STAGE3_DPD];
    }""", r"""    /** @return array{0:int,1:int} [stage2_dpd, stage3_dpd] */
    private function thresholds(?string $facilityClass, int $tenorMonths): array
    {
        $key = ($facilityClass ?? 'DEFAULT') . '|' . $tenorMonths;
        if (! isset($this->cached[$key])) {
            $rule = StagingThreshold::forFacility($facilityClass, $tenorMonths, $this->asOf);
            if ($rule === null) {
                throw new RuntimeException('No approved staging threshold is in force for facility class ' . ($facilityClass ?? 'DEFAULT')
                    . " (tenor {$tenorMonths} months) at {$this->asOf}. Approve one on Settings, Staging basis; the import does not assume 31 and 181 days.");
            }
            $this->cached[$key] = [(int) $rule->stage2_dpd, (int) $rule->stage3_dpd];
        }

        return $this->cached[$key];
    }""")
edit(p, r"""use App\Models\StagingThreshold;
use Throwable;""", r"""use App\Models\StagingThreshold;
use RuntimeException;""")
p='app/Models/StagingThreshold.php'
edit(p, r"""    public static function forFacility(?string $facilityClass, int $tenorMonths, ?string $asOf = null): ?self""",
r"""    public static function forFacility(?string $facilityClass, int $tenorMonths, string $asOf): ?self""")
edit(p, r"""            ->whereDate('effective_from', '<=', $asOf ?? now()->toDateString())""", r"""            ->whereDate('effective_from', '<=', $asOf)""")
p='app/Services/Eir/StagingService.php'
edit(p, r"""    private function thresholds(string $class, int $tenorMonths, ?CarbonImmutable $asOf = null): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths, $asOf?->toDateString());""", r"""    private function thresholds(string $class, int $tenorMonths, CarbonImmutable $asOf): array
    {
        $t = StagingThreshold::forFacility($class, $tenorMonths, $asOf->toDateString());""")
p='app/Imports/LoanBooksImport.php'
edit(p, r"""        // DPD waterfall now lives in staging_thresholds configuration
        // (docs/EIR_Build.md Phase 2.4). The seeded DEFAULT rule reproduces
        // the ladder previously hardcoded here; with no configuration the
        // classifier falls back to that same ladder.
        $this->stagingClassifier ??= new \App\Services\Eir\StagingClassifier();""", r"""        // DPD waterfall lives in staging_thresholds configuration, read at the
        // end of the reporting period being imported (audit H11); with no
        // approved rule in force the import stops and says so.
        $this->stagingClassifier ??= new \App\Services\Eir\StagingClassifier($this->stagingAsOf());""")
