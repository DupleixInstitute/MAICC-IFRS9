d = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/e123/'
p = 'docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md'
raw = open(p, 'rb').read().decode('utf-8')
crlf = '\r\n' in raw
s = raw.replace('\r\n', '\n')


def rep(a, b, count=1):
    global s
    assert s.count(a) == count, (a[:90], s.count(a))
    s = s.replace(a, b)


sec = open(d + 'spec_311.md', encoding='utf-8').read().rstrip('\n') + '\n\n'
rep('## 4. Decisions, and the Governance Centre\n', sec + '## 4. Decisions, and the Governance Centre\n')

d39 = [l for l in s.split('\n') if l.startswith('| D39 |')][0]
rep(d39 + '\n', d39 + '\n'
    + '| D40 | **A facility drawn in tranches is scheduled and solved on its drawdowns** (section 3.11, E1): each tranche added on its own date, the instalment sized again at each one, the solve from the first drawdown; a fee debit is part of its drawdown, not a tranche; while partly drawn with no stated basis, the interest basis is read from E-Banker\'s own postings, else refused. | Edward, 14 Oct 2026 | Mphunzitsi SACCO solved to 41.3 percent against 29.0 with no fee; it now solves to 29.1. |\n'
    + '| D41 | **The take-on EIR is solved on the workbook\'s balance chain, checked against the rates its lines charge, and carried into the EIR engine** (sections 3.11 and 6.9, E2) through schedule review and the maker-checker lock, with the take-on fees once reviewed; a block that fails the governed check (`takeon_eir_plausibility`) stays on its take-on balance with the reason. | Edward, 14 Oct 2026 | 21 of 73 solves were implausible; 19 were the engine\'s reading of the workbook. 71 now pass, 2 are refused for Tamanda. |\n'
    + '| D42 | **The GL fee postings are the source of record for a loan\'s fees** (sections 3.11 and 7.8, E3): GL_04 tied to the loan by the batch, the rest held with the reason and a candidate for a person to tie, `fee_source_precedence` marking the lower sources superseded; every line still reviewed before it reaches an EIR. | Edward, 14 Oct 2026 | 180.8 million of 2025 fees on eight loans never reached the EIR; six of those loans were locked without them. |\n')

rep("| Which source a contract's fees come from (`fee_source_precedence`) | GL posting, then Finance workbook, then LOS capture, then Extract A | To build (P4b, section 7.8.2); a lower source is superseded, never added | new |",
    "| Which source a contract's fees come from (`fee_source_precedence`) | GL posting, then Finance workbook, then LOS capture, then Extract A | Built 14 October 2026 (sections 3.11 and 7.8.2); a lower source is superseded, never added | new |\n"
    "| The check on a take-on EIR before it is used (`takeon_eir_plausibility`) | Inside the schedule's own rates, 0.5 point either side | Built 14 October 2026 (sections 3.11 and 6.9); 1 point and 0.25 point are the alternatives | new |")

anchor = [l for l in s.split('\n') if l.startswith('**Where.** The workbook is committed under `docs/bootstrap/takeon/`')][0]
rep(anchor + '\n', anchor + '\n\n'
    + '**Built 14 October 2026 (results review E2, decision D41).** The recompute reads the workbook as a balance chain in sheet order (each line\'s principal and interest due on its end date; a rise in the balance the line does not explain advanced on its date; a fall outside the instalments received) and solves from the date the schedule starts, at the balance it opens with, flagging a different Loan Book value date or header principal. The governed check `takeon_eir_plausibility` asks the schedule, solved with no fee, to give back the rates its lines charge and refuses one that does not retire the loan; a refused block stays on its take-on balance (`contract_takeon.eir_check`, `eir_check_reason`). The fees of the block land in `contract_fees` (source `TAKEON_WORKBOOK`, PENDING, the cell in the description) and count once reviewed. The schedule goes to Schedule Review as `PENDING_REVIEW`; once approved the engine calculates the EIR on the checked chain and the maker-checker lock locks it. The roll-forward of a migrated loan starts in August 2024 from its balance at 31 July 2024. On the workbook of 13 October 2026, 71 of 73 pass and COMSIV and BenCal Technologies are refused (section 3.11).\n')

rep("**A fee debit is not a drawdown.** The disbursement builder (`ContractInputsBuildService::disbursements`) today turns every type-301 debit on a loan account into a tranche.",
    "**A fee debit is not a drawdown.** The disbursement builder (`ContractInputsBuildService::disbursements`) turned every type-301 debit on a loan account into a tranche until 14 October 2026; it is now built as below (section 3.11), the fee debit added to the drawdown of its date (or, with none that day, the first) and recorded as `capitalised_fees`, and the take-on opening balance of 31 July 2024 no longer read as a drawdown.")

rep("| **Fee GL gate** on the monthly pack | When a pack lands | The month's movement on each fee GL in `GL_04a` equals the trial balance's | To build (P4b) |",
    "| **Fee GL gate** on the monthly pack | When the GL fee route is built (bootstrap, `eir:build-gl-fees`) | The month's movement on each fee GL in `GL_04a` equals the trial balance's | **Built 14 October 2026** as a report on every build (39 months agree, none differ); not yet a refusal at landing |")
rep("| **Assignment completeness** | After the batch link | Every `GL_04a` line is on a loan, or listed as unassigned or held, with its reason | To build (P4b) |",
    "| **Assignment completeness** | After the batch link | Every `GL_04a` line is on a loan, or listed as unassigned or held, with its reason | **Built 14 October 2026**: 51 on a loan, 127 held in `fee_gl_unassigned` with the reason and, for 30, a candidate loan |")
rep("| **Source cross-check** | After each load | Where a lower source differs from the source of record: the loan, the fee type, both amounts and the difference, on Fee Classification | To build (P4b) |",
    "| **Source cross-check** | After each load | Where a lower source differs from the source of record: the loan, the fee type, both amounts and the difference, on Fee Classification | **Built 14 October 2026** in `FeeSourcePrecedence::apply` (17 differences on the rerun); the Fee Classification panel is to build |")
rep("| `fee_source_precedence`: which source a contract's fees come from | GL posting, then Finance workbook, then LOS capture, then Extract A; or Finance workbook first; or LOS capture first | GL posting first | To build (P4b) |",
    "| `fee_source_precedence`: which source a contract's fees come from | GL posting, then Finance workbook, then LOS capture, then Extract A; or Finance workbook first; or LOS capture first | GL posting first | Built 14 October 2026 |\n"
    "| `takeon_eir_plausibility`: the check on a take-on EIR | Inside the schedule's own rates, 0.5, 1 or 0.25 point either side | 0.5 point | Built 14 October 2026 |")

st = [l for l in s.split('\n') if l.startswith('**Status at 13 October 2026.** P4b is built')][0]
rep(st + '\n', st + '\n\n'
    + '**Status at 14 October 2026.** The three EIR gaps of the results review are built (section 3.11): E1, schedules and solves on the drawdown profile with fee debits inside their drawdown (D40); E2, the take-on EIR solved on the checked balance chain and carried into the engine through schedule review and the lock (D41); E3, the GL fee route with `fee_source_precedence`, the held list and its candidates, the fee GL gate as a report and the source cross-check (D42). Still to build: the Fee Classification panels for the held GL lines (with the tie action) and the superseded lines and differences, the Drawdowns column for the capitalised fees, and the fee GL gate as a refusal when a pack lands.\n')

out = s.replace('\n', '\r\n') if crlf else s
open(p, 'wb').write(out.encode('utf-8'))
print('ok', crlf)
