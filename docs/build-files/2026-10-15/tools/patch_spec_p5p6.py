P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b, 1)

rep("status: Working document, updated 13 October 2026 (section 3.10, the opening of the revenue roll-forward and facilities drawn in tranches; decisions D32 to D37;",
    "status: Working document, updated 13 October 2026 (section 3.10, the opening of the revenue roll-forward, facilities drawn in tranches, floating-rate re-estimation and arrears at the opening; decisions D32 to D39;")

rep("The revenue roll-forward corrected for its first month and for facilities drawn in tranches (section 3.10); the book's EIR revenue shift to 31 August 2026 is now -219.1 million. |",
    "The revenue roll-forward corrected for its first month, for facilities drawn in tranches, for floating-rate loans whose rate has moved and for arrears at the opening (section 3.10); amortised cost now ties to the ledger within 5.8 million on 11.47 billion at August 2026 and the book's EIR revenue shift for January to August 2026 is -117.7 million. |")

old_open = s[s.index("**What it did to the figures (demo database, August 2026).**"):s.index("## 4. Decisions, and the Governance Centre")]
new_open = """**What it did to the figures (demo database, August 2026), first step.** The ten loans on the ledger basis tie to the loan book within 2.8 million on 6,675.7 million: Mphunzitsi 127.2 million against 133.6 million, Malawi Police SACCO 3,065.1 million against 3,054.2 million. EIR interest for January to August 2026 read 1,812.3 million against contractual interest of 2,031.4 million, a shift of -219.1 million (it read -51.6 million before, because the double-counted first months raised the EIR side).

**The headline number changed, and two further causes were found.** After that step the other 21 loans, all on the schedule basis, were 479.5 million below the ledger by August 2026 and the gap grew every month. The largest were Chipala Investment (-117.6 million), Saile Financial Services (-70.1 million) and Chikomanyama Farms (-41.2 million). Until both causes were built, that part of the shift was overstated. Both are now built (13 October 2026, Edward's instruction to build it):

1. **Floating-rate loans whose rate has moved (decision D38, IFRS 9 B5.4.5).** The version 1 schedules of the floating-rate loans were generated at the contractual rate in the contract master on the day they were built, which is today's rate, not the rate the loan was lent at: Chipala Investment was lent at 30.0 percent and charged 29.1 to 30.2 percent through 2025, but its schedule was built at 26.1 percent, its August 2026 rate, and the EIR locked on it is 29.43 percent effective (26.07 nominal). The roll-forward therefore accrued about four points less than E-Banker charged for most of the period. The EIR in force for a month is now the locked nominal EIR plus the change from the rate the schedule was built on (`contract_eir.contractual_rate`) to the rate E-Banker charged that month (the loan book's rate, section 3.2), converted to an effective rate; the margin the fees add is kept. A fixed-rate loan keeps its locked EIR. Each row records the EIR it accrued at (`eir_annual`), and the GL reconciliation's rate effect and the EIR as at a date read it.
2. **Arrears at the opening (decision D39, section 6.9).** The present value at the end of the month before assumes every instalment up to then was paid as scheduled. Where the ledger owes more than the balance the schedule expects at that date (the same flows at the contractual rate) by more than one percent, the difference is booked as arrears and added to the opening, undiscounted because it is owed now. The first row records it (`opening_arrears`) and the audit log records the present value, the scheduled balance, the ledger balance and the arrears. Chipala had paid 152.2 million of 219.5 million due by 30 November 2025; its arrears at the opening are 81.3 million. In total 363.1 million of arrears were added across the book.

**Where the figures stand now (demo database, 31 August 2026).** Amortised cost 11,472.0 million against a gross ledger balance of 11,466.2 million, a difference of 5.8 million: the ten ledger-basis loans within 9.0 million, the 21 schedule-basis loans within 3.2 million (Chipala 907.9 million against 916.0 million). EIR interest for January to August 2026 is 1,913.7 million against contractual interest of 2,031.4 million: **the revenue shift is -117.7 million**. It was -51.6 million when the first months were double-counted and -219.1 million after the first correction alone.

"""
s = s.replace(old_open, new_open, 1)

