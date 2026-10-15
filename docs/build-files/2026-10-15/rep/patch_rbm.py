import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep("""/**
 * The classification and provisioning return to the Reserve Bank of Malawi
 * (the DFI directive of 2018, section 17), PROVISIONAL LAYOUT.
 *
 * The directive requires the return "in the prescribed form"; the form
 * itself is MAIIC Risk's and is not yet in the repository. This service
 * fills the lines such a return invariably carries, from the system, in
 * the directive's own order: the facilities by class and term under the
 * directive's day bands (section 10 and 11), the minimum provision per
 * class (section 12) against the IFRS 9 allowance held, interest in
 * suspense on non-performing facilities (section 13), restructured
 * facilities (section 15) and the security held. When the prescribed form
 * arrives, LINES is re-ordered and re-worded to it line for line and the
 * figures stay as they are; until then every output says "provisional".
 *
 * Bands, by the term of the facility (section 2: short-term 12 months or
 * less; medium and long term over 12), from section 10 of the Gazette
 * (docs/regulatory, pages 683 and 685):""", """/**
 * The classification and provisioning return to the Reserve Bank of Malawi
 * under the Financial Services (Credit Risk Management for Development
 * Finance Institutions) Directive, 2018, PROVISIONAL LAYOUT.
 *
 * The directive asks for the loan loss reserve "on the call report as
 * prescribed by the Registrar" (paragraph 15(3)); the form itself is MAIIC
 * Risk's and is not yet in the repository. This service fills the lines
 * such a return invariably carries, from the system, in the directive's own
 * order: the facilities by class and term under the directive's day bands
 * (paragraph 10, reviewed quarterly under paragraph 11), the write-off of
 * loss facilities (paragraph 12), interest in suspense on non-performing
 * facilities (paragraph 13), the minimum provision per class (paragraph 14
 * and its Schedule) against the IFRS 9 allowance held, with the excess as
 * the loan loss reserve (paragraph 15), restructured facilities (paragraph
 * 16(1)) and the security held. When the prescribed form arrives, the lines
 * are re-ordered and re-worded to it line for line and the figures stay as
 * they are; until then every output says "provisional". (Paragraph
 * references corrected 14 October 2026 against the Gazette of 13 July 2018:
 * they had cited section 17, which is the monetary penalties, for the return,
 * section 12 for the provisions and section 15 for restructuring.)
 *
 * Bands, by the term of the facility (paragraph 2: short-term 12 months or
 * less; medium and long term over 12), from paragraph 10 and the Schedule
 * (docs/regulatory, pages 683 and 685):""")

# the booked allowance on each facility
rep("""        $loans = $q->get(['contract_id', 'customer_name', 'product_group', 'product_code', 'tenor', 'overdue_days', 'overdue_principal_date', 'carrying_amount', 'principal_balance', 'interest_to_date', 'ecl_value', 'ifrs9stage_post_qualitative', 'contract_status']);
        $notes = ['PROVISIONAL LAYOUT: the lines follow the directive\\'s own order; the prescribed form from MAIIC Risk re-orders and re-words them line for line when it arrives (directive s.17).'];
""", """        // the IFRS 9 allowance is the booked allowance: discounted at the original EIR where the engine
        // discounted the loan (BookedEcl; built 14 October 2026, the return read the undiscounted ecl_value)
        $loans = $q->select(['contract_id', 'customer_name', 'product_group', 'product_code', 'tenor', 'overdue_days', 'overdue_principal_date', 'carrying_amount', 'principal_balance', 'interest_to_date', 'ecl_value', 'ifrs9stage_post_qualitative', 'contract_status'])
            ->selectRaw(BookedEcl::SQL . ' AS ecl_booked')->get();
        $notes = ['PROVISIONAL LAYOUT: the lines follow the directive\\'s own order; the prescribed form from MAIIC Risk re-orders and re-words them line for line when it arrives (the loan loss reserve is reported on the call report as the Registrar prescribes, paragraph 15(3)).'];
""")
rep("""            $a[$class][$term]['ifrs9_allowance'] += (float) ($l->ecl_value ?? 0);
            $perLoan[$l->contract_id] = ['class' => $class, 'term' => $term, 'balance' => (float) $l->carrying_amount, 'dpd' => (int) $l->overdue_days, 'stage' => $l->ifrs9stage_post_qualitative];""",
    """            $a[$class][$term]['ifrs9_allowance'] += (float) $l->ecl_booked;
            $perLoan[$l->contract_id] = ['class' => $class, 'term' => $term, 'balance' => (float) $l->carrying_amount, 'dpd' => (int) $l->overdue_days, 'stage' => $l->ifrs9stage_post_qualitative,
                'customer_name' => $l->customer_name, 'tenor' => (int) $l->tenor, 'ifrs9_allowance' => round((float) $l->ecl_booked, 2)];""")

