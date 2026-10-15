p = r'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/StagingService.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a
    s = s.replace(a, b)


rep(""" * The DPD stage is written to ifrs9stage_pre_qualitative; the stage after the
 * missed-instalment trigger and any SICR flag already on the row goes to
 * ifrs9stage_post_qualitative. A row never moves down by the trigger alone.
""", """ * The DPD stage is written to ifrs9stage_pre_qualitative; the stage after the
 * missed-instalment trigger and the qualitative triggers goes to
 * ifrs9stage_post_qualitative. A row never moves down by a trigger.
 *
 * Qualitative triggers (IFRS 9 B5.5.17, Appendix A): the approved entries of
 * the SICR trigger register in force at the month-end (QualitativeSicrService),
 * matched on the contract or, for a customer-wide trigger, on every loan of
 * the customer. The highest stage they imply is applied: 2 for a significant
 * increase in credit risk, 3 for credit-impaired evidence. The engine writes
 * loan_books.sicr = 1 on a row a qualitative trigger reached and 0 otherwise;
 * it no longer reads that flag, which nothing governed or reproducible set
 * (system review of 9 October 2026).
""")
rep(""" * stage stored on the prior months' book rows. A move up is immediate. A
 * loan the instalment trigger still holds this month is not cured at all;
 * once the trigger releases, its DPD history governs the probation.
 */""", """ * stage stored on the prior months' book rows, raised to the stage of any
 * qualitative trigger in force at that month-end. A move up is immediate. A
 * loan the instalment trigger still holds this month is not cured at all;
 * once the trigger releases, its DPD history governs the probation; a loan
 * a qualitative trigger held cures from the month-end after the trigger ended.
 */""")
rep("""    public function __construct(private GovernanceService $governance, private LandingZoneReader $zone)
    {
    }

    /** @return array{period:string,rows:int,stage1:int,stage2:int,stage3:int,by_dpd:int,by_instalments:int,by_sicr:int,by_bucket:int,held_by_cure:int,cure_months:int} */""",
"""    private QualitativeSicrService $qualitative;

    public function __construct(private GovernanceService $governance, private LandingZoneReader $zone, ?QualitativeSicrService $qualitative = null)
    {
        $this->qualitative = $qualitative ?? new QualitativeSicrService();
    }

    /** @return array{period:string,rows:int,stage1:int,stage2:int,stage3:int,by_dpd:int,by_instalments:int,by_sicr:int,by_credit_impaired:int,by_bucket:int,held_by_cure:int,cure_months:int,qualitative_triggers:int} */""")
rep("""->get(['id', 'contract_id', 'external_identity_id', 'product_group', 'tenor', 'overdue_days', 'sicr', 'ifrs9stage_post_qualitative',""",
"""->get(['id', 'contract_id', 'external_identity_id', 'customer_id', 'product_group', 'tenor', 'overdue_days', 'ifrs9stage_post_qualitative',""")
rep("""        [$priorStage, $dpdHistory] = $this->priorMonths($period, $cureMonths);
        $counts = ['period' => $period, 'rows' => 0, 'stage1' => 0, 'stage2' => 0, 'stage3' => 0, 'by_dpd' => 0, 'by_instalments' => 0, 'by_sicr' => 0, 'by_bucket' => 0, 'held_by_cure' => 0, 'cure_months' => $cureMonths];
        $updates = [];""", """        $customers = $rows->pluck('customer_id', 'contract_id')->map(fn ($c) => $c === null ? null : (string) $c)->all();
        [$priorStage, $dpdHistory] = $this->priorMonths($period, $cureMonths, $customers);
        $qualMap = $this->qualitative->mapAt($monthEnd->toDateString());
        $counts = ['period' => $period, 'rows' => 0, 'stage1' => 0, 'stage2' => 0, 'stage3' => 0, 'by_dpd' => 0, 'by_instalments' => 0, 'by_sicr' => 0, 'by_credit_impaired' => 0, 'by_bucket' => 0, 'held_by_cure' => 0, 'cure_months' => $cureMonths, 'qualitative_triggers' => 0];
        $applied = [];
        $updates = [];""")
