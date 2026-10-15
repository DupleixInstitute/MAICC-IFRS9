p = 'C:/xampp/htdocs/MAICC-IFRS9/docs/MAIIC_EIR_Engine_Specification_v4_2026-10-07.md'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'


def after_line(starts, add):
    global s
    i = s.find(starts)
    assert i >= 0 and s.count(starts) == 1, starts[:60]
    j = s.find(nl, i)
    s = s[:j] + nl + nl + add + s[j:]


def append_cell(row_start, note):
    global s
    i = s.find(row_start)
    assert i >= 0 and s.count(row_start) == 1, row_start[:60]
    j = s.find(nl, i)
    line = s[i:j]
    assert line.rstrip().endswith('|')
    k = line.rstrip()[:-1].rstrip().rfind('|')  # the last cell starts after this bar
    # put the note at the end of the status cell (the second-last cell for 4-column tables handled by caller)
    s = s[:i] + note(line) + s[j:]


REG = ('**Status 14 October 2026 (restructure register built):** the register is built, on the pattern of the write-off register '
       '(`RestructureCandidateService`, `RestructureRegisterService`, `ModificationAccountingService`, migration `2026_10_14_230000`, screen EIR Data, '
       '**Restructures & Resets**, `eir-restructures.*`, command `php artisan eir:restructure-candidates`). The detector finds candidates in four places: '
       'a regeneration of the instalment chart (`RS_02a` and `P2_10`; the old terms are the chart in force the day before, the new terms the instalments '
       'created that day) that extends the maturity by more than one instalment period and keeps it, that amortises more than the old chart expected beyond '
       'the money the ledger advanced in between (capitalised arrears), or that falls on a date with no rate change and no drawdown; two dated copies of the '
       'chart (`RS_02b`, `RS_02d`, `RS_02c`) that differ, for a loan in no other extract, which fail closed without a date (`RS_02e` has none); an accepted '
       '`MODIFICATION_LOG` edit to a loan\'s maturity, tenor, sanction, moratorium, rate or interest policy, resolved to its account through `P1_03` or `P1_02` '
       '(a rejected edit is left out, an unpassed one fails closed, an edit before the first drawdown and a tenor written again in other units are suggested as '
       'corrections); and the migration worksheet `CV_10` (pack 9, 14 October 2026), whose eight restructures before the take-on are suggested as '
       'modifications. A regeneration that only re-amortises the balance for a PLR change is a reset, on the reset register (P5), not a candidate; a chart that '
       'amortises more than the whole ledger balance on its date fails closed (it counts money not lent). Every candidate carries the engine\'s suggestion '
       '(correction, contractual reset, modification, revised estimate or uncertain) and stays a candidate: one person classifies it with a reason (and, for a '
       'modification, the Board approval of directive paragraph 16(1) and the qualitative factors), a different person approves; the bootstrap never confirms '
       'one. On the clone of the fresh bootstrap with pack 9 landed (`maiic_ifrs9_restructure`): 70 candidates on 41 loans (12 chart regenerations, 11 copy '
       'differences, 14 log edits, 8 worksheet restructures, 25 rate moves of fixed-rate loans), 23 of which cannot be booked; the engine suggests a '
       'modification for 17 (MWK 1,108.7 million restructured in the worksheet before the take-on; MWK 81.8 million capitalised on Pinnacle Financial '
       'Services on 30 June 2026). None is confirmed. `InstalmentChartHistory` now also reads the dated copies for a loan held only there (BenCal '
       'Technologies: its chart is in force from 28 March 2024 because its plans 1326 to 1328 are live in `P3_11`, and its collapsed serials are dated by '
       'the monthly plan, so its days past due count 0 at August 2026 against the 254 reported).')

after_line('**Status 14 October 2026 (review items R10 and Cp3): proposed; `RS_02` received; register not built.**', REG)