rep("""        $notes[] = 'Section B applies the minimum rates to the gross balance: the eligible security to net against classified facilities is not yet held (31 active accounts carry no security on the register).';
""", """        $notes[] = 'Section B applies the minimum rates to the gross balance: the eligible security to net against classified facilities is not yet held (31 active accounts carry no security on the register).';
        $basis = BookedEcl::summary($period, fn ($b) => $includeMegaFarm ? $b : $b->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS)));
        $notes[] = 'IFRS 9 allowance: ' . $basis['message'];

        // paragraph 12: a facility classified loss is written off in the quarter after the one in which it was classified loss
        $history = $this->classHistory(array_keys(array_filter($perLoan, fn ($p) => in_array($p['class'], ['Substandard', 'Doubtful', 'Loss'], true))), $period);
        $sectionLoss = $this->lossWriteOffs($perLoan, $history, $period);
        if ($sectionLoss['facilities'] > 0) {
            $notes[] = 'Paragraph 12: ' . number_format($sectionLoss['facilities']) . ' facilities are classified loss (' . number_format($sectionLoss['balance'], 2) . '); '
                . number_format($sectionLoss['overdue']) . ' of them are past the quarter by which the directive requires the write-off.';
        }
""")

# section C: the period measure plus the cumulative measure under paragraph 13(1)
rep("""        // C. interest in suspense: on non-performing facilities, the contractual interest posted in the period less the EIR interest on the net basis
        $ym = $period;
        $suspense = 0.0; $suspenseLoans = 0;
        $npLoans = array_keys(array_filter($perLoan, fn ($p) => in_array($p['class'], ['Substandard', 'Doubtful', 'Loss'], true)));
        if ($npLoans !== []) {
            $posted = [];
            foreach ($this->zone->ledgerByAccount(\\Carbon\\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString()) as $account => $posts) {
                $cid = ltrim($account, '0');
                if (! isset($perLoan[$cid]) || ! in_array($perLoan[$cid]['class'], ['Substandard', 'Doubtful', 'Loss'], true)) {
                    continue;
                }
                foreach ($posts as $p) {
                    if (substr((string) $p['row_date'], 0, 7) === $ym && in_array((string) ($p['payload']['TRANTYPE'] ?? ''), LoanBookBuildService::TYPE_INTEREST, true)) {
                        $posted[$cid] = ($posted[$cid] ?? 0) - (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                    }
                }
            }""", """        // C. interest in suspense: on non-performing facilities, the contractual interest posted in the period less the EIR interest on the net basis;
        // and, under paragraph 13(1), the cumulative interest charged since each facility became non-performing (built 14 October 2026)
        $ym = $period;
        $suspense = 0.0; $suspenseLoans = 0;
        $npLoans = array_keys(array_filter($perLoan, fn ($p) => in_array($p['class'], ['Substandard', 'Doubtful', 'Loss'], true)));
        $cumulative = ['interest' => 0.0, 'cash' => 0.0, 'loans' => 0, 'open_start' => 0, 'per_loan' => []];
        if ($npLoans !== []) {
            $posted = [];
            foreach ($this->zone->ledgerByAccount(\\Carbon\\CarbonImmutable::parse($period . '-01')->endOfMonth()->toDateString()) as $account => $posts) {
                $cid = ltrim($account, '0');
                if (! isset($perLoan[$cid]) || ! in_array($perLoan[$cid]['class'], ['Substandard', 'Doubtful', 'Loss'], true)) {
                    continue;
                }
                $since = $history[$cid]['npl_since'] ?? $period;
                foreach ($posts as $p) {
                    $type = (string) ($p['payload']['TRANTYPE'] ?? '');
                    $amount = (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0));
                    $month = substr((string) $p['row_date'], 0, 7);
                    if ($month === $ym && in_array($type, LoanBookBuildService::TYPE_INTEREST, true)) {
                        $posted[$cid] = ($posted[$cid] ?? 0) - $amount;
                    }
                    if ($month >= $since && $month <= $ym) {
                        if (in_array($type, LoanBookBuildService::TYPE_INTEREST, true)) {
                            $cumulative['per_loan'][$cid]['interest'] = ($cumulative['per_loan'][$cid]['interest'] ?? 0) - $amount;
                        } elseif (in_array($type, LoanBookBuildService::TYPE_RECEIPT, true) && $amount > 0) {
                            $cumulative['per_loan'][$cid]['cash'] = ($cumulative['per_loan'][$cid]['cash'] ?? 0) + $amount;
                        }
                    }
                }
            }
            foreach ($npLoans as $cid) {
                $cumulative['interest'] += $cumulative['per_loan'][$cid]['interest'] ?? 0;
                $cumulative['cash'] += $cumulative['per_loan'][$cid]['cash'] ?? 0;
                $cumulative['loans']++;
                $cumulative['open_start'] += ! empty($history[$cid]['npl_open']) ? 1 : 0;
            }""")