rep("""            if ((int) $r->sicr === 1 && $post < 2) {
                $post = 2;
                $reason = 'sicr';
            }""", """            // the qualitative triggers in force at the month-end (B5.5.17, Appendix A)
            $qual = QualitativeSicrService::forLoan($qualMap, (string) $r->contract_id, $r->customer_id === null ? null : (string) $r->customer_id);
            if ($qual['ids'] !== []) {
                $applied[(string) $r->contract_id] = ['stage' => $qual['stage'], 'triggers' => $qual['ids']];
            }
            if ($qual['stage'] > $post) {
                $post = $qual['stage'];
                $reason = $qual['stage'] >= 3 ? 'credit_impaired' : 'sicr';
            }""")
rep("""            if ($reason === 'sicr') { $counts['by_sicr']++; }""", """            if ($reason === 'sicr') { $counts['by_sicr']++; }
            if ($reason === 'credit_impaired') { $counts['by_credit_impaired']++; }""")
rep("""            $updates[] = ['id' => $r->id, 'pre' => (string) $pre, 'post' => (string) $post];
        }
        if (! $dryRun) {
            DB::transaction(function () use ($updates, $period, $counts, $userId, $missedTrigger) {
                foreach ($updates as $u) {
                    DB::table('loan_books')->where('id', $u['id'])->update([
                        'ifrs9stage_pre_qualitative' => $u['pre'], 'ifrs9stage_post_qualitative' => $u['post'],
                        'calculated_ifrs9_stage' => $u['post'], 'ifrs9_stage' => (int) $u['post'],
                    ]);
                }
                AuditLoggerService::log('Loan Book Staged', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $counts['rows'],
                    'new_values' => $counts + ['missed_instalment_trigger' => $missedTrigger], 'meta' => ['user' => $userId]]);
            });
        }""", """            $updates[] = ['id' => $r->id, 'pre' => (string) $pre, 'post' => (string) $post, 'sicr' => $qual['ids'] !== [] ? 1 : 0];
        }
        $counts['qualitative_triggers'] = count(array_unique(array_merge([], ...array_column($applied, 'triggers'))));
        if (! $dryRun) {
            DB::transaction(function () use ($updates, $period, $counts, $userId, $missedTrigger, $applied) {
                foreach ($updates as $u) {
                    DB::table('loan_books')->where('id', $u['id'])->update([
                        'ifrs9stage_pre_qualitative' => $u['pre'], 'ifrs9stage_post_qualitative' => $u['post'],
                        'calculated_ifrs9_stage' => $u['post'], 'ifrs9_stage' => (int) $u['post'], 'sicr' => $u['sicr'],
                    ]);
                }
                // the qualitative triggers each loan was staged under, by contract, for the audit trail
                AuditLoggerService::log('Loan Book Staged', 'loan_books', null, ['reporting_period' => $period, 'rows_affected' => $counts['rows'],
                    'new_values' => $counts + ['missed_instalment_trigger' => $missedTrigger, 'qualitative' => $applied], 'meta' => ['user' => $userId]]);
            });
        }""")
rep("""    private function priorMonths(string $period, int $cureMonths): array
    {""", """    private function priorMonths(string $period, int $cureMonths, array $customers = []): array
    {""")
rep("""        $history = [];
        foreach ($prior as $contract => $stage) {
            foreach ($months as $m) {
                $history[$contract][] = $stored[$contract][$m] ?? null;
            }
        }""", """        // a month-end a qualitative trigger covered counts at the stage the trigger implied
        $qualByMonth = [];
        foreach ($months as $m) {
            $qualByMonth[$m] = $this->qualitative->mapAt(CarbonImmutable::parse($m . '-01')->endOfMonth()->toDateString());
        }
        $history = [];
        foreach ($prior as $contract => $stage) {
            foreach ($months as $m) {
                $dpdStage = $stored[$contract][$m] ?? null;
                if ($dpdStage !== null) {
                    $q = QualitativeSicrService::forLoan($qualByMonth[$m], (string) $contract, $customers[$contract] ?? null)['stage'];
                    $dpdStage = max($dpdStage, $q);
                }
                $history[$contract][] = $dpdStage;
            }
        }""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