after_line('**Status 14 October 2026:** the spread is derived (`SpreadDerivationService`, a spread that wanders by more than 0.15 point is flagged `DRIFT`), but no spread-change event is written',
           '**Status 14 October 2026 (reset register built):** `RateResetService` writes `rate_reset_events`: every month whose loan-book rate differs from the month before, '
           'with the PLR at both month-ends (`reference_rate_series`) and the spread; a move whose spread held within the 0.15 point tolerance is a `PLR_RESET` (suggested '
           'in-contract), otherwise a `SPREAD_CHANGE` for a person to classify (O17). A loan the engine treats as fixed-rate whose rate moved with the PLR is recorded '
           'as a reset with that named on the evidence; any other move of a fixed-rate loan is a restructure candidate. One person proposes the classification, a '
           'different person approves, a move in a locked period is refused, `rate_change_classification` is read (its "Every rate change is a reset" closes the '
           'negotiated option) and a move approved as negotiated becomes a restructure candidate. The B5.4.5 treatment stays the monthly re-estimation of D38; the '
           'register does not gate it. On the clone: 281 PLR resets on 40 loans (59 on 8 loans the engine treats as fixed-rate) and 42 spread changes on 22 loans, '
           'September 2024 to September 2026; none approved.')

append_map = {
    '| The derecognition test | 10 percent |': ' **Status 14 October 2026:** read by `ModificationAccountingService` in the revenue run of a confirmed modification (the threshold in force at the month-end; a qualitative factor recorded on the register derecognises whatever the test says).',
    '| Reset or modification | In-contract moves reset; negotiated changes modify |': ' **Status 14 October 2026:** read by `RateResetService` (the negotiated classification is refused under "Every rate change is a reset"); `rate_reset_events` is written.',
    '| P5 | Partly built |': ' **Status 14 October 2026 (later):** the writer of `rate_reset_events`, the spread-change event, maker-checker intake and the refusal in a locked period are built (`RateResetService`, section 7.2); the time-phased ECL discounts a floating loan at its current EIR (`EIR_CURRENT_FLOATING`, B5.5.44) and leaves it unresolved where none can be determined.',
    '| P6 | Partly built |': ' **Status 14 October 2026 (later):** the B5.4.6 catch-up is built for a confirmed revised estimate or a contractual reset of a fixed-rate loan: the gross carrying amount becomes the present value of the revised flows at the original EIR and the difference is booked on the row (`modification_adjustment`, `CATCH_UP`). Re-estimation of the expected flows of a credit-impaired loan without a confirmed event is not built.',
    '| P7 | Not built |': ' **Status 14 October 2026 (later): built.** `contract_modifications`, schedule version N+1 loaded from E-Banker\'s chart on confirmation, the modification test (`modification_threshold`), the 5.4.3 gain or loss at the original EIR, derecognition and version N+1 at the fair value recorded on the register with a new EIR, POCI failing closed, lineage on the register and the row; an event before the take-on is history and disclosure only (section 3.9).',
}
for start, note in append_map.items():
    i = s.find(start)
    assert i >= 0 and s.count(start) == 1, start
    j = s.find(nl, i)
    line = s[i:j].rstrip()
    body = line[:-1].rstrip()          # without the closing bar
    bars = [k for k, c in enumerate(body) if c == '|']
    if start.startswith('| P'):
        # three columns: Phase | Status | Evidence: append to the evidence cell
        s = s[:i] + body + note + ' |' + s[j:]
    else:
        # four columns: Setting | Value | Status | Item: append to the status cell (before the last bar)
        last = bars[-1]
        s = s[:i] + body[:last].rstrip() + note + ' ' + body[last:] + ' |' + s[j:]

after_line('Design rules of the consolidated review, section C:',
           '**Status 14 October 2026 (restructure build):** R10 is built: candidates are detected separately from confirmed events, each resolved to its loan, using the accepted or passed flag, classified by a person as a correction, a contractual reset, a modification, a revised estimate or not a change, and failing closed without an effective date, the old terms or the new schedule (section 3.9). The RBM return\'s section D reports the confirmed restructures with their Board approval (directive 16(1)) and counts the candidates awaiting confirmation.')
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok', nl == '\r\n')
