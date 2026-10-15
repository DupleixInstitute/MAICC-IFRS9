import sys, re
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def after(anchor, add):
    global s
    assert s.count(anchor) == 1, (anchor[:80], s.count(anchor))
    s = s.replace(anchor, anchor + add)


# 1. section 2/RBM paragraph: GL 1320, loss-age, reserve
after("""The section 12 write-off of loss facilities in the following quarter has no loss-age flag (section 3.8).
""", """
**Status 14 October 2026 (reports build):** the RBM return now carries (RbmReturnService, RbmReturnController, Reports/RbmReturn.vue; RbmReturnServiceTest): the directive's paragraph 12 as its own section, "Loss facilities to be written off", each loss facility with the month it became loss on the loan books (the unbroken run of loss classification by days past due and term, "at or before" where the run reaches the first month held), its age, the quarter-end by which it must be written off and its status (overdue, due this quarter, due next quarter); on the 13 October bootstrap at September 2026, 10 loss facilities of 1,510,956,083.45, 7 of them (1,030,047,803.06) past the write-off quarter. Section C keeps the month's figure and adds the cumulative measure of paragraph 13(1): the contractual interest charged on each facility non-performing at the month-end since the month it became non-performing (821,916,269.33 on 50 facilities at September 2026), the cash received in the same window beside it (760,304,079.21, not split between principal and interest because the ledger does not split a repayment, so the cash-basis income the directive allows is a named gap), and GL 1320 from the trial balance as the tie (the trial balances from March 2026 carry no 1320 line; the last held is 21,865,867.35 credit at February 2026, unchanged since January 2025). The loan loss reserve of paragraph 15(1) is a labelled line (the minimum provision over the IFRS 9 allowance). Paragraph 13(3) is still not built. The return's paragraph references were corrected against the Gazette: it had cited section 17 (the monetary penalties) for the return, section 12 for the provisions (paragraph 14 and the Schedule) and section 15 for restructuring (paragraph 16(1)).
""")

# 2. section 3.8 write-off rule status
after("""and hold Imani's 861,723.83 as a reconciliation exception for Finance, not book it as a liability.
""", """
**Status 14 October 2026 (reports build):** the write-offs now reach the reports from one source, `WriteOffSource` (the ledger's write-offs by `WriteOffClassifier`, through `WriteOffRegisterService::ledgerWriteOffs`, and the APPROVED entries of the write-off register; a register entry repeating a ledger write-off of the same contract, date and amount is counted once): the IFRS 9 note (IFRS 7.35H and 35I, the write-off line of both reconciliations; the allowance line takes the allowance each write-off used, the opening allowance up to the amount written off; a contract still in the book at nil after a write-off is attributed as well as one that left the book), the ECL reconciliation CSV, the Loan Book Reconciliation (a written-off line, with the list; the old filter on contract_status never matched), the hub's opening-to-closing ECL and ECL charge, and the RBM return's paragraph 12 section. IFRS 7.35L (amounts written off still subject to enforcement activity) is stated as not recorded: no enforcement status is held. On the 13 October bootstrap the note from December 2025 to September 2026 carries 20 contracts written off, 38,492,032.54 gross (TRF 3 of 29 June 2026), using 22,262,632.66 of allowance; it had shown nil. `M09_01` (the monthly ledger feed) is read for write-off batch legs as well as `GL_05` and `GL_03` (WriteOffClassifier::GL_LEG_QUERIES). Still open: the LGD engine does not use the classifier, R08 (by voucher, partial), and the write-off status table for H.
""")

# 3. 6.11 as-at R12
m = re.search(r"\*\*Status 14 October 2026:\*\* built \(`EirAsAtService`[^\n]*\n", s)
assert m
anchor = m.group(0)
after(anchor, """
**Status 14 October 2026 (reports build), review item R12 built:** inside a month the EIR interest is now the monthly EIR on the prior month-end amortised cost for the days of the month elapsed, so the month-end equals the revenue run's month, and it moves the amortised cost; the contractual accrual (section 3: the contractual rate, simple, on the prior month-end ledger balance, actual days over 365) is returned beside it as `contractual_accrued_period_to_date`, accrued and not yet posted, and the contractual year to date and cumulative add it so the difference compares like with like (EirAsAtService; EirAsAtServiceTest, whose part-month test had asserted the defect). A Stage 3 loan's part month is accrued on the gross amortised cost; the month-end row nets the allowance.
""")

# 4. legacy set
m = re.search(r"- \*\*The legacy set\.\*\*[^\n]*\n", s)
assert m
after(m.group(0), """
  **Status 14 October 2026 (reports build):** the bootstrap no longer seeds the legacy set (`EclScenarioAssumptionSeeder` removed from the seeder list, Bootstrap.php:106). Nothing in the bootstrap's chain still needs it: every MAIIC portfolio-month the bootstrap runs carries the route's per-scenario PD on its loans (checked on the 13 October bootstrap, December 2025 to September 2026), so the time-phased engine runs on the governed set, and the projections screen lists the scenarios the run itself projected rather than the legacy table (EclProjectionController). The code paths that read the table remain: with neither the governed set on the loans nor the legacy table, `TimePhasedEclService` refuses the run, but the ECL controller's discounted path falls to the horizon-proxy discounting (ExpectedCreditLossController.php:532-538), which the reports count as not booked; that fall-back is for the engine owner to close.
""")

# 5. the booked allowance in reports: after the 14 Oct change-log row about discounted month-end
anchor = """the month-end ECL discounted at the EIR (`fli:month-end` runs `ifrs9:segment-ecl --discounting=discounted`, FliMonthEndCommand.php:85). |
"""
if anchor in s:
    after(anchor, """| 14 Oct 2026 | Reports build: every report carries the booked loss allowance (`BookedEcl`: discounted at the original EIR; the Mega Farm programme measure, D30; and, for a loan with no discounted figure, its undiscounted ECL, counted and named), with the reconciliation from the undiscounted measure (the time-phased projection and the effect of discounting); the IFRS 9 note fails closed while any loan lacks its discounted allowance. Write-offs reach the note, the reconciliations and the RBM return from the ledger rule and the approved register (section 3.8). RBM return: paragraph 12 loss list, cumulative suspense, paragraph references (section 2). As-at R12 (section 6.11). The legacy scenario set is no longer seeded (section 15.5). |
""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