rep("""        $sectionC = ['non_performing_accounts' => count($npLoans), 'non_performing_balance' => round($npl, 2), 'interest_posted_in_period' => round(array_sum($posted ?? []), 2), 'interest_recognised_eir_net_basis' => round(array_sum(array_map(fn ($cid) => (float) ($eir[$cid] ?? 0), array_keys($posted ?? []))), 2), 'interest_in_suspense' => round($suspense, 2), 'accounts_with_interest_posted' => $suspenseLoans];
        $notes[] = 'Section C: E-Banker continues to post contractual interest on non-performing accounts; the suspense the directive requires is the excess over the interest the EIR engine recognises on the net basis (spec v4 section 3.4).';
""", """        $gl1320 = $this->gl1320($period);
        $sectionC = ['non_performing_accounts' => count($npLoans), 'non_performing_balance' => round($npl, 2), 'interest_posted_in_period' => round(array_sum($posted ?? []), 2), 'interest_recognised_eir_net_basis' => round(array_sum(array_map(fn ($cid) => (float) ($eir[$cid] ?? 0), array_keys($posted ?? []))), 2), 'interest_in_suspense' => round($suspense, 2), 'accounts_with_interest_posted' => $suspenseLoans,
            'cumulative_interest_charged_since_non_performing' => round($cumulative['interest'], 2),
            'cash_received_since_non_performing' => round($cumulative['cash'], 2),
            'facilities_non_performing_from_the_first_month_held' => $cumulative['open_start'],
            'gl_1320_interest_suspense' => $gl1320['balance'],
            'gl_1320_source' => $gl1320['source'],
            'difference_cumulative_less_gl_1320' => $gl1320['balance'] !== null ? round($cumulative['interest'] - $gl1320['balance'], 2) : null,
            'note' => 'Paragraph 13(1): a non-performing facility is placed on non-accrual; interest accrued and not collected is reversed and interest is income only when received in cash. Measured: the contractual interest E-Banker charged on each facility non-performing at the month-end since the month it became non-performing on the loan books (' . number_format($cumulative['open_start']) . ' were already non-performing in the first month the books hold, so theirs is from that month). Not measured: the part of the cash received since then that is interest (the cash-basis income the directive allows), because the ledger does not split a repayment between principal and interest; the cash received is shown beside it. The tie is GL 1320 Interest Suspense in the trial balance: ' . $gl1320['source'] . '.'];
        $notes[] = 'Section C: E-Banker continues to post contractual interest on non-performing accounts; the suspense the directive requires is the excess over the interest the EIR engine recognises on the net basis (spec v4 section 3.4). The cumulative line follows paragraph 13(1) from the loan books and the ledger; ' . $gl1320['source'] . '.';
""")