rep("""| D23 | **The three MAIIC extract scripts are retired.**""", """| D38 | **A floating-rate loan's EIR is re-estimated each month with the rate E-Banker charged** (section 3.10, IFRS 9 B5.4.5): the locked nominal EIR plus the change from the rate the schedule was built on to the month's loan-book rate; a fixed-rate loan keeps its locked EIR; each row records the EIR it accrued at. | Edward, 13 Oct 2026 | The floating loans' schedules were generated at today's rate, not the rate lent at; the roll-forward ran 479.5 million below the ledger with D39. |
| D39 | **Arrears at the opening are booked as arrears** (sections 3.10 and 6.9): where the ledger at the end of the month before owes more than the balance the schedule expects, by more than one percent, the difference is added to the opening, undiscounted, recorded on the row and in the audit log. | Edward, 13 Oct 2026 | The opening assumed every instalment had been paid; Chipala had paid 152.2 million of 219.5 million due. |
| D23 | **The three MAIIC extract scripts are retired.**""")

rep("""P5 (re-estimation when the prime rate moves) and P6 (arrears at the opening and re-estimation under B5.4.6) are the next engine work: section 3.10 gives the evidence and the size, 479.5 million on 21 loans at August 2026.""",
"""The re-estimation of floating-rate loans when the charged rate moves (P5, decision D38) and the arrears at the opening (part of P6, decision D39) are built: amortised cost ties to the ledger within 5.8 million at August 2026 (section 3.10). Still to build in P5: the reset events written to `rate_reset_events` from the PLR series with maker-checker intake and the refusal of a reset inside a locked period. Still to build in P6: re-estimation of expected cash flows under B5.4.6 when a loan is credit-impaired.""")

open(P, 'w', encoding='utf-8').write(s)

M = 'C:/xampp/htdocs/MAICC-IFRS9/docs/manuals/technical/06-eir-engine.md'
m = open(M, encoding='utf-8').read()
a = """monthlyRate = (1 + eir_effective_annual)^(1/12) - 1"""
b = """eirInForce  = FIXED ? eir_effective_annual
            : (1 + (nominal(eir_effective_annual) + loanBookRate - contractual_rate) / 12)^12 - 1
monthlyRate = (1 + eirInForce)^(1/12) - 1"""
assert a in m; m = m.replace(a, b, 1)
a = """- **SCHEDULE**: the present value, at the locked rate, of the contractual flows due after the end of the month before. (Until 13 October 2026 the value was taken at the end of the month itself, which counted that month's interest twice.)"""
b = """- **SCHEDULE**: the present value, at the locked rate, of the contractual flows due after the end of the month before. (Until 13 October 2026 the value was taken at the end of the month itself, which counted that month's interest twice.) Where the ledger at that date owes more than the balance the schedule expects (the same flows at `contractual_rate`) by more than one percent, the difference is booked as arrears and added, undiscounted; the first row holds it as `opening_arrears` and the audit log as `EIR Opening Arrears` (decision D39)."""
assert a in m; m = m.replace(a, b, 1)
a = """Each ledger opening is written to the audit log as `EIR Opening From Ledger` with both figures and the reason."""
b = """Each ledger opening is written to the audit log as `EIR Opening From Ledger` with both figures and the reason.

A floating-rate loan (`contract_eir.rate_type` FLOATING) accrues at the EIR in force for the month: the locked nominal EIR moved by the change from the rate its schedule was built on (`contractual_rate`) to the rate E-Banker charged that month (`loan_books.interest_rate`), keeping the margin the fees add (IFRS 9 B5.4.5, decision D38). Every row records the effective annual rate it accrued at as `eir_annual`; the GL reconciliation's rate effect and `EirAsAtService` read it."""
assert a in m; m = m.replace(a, b, 1)
a = """Re-estimating the EIR of a floating-rate loan when its reference rate moves (IFRS 9 B5.4.5): the locked rate is applied for the life of the loan, so where the contractual rate has risen the amortised cost runs below the ledger. Opening arrears on a schedule-basis loan (instalments due before the first month and not paid) are not added to the opening. """
b = """Reset events written to `rate_reset_events` from the PLR series with maker-checker intake (the re-estimation itself is built, from the loan book's rate); re-estimation of expected cash flows under B5.4.6 for a credit-impaired loan. """
assert a in m; m = m.replace(a, b, 1)
open(M, 'w', encoding='utf-8').write(m)
print('ok')
