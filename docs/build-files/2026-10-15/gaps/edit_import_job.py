import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')

def sub(path, pairs):
    s = open(path, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (path, a[:80])
        s = s.replace(a, b, 1)
    open(path, 'w', encoding='utf-8', newline='').write(s)

sub('app/Services/Eir/StagingClassifier.php', [
("""                throw new RuntimeException('No approved staging threshold is in force for facility class '""",
 """                throw new \\App\\Exceptions\\StagingRuleMissingException('No approved staging threshold is in force for facility class '"""),
])

p = 'app/Jobs/ProcessLoanImportJob.php'
sub(p, [
("""    protected $import; // Add import record
""",
"""    protected $import; // Add import record

    /** The approved staging rules in force at the period end (StagingClassifier); built on first use. */
    protected ?StagingClassifier $classifier = null;

    /** Loans in arrears whose tenor the report does not give: left unstaged for eir:stage, never guessed. */
    protected int $unstaged = 0;
"""),
("""use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Log;
""",
"""use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Log;
use App\\Exceptions\\StagingRuleMissingException;
use App\\Services\\Eir\\StagingClassifier;
"""),
("""        try {
            $data = $this->mapRowToData($row, $periodString);
            return $data; // Return data instead of inserting directly
        } catch (\\Exception $e) {""",
"""        try {
            $data = $this->mapRowToData($row, $periodString);
            return $data; // Return data instead of inserting directly
        } catch (StagingRuleMissingException $e) {
            // no approved rule in force: the import fails and says so, it never drops the rows quietly
            throw $e;
        } catch (\\Exception $e) {"""),
("""            'ifrs9stage_pre_qualitative'  => $this->classifyIFRS9StageF1($row),
            'ifrs9stage_post_qualitative' => $this->classifyIFRS9StageF1($row),""",
"""            'ifrs9stage_pre_qualitative'  => $stage = $this->stage([
                '1_30_days' => $row[25] ?? null, '31_90_days' => $row[26] ?? null, '91_180_days' => $row[27] ?? null, '181_270_days' => $row[29] ?? null,
            ], $row[6] ?? '', $row[7] ?? ''),
            'ifrs9stage_post_qualitative' => $stage,"""),
("""            'ifrs9stage_pre_qualitative'  => $this->classifyIFRS9StageF2($row),
            'ifrs9stage_post_qualitative' => $this->classifyIFRS9StageF2($row),""",
"""            'ifrs9stage_pre_qualitative'  => $stage = $this->stage([
                '1_30_days' => $row[24] ?? null, '31_90_days' => $row[25] ?? null, '91_180_days' => $row[26] ?? null, '181_270_days' => $row[28] ?? null,
            ], $row[5] ?? '', $row[6] ?? ''),
            'ifrs9stage_post_qualitative' => $stage,"""),
])

s = open(p, encoding='utf-8').read()
i = s.index('    private function classifyIFRS9StageF1($row)')
j = s.index('    private function calculateRemainingTenor($dueDateStr, $reportingPeriod)')
s = s[:i] + '''    /**
     * The stage of one loan by the approved staging thresholds in force at the
     * end of the period (StagingThreshold::forFacility through
     * StagingClassifier; documentation audit of 14 October 2026). Until then
     * this job staged with a ladder written in code: Stage 2 from 31 days,
     * Stage 3 from 181, whatever the facility or the approved rule, so a
     * short-term or Mega Farm loan past 91 days was left in Stage 2.
     *
     * The class is the one StagingService reads (Mega Farm by its group or
     * GL, else DEFAULT) and the tenor is the loan's term in months, from its
     * start to its maturity. A loan in arrears whose report row gives no term
     * is left unstaged for eir:stage, never staged on a guessed tenor; a
     * current loan is Stage 1 under every rule. With no approved rule in force
     * the import stops (StagingRuleMissingException).
     *
     * @param array<string,mixed> $buckets the arrears buckets, keyed as StagingClassifier reads them
     */
    private function stage(array $buckets, $startDate, $dueDate): ?string
    {
        if ($this->classifier === null) {
            $period = (string) $this->reportingPeriod;
            $date = strpos($period, '-') !== false ? Carbon::createFromFormat('Y-m', $period) : Carbon::createFromFormat('Ym', $period);
            $this->classifier = new StagingClassifier($date->endOfMonth()->toDateString());
        }
        if ($this->classifier->daysPastDue($buckets) === 0) {
            return '1';
        }
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($dueDate);
        if (! $start || ! $end || $end <= $start) {
            $this->unstaged++;

            return null;
        }
        $tenor = (int) Carbon::parse($start)->diffInMonths(Carbon::parse($end));
        $group = strtolower((string) ($this->loanContext['group'] ?? ''));
        $code = (string) ($this->loanContext['code'] ?? '');
        $class = (str_contains($group, 'mega') || in_array($code, \\App\\Services\\MegaFarm\\MegaFarmEclService::GLS, true)) ? 'MEGA_FARM' : 'DEFAULT';

        return $this->classifier->classify($buckets, $class, $tenor);
    }

''' + s[j:]
# report the unstaged count at the end
s = s.replace("""            Log::info("Queued import finished. Imported: {$this->importedCount} rows.");""",
"""            Log::info("Queued import finished. Imported: {$this->importedCount} rows.");
            if ($this->unstaged > 0) {
                Log::warning("{$this->unstaged} loan(s) in arrears have no term on the report and were left unstaged for eir:stage.");
            }""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
