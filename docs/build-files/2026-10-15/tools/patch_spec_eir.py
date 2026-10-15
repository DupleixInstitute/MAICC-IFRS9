P = 'C:/xampp/htdocs/MAICC-IFRS9/docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b, 1)

# front matter
rep("status: Working document, updated 11 October 2026 (section 3.7, the fee ledgers and the true-up into 4206).",
    "status: Working document, updated 13 October 2026 (section 3.10, the opening of the revenue roll-forward and facilities drawn in tranches; decisions D32 to D37; the forward-looking module now specified in its own document). Earlier update 11 October 2026 (section 3.7, the fee ledgers and the true-up into 4206).")

# section 1 timeline: append a row at the end of the table (before the blank line that ends it)
i = s.index('## 1. What has happened since version 3')
j = s.index('\n\n', s.index('|---|---|', i) )
# find end of table: first blank line after the header separator
row = ("\n| 13 Oct 2026 | The forward-looking module rebuilt to its own specification (`MAIIC_FLI_Module_Specification_v1_2026-10-13.md`): model registry, month record per scenario, sign-off. "
       "Two UI audits closed (imports to reports, create, edit, delete and refresh on every screen; Maintenance reduced to seven jobs). "
       "The revenue roll-forward corrected for its first month and for facilities drawn in tranches (section 3.10); the book's EIR revenue shift to 31 August 2026 is now -219.1 million. |")
s = s[:j] + row + s[j:]

# section 3.10 before section 4
rep("## 4. Decisions, and the Governance Centre", """### 3.10 Where the revenue roll-forward starts, and facilities drawn in tranches (added 13 October 2026)

**In plain language.** The engine builds each loan's amortised cost month by month: the opening balance, plus the interest at the EIR, less the cash. Everything after the first month follows from the month before, so the first month has to be right. Two faults were found in it on 13 October 2026 and both are corrected.

**The first month was dated a month late.** The opening was the present value of the remaining instalments taken at the end of the first month, and the month's interest was then added on top, so that month's interest was counted twice. A loan with one repayment of 1,030 due at the end of March, run from January at 1 percent a month, was left with about 10 outstanding after it was repaid in full. The opening is now the present value at the end of the month before (decision D32).

**A facility drawn in tranches was treated as if it had been lent in full on day one.** The schedule assumes every tranche is advanced at the start, so its present value counts money not yet lent, and the roll-forward never added a later tranche. Proven on Mphunzitsi SACCO (104430000085): approved 1,047.7 million, drawn 397.7 million in August 2025, 350 million in November 2025 and 300 million in February 2026; the engine opened December 2025 at 887.5 million against a ledger of 421.2 million and closed August 2026 at 349.9 million against 133.6 million. Malawi Police SACCO (3,139.3 million drawn on 19 January 2026) and KWACHA Boutique Hotel (800 million on 20 May and 700 million on 2 June 2026) were lent within their first engine month and were opened at the full present value with a full month's interest.

**The rule now (decision D33).** The first month opens on one of two bases, recorded on every row as `opening_basis` and carried forward with the closing balance:

| Basis | When | Opening | Later advances |
|---|---|---|---|
| SCHEDULE | The usual case | Present value, at the locked EIR, of the contractual flows due after the end of the month before | Never added: the schedule's value already counts every tranche |
| LEDGER | The facility is still drawing down (an advance falls after the end of the month before), or was lent within the month (opening nil), or the borrower has prepaid (the ledger is below half the present value, decision D34) | The drawn balance in the E-Banker ledger at the end of the month before | Each tranche added in the month it is drawn, earning interest from that day; held on the row as `advances` |

Every ledger opening is written to the audit log (`EIR Opening From Ledger`) with the present value, the ledger balance and the reason. The row now ties as opening + advances + interest + unwind - cash - derecognised = closing.

**What it did to the figures (demo database, August 2026).** The ten loans on the ledger basis tie to the loan book within 2.8 million on 6,675.7 million: Mphunzitsi 127.2 million against 133.6 million, Malawi Police SACCO 3,065.1 million against 3,054.2 million. EIR interest for January to August 2026 is 1,812.3 million against contractual interest of 2,031.4 million, a shift of -219.1 million (it read -51.6 million before the correction, because the double-counted first months raised the EIR side).

**What is still open, with the evidence.** The 21 loans on the schedule basis opened December 2025 within 62 million of the ledger in total but are 479.5 million below it by August 2026, and the gap grows every month. The largest are Chipala Investment (-117.6 million), Saile Financial Services (-70.1 million) and Chikomanyama Farms (-41.2 million). Two causes, both already in the build plan: the contractual rate has moved with the prime lending rate while the locked EIR has not been re-estimated (IFRS 9 B5.4.5, phase P5); and instalments unpaid before the first month are not added to the opening (Chipala had paid 152.2 million of 219.5 million due by 30 November 2025; phase P6, arrears). Until P5 and P6 are built the shift for these loans is overstated and is reported as such.

## 4. Decisions, and the Governance Centre""")