# the return: the basis and the loss section
rep("""            'sections' => ['A_classification' => $sectionA, 'B_provisions' => $sectionB, 'B_totals' => ['minimum_provision' => round($minTotal, 2), 'ifrs9_allowance' => round($heldTotal, 2), 'higher_of_the_two' => round(max($minTotal, $heldTotal), 2)], 'C_interest_in_suspense' => $sectionC, 'D_restructured' => $sectionD, 'E_security' => $sectionE],""",
    """            'sections' => ['A_classification' => $sectionA, 'B_provisions' => $sectionB, 'B_totals' => ['minimum_provision' => round($minTotal, 2), 'ifrs9_allowance' => round($heldTotal, 2), 'higher_of_the_two' => round(max($minTotal, $heldTotal), 2), 'loan_loss_reserve' => round(max(0, $minTotal - $heldTotal), 2)], 'B_allowance_basis' => $basis, 'B_loss_write_offs' => $sectionLoss, 'C_interest_in_suspense' => $sectionC, 'D_restructured' => $sectionD, 'E_security' => $sectionE],""")

# helpers at the end of the class
rep("""            'per_loan' => $perLoan, 'notes' => $notes];
    }
}""", """            'per_loan' => $perLoan, 'notes' => $notes];
    }

    /**
     * Each facility's class month by month from the loan books, read back from
     * the period while it stays non-performing: the month it became
     * non-performing (npl_since) and the month it became loss (loss_since) in
     * the run that reaches the period. A run that reaches the first month the
     * books hold for the facility is open: the true start is that month or
     * earlier (npl_open, loss_open).
     *
     * @return array<string, array{npl_since:string,npl_open:bool,loss_since:?string,loss_open:bool}>
     */
    private function classHistory(array $contracts, string $period): array
    {
        if ($contracts === []) {
            return [];
        }
        $rows = DB::table('loan_books')->whereIn('contract_id', array_map('strval', $contracts))->where('reporting_period', '<=', $period)
            ->orderBy('contract_id')->orderByDesc('reporting_period')->get(['contract_id', 'reporting_period', 'tenor', 'overdue_days'])->groupBy('contract_id');
        $out = [];
        foreach ($rows as $cid => $months) {
            $npl = null; $loss = null; $lossRun = true; $prev = null; $broken = false;
            foreach ($months as $m) {
                // consecutive month-ends only: a month missing from the book ends the run
                if ($prev !== null && \\Carbon\\CarbonImmutable::parse($m->reporting_period . '-01')->addMonth()->format('Y-m') !== $prev) {
                    $broken = true;
                    break;
                }
                $class = self::classify((int) $m->overdue_days, (int) $m->tenor);
                if (! in_array($class, ['Substandard', 'Doubtful', 'Loss'], true)) {
                    $broken = true;
                    break;
                }
                $npl = $m->reporting_period;
                if ($lossRun && $class === 'Loss') {
                    $loss = $m->reporting_period;
                } else {
                    $lossRun = false;
                }
                $prev = $m->reporting_period;
            }
            $out[(string) $cid] = ['npl_since' => $npl ?? $period, 'npl_open' => ! $broken, 'loss_since' => $loss, 'loss_open' => ! $broken && $lossRun];
        }

        return $out;
    }

    /**
     * Paragraph 12: "An institution shall write off all credit facilities that were
     * classified as loss, from its books in the subsequent quarter" to the one in
     * which the loss classification was made. Each loss facility with the month it
     * became loss on the books, its age, the quarter-end by which it should be written
     * off and where it stands at the period.
     */
    private function lossWriteOffs(array $perLoan, array $history, string $period): array
    {
        $periodEnd = \\Carbon\\CarbonImmutable::parse($period . '-01')->endOfMonth();
        $rows = [];
        foreach ($perLoan as $cid => $p) {
            if ($p['class'] !== 'Loss') {
                continue;
            }
            $h = $history[(string) $cid] ?? ['loss_since' => $period, 'loss_open' => false];
            $since = \\Carbon\\CarbonImmutable::parse(($h['loss_since'] ?? $period) . '-01');
            $deadline = $since->lastOfQuarter()->addDay()->lastOfQuarter()->endOfDay(); // the end of the following quarter
            $status = $periodEnd->greaterThan($deadline) ? 'overdue' : ($periodEnd->greaterThan($since->lastOfQuarter()) ? 'due this quarter' : 'due next quarter');
            $rows[] = ['contract_id' => (string) $cid, 'customer_name' => $p['customer_name'], 'term' => $p['term'], 'days_past_due' => $p['dpd'], 'balance' => round($p['balance'], 2),
                'ifrs9_allowance' => $p['ifrs9_allowance'],
                'loss_since' => $since->format('Y-m'), 'loss_since_open' => (bool) $h['loss_open'],
                'months_classified_loss' => (int) $since->diffInMonths($periodEnd->startOfMonth()) + 1,
                'write_off_due_by' => $deadline->toDateString(), 'status' => $status];
        }
        usort($rows, fn ($a, $b) => [$a['loss_since'], $b['balance']] <=> [$b['loss_since'], $a['balance']]);
        $overdue = array_filter($rows, fn ($r) => $r['status'] === 'overdue');

        return ['facilities' => count($rows), 'balance' => round(array_sum(array_column($rows, 'balance')), 2), 'overdue' => count($overdue),
            'overdue_balance' => round(array_sum(array_column($overdue, 'balance')), 2), 'rows' => $rows,
            'note' => 'Paragraph 12: a facility classified loss is to be written off in the quarter after the one in which it was classified loss. "Loss since" is the first month-end of the unbroken run of loss classification on the loan books (by days past due and term, paragraph 10); where the run reaches the first month the books hold, the true date is that month or earlier ("at or before"). Write-offs are proposed and approved in the write-off register (EIR Data, Write-offs).'];
    }

    /** GL 1320 Interest Suspense in the trial balance at the period end, or what is held instead. */
    private function gl1320(string $period): array
    {
        $date = $period . '-01';
        $tbHeld = DB::table('gl_trial_balance_lines')->where('period', $date)->exists();
        if ($tbHeld) {
            $row = DB::table('gl_trial_balance_lines')->where('period', $date)->where('gl_code', '1320')->orderByRaw("basis = 'POSTCLOSING' DESC")->first();

            return $row
                ? ['balance' => round((float) $row->credit - (float) $row->debit, 2), 'source' => 'GL 1320 at ' . $period . ' in the trial balance (' . $row->source_file . ') is ' . number_format((float) $row->credit - (float) $row->debit, 2) . ' credit']
                : ['balance' => null, 'source' => 'the trial balance for ' . $period . ' is loaded but carries no GL 1320 line'];
        }
        $last = DB::table('gl_trial_balance_lines')->where('gl_code', '1320')->where('period', '<', $date)->orderByDesc('period')->first();

        return ['balance' => null, 'source' => 'no trial balance is loaded for ' . $period . ($last ? '; the latest GL 1320 line held is ' . number_format((float) $last->credit - (float) $last->debit, 2) . ' credit at ' . substr((string) $last->period, 0, 7) : '; no GL 1320 line is held')];
    }
}""")

rep("""use App\\Services\\Ebanker\\LandingZoneReader;
use App\\Services\\Ebanker\\LoanBookBuildService;""", """use App\\Services\\Ebanker\\LandingZoneReader;
use App\\Services\\Ebanker\\LoanBookBuildService;
use App\\Services\\Reports\\BookedEcl;""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