# decisions D32 to D37 after the D23 row
rep("""| D23 | **The three MAIIC extract scripts are retired.**""", """| D32 | **The revenue roll-forward's first month opens on the amortised cost at the end of the month before** (section 3.10): the present value at that date of the flows still to come. | Dupleix, 13 Oct 2026 | Taking it at the end of the month itself counted that month's interest twice. |
| D33 | **A facility still drawing down, or lent within the month, runs on the ledger's drawn balance** (section 3.10): the opening is the drawn balance (nil when lent in the month) and each later tranche is added in the month it is drawn, with interest from that day; a schedule-basis loan never adds a tranche. The basis is recorded on every row. | Dupleix, 13 Oct 2026 | The schedule assumes every tranche on day one; Mphunzitsi SACCO was overstated by 216 million at August 2026. |
| D34 | **A prepaid loan opens on the ledger balance** when the ledger at the end of the month before is below half the present value of the remaining schedule; the substitution is audit-logged with both figures. | Dupleix, 13 Oct 2026 | Barnet Agriculture repaid 55.6 million in November 2025; the schedule's value was 38.8 million against 0.4 million owed. |
| D35 | **The actual cash of every loan is loaded from the E-Banker ledger** (`eir:load-ledger-cash`, source `EBANKER_LEDGER` in `eir_actual_transactions`) and is part of the month-end job, so the roll-forward uses receipts, not the schedule, wherever the ledger covers the month. A customer who has paid more than the ledger says is owed is shown as a credit balance, not a negative loan. | Dupleix, 13 Oct 2026 | EIR as at a date read the schedule where the ledger held the receipts. |
| D36 | **Every client is linked to its loans** (`clients:link-loan-book`, by external identity and then by name, recorded on the client) and **each import screen shows only its own import history** (`imports.kind`). | Edward, 13 Oct 2026 | Clients were not linked to the loan book; every import screen listed every import. |
| D37 | **Maintenance is seven jobs, as in the Stress Testing suite** (`maiic:task`): month-end (link clients, stage, PD by segment, forward-looking month-end, ledger cash, revenue run, Mega Farm ECL where present), refresh data, health check, update and bootstrap behind a typed confirmation, with a run history. | Edward, 13 Oct 2026 | The tabs were overwhelming; the month-end order is now one button. |
| D23 | **The three MAIIC extract scripts are retired.**""")

# build plan status
rep("""P4b and P5 start now. Fees and the Mega Farms decision are the two items that gate the number; everything else is engineering.""",
"""P4b and P5 start now. Fees and the Mega Farms decision are the two items that gate the number; everything else is engineering.

**Status at 13 October 2026.** P4b is built, with `EirAsAtService` (the EIR as at any date, by contract, product and GL, with the credit-balance flag and the months a loan has no revenue row). The month-end run of P8 is built as the Maintenance month-end job (D37) and the corrected roll-forward of section 3.10 is in it. P5 (re-estimation when the prime rate moves) and P6 (arrears at the opening and re-estimation under B5.4.6) are the next engine work: section 3.10 gives the evidence and the size, 479.5 million on 21 loans at August 2026.""")

# sections 14 and 15: point to the FLI specification
rep("## 14. Forward-looking adjustments: the regression, the correlation finder and the manual route",
"""## 14. Forward-looking adjustments: the regression, the correlation finder and the manual route

*Since 13 October 2026 the forward-looking adjustment is specified in its own document, `MAIIC_FLI_Module_Specification_v1_2026-10-13.md` (decisions D-F1 to D-F12 and its section 12, what is built). Sections 14 and 15 here record how the design began; where the two differ, the FLI specification governs.*""")
rep("## 15. Economic scenarios: governance and incorporation",
"""## 15. Economic scenarios: governance and incorporation

*See the note at the head of section 14: the scenario set, its weighting and the month record are now specified in the FLI module specification.*""")

open(P, 'w', encoding='utf-8').write(s)
print('ok')
